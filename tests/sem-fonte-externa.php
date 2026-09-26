<?php
/**
 * Nenhuma pagina carrega fonte do Google: o IP de quem abre iria para os EUA sem constar na
 * Politica de Privacidade. As fontes ficam em public/assets/fonts (fonts.css).
 * Rodar: php tests/sem-fonte-externa.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);

echo "\n1. Nenhuma view aponta para o Google Fonts\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/views', FilesystemIterator::SKIP_DOTS));
$achou = [];
foreach ($it as $f) {
    if (substr($f->getFilename(), -4) !== '.php') continue;
    $s = file_get_contents($f->getPathname());
    if (preg_match('#fonts\.(googleapis|gstatic)\.com#', $s)) $achou[] = substr($f->getPathname(), strlen($ROOT) + 1);
}
if (!$achou) ok('nenhuma'); else falha('ainda carregam do Google: ' . implode(', ', $achou));

echo "\n2. As paginas com <head> proprio usam as fontes locais\n";
foreach (['views/admin/layout.php', 'views/admin/login.php', 'views/admin/login_2fa.php', 'views/admin/forgot.php', 'views/admin/reset.php', 'views/pages/maintenance.php', 'views/pages/error_500.php', 'views/layouts/main.php'] as $v) {
    $s = file_get_contents($ROOT . '/' . $v);
    if (str_contains($s, "css/fonts.css")) ok($v); else falha("$v sem css/fonts.css", 'fica sem as fontes do tema');
}
foreach (['black-ops-one-400.woff2', 'inter.woff2', 'vt323-400.woff2'] as $w) {
    if (is_file($ROOT . '/public/assets/fonts/' . $w)) ok("fonte $w no pacote"); else falha("falta public/assets/fonts/$w");
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
