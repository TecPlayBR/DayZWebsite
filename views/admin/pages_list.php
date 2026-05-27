<?php /** @var array $config, $pages */ ?>
<?php $title = 'Páginas'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Páginas estáticas</h1>
        <p>Páginas editáveis (regras, sobre, privacidade, etc). Acessíveis em <code>/page/&lt;slug&gt;</code>.</p>
    </div>
    <a href="/admin/pages/new" class="btn-mini">+ Nova página</a>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert-toast">Salvo.</div><?php endif; ?>

<table class="admin-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Slug</th>
            <th>Título (PT-BR)</th>
            <th>EN-US</th>
            <th>Status</th>
            <th>Atualizada</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($pages)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--dim);padding:2rem;">
                Sem páginas ainda. Crie uma com "+ Nova página".
            </td></tr>
        <?php else: foreach ($pages as $p): ?>
            <tr>
                <td class="dim"><?= (int)$p['sort_order'] ?></td>
                <td class="mono"><?= e($p['slug']) ?> &nbsp; <a href="/page/<?= e($p['slug']) ?>" target="_blank" style="color: var(--dim); font-size: 0.75rem;">↗</a></td>
                <td><strong><?= e($p['title_ptbr']) ?></strong></td>
                <td class="dim"><?= e($p['title_enus'] ?? '—') ?></td>
                <td>
                    <?php if ((int)$p['published']): ?>
                        <span class="badge success">publicada</span>
                    <?php else: ?>
                        <span class="badge info">rascunho</span>
                    <?php endif; ?>
                </td>
                <td class="dim"><?= e($p['updated_at']) ?></td>
                <td>
                    <a href="/admin/pages/<?= (int)$p['id'] ?>/edit" class="btn-mini outline">Editar</a>
                    <form method="POST" action="/admin/pages/<?= (int)$p['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Apagar página &quot;<?= e($p['slug']) ?>&quot;?');">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline" style="border-color: rgba(231,76,60,0.4); color: #fca5a5;">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php \App\View::endSection(); ?>
