<?php /** @var array $config, $coupon, $packages */ ?>
<?php $title = 'Editar cupom'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<?php
$cpids = !empty($coupon['package_ids']) ? (json_decode($coupon['package_ids'], true) ?: []) : [];
// datetime do banco ("YYYY-MM-DD HH:MM:SS") -> formato do <input datetime-local> ("YYYY-MM-DDTHH:MM")
$dtLocal = function ($v): string {
    $v = trim((string) $v);
    if ($v === '') return '';
    return substr(str_replace(' ', 'T', $v), 0, 16);
};
$fieldStyle = 'width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);';
?>

<div class="admin-page-head">
    <div>
        <h1>Editar cupom</h1>
        <p>Ajuste o desconto, os limites e a janela de validade. O <strong>código</strong> não muda (compras e vínculos apontam pra ele).</p>
    </div>
    <a href="/admin/coupons" class="btn-mini outline">← Voltar</a>
</div>

<?php $num = fn($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); ?>
<form method="POST" action="/admin/coupons/<?= (int)$coupon['id'] ?>/save" class="stat-card" style="padding: 1.5rem; max-width: 900px;" data-adm-form>
    <?= \App\Csrf::field() ?>
    <div class="adm-grid-3 adm-bloco">
        <div class="adm-campo">
            <label class="adm-label" for="f-code-fixo">Código</label>
            <input class="field mono" type="text" id="f-code-fixo" value="<?= e($coupon['code']) ?>" disabled>
            <small class="adm-hint">Fixo depois de criado. Pra outro código, crie um cupom novo.</small>
        </div>
        <?= admin_campo(['label' => 'Tipo', 'name' => 'discount_type', 'type' => 'select', 'value' => (string) $coupon['discount_type'], 'options' => ['percent' => '% Percentual', 'fixed' => 'R$ Fixo', 'coins' => '🪙 Moedas bônus']]) ?>
        <?= admin_campo(['label' => 'Valor', 'name' => 'discount_value', 'type' => 'number', 'required' => true, 'value' => $num($coupon['discount_value']), 'class' => 'mono', 'attrs' => 'min="0.01" step="0.01"', 'hint' => 'Porcentagem, reais de desconto ou, em Moedas bônus, quantas moedas o jogador ganha a mais.']) ?>
    </div>

    <div class="adm-grid-2 adm-bloco">
        <?= admin_campo(['label' => 'Máx. usos no total', 'name' => 'max_uses', 'type' => 'number', 'placeholder' => '∞', 'value' => $coupon['max_uses'] ? (string) (int) $coupon['max_uses'] : '', 'attrs' => 'min="1"', 'hint' => 'Somando todos os jogadores. Já usado: ' . (int) $coupon['used_count'] . 'x. Vazio = ilimitado.']) ?>
        <?= admin_campo(['label' => 'Máx. por jogador', 'name' => 'per_user_limit', 'type' => 'number', 'placeholder' => '∞', 'value' => !empty($coupon['per_user_limit']) ? (string) (int) $coupon['per_user_limit'] : '', 'attrs' => 'min="1"', 'hint' => '1 = cada pessoa usa uma vez (ex.: cupom de aniversário). Vazio = sem limite.']) ?>
    </div>

    <?php if (!empty($packages)): ?>
    <div class="adm-bloco">
        <p class="adm-label" style="margin:0 0 .4rem;">Vale só pra estes pacotes</p>
        <div class="adm-checks">
            <?php foreach ($packages as $pk): ?>
                <label class="adm-check"><input type="checkbox" name="package_ids[]" value="<?= e($pk['id']) ?>" <?= in_array($pk['id'], $cpids, true) ? 'checked' : '' ?>> <?= e($pk['name']) ?></label>
            <?php endforeach; ?>
        </div>
        <small class="adm-hint">Nada marcado = vale pra todos os pacotes.</small>
    </div>
    <?php endif; ?>

    <details class="adm-secao" <?= \App\Coupon::isAffiliate($coupon) ? 'open' : '' ?>>
        <summary>🎮 Programa de afiliado / streamer <span style="color:var(--dim);">(opcional)</span></summary>
        <div class="adm-grid-4">
            <?= admin_campo(['label' => 'Streamer', 'name' => 'affiliate_name', 'value' => (string) ($coupon['affiliate_name'] ?? ''), 'attrs' => 'maxlength="120"', 'hint' => 'Quem recebe o cachê das vendas com este cupom.']) ?>
            <?php foreach (['commission_pct_1' => '% 1ª compra', 'commission_pct_2' => '% 2ª compra', 'commission_pct_3plus' => '% 3ª+ compra'] as $k => $lbl): ?>
                <?= admin_campo(['label' => $lbl, 'name' => $k, 'type' => 'number', 'value' => $num($coupon[$k] ?? 0), 'class' => 'mono', 'attrs' => 'min="0" max="100" step="0.5"']) ?>
            <?php endforeach; ?>
        </div>
    </details>

    <div class="adm-grid-3 adm-bloco">
        <?= admin_campo(['label' => 'Válido a partir de', 'name' => 'valid_from', 'type' => 'datetime-local', 'value' => $dtLocal($coupon['valid_from'] ?? ''), 'hint' => 'Vazio = já vale.']) ?>
        <?= admin_campo(['label' => 'Válido até', 'name' => 'valid_until', 'type' => 'datetime-local', 'value' => $dtLocal($coupon['valid_until'] ?? ''), 'hint' => 'Vazio = não expira.']) ?>
        <?= admin_campo(['label' => 'Notas internas', 'name' => 'notes', 'value' => (string) ($coupon['notes'] ?? ''), 'hint' => 'Só a equipe vê.']) ?>
    </div>

    <div class="adm-acoes">
        <button type="submit" class="btn-mini" style="padding: 0.6rem 1.5rem;">Salvar alterações</button>
        <a href="/admin/coupons" class="btn-mini outline">Cancelar</a>
    </div>
</form>

<?php \App\View::endSection(); ?>
