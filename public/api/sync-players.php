<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/sync-players
// ============================================================
// Endpoint chamado pelo tecplay-agent.exe a cada ciclo (15s).
// Auth: header X-Agent-Token: <agent_token do config.php>
//
// GET  → retorna lista de players do site (pro agent comparar com JSON local)
//        Response: { "ok": true, "count": N, "players": [{steam_id, steamid, coins, ...}] }
//
// POST → recebe lista de players atualizados pelo agent (delta tracking)
//        Body:    { "players": [{steam_id|steamid, coins, display_name?}, ...] }
//        Response: { "ok": true, "updated": N, "inserted": N }
//
// COMPAT (CRITICO): o tecplay-agent.exe usa o campo "steamid" (sem underline).
// Por isso o GET devolve "steamid" como alias de "steam_id" e o POST aceita os
// dois. Sem isso, o agent NAO enxerga o DB -> compra paga nunca chega no jogo
// (DB->JSON quebra). NAO remover o alias sem antes atualizar o agent.
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
// CORS desabilitado: endpoint só é chamado server-to-server pelo tecplay-agent
// (CLI/desktop), nunca por browser. Wildcard CORS num endpoint autenticado por
// token = vetor pra brute-force cross-origin.

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(405);
    die(json_encode(['ok' => false, 'error' => 'cors_disabled']));
}

// ============ AUTH ============
// Token vai no header X-Agent-Token. Aceita:
//  1. agent_token global do config.php (legado / single-server)
//  2. agent_token de qualquer servidor ativo na tabela servers (multi-server)
$received = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
$globalToken = $config['agent_token'] ?? '';
$authServer = null;

if ($globalToken !== '' && hash_equals($globalToken, $received)) {
    // Modo legado: usa servidor padrão como contexto
    $authServer = \App\Servers::find(\App\Servers::defaultId());
} elseif ($received !== '') {
    $authServer = \App\Servers::findByToken($received);
}

if (!$authServer) {
    // Brute-force guard: conta só falhas de auth por IP (agent legítimo nunca falha).
    require_once $ROOT . '/src/RateLimit.php';
    \App\RateLimit::init($ROOT . '/storage/cache');
    $rl = \App\RateLimit::check('apifail:' . \App\RateLimit::clientIp(), 15, 300);
    if (empty($rl['allowed'])) { http_response_code(429); die(json_encode(['ok' => false, 'error' => 'rate_limited'])); }
    http_response_code(401);
    die(json_encode(['ok' => false, 'error' => 'invalid_agent_token']));
}

$serverId = (int)$authServer['id'];

// Heartbeat do Agent: marca que ele está vivo. O site usa isso pra saber que a
// ENTREGA in-game está ativa (e não prometer "liberação automática" sem entregador).
try {
    \App\Database::query(
        "INSERT INTO settings (`key`, `value`) VALUES ('agent_last_sync', ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
        [(string) time()]
    );
} catch (\Throwable $e) { /* settings pode não existir em instalação incompleta - ignora */ }

// ============ GET - agent lê players do site (apenas do SEU servidor) ============
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = \App\Database::fetchAll(
        "SELECT steam_id, display_name, coins, total_spent_brl, last_seen_at, origin
           FROM players
          WHERE server_id = ? OR server_id IS NULL
          ORDER BY steam_id",
        [$serverId]
    );
    $players = array_map(function($r) {
        return [
            'steam_id'        => $r['steam_id'],
            'steamid'         => $r['steam_id'],   // alias retrocompat: tecplay-agent lê 'steamid'
            'display_name'    => $r['display_name'],
            'coins'           => (int)$r['coins'],
            'total_spent_brl' => (float)$r['total_spent_brl'],
            'last_seen_at'    => $r['last_seen_at'],
            'origin'          => $r['origin'],
        ];
    }, $rows);
    die(json_encode(['ok' => true, 'count' => count($players), 'players' => $players]));
}

// ============ POST - agent reporta updates ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !isset($body['players']) || !is_array($body['players'])) {
        http_response_code(400);
        die(json_encode(['ok' => false, 'error' => 'expected_players_array']));
    }

    $updated = 0;
    $inserted = 0;
    $rejected = 0;

    foreach ($body['players'] as $p) {
        $steam = (string)($p['steam_id'] ?? $p['steamid'] ?? '');
        if (!preg_match('/^7656119[0-9]{10}$/', $steam)) {
            $rejected++;
            continue;
        }
        $coins = (int)($p['coins'] ?? 0);
        if ($coins < 0) $coins = 0;
        $name  = isset($p['display_name']) ? trim((string)$p['display_name']) : null;

        // Upsert escopado pelo servidor que está fazendo o sync
        $existing = \App\Database::fetchOne(
            "SELECT id FROM players WHERE steam_id = ? AND (server_id = ? OR server_id IS NULL) LIMIT 1",
            [$steam, $serverId]
        );
        if ($existing) {
            \App\Database::query(
                "UPDATE players
                    SET coins = ?,
                        display_name = COALESCE(?, display_name),
                        server_id = ?,
                        last_seen_at = NOW(),
                        origin = 'agent'
                  WHERE id = ?",
                [$coins, $name, $serverId, $existing['id']]
            );
            $updated++;
        } else {
            \App\Database::query(
                "INSERT INTO players (steam_id, display_name, coins, server_id, last_seen_at, origin)
                 VALUES (?, ?, ?, ?, NOW(), 'agent')",
                [$steam, $name, $coins, $serverId]
            );
            $inserted++;
        }
    }

    die(json_encode([
        'ok'       => true,
        'updated'  => $updated,
        'inserted' => $inserted,
        'rejected' => $rejected,
        'count'    => $updated + $inserted,
    ]));
}

http_response_code(405);
die(json_encode(['ok' => false, 'error' => 'method_not_allowed']));
