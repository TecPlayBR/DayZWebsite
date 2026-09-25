<?php
/**
 * A regra de idade em si, sem banco e sem rede. Se isto passa, o gate esta certo;
 * o resto do sistema so pergunta pra esta classe.
 * Rodar: php tests/eca-age-gate.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/AgeGate.php';
use App\AgeGate;

$hoje = new DateTimeImmutable('2026-09-24');

echo "\n1. Idade em bordas\n";
$casos = [
    ['2008-09-24', 18, 'faz 18 HOJE conta como 18'],
    ['2008-09-25', 17, 'faz 18 amanha ainda e 17'],
    ['24/09/2008', 18, 'aceita DD/MM/AAAA'],
    ['2008-02-29', 18, 'nascido em 29/02 de bissexto'],
    ['2000-01-01', 26, 'adulto comum'],
];
foreach ($casos as [$n, $esp, $d]) {
    $r = AgeGate::idade($n, $hoje);
    if ($r === $esp) ok($d); else falha($d, "esperava $esp, veio " . var_export($r, true));
}

echo "\n2. Data invalida ou impossivel nunca vira idade\n";
foreach (['', 'abc', '31/02/2005', '2005-13-01', '1800-01-01', '2030-01-01', '2026-09-25', '05/2005'] as $n) {
    if (AgeGate::idade($n, $hoje) === null) ok("recusa '$n'"); else falha("aceitou '$n'", 'data impossivel nao pode virar menor nem adulto');
}
if (AgeGate::statusPorNascimento('abc', $hoje) === null) ok('statusPorNascimento devolve null em data invalida'); else falha('statusPorNascimento inventou status');
if (AgeGate::statusPorNascimento('2010-01-01', $hoje) === 'menor') ok('16 anos = menor'); else falha('16 anos nao virou menor');
if (AgeGate::statusPorNascimento('2000-01-01', $hoje) === 'adulto_declarado') ok('26 anos = adulto_declarado'); else falha('26 anos nao virou adulto_declarado');

echo "\n3. Comprar (loja) por estado e modo\n";
$c = [
    ['desconhecido',       'declaracao', false], ['menor', 'declaracao', false],
    ['adulto_declarado',   'declaracao', true],  ['adulto_verificado', 'declaracao', true],
    ['desconhecido',       'verificado', false], ['adulto_declarado', 'verificado', true],
    ['desconhecido',       'desligado',  true],  ['menor', 'desligado', false],
];
foreach ($c as [$st, $modo, $esp]) {
    if (AgeGate::podeComprar($st, $modo) === $esp) ok("comprar: $st em $modo = " . ($esp ? 'sim' : 'nao'));
    else falha("comprar: $st em $modo deveria ser " . ($esp ? 'sim' : 'nao'));
}

echo "\n4. Abrir caixa por estado, modo e diaria\n";
$c = [
    // status, modo, diaria?, diariaExige?, esperado
    ['adulto_verificado', 'verificado', false, true,  true],
    ['adulto_declarado',  'verificado', false, true,  false],
    ['adulto_declarado',  'declaracao', false, true,  false],   // declaracao NAO abre caixa
    ['adulto_declarado',  'declaracao', true,  true,  false],   // diaria gated
    ['adulto_declarado',  'declaracao', true,  false, true],    // diaria liberada por config
    ['desconhecido',      'declaracao', true,  false, false],   // mas so pra quem declarou
    ['desconhecido',      'desligado',  false, true,  true],    // modo desligado = como hoje
    ['menor',             'desligado',  false, true,  false],   // menor NUNCA, nem desligado
    ['menor',             'desligado',  true,  false, false],
];
foreach ($c as [$st, $modo, $di, $dx, $esp]) {
    if (AgeGate::podeAbrirCaixa($st, $modo, $di, $dx) === $esp) ok("caixa: $st/$modo/diaria=" . (int)$di . "/exige=" . (int)$dx . " = " . ($esp ? 'sim' : 'nao'));
    else falha("caixa: $st/$modo/diaria=" . (int)$di . "/exige=" . (int)$dx . " deveria ser " . ($esp ? 'sim' : 'nao'));
}

echo "\n5. CPF: limpeza, validacao e hash\n";
if (AgeGate::cpfLimpo('529.982.247-25') === '52998224725') ok('tira mascara e aceita CPF valido'); else falha('nao limpou/validou CPF valido');
foreach (['111.111.111-11', '123', '52998224726', ''] as $c2) {
    if (AgeGate::cpfLimpo($c2) === null) ok("recusa '$c2'"); else falha("aceitou CPF invalido '$c2'");
}
$h1 = AgeGate::cpfHash('52998224725', 'sal-a'); $h2 = AgeGate::cpfHash('529.982.247-25', 'sal-a'); $h3 = AgeGate::cpfHash('52998224725', 'sal-b');
if ($h1 === $h2) ok('hash ignora mascara'); else falha('hash muda com mascara');
if ($h1 !== $h3) ok('hash muda com o sal (site A nao cruza com site B)'); else falha('hash nao depende do sal');
if (strlen($h1) === 64 && ctype_xdigit($h1)) ok('hash e sha256 hex'); else falha('hash nao e sha256 hex');
if (strpos($h1, '52998224725') === false) ok('o CPF nao aparece no hash'); else falha('CPF em claro no hash');

echo "\n6. Caminho de retorno: so caminho interno, nunca outro host\n";
foreach (['/caixas', '/shop?server=2', '/idade?motivo=caixa&return=%2Fcaixas', '/'] as $r) {
    if (AgeGate::returnSeguro($r) === $r) ok("aceita '$r'"); else falha("recusou caminho interno '$r'");
}
foreach (['//evil.com', '/\\evil.com', '\\\\evil.com', 'https://evil.com', 'evil.com', "/ok\r\nLocation: https://evil.com", '/ok javascript:', ''] as $r) {
    if (AgeGate::returnSeguro($r) === '/') ok('recusa ' . json_encode($r)); else falha('aceitou ' . json_encode($r), 'open redirect: o navegador normaliza barra invertida e aceita host relativo');
}

echo "\n7. Quais blocos a tela /idade mostra (status, motivo, ja consentiu?)\n";
// Revisao: quem verificou por CPF ANTES de declarar caia numa tela vazia e nunca mais comprava.
$c = [
    ['desconhecido',      'comprar', false, ['declarar']],
    ['desconhecido',      'caixa',   false, ['declarar', 'verificar']],
    ['adulto_verificado', 'comprar', false, ['consentir']],            // o beco sem saida
    ['adulto_declarado',  'comprar', false, ['consentir']],            // terms_version mudou
    ['adulto_declarado',  'comprar', true,  []],
    ['adulto_declarado',  'caixa',   true,  ['verificar']],
    ['adulto_verificado', 'caixa',   true,  []],
    ['menor',             'comprar', false, ['menor', 'verificar']],
    ['menor',             'caixa',   true,  ['menor', 'verificar']],
];
foreach ($c as [$st, $mo, $cons, $esp]) {
    $r = AgeGate::telas($st, $mo, $cons);
    if ($r === $esp) ok("telas($st, $mo, " . ($cons ? 'consentiu' : 'sem consentimento') . ") = [" . implode(',', $esp) . "]");
    else falha("telas($st, $mo, " . ($cons ? 'consentiu' : 'sem') . ") deveria ser [" . implode(',', $esp) . "]", 'veio [' . implode(',', $r) . ']');
}

echo "\n8. Declarar de novo: menor so sai por CPF, verificado nao rebaixa\n";
$c = [
    ['desconhecido',      'adulto_declarado', 'adulto_declarado'],
    ['desconhecido',      'menor',            'menor'],
    ['menor',             'adulto_declarado', null],                 // redeclarar nao reabre
    ['menor',             'menor',            'menor'],
    ['adulto_verificado', 'adulto_declarado', 'adulto_verificado'],  // nao rebaixa
    ['adulto_verificado', 'menor',            'menor'],              // menor sempre vence
    ['adulto_declarado',  'menor',            'menor'],
    ['adulto_declarado',  'adulto_declarado', 'adulto_declarado'],
];
foreach ($c as [$atual, $decl, $esp]) {
    $r = AgeGate::proximoStatusDeclaracao($atual, $decl);
    if ($r === $esp) ok("$atual + declara $decl = " . var_export($esp, true)); else falha("$atual + declara $decl deveria ser " . var_export($esp, true), 'veio ' . var_export($r, true));
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
