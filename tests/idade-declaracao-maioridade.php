<?php
/**
 * 20/10/2026, ordem do Bryan depois do caso de um menor de 15 anos que comprou um servidor: a tela /idade exige
 * uma caixa propria em que a pessoa declara ter 18 anos ou mais e confirma e comprova ser maior de idade. A caixa e
 * conferida no SERVIDOR (o `required` do navegador se pula com um POST na mao) nos dois caminhos que dao status de
 * adulto: declarar (data de nascimento) e verificar (CPF).
 * Rodar: php tests/idade-declaracao-maioridade.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d): void { global $falhas; $falhas++; echo "  FALHA $d\n"; }
$ROOT = dirname(__DIR__);
$idx  = str_replace("\r\n", "\n", file_get_contents($ROOT . '/public/index.php'));
$view = str_replace("\r\n", "\n", file_get_contents($ROOT . '/views/pages/idade.php'));
$pt   = require $ROOT . '/lang/pt-br.php';
$en   = require $ROOT . '/lang/en-us.php';

function bloco(string $src, string $inicio, string $fim): string {
    $a = strpos($src, $inicio);
    if ($a === false) return '';
    $b = strpos($src, $fim, $a + strlen($inicio));
    return substr($src, $a, $b === false ? null : $b - $a);
}

$fDeclarar  = bloco($view, 'action="/idade/declarar"', '</form>');
$fVerificar = bloco($view, 'action="/idade/verificar"', '</form>');
foreach (['declarar' => $fDeclarar, 'verificar' => $fVerificar] as $n => $f) {
    if (preg_match('/<input type="checkbox" name="adulto_ok" value="1" required>/', $f)) ok("form $n tem a caixa adulto_ok obrigatoria");
    else falha("form $n sem a caixa adulto_ok obrigatoria");
    if (str_contains($f, "__('idade.adult_label')")) ok("form $n mostra o texto da declaracao"); else falha("form $n sem o texto da declaracao");
}

$rDeclarar  = bloco($idx, "Router::post('/idade/declarar'", "Router::post(");
$rVerificar = bloco($idx, "Router::post('/idade/verificar'", "// ============ ADMIN");
foreach (['declarar' => $rDeclarar, 'verificar' => $rVerificar] as $n => $r) {
    $p = strpos($r, "empty(\$_POST['adulto_ok'])");
    if ($p !== false) ok("rota $n recusa sem adulto_ok"); else falha("rota $n nao confere adulto_ok no servidor");
    $chamada = $n === 'declarar' ? 'AgeVerification::declarar(' : 'AgeVerification::verificar(';
    $q = strpos($r, $chamada);
    if ($p !== false && $q !== false && $p < $q) ok("rota $n confere ANTES de gravar ou consultar"); else falha("rota $n confere tarde demais");
    if (str_contains($r, "erro=adulto")) ok("rota $n devolve o erro 'adulto'"); else falha("rota $n sem o erro 'adulto'");
}

$lbl = $pt['idade']['adult_label'] ?? '';
if (str_contains($lbl, '18 anos') && str_contains($lbl, 'comprovo') && str_contains($lbl, 'maior de idade')) ok('texto em portugues diz 18 anos, confirmo e comprovo');
else falha('texto em portugues errado: ' . $lbl);
if (($en['idade']['adult_label'] ?? '') !== '' && str_contains($en['idade']['adult_label'], '18')) ok('texto em ingles existe'); else falha('falta o texto em ingles');
if (($pt['idade']['err_adulto'] ?? '') !== '' && ($en['idade']['err_adulto'] ?? '') !== '') ok('mensagem de erro nos dois idiomas'); else falha('falta a mensagem de erro err_adulto');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
