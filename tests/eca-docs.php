<?php
/**
 * O CHANGELOG tem a 3.3.0 no topo, sem travessao, e o README fala do recurso.
 * Rodar: php tests/eca-docs.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$cl = file_get_contents($ROOT . '/CHANGELOG.md');
preg_match_all('/^## \[([0-9.]+)\]/m', $cl, $m);
if (($m[1][0] ?? '') === '3.3.0') ok('3.3.0 e a primeira versao do changelog'); else falha('CHANGELOG sem 3.3.0 no topo', 'topo atual: ' . ($m[1][0] ?? 'nenhum'));
$ini = strpos($cl, '## [3.3.0]'); $prox = $ini !== false ? strpos($cl, "\n## [", $ini + 5) : false;
$sec = $ini !== false ? substr($cl, $ini, ($prox !== false ? $prox : strlen($cl)) - $ini) : '';
foreach (['Lei 15.211/2025', 'update.php', 'terms_accepted', 'age_required', 'sem login'] as $t) { if (stripos($sec, $t) !== false) ok("changelog cita '$t'"); else falha("changelog nao cita '$t'"); }
if (!str_contains($sec, "\u{2014}") && !str_contains($sec, "\u{2013}")) ok('sem travessao'); else falha('travessao no changelog');
if (stripos(file_get_contents($ROOT . '/README.md'), 'ECA Digital') !== false) ok('README cita ECA Digital'); else falha('README nao cita ECA Digital');
echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
