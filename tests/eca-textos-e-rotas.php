<?php
/**
 * As chaves de texto existem nas duas linguas, sem travessao, e as rotas /idade estao
 * registradas com CSRF e rate limit. Estrutural: nao sobe servidor.
 * Rodar: php tests/eca-textos-e-rotas.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);

echo "\n1. Chaves de texto\n";
$pt = require $ROOT . '/lang/pt-br.php'; $en = require $ROOT . '/lang/en-us.php';
$chaves = ['title', 'confirm_title', 'confirm_intro', 'birth_label', 'terms_label', 'continue', 'verify_title', 'verify_intro',
           'cpf_label', 'cpf_not_stored', 'one_account', 'verify_btn', 'minor_title', 'minor_body', 'cpf_taken', 'verified_ok', 'failed_retry', 'why'];
foreach ($chaves as $k) {
    $p = $pt['idade'][$k] ?? null; $e = $en['idade'][$k] ?? null;
    if (is_string($p) && $p !== '' && is_string($e) && $e !== '') ok("idade.$k nas duas linguas"); else falha("idade.$k falta em pt-br ou en-us");
    if (is_string($p) && (str_contains($p, "\u{2014}") || str_contains($p, "\u{2013}"))) falha("idade.$k tem travessao");
}

echo "\n2. Rotas\n";
$idx = file_get_contents($ROOT . '/public/index.php');
foreach (["Router::get('/idade'", "Router::post('/idade/declarar'", "Router::post('/idade/verificar'"] as $r) {
    if (str_contains($idx, $r)) ok("$r registrada"); else falha("$r nao registrada");
}
if (preg_match("/Router::post\('\/idade\/verificar'.*?Csrf::check\(\).*?RateLimit::check\('idade-verificar:/s", $idx)) ok('verificar tem CSRF e rate limit'); else falha('/idade/verificar sem CSRF ou sem rate limit', 'cada tentativa custa dinheiro do cliente');
if (preg_match("/Router::post\('\/idade\/verificar'.*?unset\(\\\$_POST\['cpf'\]\)/s", $idx)) ok('o CPF e descartado do POST logo apos AgeVerification::verificar'); else falha('rota /idade/verificar nao descarta o CPF do POST', 'quanto menos maos, menos vazamento');
if (!preg_match("/\\\$_SESSION\[[^\]]*cpf/i", $idx)) ok('nenhum CPF em sessao no index.php'); else falha('index.php poe CPF na sessao');

echo "\n3. A view existe e nao tem input de CPF com autocomplete\n";
$v = @file_get_contents($ROOT . '/views/pages/idade.php') ?: '';
if ($v !== '') ok('views/pages/idade.php existe'); else falha('views/pages/idade.php nao existe');
if (preg_match('/name="cpf"[^>]*autocomplete="off"/', $v)) ok('campo CPF com autocomplete=off'); else falha('campo CPF sem autocomplete=off');
if (preg_match('/name="terms_ok"[^>]*type="checkbox"|type="checkbox"[^>]*name="terms_ok"/', $v)) ok('checkbox real de termos'); else falha('sem checkbox real de termos');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
