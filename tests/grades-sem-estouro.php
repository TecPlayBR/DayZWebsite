<?php
/**
 * Nenhuma grade exige largura minima maior que a tela (auditoria de 27/09/2026): em celular de
 * 320 px, a grade de Depoimentos (minmax(320px, 1fr)) alargava a pagina e jogava o menu pra fora.
 * Toda largura minima fixa de grade vem dentro de min(..., 100%).
 * Rodar: php tests/grades-sem-estouro.php
 */
$falhas = 0;
$ROOT = dirname(__DIR__);
$ruins = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
    if (!preg_match('#/(views|public/assets/css)/.+\.(php|css)$#', $p) || str_contains($p, '/vendor/')) continue;
    foreach (file($f->getPathname()) as $i => $l) if (preg_match('/minmax\(\s*[0-9]{3}px\s*,/', $l)) $ruins[] = substr($p, strlen($ROOT) + 1) . ':' . ($i + 1);
}
if (!$ruins) echo "  OK   nenhuma grade com largura minima fixa acima da tela\n";
else { $falhas++; echo "  FALHA grade que pode estourar a tela:\n         " . implode("\n         ", $ruins) . "\n"; }
echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
