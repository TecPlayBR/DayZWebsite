<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/bot-integration
// ============================================================
// Endpoint para o Tecplay Bot Discord (Pro/Free) consultar dados
// do site. SEPARADO do /api/health.php (que é público) — este aqui
// é autenticado via Bearer token.
//
// AUTH:
//   Header: Authorization: Bearer <token>
//   Token vive em settings.discord_integration_token
//   Gerado/visto na aba "Integração Discord" do admin
//
// ACTIONS:
//   GET  sem params           → teste de conexão
//   GET  ?action=player&steam_id=76561198XXXXX
//                             → dados do player (404 se não existe)
//   GET  ?action=stats        → métricas agregadas
//   GET  ?action=packages     → lista pacotes de coins habilitados (pro /comprar)
//   POST ?action=link_player  → cria/atualiza player (origin='bot')
//                               Body JSON: {steam_id, display_name?}
//   POST ?action=create_checkout → compra de coins pelo Discord via LINK (reusa
//                               o checkout web). Body: {steam_id, package_id,
//                               server_id?, coupon_code?} → {init_point, purchase_id}
//   POST ?action=create_pix   → compra de coins pelo Discord via QR Pix DIRETO.
//                               Mesmo body → {qr_code, qr_code_base64, ticket_url,
//                               purchase_id, expires_at}. Pix criado no MP do site.
//                               Coins creditados pelo mp-webhook.php na aprovação (ambos).
//   GET  ?action=shop_items   → catálogo de itens gastáveis (pro bot /loja):
//                               {items:[{sku,name,icon,coins_cost,enabled,deliver:[...]}]}
//   POST ?action=spend        → gasta moeda num item. Body: {steam_id, sku,
//                               server_id?, spend_ref}. Debita players.coins ATÔMICO
//                               (402 se insuficiente), idempotente por spend_ref →
//                               {ok, new_balance, deliver:[{classname,quantity,...}]}.
//
// LOG: toda chamada vai pra tabela discord_integration_log
// CORS: bloqueado pra browser (server-to-server only)
// ============================================================

declare(strict_types=1);

$ROOT = dirname(__DIR__, 2);
require $ROOT . '/src/Database.php';
require $ROOT . '/src/MercadoPago.php';
require $ROOT . '/src/Coupon.php';
require $ROOT . '/src/Servers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

// CORS disabled: server-to-server only. Token autenticado num endpoint com CORS
// wildcard = vetor pra brute force cross-origin via XHR.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(405);
    die(json_encode(['error' => 'cors_disabled']));
}

// Bootstrap config + DB
$configFile = $ROOT . '/config/config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    die(json_encode(['error' => 'site_not_installed']));
}
$config = require $configFile;

try {
    \App\Database::init($config['db']);
} catch (\Throwable $e) {
    error_log('[bot-integration] DB init falhou: ' . $e->getMessage());
    http_response_code(503);
    die(json_encode(['error' => 'db_unavailable']));
}

// ============ HELPER: log da chamada ============

$_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (str_contains($_ip, ',')) $_ip = trim(explode(',', $_ip)[0]);

function _log_call(string $action, int $status): void {
    global $_ip;
    try {
        \App\Database::query(
            "INSERT INTO discord_integration_log (called_at, ip, action, status_code)
             VALUES (NOW(), ?, ?, ?)",
            [substr($_ip, 0, 45), substr($action, 0, 64), $status]
        );
        // Garbage collection simples: mantém só os 200 mais recentes.
        // Roda 5% das chamadas pra não pesar.
        if (random_int(1, 20) === 1) {
            \App\Database::query(
                "DELETE FROM discord_integration_log WHERE id NOT IN
                 (SELECT id FROM (SELECT id FROM discord_integration_log
                  ORDER BY id DESC LIMIT 200) t)"
            );
        }
    } catch (\Throwable $e) {
        // Tabela ainda não existe? Não quebra o endpoint.
        error_log('[bot-integration] log falhou: ' . $e->getMessage());
    }
}

function _bail(int $code, string $error, string $action = ''): never {
    http_response_code($code);
    _log_call($action ?: 'unknown', $code);
    die(json_encode(['error' => $error]));
}

// ============ AUTH ============

$expectedToken = (string) (\App\Database::fetchColumn(
    "SELECT `value` FROM settings WHERE `key` = 'discord_integration_token'"
) ?: '');

