<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/mp-webhook
// ============================================================
// Recebe notificacao do Mercado Pago quando o status de um payment muda.
// Configure este URL em https://www.mercadopago.com.br/developers/panel
//   ou pela preference (notification_url) no momento do checkout.
//
// Quando recebe um evento 'payment', consulta o status real via API MP
// e credita os coins ao player se aprovado.
// ============================================================

declare(strict_types=1);

$ROOT = dirname(__DIR__, 2);
require $ROOT . '/src/Database.php';
require $ROOT . '/src/MercadoPago.php';
require $ROOT . '/src/Mailer.php';
require $ROOT . '/src/DiscordWebhook.php';
require $ROOT . '/src/Coupon.php';
require $ROOT . '/src/BalanceLog.php';

$configFile = $ROOT . '/config/config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    die('not installed');
}
$config = require $configFile;
\App\Database::init($config['db']);

// Inicializa Mailer + carrega settings do DB pra usar no template
\App\Mailer::init(array_merge($config['mail'] ?? [], [
    'dev_log_path' => $ROOT . '/storage/cache/mail-log.txt',
]));
try {
    $dbSettings = \App\Database::fetchAll("SELECT `key`, `value` FROM settings");
    foreach ($dbSettings as $s) { $config['settings'][$s['key']] = $s['value']; }
} catch (Throwable $e) {}

header('Content-Type: application/json; charset=utf-8');

// MP manda POST com JSON tipo { "type": "payment", "data": { "id": "1234567890" } }
// Limita payload a 64KB pra evitar DoS por upload gigante
$raw = file_get_contents('php://input', false, null, 0, 65536);
if (strlen($raw) >= 65536) {
    http_response_code(413);
    die(json_encode(['ok' => false, 'error' => 'payload_too_large']));
}
$payload = json_decode($raw, true);

if (!is_array($payload) || ($payload['type'] ?? '') !== 'payment') {
    // Pode ser teste/ping ou outro tipo de evento — responde 200 pra MP nao tentar de novo
    die(json_encode(['ok' => true, 'ignored' => true]));
}

$paymentId = (string)($payload['data']['id'] ?? '');
if (!$paymentId) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'error' => 'missing_payment_id']));
}

$mp = new \App\MercadoPago(
    $config['mercado_pago']['access_token'] ?? '',
    $config['mercado_pago']['webhook_secret'] ?? null
);

// Valida assinatura (defesa em profundidade — protege contra requests forjados)
$xSig    = $_SERVER['HTTP_X_SIGNATURE']  ?? '';
$xReqId  = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
$webhookSecret = $config['mercado_pago']['webhook_secret'] ?? '';
$appEnv = $config['env'] ?? 'production';

// Em produção, exige secret + assinatura válida (defesa contra forjar pagamentos)
if ($appEnv === 'production') {
    if (empty($webhookSecret)) {
        error_log('[MP webhook] webhook_secret nao configurado em PRODUCAO - request rejeitado');
        http_response_code(503);
        die(json_encode(['ok' => false, 'error' => 'webhook_secret_required']));
    }
    if (!$xSig || !$mp->verifyWebhookSignature($xSig, $xReqId, $paymentId)) {
        http_response_code(401);
        die(json_encode(['ok' => false, 'error' => 'invalid_signature']));
    }
} elseif ($xSig && !$mp->verifyWebhookSignature($xSig, $xReqId, $paymentId)) {
    // Dev/test: só valida se a assinatura foi enviada (não bloqueia ausência)
    http_response_code(401);
    die(json_encode(['ok' => false, 'error' => 'invalid_signature']));
}

if (!$mp->isConfigured()) {
    http_response_code(503);
    die(json_encode(['ok' => false, 'error' => 'mp_not_configured']));
}

// Consulta o payment real no MP pra confirmar
$payment = $mp->getPayment($paymentId);
if (!$payment) {
    http_response_code(502);
    die(json_encode(['ok' => false, 'error' => 'mp_query_failed']));
}

$status        = $payment['status'] ?? 'unknown';
$paymentMethod = $payment['payment_method_id'] ?? null;
$externalRef   = $payment['external_reference'] ?? null;

