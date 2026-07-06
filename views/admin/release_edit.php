<?php /** @var array $config; @var ?array $r */ ?>
<?php
$r = array_merge(['id'=>null,'version'=>'','category'=>'atualizacao','title'=>'','body'=>'','released_at'=>'','published'=>1,'sort_order'=>0], is_array($r ?? null) ? $r : []);
$isNew = empty($r['id']);
$fld = 'width:100%;padding:.65rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);';
$relDate = !empty($r['released_at']) ? substr((string)$r['released_at'], 0, 10) : date('Y-m-d');
?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div><h1><?= $isNew ? '➕ Nova novidade' : 'Editar: ' . e($r['title']) ?></h1></div>
    <a href="/admin/releases" class="btn-mini outline">← Voltar</a>
</div>

<?php if (($_GET['err'] ?? '') === 'title'): ?>
    <div style="background:var(--danger-overlay);border-left:3px solid var(--rust-2);padding:.7rem 1rem;margin-bottom:1.2rem;color:var(--text-danger);">O título é obrigatório.</div>
<?php endif; ?>

<form method="POST" action="/admin/releases/save" style="max-width:820px;">
    <?= \App\Csrf::field() ?>
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div style="display:grid;grid-template-columns:1fr 160px 170px;gap:1rem;">
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Título</label>
                <input type="text" name="title" required maxlength="160" value="<?= e($r['title']) ?>" style="<?= $fld ?>" placeholder="Ex: Armas corrigidas">
            </div>
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Categoria</label>
                <select name="category" style="<?= $fld ?>">
                    <?php foreach (\App\Releases::CATEGORIES as $k=>$c): ?>
                        <option value="<?= e($k) ?>" <?= $r['category']===$k?'selected':'' ?>><?= e($c[1] . ' ' . $c[0]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Data</label>
                <input type="date" name="released_at" value="<?= e($relDate) ?>" style="<?= $fld ?>">
            </div>
        </div>
        <div style="margin-top:1rem;">
            <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Versão (opcional) - ex: v2.5.2</label>
            <input type="text" name="version" maxlength="40" value="<?= e($r['version']) ?>" style="max-width:220px;<?= $fld ?>">
        </div>
    </div>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div class="label">O que mudou (HTML)</div>
        <p style="font-size:.78rem;color:var(--dim);margin:.3rem 0 .6rem;">HTML simples: &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;&lt;li&gt;, &lt;strong&gt;, &lt;a&gt;. (Scripts removidos por segurança.)</p>
        <textarea name="body" rows="12" style="<?= $fld ?> font-family:var(--font-mono);font-size:.85rem;resize:vertical;" placeholder="<p>Foi corrigido o carregador das armas que...</p>&#10;<ul><li>...</li></ul>"><?= e($r['body']) ?></textarea>
    </div>

    <div class="stat-card" style="margin-bottom:1.2rem;">
        <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;color:var(--bone);">
                <input type="checkbox" name="published" value="1" <?= (int)$r['published']?'checked':'' ?> style="width:16px;height:16px;"> Publicar (visível em /novidades)
            </label>
            <div>
                <label style="font-size:.8rem;color:var(--dim);margin-right:.5rem;">Ordem (desempate na mesma data)</label>
                <input type="number" name="sort_order" value="<?= (int)$r['sort_order'] ?>" style="width:90px;padding:.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
            </div>
        </div>
    </div>

    <button type="submit" class="btn-mini" style="padding:.7rem 1.6rem;">Salvar novidade</button>
</form>

<?php \App\View::endSection(); ?>
