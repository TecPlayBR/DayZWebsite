<?php
/**
 * 3.3.1: formularios do admin mais amigaveis + melhorias menores da revisao do 3.3.0.
 *  A. admin_campo()/admin_campo_imagem(): obrigatorio visivel, dica visivel, upload alem de URL
 *  B. melhorias: fornecedor sem chave, erros por codigo, hash dos Termos, caixas escondidas
 *     pra menor, contador de bloqueios, consentimentos por jogador, CSV sem formula, changelog
 * Rodar: php tests/eca-331-formularios-e-melhorias.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$idx = file_get_contents($ROOT . '/public/index.php');
$lay = file_get_contents($ROOT . '/views/admin/layout.php');

echo "\nA1. Helpers de campo existem e renderizam obrigatorio + dica\n";
require_once $ROOT . '/src/helpers.php';
if (function_exists('admin_campo') && function_exists('admin_campo_imagem')) {
    ok('admin_campo e admin_campo_imagem existem');
    $h = admin_campo(['label' => 'Código', 'name' => 'code', 'required' => true, 'hint' => 'Só letras e números', 'value' => 'X<Y']);
    if (str_contains($h, 'class="field') && str_contains($h, 'name="code"') && str_contains($h, ' required') && str_contains($h, 'X&lt;Y')) ok('input com .field, name, required e valor escapado'); else falha('admin_campo: input incompleto', $h);
    if (preg_match('/<span class="adm-req"[^>]*>\*<\/span>/', $h)) ok('asterisco de obrigatorio no rotulo'); else falha('sem asterisco de obrigatorio');
    if (preg_match('/<(small|p) class="adm-hint"[^>]*>Só letras e números<\/(small|p)>/', $h)) ok('dica visivel em texto, nao em placeholder'); else falha('dica nao e texto visivel', $h);
    $h2 = admin_campo(['label' => 'Bio', 'name' => 'bio', 'type' => 'textarea', 'value' => 'oi']);
    if (str_contains($h2, '<textarea') && str_contains($h2, '>oi</textarea>') && !str_contains($h2, 'adm-req')) ok('textarea sem asterisco quando opcional'); else falha('textarea', $h2);
    $h3 = admin_campo(['label' => 'Tipo', 'name' => 'type', 'type' => 'select', 'value' => 'b', 'options' => ['a' => 'A', 'b' => 'B']]);
    if (str_contains($h3, '<select') && preg_match('/value="b"[^>]*selected/', $h3)) ok('select com selected'); else falha('select', $h3);
    $hi = admin_campo_imagem(['label' => 'Imagem', 'name' => 'image', 'value' => '/assets/img/x.png']);
    if (str_contains($hi, 'type="file"') && str_contains($hi, 'name="image_file"') && str_contains($hi, 'name="image"') && str_contains($hi, 'accept="image/')) ok('imagem: upload + URL no mesmo componente'); else falha('admin_campo_imagem incompleto', $hi);
    if (stripos($hi, 'Discord') !== false && stripos($hi, 'Drive') !== false && stripos($hi, '5 MB') !== false) ok('imagem: dica fixa explica o que funciona e o que nao (Discord expira, Drive nao serve, 5 MB)'); else falha('imagem: falta a dica sobre links externos');
    if (preg_match('/<img[^>]+src="\/assets\/img\/x\.png"/', $hi)) ok('imagem: previa da atual'); else falha('imagem: sem previa');
} else { falha('helpers admin_campo/admin_campo_imagem nao existem'); }

echo "\nA2. Formularios migrados\n";
foreach (['views/admin/streamer_edit.php', 'views/admin/eventos.php'] as $v) {
    $s = file_get_contents($ROOT . '/' . $v);
    if (substr_count($s, 'admin_campo(') + substr_count($s, 'admin_campo_imagem(') >= 4) ok("$v usa admin_campo"); else falha("$v ainda com inputs soltos");
    if (str_contains($s, 'obrigatório') || str_contains($s, 'adm-legenda')) ok("$v tem a legenda de obrigatorio"); else falha("$v sem legenda '* obrigatório'");
}
$ev = file_get_contents($ROOT . '/views/admin/eventos.php');
if (str_contains($ev, 'enctype="multipart/form-data"') && preg_match("/admin_campo_imagem\(\[[^\]]*'name' => 'image'/", $ev)) ok('eventos: upload de imagem'); else falha('eventos ainda so URL');
if (preg_match("/Router::post\('\/admin\/eventos\/save'.*?upload_image\(\\\$_FILES\['image_file'\]/s", $idx)) ok('eventos/save grava o upload'); else falha('eventos/save nao trata image_file');
$set = file_get_contents($ROOT . '/views/admin/settings.php');
if (preg_match("/admin_campo_imagem\(\[[^\]]*'name' => 'og_image'/", $set) && str_contains($set, 'enctype="multipart/form-data"')) ok('settings: og_image com upload'); else falha('og_image ainda so URL');
if (preg_match("/Router::post\('\/admin\/settings'.*?upload_image\(\\\$_FILES\['og_image_file'\]/s", $idx)) ok('settings handler grava o og_image_file'); else falha('settings handler nao trata og_image_file');
if (str_contains($lay, 'data-adm-required') || str_contains($lay, 'adm-req-erro')) ok('layout do admin tem o script que destaca obrigatorios vazios ao salvar'); else falha('sem script de obrigatorios no layout');
if (str_contains($ev, 'err') && preg_match('/\$_GET\[.err.\]/', $ev)) ok('eventos mostra o err= do servidor'); else falha('eventos ignora ?err= (titulo vazio voltava mudo)');

echo "\nB1. Fornecedor sem chave: sem formulario de CPF, com explicacao\n";
if (preg_match("/Router::get\('\/idade'.*?'provedor_pronto'/s", $idx)) ok('/idade passa provedor_pronto'); else falha('/idade nao informa se o fornecedor esta pronto');
$vi = file_get_contents($ROOT . '/views/pages/idade.php');
if (str_contains($vi, 'provedor_pronto') && str_contains($vi, 'idade.err_indisponivel')) ok('view esconde o CPF e explica quando nao ha chave'); else falha('view mostra CPF mesmo sem chave');
$cx = file_get_contents($ROOT . '/views/pages/caixas.php');
if (str_contains($cx, 'age_provedor_pronto')) ok('aviso das caixas sabe se o fornecedor esta pronto'); else falha('aviso das caixas manda verificar mesmo sem chave');

echo "\nB2. Erros por codigo, nunca texto refletido\n";
$rotas = substr($idx, strpos($idx, "// ============ VERIFICACAO DE IDADE"), strpos($idx, "// ============ ADMIN ============") - strpos($idx, "// ============ VERIFICACAO DE IDADE"));
if (!preg_match("/erro=' \. rawurlencode\('/", $rotas) && !preg_match("/&erro=' \. rawurlencode\(\\\$idadeMensagem/", $rotas)) ok('rotas /idade nao mandam texto no erro='); else falha('rotas /idade ainda refletem texto em erro=');
if (preg_match("/erro=' \. \\\$(cod|r\['cod'\])/", $rotas) || str_contains($rotas, "'&erro=csrf'") || str_contains($rotas, "erro=termos")) ok('rotas mandam codigos'); else falha('rotas nao mandam codigos');
if (preg_match("/__\('idade\.err_' \. /", $vi)) ok('view traduz o codigo pelo stringtable'); else falha('view nao traduz codigo');
$pt = require $ROOT . '/lang/pt-br.php'; $en = require $ROOT . '/lang/en-us.php';
foreach (['err_csrf', 'err_termos', 'err_limite', 'err_cpf_taken', 'err_failed_retry', 'err_nasc_invalida', 'err_cpf_invalido', 'err_menor_so_cpf', 'err_sem_sal', 'err_indisponivel', 'err_generico'] as $k) {
    if (!empty($pt['idade'][$k]) && !empty($en['idade'][$k])) ok("idade.$k nas duas linguas"); else falha("idade.$k faltando");
}

echo "\nB3. Consentimento carimba os Termos de verdade; versao editavel\n";
$av = file_get_contents($ROOT . '/src/AgeVerification.php');
if (preg_match('/function textoTermos\(/', $av) && str_contains($av, "slug IN ('terms'")) ok('consentimento faz hash do conteudo das paginas terms/privacy'); else falha('hash do consentimento nao vem das paginas legais');
$h = substr($idx, strpos($idx, "Router::post('/admin/settings'"), 7000);
if (str_contains($h, "'terms_version'") && preg_match("/admin_campo\(\[[^\]]*'name' => 'terms_version'/", $set)) ok('terms_version editavel no painel'); else falha('terms_version nao e editavel');

echo "\nB4. Menor nao ve a grade de caixas; contador de bloqueios; auditoria; consentimentos\n";
if (preg_match("/\(\\\$age_status \?\? ''\) === 'menor'\): \?>.*?caixas-grid/s", $cx)) ok('grade de caixas escondida para menor'); else falha('menor ainda ve a grade de caixas');
if (str_contains($idx, "AgeVerification::contaBloqueioCaixa(") && str_contains($av, 'function contaBloqueioCaixa(')) ok('bloqueio de caixa e contado'); else falha('sem contador de bloqueios de caixa');
require_once $ROOT . '/src/Settings.php';
if ((\App\Settings::SCHEMA['age_box_blocks'] ?? null) === 'int') ok('age_box_blocks no SCHEMA'); else falha('age_box_blocks fora do SCHEMA');
if (preg_match("/Router::post\('\/idade\/verificar'.*?AuditLog::record\('age\.verified'/s", $idx)) ok('verificacao bem-sucedida entra no AuditLog'); else falha('sem AuditLog age.verified');
$eca = file_get_contents($ROOT . '/views/admin/eca.php');
if (str_contains($eca, 'n_bloqueios') && str_contains($eca, 'consentimentos')) ok('/admin/eca mostra bloqueios e consentimentos por jogador'); else falha('/admin/eca sem bloqueios/consentimentos');

echo "\nB5. CSV sem formula\n";
if (function_exists('csv_seguro')) {
    if (csv_seguro('=1+1') === "'=1+1" && csv_seguro('+x') === "'+x" && csv_seguro('-x') === "'-x" && csv_seguro('@x') === "'@x" && csv_seguro('ok') === 'ok') ok('csv_seguro neutraliza =, +, -, @'); else falha('csv_seguro nao neutraliza');
} else { falha('csv_seguro nao existe'); }
if (preg_match("/export\.csv'.*?csv_seguro/s", $idx)) ok('export.csv usa csv_seguro'); else falha('export.csv sem csv_seguro');

echo "\nB6. Changelog 3.3.1\n";
$cl = file_get_contents($ROOT . '/CHANGELOG.md');
// A 3.3.1 deixou de ser a ultima (3.3.2 veio depois): o que importa e a secao existir, com
// data, e vir logo antes da 3.3.0.
$i331 = strpos($cl, "\n## [3.3.1] - 2026-09-26"); $i330 = strpos($cl, "\n## [3.3.0]");
if ($i331 !== false && $i330 !== false && $i331 < $i330) ok('secao 3.3.1 datada, antes da 3.3.0'); else falha('CHANGELOG sem a secao 3.3.1 no lugar');
$sec = substr($cl, strpos($cl, '## [3.3.1]'), (strpos($cl, "\n## [3.3.0]") ?: strlen($cl)) - strpos($cl, '## [3.3.1]'));
foreach (['obrigatório', 'upload', 'Discord', 'age_required', 'update.php'] as $t) { if (stripos($sec, $t) !== false) ok("3.3.1 cita '$t'"); else falha("3.3.1 nao cita '$t'"); }

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
