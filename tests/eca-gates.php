<?php
/**
 * Os pontos de gate existem e o aceite de termos deixou de ser um campo oculto.
 * Rodar: php tests/eca-gates.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$idx = file_get_contents($ROOT . '/public/index.php');
$bot = file_get_contents($ROOT . '/public/api/bot-integration.php');

echo "\n1. O aceite de termos nao e mais um campo oculto\n";
foreach (['views/pages/shop.php', 'views/pages/checkout_pix.php'] as $v) {
    $s = file_get_contents($ROOT . '/' . $v);
    if (!preg_match('/name="terms_accepted"\s+value="1"/', $s) && !preg_match('/type="hidden"[^>]*name="terms_accepted"/', $s)) ok("$v sem hidden terms_accepted"); else falha("$v ainda marca os termos pelo jogador", 'consentimento que o site marca sozinho nao vale');
}

echo "\n2. Checkout exige podeComprar e consentimento\n";
$chk = substr($idx, strpos($idx, "Router::post('/shop/checkout'"), 4000);
if (str_contains($chk, 'AgeVerification::podeComprar(')) ok('checkout consulta podeComprar'); else falha('checkout nao consulta podeComprar');
if (str_contains($chk, 'AgeVerification::temConsentimento(')) ok('checkout exige consentimento da versao atual'); else falha('checkout nao exige consentimento');
if (str_contains($chk, "'/idade?motivo=comprar")) ok('checkout redireciona pra /idade'); else falha('checkout nao redireciona pra /idade');

echo "\n3. Abrir caixa\n";
$op = substr($idx, strpos($idx, "Router::post('/caixas/{slug}/open'"), 2500);
if (str_contains($op, 'AgeVerification::podeAbrirCaixa(')) ok('abrir caixa consulta podeAbrirCaixa'); else falha('abrir caixa sem gate');
if (str_contains($op, "'error' => 'age'")) ok('devolve error=age'); else falha('nao devolve error=age');
if (strpos($op, 'podeAbrirCaixa(') !== false && strpos($op, 'podeAbrirCaixa(') < strpos($op, 'Boxes::open(')) ok('gate vem ANTES de Boxes::open'); else falha('gate depois do open', 'a caixa ja teria sido sorteada');
$js = file_get_contents($ROOT . '/views/pages/caixas.php');
if (preg_match("/data\.error\s*===\s*'age'/", $js)) ok('JS da pagina redireciona em error=age'); else falha('JS nao trata error=age');

echo "\n4. Cartao\n";
$cd = substr($idx, strpos($idx, "Router::post('/shop/card-pay/{id}'"), 2500);
if (str_contains($cd, 'AgeVerification::podeComprar(')) ok('card-pay consulta podeComprar pelo steam_id da compra'); else falha('card-pay sem gate');

echo "\n5. Bot\n";
$pp = substr($bot, strpos($bot, 'function _prepare_purchase'), 1500);
if (str_contains($pp, 'AgeVerification::podeComprar(') && str_contains($pp, "'age_required'")) ok('_prepare_purchase recusa com age_required'); else falha('bot sem gate age_required');
if (preg_match("/require(_once)?\s+[^;]*AgeGate\.php/", $bot) && preg_match("/require(_once)?\s+[^;]*AgeVerification\.php/", $bot)) ok('bot-integration carrega AgeGate e AgeVerification'); else falha('bot-integration nao carrega as classes', 'este arquivo nao passa pelo bootstrap do index.php');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
