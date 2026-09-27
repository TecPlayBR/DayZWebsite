<?php
/**
 * A faixa de aviso de seguranca tem que aparecer em qualquer tela (27/09/2026).
 * Ela ficava ANTES do cabecalho, que e fixo no topo: no notebook sumia inteira atras dele,
 * no celular so a ultima linha escapava. Agora mora dentro do cabecalho fixo, e o conteudo
 * da pagina desce a altura dela (--aviso-h, medida pelo app.js).
 * Rodar: php tests/aviso-visivel.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$main = file_get_contents($ROOT . '/views/layouts/main.php');
$hdr  = file_get_contents($ROOT . '/views/partials/header.php');
$css  = file_get_contents($ROOT . '/public/assets/css/theme.css');
$js   = file_get_contents($ROOT . '/public/assets/js/app.js');

if (!str_contains($main, 'class="security-notice"')) ok('layout nao desenha mais a faixa fora do cabecalho'); else falha('faixa continua antes do cabecalho fixo', 'fica escondida atras dele');
$i = strpos($hdr, '<header class="site-header">'); $j = strpos($hdr, 'class="security-notice"');
if ($i !== false && $j !== false && $j > $i && $j < strpos($hdr, 'header-inner')) ok('faixa e o primeiro filho do cabecalho fixo'); else falha('faixa nao esta dentro do cabecalho, antes do conteudo dele');
if (preg_match('/security_notice_enabled/', $hdr) && preg_match("/security_notice_text/", $hdr)) ok('continua ligada pelas mesmas configuracoes'); else falha('perdeu as chaves security_notice_*');
if (preg_match('/main#main\s*\{[^}]*padding-top:\s*var\(--aviso-h/s', $css) || preg_match('/#main\s*\{[^}]*padding-top:\s*var\(--aviso-h/s', $css)) ok('conteudo desce a altura da faixa'); else falha('conteudo nao compensa a altura da faixa');
if (str_contains($js, '--aviso-h') && str_contains($js, '.security-notice')) ok('app.js mede a faixa e atualiza --aviso-h'); else falha('app.js nao mede a faixa');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
