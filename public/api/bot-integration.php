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
//   POST ?action=link_player  → cria/atualiza player (origin='bot')
//                               Body JSON: {steam_id, display_name?}
//
// LOG: toda chamada vai pra tabela discord_integration_log
// CORS: bloqueado pra browser (server-to-server only)
// ============================================================

declare(strict_types=1);

$ROOT = dirname(__DIR__, 2);
require $ROOT . '/src/Database.php';

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

default:
    _bail(400, 'unknown_action', 'unknown');
}
