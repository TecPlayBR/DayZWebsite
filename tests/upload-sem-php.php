<?php
/**
 * Nada dentro de assets/ (onde caem todos os uploads do painel) roda ou e servido como script.
 * Rodar: php tests/upload-sem-php.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$ht = file_get_contents($ROOT . '/public/.htaccess');

echo "\n1. Regra de bloqueio existe e vem ANTES da passagem direta de arquivo\n";
$reRegra = '/^\s*RewriteRule\s+(\^assets\/\S+)\s+-\s+\[F,NC,L\]\s*$/m';
$pRegra = preg_match($reRegra, $ht, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;
$pPassa = strpos($ht, 'RewriteCond %{REQUEST_FILENAME} -f');
if ($pRegra !== false) ok('RewriteRule ^assets/... - [F,NC,L] presente'); else falha('falta a regra que barra script em assets/');
if ($pRegra !== false && $pPassa !== false && $pRegra < $pPassa) ok('vem antes do "arquivo real passa direto"'); else falha('regra depois da passagem direta', 'o arquivo real seria servido antes de chegar nela');

echo "\n2. A regra pega o que deve e deixa passar o resto\n";
$padrao = $pRegra !== false ? $m[1][0] : '^$';
$re = '#' . str_replace('#', '\#', $padrao) . '#i';   // NC = sem diferenciar maiusculas
$bloqueia = ['assets/img/events/x.php', 'assets/img/custom/logo.PHP5', 'assets/img/gallery/a.phtml',
             'assets/img/packages/b.phar', 'assets/img/help/c.pht', 'assets/img/custom/d.php.jpg',
             'assets/img/gallery/e.phps', 'assets/novapasta/f.php7'];
$libera  = ['assets/img/logo.png', 'assets/js/app.js', 'assets/img/phpstorm.png', 'assets/css/theme.css',
            'assets/img/gallery/g_123.webp', 'index.php', 'api/health.php', 'assets/js/lib/chart.umd.min.js'];
foreach ($bloqueia as $u) if (preg_match($re, $u)) ok("barra $u"); else falha("deixou passar $u");
foreach ($libera as $u) if (!preg_match($re, $u)) ok("libera $u"); else falha("barrou $u", 'arquivo legitimo do site');

echo "\n3. Todo destino de upload do codigo fica dentro de assets/\n";
$idx = file_get_contents($ROOT . '/public/index.php') . file_get_contents($ROOT . '/src/helpers.php');
preg_match_all("#public_dir\(\)\s*\.\s*'(/[^']+)'#", $idx, $dirs);
$fora = array_filter(array_unique($dirs[1]), fn($d) => !str_starts_with($d, '/assets/'));
if ($dirs[1] && !$fora) ok(count(array_unique($dirs[1])) . ' destino(s) em public/, todos em /assets/'); else falha('destino fora de /assets/', implode(', ', $fora));

echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
exit($falhas ? 1 : 0);
