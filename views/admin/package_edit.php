<?php /** @var array $config; @var ?array $pkg */ ?>
<?php
// Null-safe: $pkg null = criar pacote novo; senão = editar.
$pkg = array_merge([
    'id'=>null,'name'=>'','icon'=>'🪙','image'=>null,'coins'=>0,'bonus_coins'=>0,
    'price_brl'=>0,'bonus_badge'=>'','ribbon'=>'','featured'=>0,'sort_order'=>0,
    'perks_json'=>'[]','bonus_perks_json'=>'[]',
], is_array($pkg ?? null) ? $pkg : []);
$isNew = empty($pkg['id']);
$title = $isNew ? 'Novo pacote' : 'Editar pacote';
?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<?php $perks = json_decode($pkg['perks_json'] ?? '[]', true) ?: []; ?>
<?php $bonusPerks = json_decode($pkg['bonus_perks_json'] ?? '[]', true) ?: []; ?>

<div class="admin-page-head">
    <div>
        <h1><?= $isNew ? '➕ Novo pacote' : 'Editar: ' . e($pkg['name']) ?></h1>
        <?php if (!$isNew): ?><p>ID: <code><?= e($pkg['id']) ?></code> (não editável)</p><?php endif; ?>
    </div>
    <a href="/admin/packages" class="btn-mini outline">← Voltar</a>
</div>