if ($expectedToken === '') {
    _bail(401, 'token_not_configured', 'auth');
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
// Alguns hosts movem Authorization pra REDIRECT_HTTP_AUTHORIZATION
if ($authHeader === '') {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
}
if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    _bail(401, 'unauthorized', 'auth');
}
$receivedToken = trim($m[1]);

if (!hash_equals($expectedToken, $receivedToken)) {
    _bail(401, 'unauthorized', 'auth');
}

// ============ ACTION ROUTING ============

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Marca "última chamada OK" (lido pelo admin pra mostrar status verde)
function _mark_last_ok(): void {
    \App\Database::query(
        "INSERT INTO settings (`key`, `value`) VALUES ('discord_integration_last_ok', ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
        [(string) time()]
    );
}

/**
 * Valida steam_id + package + server + cupom e cria a purchase pending.
 * Compartilhado por create_checkout e create_pix (mesma regra do checkout web).
 * Retorna [purchaseId, pkg, coinsTotal, priceBrl, steamId]. Faz _bail em erro.
 */
function _prepare_purchase(array $body, string $action): array {
    $steamId   = trim((string) ($body['steam_id'] ?? ''));
    $packageId = trim((string) ($body['package_id'] ?? ''));
    if (!preg_match('/^7656119\d{10}$/', $steamId)) {
        _bail(400, 'invalid_steam_id', $action);
    }
    $pkg = \App\Database::fetchOne(
        "SELECT * FROM packages WHERE id = ? AND enabled = 1 LIMIT 1",
        [$packageId]
    );
    if (!$pkg) {
        _bail(404, 'package_not_found', $action);
    }
    $serverId = (int) ($body['server_id'] ?? 0);
    if ($serverId < 1) $serverId = \App\Servers::defaultId();
    $server = \App\Servers::find($serverId);
    if (!$server || empty($server['active'])) {
        _bail(400, 'invalid_server', $action);
    }
    $bonusEnabled = (int) (\App\Database::fetchColumn(
        "SELECT `value` FROM settings WHERE `key` = 'bonus_enabled'"
    ) ?: 0);
    $coinsBase  = (int) $pkg['coins'];
    $coinsBonus = $bonusEnabled ? (int) $pkg['bonus_coins'] : 0;
    $coinsTotal = $coinsBase + $coinsBonus;
    $priceOriginal = (float) $pkg['price_brl'];

    $couponCode = strtoupper(trim((string) ($body['coupon_code'] ?? '')));
    $discount = 0.0;
    $appliedCouponCode = null;
    $priceFinal = $priceOriginal;
    if ($couponCode !== '') {
        [$coupon, $err] = \App\Coupon::lookup($couponCode, $packageId, $priceOriginal);
        if ($err) {
            _bail(400, 'invalid_coupon', $action);
        }
        [$discount, $priceFinal] = \App\Coupon::applyDiscount($coupon, $priceOriginal);
        $appliedCouponCode = $coupon['code'];
    }
    $priceBrl = $priceFinal;

    \App\Database::query(
        "INSERT INTO purchases
            (steam_id, package_id, server_id, coins_base, coins_bonus, coins_total,
             price_brl, coupon_code, discount_brl,
             mp_status, terms_accepted_at, terms_version)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)",
        [$steamId, $packageId, $serverId, $coinsBase, $coinsBonus, $coinsTotal,
         $priceBrl, $appliedCouponCode, $discount, '2026-05-27']
    );
    $purchaseId = (int) \App\Database::pdo()->lastInsertId();
    return [$purchaseId, $pkg, $coinsTotal, $priceBrl, $steamId];
}

