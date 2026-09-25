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
if (preg_match("/Router::post\('\/idade\/verificar'.*?unset\(\\\$_POST\['cpf'\]/s", $idx)) ok('o CPF e descartado do POST logo apos AgeVerification::verificar'); else falha('rota /idade/verificar nao descarta o CPF do POST', 'quanto menos maos, menos vazamento');
if (!preg_match("/\\\$_SESSION\[[^\]]*cpf/i", $idx)) ok('nenhum CPF em sessao no index.php'); else falha('index.php poe CPF na sessao');

echo "\n2b. Revisao: consentir sem redeclarar, rate limit por IP e por site, retorno pos-login\n";
if (preg_match("/Router::post\('\/idade\/consentir'.*?Csrf::check\(\)/s", $idx)) ok('/idade/consentir com CSRF'); else falha('falta POST /idade/consentir com CSRF', 'verificado-antes-de-declarar ficava sem formulario');
if (str_contains($idx, "RateLimit::check('idade-verificar-ip:")) ok('bucket por IP na verificacao'); else falha('sem bucket por IP', 'contas Steam gratis queimam consulta paga do cliente');
if (str_contains($idx, "RateLimit::check('idade-verificar-site'")) ok('teto diario do site na verificacao'); else falha('sem teto diario do site');
$cb = substr($idx, strpos($idx, "Router::get('/auth/steam/callback'"), 4000);
if (str_contains($cb, 'AgeGate::returnSeguro(')) ok('callback do Steam usa o mesmo filtro de retorno (aceita %2F)'); else falha('callback do Steam recusa o retorno do /idade (tem % na query)', 'jogador cai na home depois do login');
if (str_contains($idx, "'/idade?motivo=comprar&return=")) ok('checkout manda pra /idade'); else falha('checkout nao manda pra /idade');
$shop = file_get_contents($ROOT . '/views/pages/shop.php');
if (!preg_match('/<input type="text" name="steam_id"/', $shop) && str_contains($shop, '/auth/steam')) ok('loja deslogada mostra Entrar com Steam, nao campo de SteamID'); else falha('loja deslogada ainda pede SteamID digitado', 'o checkout agora exige login: o campo engana');
if (str_contains($idx, "['cpf_taken', 'failed_retry']") && str_contains($idx, "__('idade.' . \$cod)")) ok('rotas mapeiam cpf_taken/failed_retry pro stringtable, sem erro cru do fornecedor'); else falha('rotas com texto fixo em vez de idade.failed_retry / idade.cpf_taken');

echo "\n3. A view existe e nao tem input de CPF com autocomplete\n";
$v0 = @file_get_contents($ROOT . '/views/pages/idade.php') ?: '';
if (str_contains($v0, 'AgeGate::telas(')) ok('view decide os blocos por AgeGate::telas'); else falha('view nao usa AgeGate::telas', 'foi assim que o beco sem saida passou');
$v = @file_get_contents($ROOT . '/views/pages/idade.php') ?: '';
if ($v !== '') ok('views/pages/idade.php existe'); else falha('views/pages/idade.php nao existe');
if (preg_match('/name="cpf"[^>]*autocomplete="off"/', $v)) ok('campo CPF com autocomplete=off'); else falha('campo CPF sem autocomplete=off');
// Bryan (25/09): mascara 000.000.000-00 montando sozinha, so digitos, 11 fixos, sem deixar o usuario errar ponto/traco.
if (preg_match('/name="cpf"[^>]*data-cpf-mask/', $v) && preg_match('/name="cpf"[^>]*maxlength="14"/', $v)) ok('campo CPF com data-cpf-mask e 14 chars (11 digitos + pontuacao)'); else falha('campo CPF sem mascara declarada');
if (preg_match('/data-cpf-mask.*?<script>.*?replace\(\/\\\\D\/g|<script>.*?data-cpf-mask.*?replace\(\/\\\\D/s', $v) && str_contains($v, 'slice(0, 11)')) ok('script da mascara tira tudo que nao e digito e corta em 11'); else falha('script da mascara ausente ou nao limita a 11 digitos');
if (preg_match('/name="terms_ok"[^>]*type="checkbox"|type="checkbox"[^>]*name="terms_ok"/', $v)) ok('checkbox real de termos'); else falha('sem checkbox real de termos');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
