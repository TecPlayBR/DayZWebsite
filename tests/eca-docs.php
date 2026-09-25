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
$guia = @file_get_contents($ROOT . '/ECA-DIGITAL.md') ?: '';
foreach (['update.php', 'cpfhub.io', 'art. 23', 'Termos de Uso', 'age_required', 'nunca é gravado'] as $t) { if (stripos($guia, $t) !== false) ok("ECA-DIGITAL.md cita '$t'"); else falha("ECA-DIGITAL.md nao cita '$t'"); }
if ($guia !== '' && !str_contains($guia, "\u{2014}") && !str_contains($guia, "\u{2013}")) ok('ECA-DIGITAL.md sem travessao'); else falha('ECA-DIGITAL.md ausente ou com travessao');
$seed = file_get_contents($ROOT . '/migrations/v2.2.0_seed_legal_pages.sql');
if (stripos($seed, 'assistência expressa dos responsáveis') === false && stripos($seed, 'verificação de idade') !== false) ok('texto-semente dos Termos fala em verificacao de idade, nao em "assistencia dos responsaveis"'); else falha('texto-semente dos Termos ainda promete compra de menor com assistencia', 'a regra nova e: menor nao compra');
echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