switch ($action) {

case '':
    // Teste de conexão
    _mark_last_ok();
    _log_call('test', 200);
    die(json_encode([
        'status'  => 'ok',
        'time'    => time(),
        'site'    => 'DayZWebsite v1.1+',
        'version' => '1.1.0',
    ]));

case 'player':
    $steamId = trim((string) ($_GET['steam_id'] ?? ''));
    if (!preg_match('/^7656119\d{10}$/', $steamId)) {
        _bail(400, 'invalid_steam_id', 'player');
    }
    $row = \App\Database::fetchOne(
        "SELECT steam_id, display_name, coins, total_spent_brl, last_seen_at
           FROM players WHERE steam_id = ? LIMIT 1",
        [$steamId]
    );
    if (!$row) {
        _bail(404, 'player_not_found', 'player');
    }
    _mark_last_ok();
    _log_call('player', 200);
    die(json_encode([
        'coins'           => (int) ($row['coins'] ?? 0),
        'total_spent_brl' => (float) ($row['total_spent_brl'] ?? 0),
        'last_seen_at'    => $row['last_seen_at'],
        'display_name'    => $row['display_name'],
    ]));

case 'link_player':
    if ($method !== 'POST') {
        _bail(405, 'method_not_allowed', 'link_player');
    }
    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true) ?: [];
    $steamId = trim((string) ($body['steam_id'] ?? ''));
    $name    = trim((string) ($body['display_name'] ?? ''));
    if (!preg_match('/^7656119\d{10}$/', $steamId)) {
        _bail(400, 'invalid_steam_id', 'link_player');
    }
    // Upsert: cria se não existe, atualiza display_name e marca origem='bot'.
    // Se player já existia com outra origem (ex: 'agent'), MANTÉM a origem antiga —
    // bot só "marca" origem quando é o primeiro registro do player. Evita reescrever
    // histórico de quem já era conhecido pelo agent/painel.
    $existing = \App\Database::fetchOne(
        "SELECT id, origin FROM players WHERE steam_id = ? LIMIT 1",
        [$steamId]
    );
    if ($existing) {
        if ($name !== '') {
            \App\Database::query(
                "UPDATE players SET display_name = ?, last_seen_at = NOW() WHERE id = ?",
                [substr($name, 0, 100), (int)$existing['id']]
            );
        } else {
            \App\Database::query(
                "UPDATE players SET last_seen_at = NOW() WHERE id = ?",
                [(int)$existing['id']]
            );
        }
        _mark_last_ok();
        _log_call('link_player', 200);
        die(json_encode([
            'status'      => 'updated',
            'player_id'   => (int)$existing['id'],
            'origin_kept' => (string)$existing['origin'],
        ]));
    }
    // Player novo — cria com origin='bot'
    \App\Database::query(
        "INSERT INTO players (steam_id, display_name, coins, total_spent_brl, last_seen_at, origin)
         VALUES (?, ?, 0, 0.00, NOW(), 'bot')",
        [$steamId, $name !== '' ? substr($name, 0, 100) : null]
    );
    $newId = \App\Database::pdo()->lastInsertId();
    _mark_last_ok();
    _log_call('link_player', 201);
    http_response_code(201);
    die(json_encode([
        'status'    => 'created',
        'player_id' => (int)$newId,
        'origin'    => 'bot',
    ]));

case 'stats':
    $playersTotal = (int) (\App\Database::fetchColumn(
        "SELECT COUNT(*) FROM players"
    ) ?: 0);
    $salesToday = (int) (\App\Database::fetchColumn(
        "SELECT COUNT(*) FROM purchases
           WHERE mp_status = 'approved'
             AND created_at >= CURDATE()"
    ) ?: 0);
    $revenueMonth = (float) (\App\Database::fetchColumn(
        "SELECT COALESCE(SUM(price_brl), 0) FROM purchases
           WHERE mp_status = 'approved'
             AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
    ) ?: 0);
    // "VIP ativo" = comprou nos últimos 30 dias (heurística simples;
    // DayZWebsite não tem tabela de VIP, bot tem). Pra contar VIPs
    // de verdade, bot consulta a própria tabela vip_grants.
    $vipActive = (int) (\App\Database::fetchColumn(
        "SELECT COUNT(DISTINCT steam_id) FROM purchases
           WHERE mp_status = 'approved'
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    ) ?: 0);
    _mark_last_ok();
    _log_call('stats', 200);
    die(json_encode([
        'players_total'     => $playersTotal,
        'sales_today'       => $salesToday,
        'revenue_month_brl' => round($revenueMonth, 2),
        'vip_active'        => $vipActive,
    ]));

