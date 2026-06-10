<?php /** @var array $config, $items */ ?>
<?php $title = 'Loja in-game'; ?>
<?php \App\View::extend('admin.layout'); ?>

<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Loja in-game</h1>
        <p>Itens que o jogador compra com moeda no Discord (<code>/loja</code>). O bot debita o saldo e o servidor entrega o item in-game.</p>
    </div>
    <div>
        <a href="/admin/shop/new" class="btn-mini">+ Novo item</a>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert-toast">Salvo.</div>
<?php endif; ?>

<?php if (empty($items)): ?>
    <div class="stat-card" style="text-align:center; padding:2.5rem;">
        <p style="color:var(--dim);">Nenhum item cadastrado ainda. Clique em <strong>+ Novo item</strong> pra criar o primeiro.</p>
    </div>
<?php else: ?>
<table class="admin-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Item</th>
            <th>SKU</th>
            <th>Custo</th>
            <th>Entrega</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $i => $it): ?>
            <?php $deliver = json_decode($it['deliver_json'] ?? '[]', true) ?: []; ?>
            <tr>
                <td class="dim"><?= $i + 1 ?></td>
                <td>
                    <strong><?= e($it['icon'] ? $it['icon'] . ' ' : '') ?><?= e($it['name']) ?></strong>
                </td>
                <td class="mono" style="font-size:0.8rem;"><?= e($it['sku']) ?></td>
                <td class="mono"><?= (int)$it['coins_cost'] ?> 🪙</td>
                <td style="font-size:0.8rem; color:var(--dim);">
                    <?php $n = count($deliver); ?>
                    <?= $n ?> classname<?= $n === 1 ? '' : 's' ?>
                    <?php if ($n > 0): ?>
                        <div class="mono" style="font-size:0.72rem;"><?= e($deliver[0]['classname'] ?? '?') ?><?= $n > 1 ? ' +' . ($n - 1) : '' ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int)$it['enabled']): ?>
                        <span class="badge success">ativo</span>
                    <?php else: ?>
                        <span class="badge danger">desativado</span>
                    <?php endif; ?>
                </td>
                <td style="white-space: nowrap;">
                    <a href="/admin/shop/<?= (int)$it['id'] ?>/edit" class="btn-mini outline">Editar</a>
                    <form method="POST" action="/admin/shop/<?= (int)$it['id'] ?>/toggle" style="display: inline;">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline"><?= (int)$it['enabled'] ? 'Desativar' : 'Ativar' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<p style="margin-top: 1.5rem; color: var(--dim); font-size: 0.85rem;">
    O formato de entrega (<code>deliver[]</code>) é o mesmo combinado com o mod: <code>classname</code>, <code>quantity</code>, <code>attachments[]</code>, <code>cargo[]</code> e <code>health</code>.
</p>

<?php \App\View::endSection(); ?>
