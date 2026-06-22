<?php /** @var array $config, $rows; @var string $q; @var int $total, $uniq */ ?>
<?php $title = 'Logins'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🔑 Logins no site</h1>
        <p>Registro de quem entrou via Steam (auditoria/privacidade). O ranking segue público — isto é só o log de acesso. Total: <strong><?= (int)$total ?></strong> · Jogadores únicos: <strong><?= (int)$uniq ?></strong>.</p>
    </div>
</div>

<form method="GET" action="/admin/logins" style="margin-bottom:1.2rem; display:flex; gap:.5rem; flex-wrap:wrap;">
    <input type="text" name="steam" value="<?= e($q) ?>" placeholder="Filtrar por SteamID..." style="padding:.55rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); width:240px;">
    <button type="submit" class="btn btn-sm">Buscar</button>
    <?php if ($q !== ''): ?><a href="/admin/logins" class="btn btn-sm" style="background:var(--bg-2);">Limpar</a><?php endif; ?>
</form>

<?php if (empty($rows)): ?>
    <div class="stat-card" style="text-align:center; padding:2.5rem 1rem; color:var(--dim);">Nenhum login registrado<?= $q !== '' ? ' pra esse SteamID' : '' ?>.</div>
<?php else: ?>
    <div class="stat-card" style="padding:0; overflow-x:auto;">
        <table data-enhance data-nofilter style="width:100%; border-collapse:collapse; font-size:.85rem;">
            <thead><tr style="text-align:left; color:var(--dim); border-bottom:1px solid var(--border);">
                <th style="padding:.7rem 1rem;">Quando</th><th>Nick</th><th>SteamID</th><th>IP</th><th class="hide-mobile">Navegador</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:.6rem 1rem; color:var(--dim); white-space:nowrap;"><?= e(fmt_dt($r['created_at'])) ?></td>
                    <td><?= e($r['display_name'] ?? '—') ?></td>
                    <td style="font-family:var(--font-mono);"><a href="/player/<?= e($r['steam_id']) ?>" style="color:var(--rust-2);"><?= e($r['steam_id']) ?></a></td>
                    <td style="font-family:var(--font-mono); color:var(--dim);"><?= e($r['ip'] ?? '—') ?></td>
                    <td class="hide-mobile" style="color:var(--dim); font-size:.78rem; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($r['user_agent'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<style>@media (max-width: 760px){ .hide-mobile { display:none; } }</style>