case 'packages':
    // Lista os pacotes de coins habilitados (pro bot exibir no Discord).
    $bonusEnabled = (int) (\App\Database::fetchColumn(
        "SELECT `value` FROM settings WHERE `key` = 'bonus_enabled'"
    ) ?: 0);
    $rows = \App\Database::fetchAll(
        "SELECT id, name, icon, coins, bonus_coins, price_brl, badge, ribbon, featured
           FROM packages WHERE enabled = 1 ORDER BY sort_order ASC, price_brl ASC"
    );
    $packages = array_map(static function (array $r) use ($bonusEnabled): array {
        return [
            'id'          => $r['id'],
            'name'        => $r['name'],
            'icon'        => $r['icon'],
            'coins'       => (int) $r['coins'],
            'bonus_coins' => $bonusEnabled ? (int) $r['bonus_coins'] : 0,
            'price_brl'   => (float) $r['price_brl'],
            'badge'       => $r['badge'],
            'ribbon'      => $r['ribbon'],
            'featured'    => (bool) $r['featured'],
        ];
    }, $rows);
    _mark_last_ok();
    _log_call('packages', 200);
    die(json_encode(['bonus_enabled' => (bool) $bonusEnabled, 'packages' => $packages]));

case 'create_checkout':
    // Compra de coins pelo Discord via LINK de checkout web (reusa o fluxo do site).
    // O cliente paga pelo link e o mp-webhook.php credita os coins — zero duplicação.
    if ($method !== 'POST') {
        _bail(405, 'method_not_allowed', 'create_checkout');
    }
    $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
    [$purchaseId, $pkg, $coinsTotal, $priceBrl, $steamId] = _prepare_purchase($body, 'create_checkout');

    $mp = new \App\MercadoPago(
        $config['mercado_pago']['access_token'] ?? '',
        $config['mercado_pago']['webhook_secret'] ?? null
    );
    if (!$mp->isConfigured()) {
        _bail(503, 'payments_not_configured', 'create_checkout');
    }

    $siteUrl = rtrim($config['site_url'] ?? '', '/');
    $pref = $mp->createPreference([
        'items' => [[
            'id'          => $pkg['id'],
            'title'       => $pkg['name'] . ' — ' . $coinsTotal . ' moedas',
            'description' => 'Compra (Discord) para SteamID ' . $steamId,
            'quantity'    => 1,
            'currency_id' => 'BRL',
            'unit_price'  => $priceBrl,
        ]],
        'external_reference' => (string) $purchaseId,
        'back_urls' => [
            'success' => $siteUrl . '/shop/return?status=success',
            'pending' => $siteUrl . '/shop/return?status=pending',
            'failure' => $siteUrl . '/shop/return?status=failure',
        ],
        'auto_return'          => 'approved',
        'notification_url'     => $siteUrl . '/api/mp-webhook.php',
        'statement_descriptor' => $config['site_name'] ?? 'DAYZ',
    ]);
    if (!$pref || empty($pref['init_point'])) {
        _bail(502, 'preference_failed', 'create_checkout');
    }
    \App\Database::query(
        "UPDATE purchases SET mp_payment_id = ? WHERE id = ?",
        [$pref['id'], $purchaseId]
    );

    _mark_last_ok();
    _log_call('create_checkout', 201);
    http_response_code(201);
    die(json_encode([
        'ok'          => true,
        'purchase_id' => $purchaseId,
        'init_point'  => $pref['init_point'],
        'price_brl'   => round($priceBrl, 2),
        'coins_total' => $coinsTotal,
    ]));

