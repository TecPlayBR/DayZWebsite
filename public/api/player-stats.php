<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/player-stats — estatísticas de gameplay (leaderboard/perfil)
// ============================================================
// O Bot puxa os stats do CFTools (resolve Steam64 -> cftools_id) e EMPURRA aqui.
// Auth: header  Authorization: Bearer <discord_integration_token>  (mesmo token
// do /api/bot-integration.php).
//
// POST → upsert de stats (aceita lote):
//   Body: { "players": [ {
//     "steam_id": "7656119...", "cftools_id": "uuid?",
//     "kills": 9, "deaths": 1, "kdratio": 9.00, "playtime_seconds": 995460,
//     "longest_kill_m": 16, "longest_shot_m": 16, "suicides": 0, "kills_infected": 4,
//     "extra": { ... campos ricos: precisão, top_weapons[], kills_animals ... }
//   } ] }
//   Response: { "ok": true, "upserted": N }
//
// GET  → leitura:
//   ?steam_id=765...                  -> { ok, stats: {...} | null }
//   ?leaderboard=kills|kd|playtime|longest&limit=100 -> { ok, stat, entries: [...] }
// ============================================================

declare(strict_types=1);

$ROOT = dirname(__DIR__, 2);
require $ROOT . '/src/Database.php';

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

// ============ AUTH (Bearer == discord_integration_token) ============
function _bearer_token(): string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) { $h = $v; break; }
        }
    }
    if (stripos($h, 'Bearer ') === 0) return trim(substr($h, 7));
    return '';
}

$expected = (string) (\App\Database::fetchColumn(
    "SELECT `value` FROM settings WHERE `key` = 'discord_integration_token'"
) ?: '');
if ($expected === '') {
    http_response_code(401);
    die(json_encode(['ok' => false, 'error' => 'token_not_configured']));
}
if (!hash_equals($expected, _bearer_token())) {
    http_response_code(401);
    die(json_encode(['ok' => false, 'error' => 'unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ============ POST — Bot empurra stats (upsert em lote) ============
if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $players = $body['players'] ?? null;
    // Aceita também um único objeto (sem o wrapper "players")
    if ($players === null && isset($body['steam_id'])) $players = [$body];
    if (!is_array($players)) {
        http_response_code(400);
        die(json_encode(['ok' => false, 'error' => 'invalid_body']));
    }

    $sql = "INSERT INTO player_stats
        (steam_id, cftools_id, kills, deaths, kdratio, playtime_seconds,
         longest_kill_m, longest_shot_m, suicides, kills_infected, extra_json)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
         cftools_id=VALUES(cftools_id), kills=VALUES(kills), deaths=VALUES(deaths),
         kdratio=VALUES(kdratio), playtime_seconds=VALUES(playtime_seconds),
         longest_kill_m=VALUES(longest_kill_m), longest_shot_m=VALUES(longest_shot_m),
         suicides=VALUES(suicides), kills_infected=VALUES(kills_infected),
         extra_json=VALUES(extra_json)";

    $upserted = 0; $skipped = 0;
    foreach ($players as $p) {
        $steamId = (string) ($p['steam_id'] ?? $p['steamid'] ?? '');
        if (!preg_match('/^7656119[0-9]{10}$/', $steamId)) { $skipped++; continue; }
        $extra = isset($p['extra']) && (is_array($p['extra']) || is_object($p['extra']))
               ? json_encode($p['extra'], JSON_UNESCAPED_UNICODE)
               : null;
        try {
            \App\Database::query($sql, [
                $steamId,
                isset($p['cftools_id']) ? (string)$p['cftools_id'] : null,
                (int)   ($p['kills'] ?? 0),
                (int)   ($p['deaths'] ?? 0),
                (float) ($p['kdratio'] ?? 0),
                (int)   ($p['playtime_seconds'] ?? 0),
                (int)   ($p['longest_kill_m'] ?? 0),
                (int)   ($p['longest_shot_m'] ?? 0),
                (int)   ($p['suicides'] ?? 0),
                (int)   ($p['kills_infected'] ?? 0),
                $extra,
            ]);
            $upserted++;
        } catch (\Throwable $e) {
            error_log('[player-stats] upsert falhou: ' . $e->getMessage());
            $skipped++;
        }
    }
    echo json_encode(['ok' => true, 'upserted' => $upserted, 'skipped' => $skipped]);
    exit;
}

// ============ GET — leitura (perfil ou leaderboard) ============
$lbCols = [
    'kills'    => 'kills',
    'kd'       => 'kdratio',
    'playtime' => 'playtime_seconds',
    'longest'  => 'longest_kill_m',
];

if (isset($_GET['leaderboard'])) {
    $stat = $_GET['leaderboard'];
    if (!isset($lbCols[$stat])) {
        http_response_code(400);
        die(json_encode(['ok' => false, 'error' => 'invalid_stat']));
    }
    $col   = $lbCols[$stat];
    $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
    $rows = \App\Database::fetchAll(
        "SELECT ps.steam_id, p.display_name, ps.$col AS value
           FROM player_stats ps
           LEFT JOIN players p ON p.steam_id = ps.steam_id
          ORDER BY ps.$col DESC
          LIMIT $limit"
    );
    echo json_encode(['ok' => true, 'stat' => $stat, 'entries' => $rows]);
    exit;
}

$steamId = (string) ($_GET['steam_id'] ?? '');
if (!preg_match('/^7656119[0-9]{10}$/', $steamId)) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'error' => 'invalid_steam_id']));
}
$row = \App\Database::fetchOne("SELECT * FROM player_stats WHERE steam_id = ?", [$steamId]);
if ($row && isset($row['extra_json'])) {
    $row['extra'] = $row['extra_json'] ? json_decode($row['extra_json'], true) : null;
    unset($row['extra_json']);
}
echo json_encode(['ok' => true, 'stats' => $row ?: null]);
