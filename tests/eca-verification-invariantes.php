<?php
/**
 * Invariantes da camada de banco da verificacao de idade, lidos do codigo:
 *  - o CPF nunca vai pro banco, pro log ou pra sessao
 *  - a revogacao move o hash pra cpf_hash_revoked (libera o UNIQUE)
 *  - falha do fornecedor nao muda o age_status
 * Rodar: php tests/eca-verification-invariantes.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }

$ROOT = dirname(__DIR__);
$src = @file_get_contents($ROOT . '/src/AgeVerification.php') ?: '';
if ($src === '') { falha('src/AgeVerification.php nao existe'); }
require_once $ROOT . '/src/AgeGate.php';
require_once $ROOT . '/src/AgeVerifier.php';
if ($src !== '') require_once $ROOT . '/src/AgeVerification.php';

echo "\n1. O CPF nao vaza\n";
$semComentario = preg_replace('#//[^\n]*|/\*.*?\*/#s', '', $src);
if (!preg_match('/\$_SESSION\[[^\]]*cpf/i', $semComentario)) ok('nenhum CPF em sessao'); else falha('CPF posto em $_SESSION');
if (!preg_match('/error_log\([^;]*\$cpf/i', $semComentario)) ok('nenhum error_log com $cpf'); else falha('error_log com o CPF');
if (!preg_match('/INSERT INTO age_verifications[^;]*\bcpf\b(?!_hash)/i', $semComentario)) ok('o INSERT so grava cpf_hash'); else falha('INSERT grava uma coluna cpf');
if (preg_match('/AgeGate::cpfHash\(/', $semComentario)) ok('usa AgeGate::cpfHash'); else falha('nao usa AgeGate::cpfHash');

echo "\n2. Revogacao libera o hash\n";
if (preg_match('/UPDATE age_verifications SET[^;]*cpf_hash_revoked\s*=\s*cpf_hash[^;]*cpf_hash\s*=\s*NULL/is', $semComentario)) ok('revogar move o hash e zera cpf_hash'); else falha('revogar nao move o hash pra cpf_hash_revoked');

echo "\n3. Falha do fornecedor nao muda o status\n";
if (preg_match("/'falhou'[^;]*return \[\s*'ok'\s*=>\s*false/s", $semComentario) || preg_match("/result'\]\s*===\s*'falhou'\)\s*\{[^}]*return/s", $semComentario)) ok('retorna antes de mexer em players quando falhou'); else falha('falha do fornecedor pode estar mudando o age_status');

echo "\n4. Assinaturas publicas que as rotas usam\n";
foreach (['statusDe', 'modo', 'diariaExige', 'declarar', 'verificar', 'consentir', 'temConsentimento', 'revogar', 'podeComprar', 'podeAbrirCaixa'] as $f) {
    if ($src !== '' && method_exists('App\\AgeVerification', $f)) ok("AgeVerification::$f existe"); else falha("AgeVerification::$f nao existe");
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
