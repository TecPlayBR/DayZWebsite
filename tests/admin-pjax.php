<?php
/**
 * Navegacao do admin (PJAX): a tela de Logins nao pode ser confundida com a de login, e script
 * de tela nao pode quebrar quando o PJAX re-executa ele numa segunda visita.
 * Rodar: php tests/admin-pjax.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$lay = file_get_contents($ROOT . '/views/admin/layout.php');

echo "\n1. Sessao caida e detectada pelo caminho EXATO do login\n";
// '/admin/logins' tambem contem '/admin/login': so o caminho exato separa as duas telas.
if (!str_contains($lay, "res.url.includes('/admin/login')")) ok('sem includes() no endereco do login'); else falha("PJAX usa res.url.includes('/admin/login')", '/admin/logins tambem casaria');
if (preg_match("#new URL\(res\.url\)\.pathname\s*===\s*'/admin/login'#", $lay)) ok('compara o pathname exato com /admin/login'); else falha('PJAX nao compara o pathname exato');

echo "\n2. Script de tela do admin aguenta ser re-executado pelo PJAX\n";
// O PJAX roda o <script> de novo a cada visita pelo menu. const/let/class no topo do script vao
// pro escopo global e a segunda execucao da "Identifier ... has already been declared".
$ruins = [];
foreach (glob($ROOT . '/views/admin/*.php') as $f) {
    $dentro = false;
    foreach (preg_split('/\R/', file_get_contents($f)) as $n => $l) {
        if (preg_match('/<script\b(?![^>]*\bsrc=)/', $l)) { $dentro = true; continue; }
        if (str_contains($l, '</script>')) { $dentro = false; continue; }
        if ($dentro && preg_match('/^(const|let|class)\s/', $l)) $ruins[] = basename($f) . ':' . ($n + 1) . ' ' . trim($l);
    }
}
if (!$ruins) ok('nenhuma view do admin declara const/let/class no topo do script'); else falha(count($ruins) . ' declaracao(oes) no topo', implode("\n         ", $ruins));

echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
exit($falhas ? 1 : 0);
