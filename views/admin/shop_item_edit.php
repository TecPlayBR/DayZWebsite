<?php /** @var array $config; @var ?array $item */ ?>
<?php
    $isNew = empty($item['id']);
    $deliverPretty = '';
    if (!empty($item['deliver_json'])) {
        $decoded = json_decode($item['deliver_json'], true);
        if (is_array($decoded)) $deliverPretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $title = $isNew ? 'Novo item da loja' : 'Editar item';
    $exampleDeliver = "[\n  {\n    \"classname\": \"Mod_Item_X\",\n    \"quantity\": 1,\n    \"attachments\": [],\n    \"cargo\": [],\n    \"health\": 1.0\n  }\n]";
?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1><?= $isNew ? 'Novo item da loja' : 'Editar: ' . e($item['name']) ?></h1>
        <p>O bot lista no <code>/loja</code>, debita a moeda e o servidor entrega o(s) classname(s) in-game.</p>
    </div>
    <a href="/admin/shop" class="btn-mini outline">← Voltar</a>
</div>

<?php if (!empty($_GET['err'])): ?>
    <div style="background:var(--danger-overlay);border-left:3px solid var(--rust-2);padding:0.7rem 1rem;margin-bottom:1.5rem;color:var(--text-danger);font-size:0.9rem;">
        <?php $errMsgs = [
            'invalid'      => 'Verifique: SKU e nome obrigatórios, custo ≥ 0.',
            'sku_taken'    => 'Esse SKU já existe — escolha outro.',
            'bad_deliver'  => 'Entrega (deliver) inválida: precisa ser uma lista JSON com pelo menos um objeto contendo "classname".',
        ]; echo e($errMsgs[$_GET['err']] ?? 'Erro ao salvar.'); ?>
    </div>
<?php endif; ?>

<form method="POST" action="/admin/shop/save" style="max-width: 800px;">
    <?= \App\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Informações básicas</div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 80px; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Nome do item</label>
                <input type="text" name="name" required value="<?= e($item['name'] ?? '') ?>" placeholder="ex: Kit VIP 7 dias" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Ícone</label>
                <input type="text" name="icon" value="<?= e($item['icon'] ?? '💎') ?>" maxlength="8" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); text-align:center; font-size:1.2rem;">
            </div>
        </div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 120px 100px; gap: 1rem; align-items:end;">
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">SKU (id único, sem espaços)</label>
                <input type="text" name="sku" required value="<?= e($item['sku'] ?? '') ?>" placeholder="vip_kit_7d" pattern="[A-Za-z0-9_\-]+" <?= $isNew ? '' : 'readonly style="opacity:0.6;"' ?> style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
                <?php if (!$isNew): ?><div class="dim" style="font-size:0.72rem; margin-top:0.25rem;">SKU não é editável após criado (o bot já referencia).</div><?php endif; ?>
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Custo (moedas)</label>
                <input type="number" name="coins_cost" min="0" required value="<?= (int)($item['coins_cost'] ?? 0) ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Ordem</label>
                <input type="number" name="sort_order" value="<?= (int)($item['sort_order'] ?? 0) ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
        </div>
        <label style="display:inline-flex; align-items:center; gap:0.4rem; margin-top:1rem; font-size:0.85rem; color:var(--bone); cursor:pointer;">
            <input type="checkbox" name="enabled" <?= (!isset($item['enabled']) || (int)$item['enabled']) ? 'checked' : '' ?> style="width:18px; height:18px;">
            Habilitado (aparece no <code>/loja</code>)
        </label>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Entrega in-game (deliver)</div>
        <p style="margin-top:0.5rem; font-size:0.82rem; color:var(--dim);">
            Lista JSON do que o servidor entrega. Cada objeto: <code>classname</code> (obrigatório),
            <code>quantity</code>, <code>attachments[]</code>, <code>cargo[]</code> (classnames dentro do item) e <code>health</code> (0 a 1).
            Um kit pode entregar vários classnames.
        </p>
        <textarea name="deliver" rows="12" spellcheck="false" placeholder="<?= e($exampleDeliver) ?>" style="width:100%; margin-top:0.7rem; padding:0.7rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono); font-size:0.82rem; resize:vertical; line-height:1.5;"><?= e($deliverPretty) ?></textarea>
    </div>

    <div style="display: flex; gap: 0.6rem;">
        <button type="submit" class="btn-mini" style="padding: 0.7rem 1.6rem;">Salvar</button>
        <a href="/admin/shop" class="btn-mini outline" style="padding:0.7rem 1.6rem; text-decoration:none; display:inline-flex; align-items:center;">Cancelar</a>
        <?php if (!$isNew): ?>
            <form method="POST" action="/admin/shop/<?= (int)$item['id'] ?>/delete" style="margin-left:auto;" onsubmit="return confirm('Excluir este item da loja? O bot deixa de listá-lo.');">
                <?= \App\Csrf::field() ?>
                <button type="submit" class="btn-mini outline" style="padding:0.7rem 1.2rem; color:var(--text-danger); border-color:var(--rust-2);">Excluir</button>
            </form>
        <?php endif; ?>
    </div>
</form>

<?php \App\View::endSection(); ?>
