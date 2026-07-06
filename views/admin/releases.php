<?php /** @var array $config, $releases */ ?>
<?php $releases = $releases ?? []; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div><h1>📢 Novidades / Notas de Atualização</h1></div>
    <a href="/admin/releases/new" class="btn-mini">➕ Nova</a>
</div>

<?php if (($_GET['ok'] ?? '') !== ''): ?>
    <div style="background:var(--success-overlay);border-left:3px solid var(--moss);padding:.7rem 1rem;margin-bottom:1.2rem;color:var(--text-success);">Salvo.</div>
<?php endif; ?>

<p style="color:var(--dim);font-size:.9rem;margin-bottom:1.2rem;">O que você publicar aqui aparece pro jogador em <a href="/novidades" style="color:var(--hazard);">/novidades</a> (mais recente primeiro). Use pra avisar update de mod, correção, novidade.</p>

<?php if (empty($releases)): ?>
    <div style="text-align:center;color:var(--dim);padding:3rem 1rem;background:var(--bg-1);border:1px dashed var(--border);border-radius:6px;">
        Nenhuma novidade ainda. Crie a primeira no botão acima.
    </div>
<?php else: ?>
    <table class="admin-table">
        <thead><tr><th>Data</th><th>Categoria</th><th>Versão</th><th>Título</th><th>Status</th><th data-nosort data-nofilter></th></tr></thead>
        <tbody>
        <?php foreach ($releases as $r):
            $date = !empty($r['released_at']) ? date('d/m/Y', strtotime($r['released_at'])) : date('d/m/Y', strtotime($r['created_at']));
        ?>
            <tr>
                <td class="mono"><?= e($date) ?></td>
                <td><?= e(\App\Releases::catEmoji($r['category'])) ?> <?= e(\App\Releases::catLabel($r['category'])) ?></td>
                <td class="mono"><?= e($r['version'] ?: '-') ?></td>
                <td><?= e($r['title']) ?></td>
                <td><?= (int)$r['published'] ? '<span style="color:var(--moss);">✓ Publicada</span>' : '<span style="color:var(--dim);">Rascunho</span>' ?></td>
                <td style="text-align:right;white-space:nowrap;">
                    <a href="/admin/releases/<?= (int)$r['id'] ?>/edit" class="btn-mini outline">Editar</a>
                    <form method="POST" action="/admin/releases/<?= (int)$r['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Apagar essa novidade?');">
                        <?= \App\Csrf::field() ?><button class="btn-mini outline" style="color:var(--rust-2);border-color:var(--rust-2);">Apagar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php \App\View::endSection(); ?>
