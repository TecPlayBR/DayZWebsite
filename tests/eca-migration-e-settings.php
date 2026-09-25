<?php
/**
 * Garante que a migration do ECA Digital existe, e idempotente e que toda chave de
 * settings que ela cria esta no SCHEMA (senao o painel nao consegue gravar).
 * Rodar: php tests/eca-migration-e-settings.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/Settings.php';

echo "\n1. A migration existe e e idempotente\n";
$mig = $ROOT . '/migrations/v3.3.0_eca_digital.sql';
if (!file_exists($mig)) { falha('migrations/v3.3.0_eca_digital.sql nao existe'); }
$sql = file_exists($mig) ? file_get_contents($mig) : '';
foreach (['CREATE TABLE IF NOT EXISTS age_verifications', 'CREATE TABLE IF NOT EXISTS consents',
          'ADD COLUMN IF NOT EXISTS age_status', 'ADD COLUMN IF NOT EXISTS age_verified_at',
          'ADD COLUMN IF NOT EXISTS cpf_hash_revoked', 'INSERT IGNORE INTO settings'] as $trecho) {
    if (stripos($sql, $trecho) !== false) ok("tem `$trecho`"); else falha("falta `$trecho`", 'sem IF NOT EXISTS a migration quebra na segunda rodada');
}
if (preg_match('/\bcpf\b(?!_hash)/i', preg_replace('/--[^\n]*/', '', $sql))) {
    falha('a migration tem uma coluna/campo chamado `cpf` sem ser hash', 'CPF nunca e gravado');
} else { ok('nenhuma coluna guarda o CPF em claro'); }
if (stripos($sql, "'age_gate_mode', 'declaracao'") !== false) ok('modo inicial e declaracao'); else falha('modo inicial nao e declaracao');
if (stripos($sql, "'age_hash_salt', SHA2(") !== false) ok('sal do hash e gerado no banco, aleatorio por site'); else falha('sal do hash nao e gerado na migration');

echo "\n2. Toda chave nova esta no Settings::SCHEMA\n";
$esperado = ['age_gate_mode' => 'string', 'age_provider' => 'string', 'age_provider_key' => 'string',
             'age_provider_secret' => 'string', 'age_daily_box_gated' => 'bool',
             'age_hash_salt' => 'string', 'terms_version' => 'string'];
foreach ($esperado as $k => $tipo) {
    if ((\App\Settings::SCHEMA[$k] ?? null) === $tipo) ok("$k => $tipo"); else falha("$k nao esta no SCHEMA como $tipo", 'Settings::set() rejeita chave fora do SCHEMA');
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
