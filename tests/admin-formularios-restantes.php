<?php
/**
 * Cupons, pacotes e caixas no padrao de formulario do admin (admin_campo): obrigatorio com
 * asterisco e destaque ao salvar, dica em texto visivel, e NENHUM nome de campo perdido (o
 * servidor le cada um pelo nome).
 * Rodar: php tests/admin-formularios-restantes.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$ler = fn($f) => file_get_contents($ROOT . '/views/admin/' . $f);

/** Trecho do <form> cuja action contem $acao (ate o </form>). */
function form_de(string $src, string $acao): string {
    $i = strpos($src, $acao);
    if ($i === false) return '';
    $ini = strrpos(substr($src, 0, $i), '<form');
    $fim = strpos($src, '</form>', $i);
    return substr($src, $ini, $fim - $ini + 7);
}

echo "\n0. admin_campo aceita classe extra no controle\n";
require_once $ROOT . '/src/helpers.php';
$h = admin_campo(['label' => 'Codigo', 'name' => 'code', 'class' => 'mono upper']);
if (str_contains($h, 'class="field mono upper"')) ok('class extra entra junto de .field'); else falha('admin_campo ignora a classe extra', $h);
$h2 = admin_campo(['label' => 'x', 'name' => 'x', 'class' => '" onclick="alert(1)']);
if (!preg_match('/\sonclick\s*=/', $h2) && substr_count($h2, '"') % 2 === 0) ok('classe extra e saneada (sem injetar atributo)'); else falha('classe extra injeta atributo', $h2);

$casos = [
    // arquivo, trecho da action, nomes que TEM que continuar, obrigatorios (via admin_campo)
    ['coupons.php', '/admin/coupons/create',
        ['code', 'discount_type', 'discount_value', 'max_uses', 'per_user_limit', 'package_ids[]', 'affiliate_name', 'commission_pct_1', 'commission_pct_2', 'commission_pct_3plus', 'valid_from', 'valid_until', 'notes'],
        ['code', 'discount_value']],
    ['coupon_edit.php', '/save"',
        ['discount_type', 'discount_value', 'max_uses', 'per_user_limit', 'package_ids[]', 'affiliate_name', 'commission_pct_1', 'commission_pct_2', 'commission_pct_3plus', 'valid_from', 'valid_until', 'notes'],
        ['discount_value']],
    ['package_edit.php', "'/admin/packages/create'",
        ['id', 'name', 'icon', 'image', 'remove_image', 'coins', 'bonus_coins', 'price_brl', 'bonus_badge', 'ribbon', 'sort_order', 'featured', 'perks', 'bonus_perks'],
        ['id', 'name', 'coins', 'price_brl']],
    ['caixas.php', '/admin/caixas/save',
        ['name', 'cost_coins', 'is_daily'],
        ['name']],
    ['caixa_edit.php', 'action="/admin/caixas/save"',
        ['id', 'name', 'slug', 'image', 'description', 'cost_coins', 'sort_order', 'cooldown_hours', 'is_daily', 'enabled'],
        ['name']],
];

foreach ($casos as [$arq, $acao, $nomes, $obrig]) {
    echo "\n-- $arq\n";
    $f = form_de($ler($arq), $acao);
    if ($f === '') { falha("formulario $acao nao encontrado"); continue; }
    if (preg_match('/<form\b(?:<\?.*?\?>|[^>])*\bdata-adm-form\b/s', $f)) ok('form com data-adm-form (destaca obrigatorio vazio ao salvar)'); else falha('form sem data-adm-form');
    $faltando = [];
    foreach ($nomes as $n) {
        $q = preg_quote($n, '/');
        if (!preg_match("/name=\"$q\"|'name'\s*=>\s*'$q'|'$q'\s*=>|name=\"<\?= \\\$k \?>\"/", $f)) $faltando[] = $n;
        // image em admin_campo_imagem gera image + image_file; o nome aparece como 'name' => 'image'
    }
    if (!$faltando) ok(count($nomes) . ' nomes de campo preservados'); else falha('campo perdido: ' . implode(', ', $faltando), 'o servidor le pelo nome');
    $ruins = [];
    foreach ($obrig as $n) {
        $q = preg_quote($n, '/');
        if (!preg_match("/admin_campo\(\[[^\]]*'name'\s*=>\s*'$q'[^\]]*'required'\s*=>\s*true/s", $f)) $ruins[] = $n;
    }
    if (!$ruins) ok('obrigatorios via admin_campo com asterisco: ' . implode(', ', $obrig)); else falha('obrigatorio fora do padrao: ' . implode(', ', $ruins));
    // Nenhum <input|select|textarea> desenhado a mao com style="" (o visual vem das classes).
    if (!preg_match('/<(input|select|textarea)\b(?![^>]*type="(hidden|checkbox|file)")[^>]*\bstyle="/', $f)) ok('sem campo estilizado a mao'); else falha('ainda tem campo com style="" a mao');
    if (preg_match_all("/'hint'\s*=>/", $f) >= 1) ok(preg_match_all("/'hint'\s*=>/", $f) . ' dica(s) em texto'); else falha('nenhuma dica em texto');
}

echo "\n-- caixa_edit.php: editor de item continua com os ids do script\n";
$ce = $ler('caixa_edit.php');
foreach (['bi-type', 'bi-classname', 'bi-class-l', 'bi-name', 'bi-qty', 'bi-qty-l', 'bi-rarity', 'bi-enabled', 'bi-submit', 'bi-cancel', 'bi-item-id', 'bi-sort'] as $id)
    if (!str_contains($ce, 'id="' . $id . '"')) falha("id $id sumiu", 'o script do editor de item quebra');
ok('ids do editor de item conferidos');

echo "\n-- CSS: grades de 3 e 4 colunas que viram 1 no celular\n";
$css = file_get_contents($ROOT . '/public/assets/css/admin.css');
if (preg_match('/\.adm-grid-3\s*\{[^}]*grid-template-columns/', $css) && preg_match('/\.adm-grid-4\s*\{[^}]*grid-template-columns/', $css)) ok('.adm-grid-3 e .adm-grid-4 existem'); else falha('faltam .adm-grid-3/.adm-grid-4');
if (preg_match('/@media\s*\(max-width:\s*720px\)\s*\{[^}]*\.adm-grid-3[^}]*\.adm-grid-4|@media\s*\(max-width:\s*720px\)\s*\{[^}]*\.adm-grid-4[^}]*\.adm-grid-3/s', $css)) ok('as duas viram 1 coluna em ate 720px'); else falha('grades novas sem regra de celular');

echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
exit($falhas ? 1 : 0);
