<?php
/**
 * Depoimentos da home em carrossel (27/09/2026): antes a home mostrava so os 3 mais recentes
 * (LIMIT 3) e o 4o nunca aparecia. Agora lista todos os aprovados, passa sozinho e tem setas.
 * Rodar: php tests/depoimentos-carrossel.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$idx  = file_get_contents($ROOT . '/public/index.php');
$home = file_get_contents($ROOT . '/views/pages/home.php');
$js   = file_get_contents($ROOT . '/public/assets/js/app.js');

$q = substr($idx, (int) strpos($idx, '$homeReviews = '), 500);
if (preg_match('/LIMIT\s+(\d+)/', $q, $m) && (int) $m[1] >= 12) ok("home busca ate {$m[1]} depoimentos (antes 3)"); else falha('home ainda busca poucos depoimentos', $m[1] ?? 'sem LIMIT');
if (str_contains($home, 'data-carrossel') && preg_match('/data-carrossel-prev[^>]*aria-label=/', $home) && preg_match('/data-carrossel-next[^>]*aria-label=/', $home)) ok('carrossel com setas acessiveis'); else falha('sem carrossel ou setas sem aria-label');
if (preg_match('/scroll-snap-type:\s*x/', $home)) ok('cards encaixam ao rolar (scroll-snap), funciona ate sem JS'); else falha('trilha sem scroll-snap');
if (preg_match("/\[data-carrossel\]/", $js) && str_contains($js, 'prefers-reduced-motion') && str_contains($js, 'visibilityState') && preg_match('/setInterval|setTimeout/', $js)) ok('app.js: passa sozinho, respeita reduzir movimento e pausa com a aba escondida'); else falha('app.js sem autoplay seguro do carrossel');
if (preg_match("/mouseenter|pointerenter/", $js) && preg_match("/focusin/", $js)) ok('pausa no mouse e no foco do teclado'); else falha('carrossel nao pausa no mouse ou no foco');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
