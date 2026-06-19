<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// API: /api/csp-report  (coletor de violacoes do Content-Security-Policy)
// ============================================================
// Recebe os reports do navegador enquanto o CSP esta em modo Report-Only
// (ver public/.htaccess). NAO bloqueia nada; so registra pra a gente saber
// o que um CSP enforce quebraria ANTES de ligar o enforce.
//
// Seguranca: responde 204 sempre, sem corpo. Loga em storage/cache (acima do
// docroot + .log e bloqueado no .htaccess), com cap de tamanho. Sem DB, sem PII.
// ============================================================

declare(strict_types=1);
http_response_code(204);

// Le no maximo 16KB do report (anti-DoS por payload gigante)
$raw = file_get_contents('php://input', false, null, 0, 16384);
if ($raw === false || $raw === '') exit;

$ROOT = dirname(__DIR__, 2);
$dir  = $ROOT . '/storage/cache';
if (!is_dir($dir)) exit;
$log = $dir . '/csp-reports.log';

// Rotaciona quando passa de ~512KB (trunca; e log de diagnostico, nao critico)
if (is_file($log) && filesize($log) > 512 * 1024) {
    @file_put_contents($log, "");
}

$line = gmdate('c') . ' ' . substr(preg_replace('/\s+/', ' ', $raw), 0, 2000) . "\n";
@file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
