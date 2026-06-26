<?php /** @var array $config, $articles */ ?>
<?php $title = 'Central de Ajuda'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>📚 Central de Ajuda</h1>
        <p>Os guias/tutoriais que aparecem em <a href="/ajuda" target="_blank" style="color:var(--hazard);">/ajuda</a>. Cada artigo tem categoria, texto, vídeo (YouTube) e imagem.</p>
    </div>
    <a href="/admin/help/new" class="btn-mini">➕ Novo artigo</a>
</div>

<?php if (isset($_GET['ok'])): ?><div class="alert-toast">Salvo.</div><?php endif; ?>

<?php if (empty($articles)): ?>
    <div class="stat-card" style="text-align:center;padding:2.5rem;color:var(--dim);">Nenhum artigo ainda. Crie o primeiro pra montar a Central de Ajuda.</div>
<?php else: ?>
<table class="admin-table">
    <thead><tr><th>Categoria</th><th>Título</th><th>Mídia</th><th>Ordem</th><th>Status</th><th></th></tr></thead>
    <tbody>
        <?php foreach ($articles as $a): ?>
            <tr>
                <td><span class="badge info"><?= e(\App\Help::catLabel($a['category'])) ?></span></td>
                <td>
                    <a href="/ajuda/<?= e($a['slug']) ?>" target="_blank" style="color:var(--bone);"><?= e($a['title']) ?></a>
                    <div class="dim" style="font-size:.72rem;font-family:var(--font-mono);"><?= e($a['slug']) ?></div>
                </td>
                <td style="font-size:.8rem;">
                    <?= !empty($a['video_url']) ? '🎥' : '' ?><?= !empty($a['image']) ? ' 🖼' : '' ?><?= empty($a['video_url']) && empty($a['image']) ? '<span class="dim">—</span>' : '' ?>
                </td>
                <td class="mono"><?= (int)$a['sort_order'] ?></td>
                <td><?= (int)$a['published'] ? '<span class="badge success">visível</span>' : '<span class="badge danger">oculto</span>' ?></td>
                <td style="white-space:nowrap;">
                    <a href="/admin/help/<?= (int)$a['id'] ?>/edit" class="btn-mini outline">Editar</a>
                    <form method="POST" action="/admin/help/<?= (int)$a['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Excluir o artigo &quot;<?= e(addslashes($a['title'])) ?>&quot;?');">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline" style="color:var(--rust-2);border-color:var(--rust-2);">Excluir</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php \App\View::endSection(); ?>
