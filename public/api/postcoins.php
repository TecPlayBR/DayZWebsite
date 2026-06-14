<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/postcoins.php — gravação de saldo pro MOD SPARDA (Api_Post)
// ============================================================
// O mod Sparda grava o novo saldo do jogador aqui (depois que ele gasta in-game).
//
//   Mod (Api_Post): POST /api/postcoins.php?token=<AGENT_TOKEN>
//                   body { "steamid": "765...", "coins": 90 }
//   Resposta:       { "success": true, "steamid": "...", "moedas": "90", "coins": 90 }
//
// Aceita "coins" ou "moedas", em JSON ou form. Auth = agent_token.
// Registra heartbeat (sparda_last_sync) -> o site sabe que a entrega está ativa.
// ============================================================

declare(strict_types=1);

$ROOT = dirname(__DIR__, 2);
require $ROOT . '/src/Database.php';

$configFile = $ROOT . '/config/config.php';
if (!is_file($configFile)) { http_response_code(503); die(json_encode(['error' => 'not_installed'])); }
$config = require $configFile;
\App\Database::init($config['db']);
require $ROOT . '/src/RateLimit.php';
\App\RateLimit::init($ROOT . '/storage/cache');

header('Content-Type: application/json; charset=utf-8');

$__rawbody = (string) file_get_contents('php://input');

$token    = $_GET['token'] ?? $_SERVER['HTTP_X_SPARDA_TOKEN'] ?? '';
// Mod Sparda chama URL limpa + body JSON; aceita token no PATH_INFO também:
// /api/postcoins.php/<TOKEN>  (compat total com o mod, sem ?token=).
if ($token === '' && !empty($_SERVER['PATH_INFO'])) { $token = ltrim((string) $_SERVER['PATH_INFO'], '/'); }
$expected = (string) ($config['agent_token'] ?? '');
if ($expected === '' || !hash_equals($expected, (string) $token)) {
    // Brute-force guard: conta SÓ falhas de auth por IP. Tráfego legítimo do mod
    // (token válido) NUNCA é limitado.
    $rl = \App\RateLimit::check('apifail:' . \App\RateLimit::clientIp(), 15, 300);
    if (empty($rl['allowed'])) { http_response_code(429); die(json_encode(['error' => 'rate_limited'])); }
    http_response_code(401);
    die(json_encode(['error' => 'unauthorized']));
}

$input = json_decode($__rawbody, true);
if (!is_array($input) || !$input) $input = $_POST;

$steamid = trim((string) ($input['steamid'] ?? $_GET['steamid'] ?? ''));
$coins   = $input['coins'] ?? $input['moedas'] ?? $_GET['coins'] ?? $_GET['moedas'] ?? null;

if (!preg_match('/^\d{17}$/', $steamid)) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid_steamid']));
}
if (!is_numeric($coins) || (int) $coins < 0 || (int) $coins > 1000000000) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid_coins']));
}
$new = (int) $coins;

try {
    $existing = \App\Database::fetchOne("SELECT id, coins FROM players WHERE steam_id = ? LIMIT 1", [$steamid]);
    $old = $existing ? (int) $existing['coins'] : null;

    \App\Database::query(
        "INSERT INTO players (steam_id, coins, last_seen_at) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE coins = VALUES(coins), last_seen_at = NOW()",
        [$steamid, $new]
    );

    // Auditoria de saldo (silenciosa se faltar a tabela / player).
    try {
        $pid = $existing['id'] ?? \App\Database::fetchColumn("SELECT id FROM players WHERE steam_id = ?", [$steamid]);
        if ($pid) {
            \App\Database::query(
                "INSERT INTO balance_log (player_id, steam_id, balance_before, balance_after, source, ref_type)
                 VALUES (?, ?, ?, ?, 'sparda', 'mod')",
                [(int) $pid, $steamid, $old ?? 0, $new]
            );
        }
    } catch (\Throwable $e) { /* balance_log opcional */ }

    // Heartbeat: marca a entrega-nativa Sparda como ativa (pro delivery_active do site).
    try {
        \App\Database::query(
            "INSERT INTO settings (`key`, `value`) VALUES ('sparda_last_sync', ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [(string) time()]
        );
    } catch (\Throwable $e) { /* settings opcional */ }

    echo json_encode(['success' => true, 'steamid' => $steamid, 'moedas' => (string) $new, 'coins' => $new]);
} catch (\Throwable $e) {
    error_log('[postcoins] ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'db_error']));
}
