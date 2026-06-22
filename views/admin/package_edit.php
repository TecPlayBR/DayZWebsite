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

<form method="POST" action="<?= $isNew ? '/admin/packages/create' : '/admin/packages/' . e($pkg['id']) . '/save' ?>" enctype="multipart/form-data" style="max-width: 800px;">
    <?php if ($isNew): ?>
    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Identificador</div>
        <div style="margin-top: 1rem;">
            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">ID do pacote (slug — não muda depois)</label>
            <input type="text" name="id" required pattern="[a-z0-9][a-z0-9_\-]{1,39}" placeholder="ex: starter, pro, mega" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            <p style="font-size:0.78rem; color:var(--dim); margin-top:0.3rem;">Único e fixo. Só minúsculas, números, <code>-</code> ou <code>_</code> (2–40 caracteres).</p>
        </div>
    </div>
    <?php endif; ?>
    <?= \App\Csrf::field() ?>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Informações básicas</div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 80px; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Nome do pacote</label>
                <input type="text" name="name" required value="<?= e($pkg['name']) ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Ícone</label>
                <input type="text" name="icon" value="<?= e($pkg['icon']) ?>" maxlength="6" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); text-align:center; font-size:1.2rem;">
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Imagem do pacote (capa)</div>
        <p style="margin-top:0.4rem; font-size:0.82rem; color:var(--dim);">PNG transparente, ~512×512. Aparece em destaque no shop e na home no lugar do ícone. Vazio = usa o emoji.</p>
        <div style="display:flex; align-items:center; gap:1rem; margin-top:0.8rem;">
            <?php if (!empty($pkg['image'])): ?>
                <img src="<?= preg_match('#^https?://#i', $pkg['image']) ? e($pkg['image']) : asset('img/packages/' . $pkg['image']) ?>" alt="" style="width:84px; height:84px; object-fit:contain; background:var(--bg-0); border:1px solid var(--border); border-radius:6px; padding:4px;">
            <?php endif; ?>
            <div style="flex:1;">
                <input type="file" name="image" accept="image/png,image/webp,image/jpeg" style="color:var(--bone); font-size:0.85rem;">
                <?php if (!empty($pkg['image'])): ?>
                    <label style="display:inline-flex; align-items:center; gap:0.3rem; margin-top:0.6rem; font-size:0.8rem; color:var(--dim); cursor:pointer;">
                        <input type="checkbox" name="remove_image" value="1"> Remover imagem atual (voltar ao emoji)
                    </label>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Moedas e preço</div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Moedas base</label>
                <input type="number" name="coins" min="1" required value="<?= (int)$pkg['coins'] ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Moedas bônus</label>
                <input type="number" name="bonus_coins" min="0" value="<?= (int)$pkg['bonus_coins'] ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Preço (R$)</label>
                <input type="text" name="price_brl" required value="<?= number_format((float)$pkg['price_brl'], 2, ',', '') ?>" pattern="[0-9]+([.,][0-9]{1,2})?" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Destaque visual</div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 1fr 80px 100px; gap: 1rem; align-items: end;">
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Bonus Badge (canto do card)</label>
                <input type="text" name="bonus_badge" value="<?= e($pkg['bonus_badge']) ?>" placeholder="ex: BÔNUS +5" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Ribbon (faixa topo)</label>
                <input type="text" name="ribbon" value="<?= e($pkg['ribbon']) ?>" placeholder="ex: MAIS POPULAR" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Ordem</label>
                <input type="number" name="sort_order" value="<?= (int)$pkg['sort_order'] ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
            </div>
            <div>
                <label style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.65rem 0; font-size:0.85rem; color:var(--bone); cursor:pointer;">
                    <input type="checkbox" name="featured" <?= (int)$pkg['featured'] ? 'checked' : '' ?> style="width:18px; height:18px;">
                    Destaque
                </label>
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Perks (1 por linha)</div>
        <div style="margin-top: 1rem;">
            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem; color:var(--bone);">Sempre visíveis</label>
            <textarea name="perks" rows="4" style="width:100%; padding:0.7rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono); font-size:0.85rem; resize:vertical;"><?= e(implode("\n", $perks)) ?></textarea>
        </div>
        <div style="margin-top: 1rem;">
            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem; color:var(--moss);">Só quando bônus está ligado</label>
            <textarea name="bonus_perks" rows="3" style="width:100%; padding:0.7rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono); font-size:0.85rem; resize:vertical;"><?= e(implode("\n", $bonusPerks)) ?></textarea>
        </div>
    </div>

    <div style="display: flex; gap: 0.6rem;">
        <button type="submit" class="btn-mini" style="padding: 0.7rem 1.6rem;">Salvar</button>
        <a href="/admin/packages" class="btn-mini outline" style="padding:0.7rem 1.6rem; text-decoration:none; display:inline-flex; align-items:center;">Cancelar</a>
    </div>
</form>

<?php \App\View::endSection(); ?>
