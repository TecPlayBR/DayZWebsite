<?php /** @var array $config; @var ?array $a */ ?>
<?php
$a = array_merge(['id'=>null,'category'=>'comecando','title'=>'','summary'=>'','body'=>'','video_url'=>'','image'=>null,'published'=>1,'sort_order'=>0], is_array($a ?? null) ? $a : []);
$isNew = empty($a['id']);
$title = $isNew ? 'Novo artigo' : 'Editar artigo';
$fld = 'width:100%;padding:.65rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);';
?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div><h1><?= $isNew ? '➕ Novo artigo' : 'Editar: ' . e($a['title']) ?></h1></div>
    <a href="/admin/help" class="btn-mini outline">← Voltar</a>
</div>

<?php if (($_GET['err'] ?? '') === 'title'): ?>
    <div style="background:var(--danger-overlay);border-left:3px solid var(--rust-2);padding:.7rem 1rem;margin-bottom:1.2rem;color:var(--text-danger);">O título é obrigatório.</div>
<?php endif; ?>

<form method="POST" action="/admin/help/save" enctype="multipart/form-data" style="max-width:820px;">
    <?= \App\Csrf::field() ?>
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><?php endif; ?>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div style="display:grid;grid-template-columns:1fr 200px;gap:1rem;">
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Título</label>
                <input type="text" name="title" required maxlength="160" value="<?= e($a['title']) ?>" style="<?= $fld ?>">
            </div>
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Categoria</label>
                <select name="category" style="<?= $fld ?>">
                    <?php foreach (\App\Help::CATEGORIES as $k=>$lbl): ?>
                        <option value="<?= e($k) ?>" <?= $a['category']===$k?'selected':'' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top:1rem;">
            <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Resumo (1 linha — aparece no card e no Google)</label>
            <input type="text" name="summary" maxlength="300" value="<?= e($a['summary']) ?>" style="<?= $fld ?>">
        </div>
    </div>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div class="label">Conteúdo (HTML)</div>
        <p style="font-size:.78rem;color:var(--dim);margin:.3rem 0 .6rem;">Pode usar HTML simples: &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;&lt;li&gt;, &lt;strong&gt;, &lt;a&gt;, &lt;img&gt;. (Scripts são removidos por segurança.)</p>
        <textarea name="body" rows="16" style="<?= $fld ?> font-family:var(--font-mono);font-size:.85rem;resize:vertical;"><?= e($a['body']) ?></textarea>
    </div>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div class="label">Mídia</div>
        <div style="margin-top:1rem;">
            <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Vídeo do YouTube (link) <small>— embuto automático</small></label>
            <input type="url" name="video_url" value="<?= e($a['video_url']) ?>" placeholder="https://youtu.be/..." style="<?= $fld ?>">
        </div>
        <div style="margin-top:1rem;display:flex;align-items:center;gap:1rem;">
            <?php if (!empty($a['image'])): ?><img src="<?= e($a['image']) ?>" alt="" style="width:90px;height:60px;object-fit:cover;border:1px solid var(--border);border-radius:5px;"><?php endif; ?>
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Imagem <?= !empty($a['image']) ? '(trocar)' : '(opcional)' ?></label>
                <input type="file" name="image_file" accept="image/png,image/webp,image/jpeg" style="color:var(--bone);font-size:.85rem;">
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom:1.2rem;">
        <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;color:var(--bone);">
                <input type="checkbox" name="published" value="1" <?= (int)$a['published']?'checked':'' ?> style="width:16px;height:16px;"> Visível no site
            </label>
            <div>
                <label style="font-size:.8rem;color:var(--dim);margin-right:.5rem;">Ordem</label>
                <input type="number" name="sort_order" value="<?= (int)$a['sort_order'] ?>" style="width:90px;padding:.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
            </div>
        </div>
    </div>

    <button type="submit" class="btn-mini" style="padding:.7rem 1.6rem;">Salvar artigo</button>
</form>

<?php \App\View::endSection(); ?>
