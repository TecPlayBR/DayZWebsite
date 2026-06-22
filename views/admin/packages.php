<?php /** @var array $config, $packages; @var int $bonus_enabled */ ?>
<?php $title = 'Pacotes'; ?>
<?php \App\View::extend('admin.layout'); ?>

<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Pacotes de Moedas</h1>
        <p>Quando o bônus está desligado, os jogadores recebem só o valor base (sem o "+X" extra).</p>
    </div>
    <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
        <a href="/admin/packages/new" class="btn-mini">➕ Novo pacote</a>
        <form method="POST" action="/admin/packages/toggle-bonus" style="display: inline;">
            <?= \App\Csrf::field() ?>
            <button type="submit" class="<?= $bonus_enabled ? 'btn-mini' : 'btn-mini outline' ?>">
                <?php if ($bonus_enabled): ?>
                    ✓ Bônus LIGADO — clique pra desligar
                <?php else: ?>
                    ✗ Bônus DESLIGADO — clique pra ligar
                <?php endif; ?>
            </button>
        </form>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert-toast">Atualizado.</div>
<?php endif; ?>

<table class="admin-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Nome</th>
            <th>Moedas</th>
            <th>Bônus</th>
            <th>Preço</th>
            <th>Featured</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($packages as $i => $p): ?>
            <tr>
                <td class="dim"><?= $i + 1 ?></td>
                <td>
                    <strong><?= e($p['name']) ?></strong>
                    <div class="dim" style="font-size: 0.75rem; font-family: var(--font-mono);"><?= e($p['id']) ?></div>
                </td>
                <td class="mono"><?= (int)$p['coins'] ?></td>
                <td>
                    <?php if ((int)$p['bonus_coins'] > 0): ?>
                        <span class="badge <?= $bonus_enabled ? 'success' : 'info' ?>">+<?= (int)$p['bonus_coins'] ?></span>
                    <?php else: ?>
                        <span class="dim">—</span>
                    <?php endif; ?>
                </td>
                <td><strong>R$ <?= number_format($p['price_brl'], 2, ',', '.') ?></strong></td>
                <td>
                    <?php if ((int)$p['featured']): ?>
                        <span class="badge warning"><?= e($p['ribbon'] ?: 'destaque') ?></span>
                    <?php else: ?>
                        <span class="dim">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int)$p['enabled']): ?>
                        <span class="badge success">ativo</span>
                    <?php else: ?>
                        <span class="badge danger">desativado</span>
                    <?php endif; ?>
                </td>
                <td style="white-space: nowrap;">
                    <a href="/admin/packages/<?= e($p['id']) ?>/edit" class="btn-mini outline">Editar</a>
                    <form method="POST" action="/admin/packages/<?= e($p['id']) ?>/toggle" style="display: inline;">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline">
                            <?= (int)$p['enabled'] ? 'Desativar' : 'Ativar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p style="margin-top: 1.5rem; color: var(--dim); font-size: 0.85rem;">
    Clique em <strong>Editar</strong> pra mexer em nome, preço, moedas, perks e destaque.
</p>

<?php \App\View::endSection(); ?>
