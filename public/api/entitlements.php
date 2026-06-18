<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/entitlements  (VIP / BattlePass)
// ============================================================
// O SITE e quem manda no servidor de jogo. Admin concede VIP/Passe em
// /admin/entitlements; o tecplay-agent puxa os pendentes aqui e escreve os JSON
// do mod Sparda no servidor, depois confirma (ack). Espelha o sync-players.php.
//
// Auth: token do servidor (header X-Agent-Token OU ?token=). Resolve o servidor
//       (multi-server) e escopa os grants por server_id.
//
// GET  ?serverId=<slug>&token=<tok>
//   → { ok:true, grants:[{ id, steam_id, nickname, type, tier,
//        expiration_date('yyyy-mm-dd'|null), action('grant'|'revoke') }] }
//   (status pending => action 'grant'; status revoked => action 'revoke')
//
// POST ?token=<tok>  body { "id": <int> }
//   → ACK: pending->applied | revoked->removed. { ok:true, id, status }. Idempotente.
// ============================================================

declare(strict_types=1);

$ROOT = dirname(__DIR__, 2);
require $ROOT . '/src/Database.php';
require $ROOT . '/src/Servers.php';

$configFile = $ROOT . '/config/config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    die(json_encode(['ok' => false, 'error' => 'site_not_installed']));
}
$config = require $configFile;
\App\Database::init($config['db']);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(405);
    die(json_encode(['ok' => false, 'error' => 'cors_disabled']));
}

// ============ AUTH (igual sync-players: token global do config OU por-servidor) ============
$received = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_GET['token'] ?? '');
$received = (string) $received;
$globalToken = (string) ($config['agent_token'] ?? '');
$authServer = null;
if ($globalToken !== '' && hash_equals($globalToken, $received)) {
    $authServer = \App\Servers::find(\App\Servers::defaultId());
} elseif ($received !== '') {
    $authServer = \App\Servers::findByToken($received);
}
if (!$authServer) {
    require_once $ROOT . '/src/RateLimit.php';
    \App\RateLimit::init($ROOT . '/storage/cache');
    $rl = \App\RateLimit::check('apifail:' . \App\RateLimit::clientIp(), 15, 300);
    if (empty($rl['allowed'])) { http_response_code(429); die(json_encode(['ok' => false, 'error' => 'rate_limited'])); }
    http_response_code(401);
    die(json_encode(['ok' => false, 'error' => 'invalid_agent_token']));
}
$serverId = (int) $authServer['id'];

// ============ GET — agent puxa pendentes/revogados ============
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = \App\Database::fetchAll(
        "SELECT id, steam_id, nickname, type, tier,
                DATE_FORMAT(expiration_date, '%Y-%m-%d') AS expiration_date, status
           FROM player_grants
          WHERE server_id = ? AND status IN ('pending', 'revoked')
          ORDER BY id ASC",
        [$serverId]
    );
    $grants = array_map(function ($r) {
        return [
            'id'              => (int) $r['id'],
            'steam_id'        => $r['steam_id'],
            'nickname'        => $r['nickname'],
            'type'            => $r['type'],
            'tier'            => $r['tier'],
            'expiration_date' => $r['expiration_date'],
            'action'          => ($r['status'] === 'revoked') ? 'revoke' : 'grant',
        ];
    }, $rows);
    die(json_encode(['ok' => true, 'grants' => $grants]));
}

// ============ POST — agent confirma (ack) ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        die(json_encode(['ok' => false, 'error' => 'missing_id']));
    }
    $g = \App\Database::fetchOne(
        "SELECT id, status FROM player_grants WHERE id = ? AND server_id = ? LIMIT 1",
        [$id, $serverId]
    );
    if (!$g) {
        http_response_code(404);
        die(json_encode(['ok' => false, 'error' => 'grant_not_found']));
    }
    // pending -> applied ; revoked -> removed ; ja final -> idempotente
    $new = null;
    if ($g['status'] === 'pending') $new = 'applied';
    elseif ($g['status'] === 'revoked') $new = 'removed';
    if ($new !== null) {
        \App\Database::query(
            "UPDATE player_grants SET status = ?, applied_at = NOW() WHERE id = ? AND server_id = ?",
            [$new, $id, $serverId]
        );
    } else {
        $new = $g['status']; // ja estava applied/removed/expired
    }
    die(json_encode(['ok' => true, 'id' => $id, 'status' => $new]));
}

http_response_code(405);
die(json_encode(['ok' => false, 'error' => 'method_not_allowed']));