if (!$externalRef) {
    die(json_encode(['ok' => true, 'note' => 'no_external_reference']));
}

// ============ Cobrança avulsa (/cobrar) — external_reference "inv-<ref>" ============
// Distingue do fluxo de purchases (que usa o id numérico). Roteia ANTES do
// lookup de purchases pra o cast (int) não engolir o "inv-...".
if (str_starts_with($externalRef, 'inv-')) {
    $invRef = substr($externalRef, 4);
    $inv = \App\Database::fetchOne(
        "SELECT id, status FROM invoices WHERE invoice_ref = ? LIMIT 1", [$invRef]
    );
    if (!$inv) {
        die(json_encode(['ok' => true, 'note' => 'invoice_not_found']));
    }
    if ($status === 'approved') {
        // Idempotente: vira 'paid' só na 1a aprovação (paid_at via COALESCE).
        \App\Database::query(
            "UPDATE invoices
                SET mp_payment_id = ?, status = 'paid', paid_at = COALESCE(paid_at, NOW())
              WHERE id = ? AND status <> 'paid'",
            [(string)$paymentId, (int)$inv['id']]
        );
    } else {
        // cancelled/rejected/refunded: registra o pagamento mas NÃO marca pago.
        \App\Database::query(
            "UPDATE invoices SET mp_payment_id = ? WHERE id = ?",
            [(string)$paymentId, (int)$inv['id']]
        );
    }
    die(json_encode(['ok' => true, 'invoice' => true, 'status' => $status]));
}

$purchase = \App\Database::fetchOne(
    "SELECT * FROM purchases WHERE id = ? LIMIT 1", [(int)$externalRef]
);
if (!$purchase) {
    http_response_code(404);
    die(json_encode(['ok' => false, 'error' => 'purchase_not_found']));
}

// Atualiza status no banco. CONGELA depois de entregue: um webhook tardio (ex: o QR
// antigo que expirou, mesma compra com >1 Pix gerado) NÃO pode rebaixar approved->cancelled
// de uma compra já creditada. Por isso o WHERE delivered_at IS NULL.
\App\Database::query(
    "UPDATE purchases SET mp_payment_id = ?, mp_status = ?, payment_method = ?
      WHERE id = ? AND delivered_at IS NULL",
    [(string)$paymentId, $status, $paymentMethod, (int)$purchase['id']]
);

