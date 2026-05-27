<?php /** @var array $config; @var ?array $page */ ?>
<?php $title = $page ? 'Editar página' : 'Nova página'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1><?= $page ? 'Editar: ' . e($page['title_ptbr']) : 'Nova página' ?></h1>
        <p><?= $page ? '<code>/page/' . e($page['slug']) . '</code>' : 'Slug vira a URL (ex: <code>rules</code> → <code>/page/rules</code>)' ?></p>
    </div>
    <a href="/admin/pages" class="btn-mini outline">← Voltar</a>
</div>

<form method="POST" action="/admin/pages/save" style="max-width: 900px;">
    <?= \App\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)($page['id'] ?? 0) ?>">

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Slug (a-z, 0-9, hífen)</label>
                <input type="text" name="slug" required pattern="[a-z0-9-]+" value="<?= e($page['slug'] ?? '') ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Ordem</label>
                <input type="number" name="sort_order" value="<?= (int)($page['sort_order'] ?? 10) ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Status</label>
                <label style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 0; color: var(--bone); cursor: pointer;">
                    <input type="checkbox" name="published" <?= ($page === null || (int)$page['published']) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span>Publicada</span>
                </label>
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">🇧🇷 Português (BR)</div>
        <div style="margin-top: 1rem;">
            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Título</label>
            <input type="text" name="title_ptbr" required value="<?= e($page['title_ptbr'] ?? '') ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
        </div>
        <div style="margin-top: 1rem;">
            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Conteúdo (HTML simples permitido)</label>
            <textarea name="body_ptbr" rows="14" style="width:100%; padding:0.7rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono); font-size:0.9rem; resize:vertical;"><?= e($page['body_ptbr'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1.5rem;">
        <div class="label">🇺🇸 English (US)</div>
        <div style="margin-top: 1rem;">
            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Title</label>
            <input type="text" name="title_enus" value="<?= e($page['title_enus'] ?? '') ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
        </div>
        <div style="margin-top: 1rem;">
            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Body (HTML allowed)</label>
            <textarea name="body_enus" rows="14" style="width:100%; padding:0.7rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono); font-size:0.9rem; resize:vertical;"><?= e($page['body_enus'] ?? '') ?></textarea>
        </div>
    </div>

    <div style="display: flex; gap: 0.6rem;">
        <button type="submit" class="btn-mini" style="padding: 0.7rem 1.6rem; font-size: 0.85rem;">Salvar</button>
        <a href="/admin/pages" class="btn-mini outline" style="padding: 0.7rem 1.6rem; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center;">Cancelar</a>
    </div>
</form>

<?php \App\View::endSection(); ?>