case 'create_pix':
    // /comprar no Discord = QR Pix DIRETO (sem link). Mesma regra do checkout,
    // mas cria um pagamento Pix no MP do site e devolve o QR copia-e-cola + PNG.
    // O MESMO mp-webhook.php credita os coins na aprovação (Pix notifica igual).
    if ($method !== 'POST') {
        _bail(405, 'method_not_allowed', 'create_pix');
    }
    $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
    [$purchaseId, $pkg, $coinsTotal, $priceBrl, $steamId] = _prepare_purchase($body, 'create_pix');
    if ($priceBrl < 0.01) {
        _bail(400, 'invalid_amount', 'create_pix'); // Pix não cobra R$0 (ex: cupom 100%)
    }

    $mp = new \App\MercadoPago(
        $config['mercado_pago']['access_token'] ?? '',
        $config['mercado_pago']['webhook_secret'] ?? null
    );
    if (!$mp->isConfigured()) {
        _bail(503, 'payments_not_configured', 'create_pix');
    }

    $siteUrl = rtrim($config['site_url'] ?? '', '/');
    $expires = gmdate("Y-m-d\\TH:i:s.000P", time() + 1800); // QR válido ~30 min
    $pay = $mp->createPixPayment([
        'transaction_amount' => round($priceBrl, 2),
        'description'        => $pkg['name'] . ' — ' . $coinsTotal . ' moedas (Discord)',
        'external_reference' => (string) $purchaseId,
        'notification_url'   => $siteUrl . '/api/mp-webhook.php',
        'date_of_expiration' => $expires,
        'payer'              => ['email' => $steamId . '@pix.tecplay.inf.br'],
    ]);
    $tx = $pay['point_of_interaction']['transaction_data'] ?? null;
    if (!$pay || !$tx || empty($tx['qr_code'])) {
        _bail(502, 'pix_failed', 'create_pix');
    }
    \App\Database::query(
        "UPDATE purchases SET mp_payment_id = ? WHERE id = ?",
        [(string) $pay['id'], $purchaseId]
    );

    _mark_last_ok();
    _log_call('create_pix', 201);
    http_response_code(201);
    die(json_encode([
        'ok'             => true,
        'purchase_id'    => $purchaseId,
        'qr_code'        => $tx['qr_code'],
        'qr_code_base64' => $tx['qr_code_base64'] ?? null,
        'ticket_url'     => $tx['ticket_url'] ?? null,
        'price_brl'      => round($priceBrl, 2),
        'coins_total'    => $coinsTotal,
        'expires_at'     => $pay['date_of_expiration'] ?? $expires,
    ]));

case 'create_invoice':
    // /cobrar do Discord: cobrança Pix de VALOR LIVRE com dados do cliente
    // (helpdesk + pagamentos). Cobra no MP do site; mp-webhook.php marca 'paid'
    // (external_reference "inv-<ref>" distingue de purchases). Idempotente por invoice_ref.
    if ($method !== 'POST') {
        _bail(405, 'method_not_allowed', 'create_invoice');
    }
    $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
    $invName   = trim((string) ($body['name'] ?? ''));
    $invDesc   = trim((string) ($body['description'] ?? ''));
    $invRef    = substr(trim((string) ($body['invoice_ref'] ?? '')), 0, 80);
    $invAmount = round((float) ($body['amount_brl'] ?? 0), 2);
    $invCpf    = preg_replace('/\D/', '', (string) ($body['cpf'] ?? ''));     // só dígitos
    $invCpf    = $invCpf !== '' ? $invCpf : null;
    $invEmail  = trim((string) ($body['email'] ?? '')) ?: null;
    $invPhone  = preg_replace('/\D/', '', (string) ($body['phone'] ?? ''));
    $invPhone  = $invPhone !== '' ? $invPhone : null;
    $invBy     = substr(trim((string) ($body['created_by'] ?? '')), 0, 40) ?: null;
    $invGuild  = substr(trim((string) ($body['guild_id'] ?? '')), 0, 32) ?: null;

    if ($invName === '' || $invDesc === '')                 _bail(400, 'missing_fields', 'create_invoice');
    if ($invRef === '')                                     _bail(400, 'missing_invoice_ref', 'create_invoice');
    if ($invAmount < 0.01)                                  _bail(400, 'invalid_amount', 'create_invoice');
    if ($invCpf !== null && strlen($invCpf) !== 11)         _bail(400, 'invalid_cpf', 'create_invoice');
    if ($invEmail !== null && !filter_var($invEmail, FILTER_VALIDATE_EMAIL)) _bail(400, 'invalid_email', 'create_invoice');

    // Idempotência: invoice_ref já existe? NÃO cria/cobra de novo — devolve o estado atual.
    $prevInv = \App\Database::fetchOne(
        "SELECT id, status, amount_brl, qr_code, expires_at FROM invoices WHERE invoice_ref = ? LIMIT 1",
        [$invRef]
    );
    if ($prevInv) {
        _mark_last_ok();
        _log_call('create_invoice', 200);
        die(json_encode([
            'ok'          => true,
            'idempotent'  => true,
            'invoice_id'  => (int) $prevInv['id'],
            'invoice_ref' => $invRef,
            'status'      => $prevInv['status'],
            'qr_code'     => $prevInv['qr_code'],
            'amount_brl'  => (float) $prevInv['amount_brl'],
            'expires_at'  => $prevInv['expires_at'],
        ]));
    }

    $mp = new \App\MercadoPago(
        $config['mercado_pago']['access_token'] ?? '',
        $config['mercado_pago']['webhook_secret'] ?? null
    );
    if (!$mp->isConfigured()) {
        _bail(503, 'payments_not_configured', 'create_invoice');
    }
    $siteUrl = rtrim($config['site_url'] ?? '', '/');
    $expires = gmdate("Y-m-d\\TH:i:s.000P", time() + 1800); // QR válido ~30 min

    \App\Database::query(
        "INSERT INTO invoices (invoice_ref, name, cpf, email, phone, description, amount_brl, created_by, guild_id, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$invRef, substr($invName, 0, 120), $invCpf, $invEmail, $invPhone,
         substr($invDesc, 0, 255), $invAmount, $invBy, $invGuild, gmdate("Y-m-d H:i:s", time() + 1800)]
    );
    $invoiceId = (int) \App\Database::pdo()->lastInsertId();

    $pay = $mp->createPixPayment([
        'transaction_amount' => $invAmount,
        'description'        => substr($invDesc, 0, 600),
        'external_reference' => 'inv-' . $invRef,
        'notification_url'   => $siteUrl . '/api/mp-webhook.php',
        'date_of_expiration' => $expires,
        'payer'              => ['email' => $invEmail ?: ('cobranca+' . $invRef . '@pix.tecplay.inf.br')],
    ]);
    $tx = $pay['point_of_interaction']['transaction_data'] ?? null;
    if (!$pay || !$tx || empty($tx['qr_code'])) {
        _bail(502, 'pix_failed', 'create_invoice');
    }
    \App\Database::query(
        "UPDATE invoices SET mp_payment_id = ?, qr_code = ? WHERE id = ?",
        [(string) $pay['id'], (string) $tx['qr_code'], $invoiceId]
    );

    _mark_last_ok();
    _log_call('create_invoice', 201);
    http_response_code(201);
    die(json_encode([
        'ok'             => true,
        'invoice_id'     => $invoiceId,
        'invoice_ref'    => $invRef,
        'qr_code'        => $tx['qr_code'],
        'qr_code_base64' => $tx['qr_code_base64'] ?? null,
        'ticket_url'     => $tx['ticket_url'] ?? null,
        'amount_brl'     => $invAmount,
        'expires_at'     => $pay['date_of_expiration'] ?? $expires,
    ]));

