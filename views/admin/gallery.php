<?php /** @var array $config; @var array $items */ ?>
<?php $title = 'Galeria'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Galeria de Screenshots</h1>
        <p>Imagens exibidas em <code>/galeria</code>. Mostre o servidor pra atrair novos jogadores.</p>
    </div>
    <div style="text-align:right;">
        <a href="/galeria" target="_blank" class="btn-mini outline">↗ Abrir página pública</a>
    </div>
</div>

<?php if (!empty($_GET['err'])): $err = $_GET['err']; ?>
    <div style="background:var(--danger-overlay); border-left:3px solid var(--rust-2); padding:0.9rem; color:var(--text-danger); margin-bottom:1.5rem;">
        <?php
        echo match($err) {
            'upload' => 'Falha no upload do arquivo.',
            'size'   => 'Arquivo grande demais. Máximo: 5 MB.',
            'type'   => 'Tipo inválido. Use JPG, PNG, WEBP ou GIF.',
            'move'   => 'Não consegui salvar o arquivo. Verifique permissões da pasta.',
            default  => 'Erro desconhecido.',
        };
        ?>
    </div>
<?php endif; ?>

<div class="admin-card" style="margin-bottom:2rem;">
    <h3>Enviar nova imagem</h3>
    <form method="POST" action="/admin/gallery/upload" enctype="multipart/form-data" class="admin-form">
        <?= \App\Csrf::field() ?>
        <input type="hidden" name="csrf_token" value="<?= e(\App\Csrf::token()) ?>">
        <label>Arquivo (JPG/PNG/WEBP, máx 5 MB)
            <input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif" required>
        </label>
        <label>Legenda (opcional)
            <input type="text" name="caption" maxlength="200" placeholder="Ex: Nascer do sol em Chernarus">
        </label>
        <label>Ordem (menor = primeiro)
            <input type="number" name="sort_order" value="0">
        </label>
        <button type="submit" class="btn-mini">↑ Enviar</button>
    </form>
</div>

<div class="gallery-admin-grid">
    <?php if (empty($items)): ?>
        <p style="color:var(--dim); grid-column: 1/-1; text-align:center; padding:2rem;">
            Nenhuma imagem cadastrada. Suba a primeira aí em cima.
        </p>
    <?php else: foreach ($items as $it): ?>
        <div class="gallery-admin-card">
            <div class="gallery-admin-thumb">
                <img src="<?= asset('img/gallery/' . $it['filename']) ?>" alt="<?= e($it['caption'] ?? '') ?>" loading="lazy">
                <?php if (!$it['published']): ?>
                    <span class="gallery-badge-hidden">Oculta</span>
                <?php endif; ?>
            </div>
            <form method="POST" action="/admin/gallery/update" class="gallery-admin-form">
        <?= \App\Csrf::field() ?>
                <input type="hidden" name="csrf_token" value="<?= e(\App\Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <input type="text" name="caption" value="<?= e($it['caption'] ?? '') ?>" placeholder="Legenda" maxlength="200">
                <div class="row">
                    <input type="number" name="sort_order" value="<?= (int)$it['sort_order'] ?>" title="Ordem">
                    <label class="checkbox">
                        <input type="checkbox" name="published" value="1" <?= $it['published'] ? 'checked' : '' ?>> Publicada
                    </label>
                </div>
                <div class="row">
                    <button type="submit" class="btn-mini">Salvar</button>
                    <button type="submit" formaction="/admin/gallery/delete" class="btn-mini danger"
                            onclick="return confirm('Apagar essa imagem? Ação irreversível.')">Excluir</button>
                </div>
            </form>
        </div>
    <?php endforeach; endif; ?>
</div>

<style>
.gallery-admin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.25rem;
}
.gallery-admin-card {
    background: var(--bg-1);
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
}
.gallery-admin-thumb {
    position: relative;
    aspect-ratio: 4/3;
    background: #000;
    overflow: hidden;
}
.gallery-admin-thumb img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}
.gallery-badge-hidden {
    position: absolute; top: 0.5rem; left: 0.5rem;
    background: var(--rust-2); color: #fff;
    padding: 0.2rem 0.5rem; font-size: 0.7rem;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.gallery-admin-form {
    padding: 0.75rem;
    display: flex; flex-direction: column; gap: 0.5rem;
}
.gallery-admin-form input[type=text],
.gallery-admin-form input[type=number] {
    background: var(--bg-0); border: 1px solid var(--border);
    color: var(--bone); padding: 0.4rem 0.5rem;
    font-family: var(--font-mono); font-size: 0.8rem;
}
.gallery-admin-form .row { display: flex; gap: 0.5rem; align-items: center; }
.gallery-admin-form .row input[type=number] { width: 70px; }
.gallery-admin-form .checkbox {
    display: inline-flex; align-items: center; gap: 0.3rem;
    color: var(--bone); font-size: 0.8rem; cursor: pointer; flex: 1;
}
.btn-mini.danger { background: var(--rust-2); border-color: var(--rust-2); }
</style>

<?php \App\View::endSection(); ?>
