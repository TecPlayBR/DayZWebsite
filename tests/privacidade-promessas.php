<?php
/**
 * O que a Politica de Privacidade promete, o site cumpre (revisao juridica de 26/09):
 * - nada do YouTube carrega antes do clique do jogador (nem o video, nem a miniatura);
 * - nenhum texto chama o identificador derivado do CPF de "irreversivel": com o sal do site
 *   e so 10^9 CPFs possiveis, quem tem o banco consegue refazer a conta;
 * - a tela de idade nao diz que guarda "so o resultado, a data e o metodo".
 * Rodar: php tests/privacidade-promessas.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);

echo "\n1. YouTube so no clique\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/views', FilesystemIterator::SKIP_DOTS));
$img = [];
foreach ($it as $f) {
    if (substr($f->getFilename(), -4) === '.php' && str_contains(file_get_contents($f->getPathname()), 'img.youtube.com')) $img[] = $f->getFilename();
}
if (!$img) ok('nenhuma view puxa miniatura do img.youtube.com'); else falha('miniatura do YouTube carrega sem clique: ' . implode(', ', $img));
$art = file_get_contents($ROOT . '/views/pages/help_article.php');
if (!preg_match('/<iframe[^>]*\$embed/', $art)) ok('artigo de ajuda nao abre o iframe sozinho'); else falha('artigo de ajuda carrega o iframe do YouTube ao abrir a pagina');
if (str_contains($art, 'data-yt-src=') && str_contains($art, "__('ajuda.video_aviso')")) ok('artigo mostra o botao com o aviso do YouTube'); else falha('artigo sem o botao data-yt-src ou sem o aviso');
$js = file_get_contents($ROOT . '/public/assets/js/app.js');
if (preg_match("/\[data-yt-src\]/", $js) && str_contains($js, "createElement('iframe')")) ok('app.js troca o botao pelo iframe no clique'); else falha('app.js nao carrega o video no clique');
foreach (['pt-br', 'en-us'] as $l) {
    $t = include $ROOT . "/lang/$l.php";
    $aviso = (string) ($t['ajuda']['video_aviso'] ?? '');
    if ($aviso !== '' && preg_match('/YouTube/', $aviso)) ok("$l: aviso do video existe e cita o YouTube"); else falha("$l: falta ajuda.video_aviso");
}

echo "\n2. Nada de \"irreversivel\" sobre o CPF\n";
foreach (['lang/pt-br.php', 'lang/en-us.php', 'ECA-DIGITAL.md', 'views/pages/idade.php', 'views/admin/eca.php', 'views/admin/eca_relatorio.php'] as $arq) {
    if (!is_file($ROOT . '/' . $arq)) continue;
    $s = file_get_contents($ROOT . '/' . $arq);
    if (!preg_match('/irrevers|irreversible/i', $s)) ok("$arq sem \"irreversivel\""); else falha("$arq ainda diz irreversivel", 'o identificador pode ser recalculado por quem tem o banco e o sal');
}

echo "\n3. Tela de idade diz o que guarda de verdade\n";
$pt = include $ROOT . '/lang/pt-br.php';
$why = (string) ($pt['idade']['why'] ?? '');
if (!str_contains($why, 'Guardamos só') && str_contains($why, 'identificador')) ok('texto cita o identificador derivado do CPF'); else falha('texto da tela de idade promete guardar so resultado, data e metodo', $why);

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
