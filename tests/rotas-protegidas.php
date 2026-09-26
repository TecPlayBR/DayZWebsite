<?php
/**
 * Toda rota do front-controller se protege: rota /admin exige permissao, todo POST confere o
 * CSRF, e POST fora do admin exige login Steam (ou esta na lista curta de excecoes).
 * Rodar: php tests/rotas-protegidas.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$src = file_get_contents(dirname(__DIR__) . '/public/index.php');

preg_match_all("/\\\\App\\\\Router::(get|post)\\('([^']+)'/", $src, $m, PREG_OFFSET_CAPTURE);
$rotas = [];
foreach ($m[0] as $i => $inteiro) {
    $ini = $inteiro[1];
    $fim = $m[0][$i + 1][1] ?? strlen($src);
    $rotas[] = [$m[1][$i][0], $m[2][$i][0], substr($src, $ini, $fim - $ini)];
}

// Rotas que por natureza nao tem sessao ainda (login/recuperacao) ou sao publicas de leitura.
$ADMIN_LIVRE  = ['/admin/login', '/admin/forgot', '/admin/reset', '/admin/logout'];
$POST_PUBLICO = ['/admin/login', '/admin/forgot', '/admin/reset', '/admin/login/2fa'];
// POST sem login Steam mas amarrado a SESSAO: so vale enquanto o corpo conferir a marca.
// card-pay so aceita compra criada nesta sessao pelo checkout (que ja exige login Steam).
$POST_SESSAO  = ['/shop/card-pay/{id}' => "\$_SESSION['checkout_pids']"];
// Etapa do codigo (duas etapas): roda ANTES da sessao de admin existir; vale so com o pendente
// que a senha certa cria (e que expira em 5 min). Sem a marca no corpo, volta a reprovar.
$ADMIN_SESSAO = ['/admin/login/2fa' => "\$_SESSION['admin_2fa_pendente']"];

$semGuarda = $semCsrf = $semLogin = [];
foreach ($rotas as [$met, $rota, $corpo]) {
    $ehAdmin = str_starts_with($rota, '/admin');
    if ($ehAdmin && !in_array($rota, $ADMIN_LIVRE, true) && !(isset($ADMIN_SESSAO[$rota]) && str_contains($corpo, $ADMIN_SESSAO[$rota])) && !preg_match('/Auth::(requireAdmin|requireCan|requireRole|requireOwner)\(/', $corpo)) $semGuarda[] = strtoupper($met) . " $rota";
    if ($met === 'post' && !str_contains($corpo, 'Csrf::check()')) $semCsrf[] = $rota;
    if ($met === 'post' && !$ehAdmin && !in_array($rota, $POST_PUBLICO, true) && !(isset($POST_SESSAO[$rota]) && str_contains($corpo, $POST_SESSAO[$rota])) && !preg_match('/SteamAuth::(check|steamId|user)\(\)/', $corpo)) $semLogin[] = $rota;
}
$nPost = count(array_filter($rotas, fn($r) => $r[0] === 'post'));
echo "\n" . count($rotas) . " rotas ($nPost POST)\n";
if (count($rotas) > 150) ok('o parser achou as rotas'); else falha('parser achou poucas rotas', 'o formato do Router mudou? o teste ficaria cego');
if (!$semGuarda) ok('toda rota /admin exige permissao'); else falha(count($semGuarda) . ' rota(s) /admin sem Auth::require*', implode(', ', $semGuarda));
if (!$semCsrf) ok('todo POST confere o CSRF'); else falha(count($semCsrf) . ' POST sem Csrf::check()', implode(', ', $semCsrf));
if (!$semLogin) ok('todo POST fora do admin exige login Steam'); else falha(count($semLogin) . ' POST publico sem login', implode(', ', $semLogin));

echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
exit($falhas ? 1 : 0);
