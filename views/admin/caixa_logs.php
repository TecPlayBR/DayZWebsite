<?php /** @var array $config, $logs; @var string $q */ ?>
<?php $title = 'Logs de Caixas'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>📜 Logs de Caixas</h1>
        <p><a href="/admin/caixas" style="color:var(--dim);">← Caixas</a> · Conferência de entregas - quando o player reclamar, busque pelo SteamID e confira o <strong>horário do drop</strong>.</p>
    </div>
</div>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <form method="GET" action="/admin/caixas/logs" style="display:flex;gap:0.5rem;align-items:flex-end;">
        <label style="flex:1;font-size:0.8rem;color:var(--dim);">Buscar por SteamID
            <input type="text" name="steam" value="<?= e($q) ?>" placeholder="76561198..." style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);font-family:var(--font-mono);">
        </label>
        <button type="submit" class="btn">Buscar</button>
        <?php if ($q !== ''): ?><a href="/admin/caixas/logs" class="btn btn-sm" style="background:var(--bg-2);">Limpar</a><?php endif; ?>
    </form>
</div>

<div class="stat-card">
    <div class="label"><?= $q !== '' ? 'Aberturas de ' . e($q) : 'Últimas 200 aberturas' ?> (<?= count($logs) ?>)</div>
    <?php if (empty($logs)): ?>
        <p style="color:var(--dim);margin-top:0.8rem;">Nenhuma abertura encontrada.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table data-enhance data-nofilter style="width:100%;border-collapse:collapse;font-size:0.82rem;margin-top:0.8rem;white-space:nowrap;">
        <thead><tr style="text-align:left;color:var(--dim);border-bottom:1px solid var(--border);">
            <th style="padding:0.5rem 0.4rem;">#</th><th>Aberta em</th><th>SteamID</th><th>Caixa</th><th>Item</th><th>Classname</th><th>Qtd</th><th>Status</th><th>Entregue em (drop)</th>
        </tr></thead>
        <tbody>
        <?php foreach ($logs as $l):
            $delivered = (($l['status'] ?? '') === 'delivered') || !empty($l['delivered_at']);
        ?>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.5rem 0.4rem;color:var(--dim);">#<?= (int)$l['id'] ?></td>
                <td><?= e(fmt_dt($l['created_at'])) ?></td>
                <td><a href="/admin/caixas/logs?steam=<?= e($l['steam_id']) ?>" style="color:var(--rust-2);font-family:var(--font-mono);font-size:0.78rem;"><?= e($l['steam_id']) ?></a></td>
                <td><?= e($l['box_name'] ?? ('#' . $l['box_id'])) ?></td>
                <td style="color:var(--bone);"><?= e($l['item_name'] ?: '-') ?></td>
                <td><code style="font-size:0.75rem;"><?= e($l['classname'] ?: '-') ?></code></td>
                <td><?= (int)$l['quantity'] ?>x</td>
                <td><?= $delivered ? '<span style="color:var(--moss);">✓ entregue</span>' : '<span style="color:var(--hazard);">⏳ pendente</span>' ?></td>
                <td style="color:var(--dim);"><?= !empty($l['delivered_at']) ? e(fmt_dt($l['delivered_at'])) : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php \App\View::endSection(); ?>
