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

header('Content-Type: application/json; charset=utf-8');

$token    = $_GET['token'] ?? $_SERVER['HTTP_X_SPARDA_TOKEN'] ?? '';
$expected = (string) ($config['agent_token'] ?? '');
if ($expected === '' || !hash_equals($expected, (string) $token)) {
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
    echo json_encode(['steamid' => $steamid, 'moedas' => (string) $coins, 'coins' => $coins]);
} catch (\Throwable $e) {
    error_log('[getcoins] ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'db_error']));
}
