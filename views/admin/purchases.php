<?php /** @var array $config, $purchases, $counts; @var string $filter */ ?>
<?php $title = 'Compras'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Compras</h1>
        <p>Histórico de transações via Mercado Pago.</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <a href="/admin/purchases" class="btn-mini outline">Todas (<?= (int)$counts['total'] ?>)</a>
        <a href="/admin/purchases?status=approved" class="btn-mini outline">Aprovadas (<?= (int)$counts['approved'] ?>)</a>
        <a href="/admin/purchases?status=pending" class="btn-mini outline">Pendentes (<?= (int)$counts['pending'] ?>)</a>
        <a href="/admin/purchases?status=rejected" class="btn-mini outline">Rejeitadas (<?= (int)$counts['rejected'] ?>)</a>
        <a href="/admin/purchases/export<?= $filter ? '?status=' . urlencode($filter) : '' ?>" class="btn-mini" style="background: var(--moss); border-color: var(--moss);">⬇ Exportar CSV</a>
    </div>
</div>

<table class="admin-table">
    <thead>
        <tr>
            <th>SteamID</th>
            <th>Pacote</th>
            <th>Moedas</th>
            <th>Valor</th>
            <th>Método</th>
            <th>Status</th>
            <th>MP ID</th>
            <th>Criada</th>
            <th>Entregue</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($purchases)): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--dim);padding:2rem;">Sem compras ainda.</td></tr>
        <?php else: foreach ($purchases as $p): ?>
            <tr>
                <td class="mono"><?= e($p['steam_id']) ?></td>
                <td><strong><?= e($p['package_id']) ?></strong></td>
                <td class="mono">
                    <?= (int)$p['coins_base'] ?>
                    <?php if ((int)$p['coins_bonus'] > 0): ?>
                        <span style="color: var(--moss);">+<?= (int)$p['coins_bonus'] ?></span>
                    <?php endif; ?>
                    <span class="dim">= <?= (int)$p['coins_total'] ?></span>
                </td>
                <td><strong>R$ <?= number_format($p['price_brl'], 2, ',', '.') ?></strong></td>
                <td class="dim"><?= e($p['payment_method'] ?? '-') ?></td>
                <td>
                    <?php
                    $status = $p['mp_status'] ?? 'pending';
                    $cls = match($status) {
                        'approved' => 'success',
                        'rejected','cancelled','refunded' => 'danger',
                        'pending' => 'warning',
                        default => 'info'
                    };
                    ?>
                    <span class="badge <?= $cls ?>"><?= e($status) ?></span>
                </td>
                <td class="mono dim" style="font-size: 0.75rem;"><?= e($p['mp_payment_id'] ?? '-') ?></td>
                <td class="dim"><?= e($p['created_at']) ?></td>
                <td class="dim"><?= e($p['delivered_at'] ?? '-') ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php \App\View::endSection(); ?>
