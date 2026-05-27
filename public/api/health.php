<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/health.json
// ============================================================
// Endpoint publico de health-check pra uptime monitors externos
// (UptimeRobot, BetterStack, StatusCake, etc).
//
// Retorna HTTP 200 se tudo ok, 503 se algo crítico ta down.
// Sem auth — monitor externo precisa acessar sem credencial.
// Cache busting OK (não cacheia).
// ============================================================

declare(strict_types=1);
$ROOT = dirname(__DIR__, 2);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');

$start = microtime(true);

// Carrega config
$configFile = $ROOT . '/config/config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    die(json_encode([
        'ok'      => false,
        'status'  => 'not_installed',
        'message' => 'Site nao instalado. Acesse /install.php',
    ]));
}
$config = require $configFile;

// Tenta DB
$dbOk = false;
$lastPurchase = null;
$agentActive = false;
$pendingCount = null;
$playersCount = null;
$dbError = null;

try {
    require $ROOT . '/src/Database.php';
    \App\Database::init($config['db']);
    $pdo = \App\Database::pdo();
    $dbOk = (int)$pdo->query("SELECT 1")->fetchColumn() === 1;

    // Última compra
    $lastPurchase = \App\Database::fetchColumn(
        "SELECT created_at FROM purchases ORDER BY created_at DESC LIMIT 1"
    ) ?: null;

    // Agent ativo? Considera ativo se algum player teve last_seen_at < 5min
    $agentLast = \App\Database::fetchColumn(
        "SELECT MAX(last_seen_at) FROM players WHERE origin = 'agent'"
    );
    $agentActive = $agentLast && strtotime($agentLast) > (time() - 300);

    $pendingCount = (int)\App\Database::fetchColumn(
        "SELECT COUNT(*) FROM purchases WHERE mp_status = 'pending'"
    );
    $playersCount = (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM players");
} catch (Throwable $e) {
    $dbError = 'connection_failed';
    error_log("[health.json] DB: " . $e->getMessage());
}

$ok = $dbOk;

if (!$ok) http_response_code(503);

$response = [
    'ok'             => $ok,
    'status'         => $ok ? 'healthy' : 'degraded',
    'db'             => $dbOk ? 'up' : 'down',
    'agent'          => $agentActive ? 'active' : 'idle',
    'players'        => $playersCount,
    'pending_orders' => $pendingCount,
    'last_purchase'  => $lastPurchase,
    'version'        => '1.0',
    'now'            => gmdate('Y-m-d\TH:i:s\Z'),
    'response_ms'    => round((microtime(true) - $start) * 1000, 1),
];
if ($dbError) $response['error'] = $dbError;

die(json_encode($response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
