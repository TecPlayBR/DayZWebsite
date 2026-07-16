<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/entitlements-import  (REVERSE sync: jogo -> site)
// ============================================================
// O agent LE os JSON do mod Sparda (SkinStore/KillFeed/VipPanel/BattlePass) e
// posta AQUI o que cada player tem ATIVO in-game (inclusive compras feitas
// DIRETO na loja do jogo, que o site nunca concedeu). Assim o perfil do site
// reflete a realidade do jogo, nao so o que o site concedeu.
//
// Esses registros entram como status='external' -> o /api/entitlements (GET do
// agent) NAO os pega (ele so puxa pending/revoked), entao o agent NUNCA re-aplica
// = nao quebra o fluxo de ida (site->jogo).
//
// Auth: token do servidor (X-Agent-Token OU ?token=), igual /api/entitlements.
//
// POST body JSON:
//   { "entitlements": [ { "steam_id":"7656...", "type":"skin|killfeed|vip|battlepass|loadout",
//                         "tier":"group1|PanelVip2|..."|null, "expiration_date":"yyyy-mm-dd"|null } , ... ] }
//   -> FULL SNAPSHOT: substitui TODO o conjunto 'external' desse servidor (reconcilia remocoes).
//   resp: { ok:true, imported:<int> }
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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    die(json_encode(['ok' => false, 'error' => 'method_not_allowed']));
}

// ============ AUTH (igual /api/entitlements) ============
$received = (string) ($_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_GET['token'] ?? ''));
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

// ============ parse body ============
$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['entitlements']) || !is_array($body['entitlements'])) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'error' => 'bad_payload']));
}
$items = $body['entitlements'];
if (count($items) > 5000) {
    http_response_code(413);
    die(json_encode(['ok' => false, 'error' => 'too_many']));
}

$validTypes = ['vip', 'battlepass', 'skin', 'killfeed', 'loadout'];
$clean = [];
foreach ($items as $it) {
    if (!is_array($it)) continue;
    $steam = trim((string) ($it['steam_id'] ?? ''));
    $type  = trim((string) ($it['type'] ?? ''));
    if (!preg_match('/^7656119[0-9]{10}$/', $steam)) continue;
    if (!in_array($type, $validTypes, true)) continue;
    $tier = isset($it['tier']) && $it['tier'] !== '' ? mb_substr((string) $it['tier'], 0, 20) : null;
    $exp  = trim((string) ($it['expiration_date'] ?? ''));
    $exp  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp) ? $exp : null; // null = permanente
    $clean[] = [$steam, $type, $tier, $exp];
}

// ============ full-replace do conjunto 'external' desse servidor ============
$pdo = \App\Database::pdo();
try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM player_grants WHERE server_id = ? AND status = 'external'")->execute([$serverId]);
    $ins = $pdo->prepare(
        "INSERT INTO player_grants (server_id, steam_id, nickname, type, tier, days, expiration_date, status, notes)
         VALUES (?, ?, NULL, ?, ?, 0, ?, 'external', 'in-game (Sparda)')"
    );
    foreach ($clean as [$steam, $type, $tier, $exp]) {
        $ins->execute([$serverId, $steam, $type, $tier, $exp]);
    }
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[entitlements-import] ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => 'failed']));
}

// Marca a hora da ultima sync in-game (pro admin mostrar "sincronizado ha X" / alertar se parou).
try {
    \App\Database::query(
        "INSERT INTO settings (`key`, `value`) VALUES ('entitlements_import_at', NOW())
         ON DUPLICATE KEY UPDATE `value` = NOW()"
    );
} catch (\Throwable $e) { /* nao critico */ }

echo json_encode(['ok' => true, 'imported' => count($clean)]);