case 'invoice_status':
    // Bot faz polling do status da cobrança (pending|paid|expired|cancelled).
    $ref = substr(trim((string) ($_GET['invoice_ref'] ?? '')), 0, 80);
    if ($ref === '') _bail(400, 'missing_invoice_ref', 'invoice_status');
    $inv = \App\Database::fetchOne(
        "SELECT status, amount_brl, paid_at, expires_at FROM invoices WHERE invoice_ref = ? LIMIT 1",
        [$ref]
    );
    if (!$inv) _bail(404, 'invoice_not_found', 'invoice_status');
    _mark_last_ok();
    _log_call('invoice_status', 200);
    die(json_encode([
        'ok'         => true,
        'status'     => $inv['status'],
        'amount_brl' => (float) $inv['amount_brl'],
        'paid_at'    => $inv['paid_at'],
        'expires_at' => $inv['expires_at'],
    ]));

case 'shop_items':
    // Catálogo de itens GASTÁVEIS (moeda → item in-game). O bot lista no /loja.
    $rows = \App\Database::fetchAll(
        "SELECT sku, name, icon, coins_cost, enabled, deliver_json
           FROM shop_items WHERE enabled = 1 ORDER BY sort_order ASC, name ASC"
    );
    $items = array_map(static function (array $r): array {
        return [
            'sku'        => $r['sku'],
            'name'       => $r['name'],
            'icon'       => $r['icon'],
            'coins_cost' => (int) $r['coins_cost'],
            'enabled'    => (bool) $r['enabled'],
            'deliver'    => json_decode((string) $r['deliver_json'], true) ?: [],
        ];
    }, $rows);
    _mark_last_ok();
    _log_call('shop_items', 200);
    die(json_encode(['items' => $items]));

