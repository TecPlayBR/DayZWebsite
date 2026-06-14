<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/getcoins.php — leitura de saldo pro MOD SPARDA (Api_Get)
// ============================================================
// Entrega NATIVA via mod Sparda (sem o Agent pago): o mod lê o saldo de moedas
// do jogador direto daqui. O site vende a moeda (Mercado Pago) e credita o saldo;
// o mod Sparda lê via este endpoint e o player gasta na loja in-game.
//
//   Mod (Api_Get):  GET /api/getcoins.php?steamid=765...&token=<AGENT_TOKEN>
//   Resposta:       { "steamid": "...", "moedas": "100", "coins": 100 }
//
// O mod usa "coins"; devolvemos as duas chaves pra compat total.
// Auth: ?token= (ou header X-Sparda-Token) == agent_token do config.php.
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

$token    = $_GET['token'] ?? $_SERVER['HTTP_X_SPARDA_TOKEN'] ?? '';
// Mod Sparda chama URL limpa + anexa ?steamid=; não manda ?token=. Aceita token
// no PATH_INFO: /api/getcoins.php/<TOKEN>?steamid=765... (compat total com o mod).
if ($token === '' && !empty($_SERVER['PATH_INFO'])) { $token = ltrim((string) $_SERVER['PATH_INFO'], '/'); }
$expected = (string) ($config['agent_token'] ?? '');
if ($expected === '' || !hash_equals($expected, (string) $token)) {
    // Brute-force guard: conta SÓ falhas de auth por IP. O tráfego legítimo do mod
    // (token válido, todo de 1 IP = servidor de jogo) NUNCA toca o limitador.
    $rl = \App\RateLimit::check('apifail:' . \App\RateLimit::clientIp(), 15, 300);
    if (empty($rl['allowed'])) { http_response_code(429); die(json_encode(['error' => 'rate_limited'])); }
    http_response_code(401);
    die(json_encode(['error' => 'unauthorized']));
}

$steamid = trim((string) ($_GET['steamid'] ?? ''));
if (!preg_match('/^\d{17}$/', $steamid)) {
    http_response_code(400);
    die(json_encode(['error' => 'invalid_steamid']));
}

try {
    $row = \App\Database::fetchOne("SELECT coins FROM players WHERE steam_id = ? LIMIT 1", [$steamid]);
    if (!$row) {
        // Jogador novo consultado pelo mod: cria com 0 (origin default = 'agent').
        \App\Database::query(
            "INSERT INTO players (steam_id, coins, last_seen_at) VALUES (?, 0, NOW())", [$steamid]
        );
        $coins = 0;
    } else {
        $coins = (int) $row['coins'];
    }
    // Heartbeat: o mod conversou com o site (mesmo só lendo) -> entrega ativa.
    // Throttle: só grava se o último sync foi há >60s (evita write a cada poll).
    try {
        $last = (int) \App\Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'sparda_last_sync'");
        if (time() - $last > 60) {
            \App\Database::query(
                "INSERT INTO settings (`key`, `value`) VALUES ('sparda_last_sync', ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                [(string) time()]
            );
        }
    } catch (\Throwable $e) { /* settings opcional */ }

    echo json_encode(['steamid' => $steamid, 'moedas' => (string) $coins, 'coins' => $coins]);
} catch (\Throwable $e) {
    error_log('[getcoins] ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'db_error']));
}
