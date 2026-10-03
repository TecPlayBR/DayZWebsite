<?php
/**
 * Campo de imagem do painel: o admin_campo_imagem(['name' => X]) manda o arquivo como X_file
 * e o link como X. A rota que salva tem que ler $_FILES['X_file'], senao o arquivo enviado
 * e ignorado e o campo fica com o valor antigo. E um arquivo recusado (tipo ou tamanho)
 * tem que voltar como erro na tela, nunca sumir calado.
 * Rodar: php tests/campos-de-imagem.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$idx = file_get_contents($ROOT . '/public/index.php');

echo "\n1. Todo campo de imagem tem a rota lendo o arquivo pelo nome certo\n";
$campos = 0;
foreach (glob($ROOT . '/views/admin/*.php') as $v) {
    $src = file_get_contents($v);
    if (!preg_match_all("/admin_campo_imagem\(\[[^\]]*'name'\s*=>\s*'([a-z0-9_]+)'/i", $src, $m)) continue;
    foreach ($m[1] as $nome) {
        $campos++;
        $arq = $nome . '_file';
        if (str_contains($idx, "\$_FILES['$arq']")) ok(basename($v) . ": '$nome' -> rota le \$_FILES['$arq']");
        else falha(basename($v) . ": campo '$nome' manda '$arq', e nenhuma rota le esse arquivo", 'o upload e ignorado em silencio');
    }
}
if ($campos >= 4) ok("$campos campos de imagem conferidos"); else falha("so $campos campos de imagem achados", 'o padrao de busca quebrou');

echo "\n2. Streamer: arquivo recusado aparece na tela (o resto do cadastro e salvo)\n";
$i = strpos($idx, "Router::post('/admin/streamers/save'");
$rota = $i !== false ? substr($idx, $i, strpos($idx, "Router::post('/admin/streamers/delete'") - $i) : '';
if ($rota && preg_match('/\$_FILES\[\'avatar_url_file\'\].*?\$recusados\[\]/s', $rota)) ok('avatar recusado entra na lista de recusados'); else falha('avatar recusado some calado');
if ($rota && preg_match('/photo_files.*?\$recusados\[\]/s', $rota)) ok('foto recusada entra na lista de recusados'); else falha('foto recusada some calada');
if ($rota && preg_match('/UPLOAD_ERR_NO_FILE\)\s*===\s*UPLOAD_ERR_NO_FILE\)\s*continue/', $rota)) ok('so pula foto quando nenhum arquivo veio (arquivo grande demais nao some)'); else falha('foto com erro de envio ainda e pulada calada');
if ($rota && preg_match('/aviso=.*rawurlencode/s', $rota)) ok('a lista de recusados vai pra tela como aviso'); else falha('recusados nao chegam na tela');
$view = file_get_contents($ROOT . '/views/admin/streamer_edit.php');
if (str_contains($view, "\$_GET['aviso']")) ok('a tela mostra o aviso junto do "Salvo"'); else falha('a tela nao mostra o aviso');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