case 'spend':
    // Gasto de moeda no Discord (estilo ShopBot): debita o saldo (players.coins)
    // de forma ATÔMICA e devolve os classnames pro bot enfileirar a entrega.
    // spend_ref = idempotência: mesmo ref NÃO debita 2x (retry de rede seguro).
    if ($method !== 'POST') {
        _bail(405, 'method_not_allowed', 'spend');
    }
    $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
    $steamId  = trim((string) ($body['steam_id'] ?? ''));
    $sku      = trim((string) ($body['sku'] ?? ''));
    $spendRef = substr(trim((string) ($body['spend_ref'] ?? '')), 0, 80);
    $serverId = (int) ($body['server_id'] ?? 0);
    $serverId = $serverId > 0 ? $serverId : null;

    if (!preg_match('/^7656119\d{10}$/', $steamId)) {
        _bail(400, 'invalid_steam_id', 'spend');
    }
    if ($sku === '')      _bail(400, 'missing_sku', 'spend');
    if ($spendRef === '') _bail(400, 'missing_spend_ref', 'spend');

    // 1) Idempotência: esse spend_ref já foi processado? Devolve o mesmo resultado.
    $prev = \App\Database::fetchOne(
        "SELECT new_balance, deliver_json FROM shop_spends WHERE spend_ref = ? LIMIT 1",
        [$spendRef]
    );
    if ($prev) {
        _mark_last_ok();
        _log_call('spend', 200);
        die(json_encode([
            'ok'          => true,
            'idempotent'  => true,
            'new_balance' => (int) $prev['new_balance'],
            'deliver'     => json_decode((string) $prev['deliver_json'], true) ?: [],
        ]));
    }

    // 2) Item precisa existir e estar habilitado.
    $item = \App\Database::fetchOne(
        "SELECT coins_cost, deliver_json FROM shop_items WHERE sku = ? AND enabled = 1 LIMIT 1",
        [$sku]
    );
    if (!$item) {
        _bail(404, 'item_not_found', 'spend');
    }
    $cost = (int) $item['coins_cost'];

    // 3) Débito atômico + registro do gasto numa transação (rollback desfaz tudo).
    $pdo = \App\Database::pdo();
    $pdo->beginTransaction();
    try {
        // O WHERE coins >= ? garante que NUNCA fica negativo (atômico no row lock).
        $aff = \App\Database::execute(
            "UPDATE players SET coins = coins - ? WHERE steam_id = ? AND coins >= ?",
            [$cost, $steamId, $cost]
        );
        if ($aff === 0) {
            $pdo->rollBack();
            $exists = \App\Database::fetchColumn(
                "SELECT 1 FROM players WHERE steam_id = ? LIMIT 1", [$steamId]
            );
            if (!$exists) _bail(404, 'player_not_found', 'spend');
            _bail(402, 'insufficient_coins', 'spend');
        }
        $newBalance = (int) \App\Database::fetchColumn(
            "SELECT coins FROM players WHERE steam_id = ? LIMIT 1", [$steamId]
        );
        \App\Database::query(
            "INSERT INTO shop_spends
                (spend_ref, steam_id, sku, coins_spent, new_balance, deliver_json, server_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$spendRef, $steamId, $sku, $cost, $newBalance, (string) $item['deliver_json'], $serverId]
        );
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Corrida no spend_ref (UNIQUE): outro request idêntico já processou →
        // devolve o resultado dele (idempotente), sem cobrar de novo.
        $raced = \App\Database::fetchOne(
            "SELECT new_balance, deliver_json FROM shop_spends WHERE spend_ref = ? LIMIT 1",
            [$spendRef]
        );
        if ($raced) {
            _mark_last_ok();
            _log_call('spend', 200);
            die(json_encode([
                'ok'          => true,
                'idempotent'  => true,
                'new_balance' => (int) $raced['new_balance'],
                'deliver'     => json_decode((string) $raced['deliver_json'], true) ?: [],
            ]));
        }
        error_log('[bot-integration] spend falhou: ' . $e->getMessage());
        _bail(500, 'spend_failed', 'spend');
    }

    _mark_last_ok();
    _log_call('spend', 200);
    die(json_encode([
        'ok'          => true,
        'new_balance' => $newBalance,
        'deliver'     => json_decode((string) $item['deliver_json'], true) ?: [],
    ]));

default:
    _bail(400, 'unknown_action', 'unknown');
}