// Se aprovado e ainda nao entregue, credita coins no player
if ($status === 'approved' && empty($purchase['delivered_at'])) {
    // ====== GUARDA ANTI-UNDERPAY (defesa em profundidade) ======
    // O valor REALMENTE pago (consultado no MP, nao no payload) tem que cobrir o preco
    // da compra. O unit_price da preference ja e server-side (do banco), entao isto e
    // redundancia FORTE contra qualquer tentativa de "paguei R$1 numa coisa cara".
    $paidAmount = (float) ($payment['transaction_amount'] ?? 0);
    $expected   = (float) $purchase['price_brl'];
    if ($expected > 0 && $paidAmount + 0.01 < $expected) {
        error_log("[MP webhook] UNDERPAY purchase {$purchase['id']}: pago R$ {$paidAmount} < esperado R$ {$expected} - NAO credita");
        \App\Database::query(
            "UPDATE purchases SET mp_status = 'underpaid' WHERE id = ? AND delivered_at IS NULL",
            [(int) $purchase['id']]
        );
        die(json_encode(['ok' => false, 'error' => 'amount_mismatch']));
    }

    // ============ CLAIM ATÔMICO ============
    // Dois webhooks paralelos do mesmo purchase_id PODEM chegar simultaneamente.
    // Marca delivered_at e checa rowCount: só quem efetivamente alterou a linha
    // (delivered_at era NULL) prossegue com o crédito. Os outros saem sem mexer.
    $claim = \App\Database::execute(
        "UPDATE purchases SET delivered_at = NOW() WHERE id = ? AND delivered_at IS NULL",
        [(int)$purchase['id']]
    );
    if ($claim === 0) {
        die(json_encode(['ok' => true, 'note' => 'already_delivered']));
    }

    $existing = \App\Database::fetchOne(
        "SELECT id, coins FROM players WHERE steam_id = ? LIMIT 1",
        [$purchase['steam_id']]
    );
    $coinsToAdd = (int)$purchase['coins_total'];
    $price = (float)$purchase['price_brl'];

    if ($existing) {
        $oldCoins = (int)($existing['coins'] ?? 0);
        \App\Database::query(
            "UPDATE players
                SET coins = coins + ?,
                    total_spent_brl = total_spent_brl + ?,
                    origin = 'payment',
                    last_seen_at = NOW()
              WHERE id = ?",
            [$coinsToAdd, $price, (int)$existing['id']]
        );
        \App\BalanceLog::record(
            (int)$existing['id'], $purchase['steam_id'],
            $oldCoins, $oldCoins + $coinsToAdd, 'payment',
            'purchase', $purchase['id'],
            "Pacote {$purchase['package_id']} via MP"
        );
    } else {
        \App\Database::query(
            "INSERT INTO players (steam_id, coins, total_spent_brl, origin, last_seen_at)
             VALUES (?, ?, ?, 'payment', NOW())",
            [$purchase['steam_id'], $coinsToAdd, $price]
        );
        $newId = (int)\App\Database::pdo()->lastInsertId();
        \App\BalanceLog::record(
            $newId, $purchase['steam_id'],
            0, $coinsToAdd, 'payment',
            'purchase', $purchase['id'],
            "Player criado via MP, pacote {$purchase['package_id']}"
        );
    }
    // (delivered_at já foi setado pelo claim atômico acima)

    // Incrementa contador de uso do cupom (se houve cupom)
    if (!empty($purchase['coupon_code'])) {
        \App\Coupon::incrementUse($purchase['coupon_code']);
    }

    // E-mail transacional de recibo (se cliente tem e-mail no perfil)
    $payerEmail = $payment['payer']['email'] ?? null;
    if ($payerEmail && filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
        $purchase['mp_payment_id'] = (string)$paymentId;
        $html = \App\Mailer::purchaseReceiptHtml($purchase, $config);
        $subject = '✓ Recibo de compra — ' . ($config['settings']['site_name'] ?? 'Tecplay');
        @\App\Mailer::send($payerEmail, $subject, $html);
    }

    // Webhook Discord (se configurado)
    $discordWebhook = $config['settings']['discord_sales_webhook'] ?? null;
    if ($discordWebhook) {
        @\App\DiscordWebhook::notifySale($discordWebhook, $purchase, $config);
    }

    // Notifica o bot Tecplay (se rodando no mesmo host). Bot embeleza e posta em #vendas.
    // Configure em config.php: 'bot' => ['endpoint' => 'http://127.0.0.1:8765', 'token' => '...']
    // ou em settings: bot_endpoint + bot_token.
    $botEndpoint = trim($config['bot']['endpoint'] ?? ($config['settings']['bot_endpoint'] ?? ''));
    $botToken    = trim($config['bot']['token']    ?? ($config['settings']['bot_token']    ?? ''));
    if ($botEndpoint && $botToken) {
        $playerRow = \App\Database::fetchOne(
            "SELECT display_name FROM players WHERE steam_id = ? LIMIT 1",
            [$purchase['steam_id']]
        );
        $packageRow = \App\Database::fetchOne(
            "SELECT name, icon FROM packages WHERE id = ? LIMIT 1",
            [$purchase['package_id']]
        );
        $payload = json_encode([
            'purchase_id'    => (int)$purchase['id'],
            'steam_id'       => $purchase['steam_id'],
            'player_name'    => $playerRow['display_name'] ?? null,
            'package_name'   => $packageRow['name']        ?? $purchase['package_id'],
            'package_icon'   => $packageRow['icon']        ?? '🪙',
            'coins_total'    => (int)$purchase['coins_total'],
            'price_brl'      => (float)$purchase['price_brl'],
            'payment_method' => $purchase['payment_method'] ?? $paymentMethod,
        ]);
        $ch = curl_init(rtrim($botEndpoint, '/') . '/notify/purchase');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Tecplay-Token: ' . $botToken,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        @curl_exec($ch); // best-effort: nao bloqueia se bot estiver fora
        curl_close($ch);
    }
}

die(json_encode(['ok' => true, 'status' => $status, 'delivered' => $status === 'approved']));
