<?php /** @var array $config, $clans */ ?>
<?php $title = 'Clãs'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🛡 Clãs</h1>
        <p>Clãs registrados pelos jogadores. Use <strong>Remover</strong> pra tirar conteúdo impróprio (logo/nome/descrição) - o clã é dissolvido e os membros liberados.</p>
    </div>
</div>

<?php if (isset($_GET['ok'])): ?><div class="alert-toast">Clã removido.</div><?php endif; ?>

<?php if (empty($clans)): ?>
    <div class="stat-card" style="text-align:center;padding:2.5rem;color:var(--dim);">Nenhum clã registrado ainda.</div>
<?php else: ?>
<table class="admin-table">
    <thead><tr><th>TAG</th><th>Nome</th><th>Dono (SteamID)</th><th>Membros</th><th>Discord</th><th>Criado</th><th></th></tr></thead>
    <tbody>
        <?php foreach ($clans as $c): ?>
            <tr>
                <td><strong class="mono" style="color:var(--hazard);"><?= e($c['tag']) ?></strong></td>
                <td>
                    <a href="/clan/<?= (int)$c['id'] ?>" target="_blank" style="color:var(--bone);"><?= e($c['name']) ?></a>
                    <?php if (!empty($c['description'])): ?><div class="dim" style="font-size:.72rem;"><?= e(mb_strimwidth($c['description'],0,80,'…')) ?></div><?php endif; ?>
                </td>
                <td class="mono" style="font-size:.78rem;"><a href="/player/<?= e($c['owner_steam_id']) ?>" target="_blank" style="color:var(--dim);"><?= e($c['owner_steam_id']) ?></a></td>
                <td class="mono"><?= (int)$c['member_count'] ?>/<?= (int)$c['member_cap'] ?></td>
                <td><?php if (!empty($c['discord_url'])): ?><a href="<?= e($c['discord_url']) ?>" target="_blank" rel="noopener" style="color:var(--hazard);font-size:.8rem;">link</a><?php else: ?><span class="dim">-</span><?php endif; ?></td>
                <td class="dim" style="font-size:.8rem;"><?= e(substr($c['created_at'],0,10)) ?></td>
                <td style="white-space:nowrap;">
                    <form method="POST" action="/admin/clans/<?= (int)$c['id'] ?>/remove" style="display:inline;" onsubmit="return confirm('Remover (dissolver) o clã [<?= e($c['tag']) ?>] <?= e(addslashes($c['name'])) ?>? Libera os membros e não dá pra desfazer.');">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline" style="color:var(--rust-2);border-color:var(--rust-2);">Remover</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php \App\View::endSection(); ?>