<?php if (!empty($_GET['err'])): ?>
    <div style="background:var(--danger-overlay);border-left:3px solid var(--rust-2);padding:0.7rem 1rem;margin-bottom:1.5rem;color:var(--text-danger);font-size:0.9rem;">
        <?= match($_GET['err']) {
            'img'   => 'Imagem inválida: use PNG/WEBP/JPG até 5MB.',
            'dup'   => 'Já existe um pacote com esse ID. Escolha outro.',
            'id'    => 'ID inválido: use minúsculas, números, - ou _ (2 a 40 caracteres).',
            default => 'Verifique: nome obrigatório, moedas > 0, preço > 0.',
        } ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= $isNew ? '/admin/packages/create' : '/admin/packages/' . e($pkg['id']) . '/save' ?>" enctype="multipart/form-data" style="max-width: 800px;" data-adm-form>
    <?php if ($isNew): ?>
    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Identificador</div>
        <div style="margin-top: 1rem;">
            <?= admin_campo(['label' => 'ID do pacote', 'name' => 'id', 'required' => true, 'placeholder' => 'ex: starter, pro, mega', 'class' => 'mono', 'attrs' => 'pattern="[a-z0-9][a-z0-9_\-]{1,39}"', 'hint' => 'Único e fixo, não muda depois. Só minúsculas, números, - ou _ (2 a 40 caracteres).']) ?>
        </div>
    </div>
    <?php endif; ?>
    <?= \App\Csrf::field() ?>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Informações básicas</div>
        <div class="adm-grid-2" style="margin-top: 1rem;">
            <?= admin_campo(['label' => 'Nome do pacote', 'name' => 'name', 'required' => true, 'value' => (string) $pkg['name'], 'hint' => 'Como aparece no card da loja.']) ?>
            <?= admin_campo(['label' => 'Ícone', 'name' => 'icon', 'value' => (string) $pkg['icon'], 'attrs' => 'maxlength="6"', 'hint' => 'Um emoji. Aparece quando o pacote não tem imagem.']) ?>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Imagem do pacote (capa)</div>
        <div class="adm-campo" style="margin-top: .8rem;">
            <div class="adm-imagem-linha">
                <?php if (!empty($pkg['image'])): ?>
                    <img class="adm-arquivo-atual" src="<?= preg_match('#^https?://#i', $pkg['image']) ? e($pkg['image']) : asset('img/packages/' . $pkg['image']) ?>" alt="">
                <?php endif; ?>
                <div class="adm-imagem-campos">
                    <label class="adm-label" for="f-image">Enviar imagem</label>
                    <input type="file" id="f-image" name="image" accept="image/png,image/webp,image/jpeg">
                    <?php if (!empty($pkg['image'])): ?>
                        <label class="adm-check"><input type="checkbox" name="remove_image" value="1"> Remover imagem atual (voltar ao emoji)</label>
                    <?php endif; ?>
                </div>
            </div>
            <small class="adm-hint">PNG transparente, perto de 512×512, até 5 MB. Aparece em destaque na loja e na home no lugar do ícone. Sem imagem, usa o emoji.</small>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Moedas e preço</div>
        <div class="adm-grid-3" style="margin-top: 1rem;">
            <?= admin_campo(['label' => 'Moedas base', 'name' => 'coins', 'type' => 'number', 'required' => true, 'value' => (string) (int) $pkg['coins'], 'class' => 'mono', 'attrs' => 'min="1"', 'hint' => 'O que o jogador recebe sempre.']) ?>
            <?= admin_campo(['label' => 'Moedas bônus', 'name' => 'bonus_coins', 'type' => 'number', 'value' => (string) (int) $pkg['bonus_coins'], 'class' => 'mono', 'attrs' => 'min="0"', 'hint' => 'Somadas quando o bônus está ligado. 0 = sem bônus.']) ?>
            <?= admin_campo(['label' => 'Preço (R$)', 'name' => 'price_brl', 'required' => true, 'value' => number_format((float) $pkg['price_brl'], 2, ',', ''), 'class' => 'mono', 'attrs' => 'pattern="[0-9]+([.,][0-9]{1,2})?" inputmode="decimal"', 'hint' => 'Ex.: 19,90.']) ?>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Destaque visual</div>
        <div class="adm-grid-3" style="margin-top: 1rem;">
            <?= admin_campo(['label' => 'Selo de bônus', 'name' => 'bonus_badge', 'value' => (string) $pkg['bonus_badge'], 'placeholder' => 'ex: BÔNUS +5', 'hint' => 'Aparece no canto do card.']) ?>
            <?= admin_campo(['label' => 'Faixa do topo', 'name' => 'ribbon', 'value' => (string) $pkg['ribbon'], 'placeholder' => 'ex: MAIS POPULAR', 'hint' => 'Faixa em cima do card. Vazio = sem faixa.']) ?>
            <?= admin_campo(['label' => 'Ordem', 'name' => 'sort_order', 'type' => 'number', 'value' => (string) (int) $pkg['sort_order'], 'class' => 'mono', 'hint' => 'Menor aparece primeiro.']) ?>
        </div>
        <label class="adm-check" style="margin-top: .9rem;"><input type="checkbox" name="featured" <?= (int) $pkg['featured'] ? 'checked' : '' ?>> Destacar este pacote na loja</label>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Vantagens (1 por linha)</div>
        <div style="margin-top: 1rem; display: grid; gap: 1rem;">
            <?= admin_campo(['label' => 'Sempre visíveis', 'name' => 'perks', 'type' => 'textarea', 'rows' => 4, 'value' => implode("\n", $perks), 'class' => 'mono', 'hint' => 'Uma vantagem por linha. Aparecem no card do pacote.']) ?>
            <?= admin_campo(['label' => 'Só quando o bônus está ligado', 'name' => 'bonus_perks', 'type' => 'textarea', 'rows' => 3, 'value' => implode("\n", $bonusPerks), 'class' => 'mono', 'hint' => 'Somem quando o bônus é desligado.']) ?>
        </div>
    </div>

    <div class="adm-acoes">
        <button type="submit" class="btn-mini" style="padding: 0.7rem 1.6rem;">Salvar</button>
        <a href="/admin/packages" class="btn-mini outline" style="padding:0.7rem 1.6rem; text-decoration:none; display:inline-flex; align-items:center;">Cancelar</a>
    </div>
</form>

<?php \App\View::endSection(); ?>
