<?php
/** @var array $config, $boxes; @var int $pending */
?>
<?php $title = 'Caixas'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🎁 Caixas / Lootboxes</h1>
        <p>Caixas que o player abre (gastando moedas ou diária grátis). Cada caixa tem um pool de itens com chance. O item sorteado cai no jogo via CFTools.</p>
    </div>
    <div><a href="/admin/caixas/logs" class="btn btn-sm">📜 Logs de aberturas</a></div>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert-toast">Salvo!</div><?php endif; ?>

<?php if (!\App\CFTools::isConfigured()): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--rust);">
        ⚠️ <strong>CFTools não configurado.</strong> As caixas abrem normalmente, mas o item sorteado <strong>não cai no jogo</strong> (fica pendente). Pra ativar a entrega in-game, preencha a integração CFTools em <a href="/admin/settings" style="color:var(--hazard);">Configurações</a>.
    </div>
<?php endif; ?>

<?php if ($pending > 0): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--hazard);">
        ⏳ <strong><?= $pending ?></strong> entrega(s) pendente(s) - caem quando o player estiver online (longe do restart).
    </div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <div class="label">Nova caixa</div>
    <form method="POST" action="/admin/caixas/save" class="adm-grid-3" style="margin-top:0.8rem; align-items:end;" data-adm-form>
        <?= \App\Csrf::field() ?>
        <?= admin_campo(['label' => 'Nome', 'name' => 'name', 'required' => true, 'placeholder' => 'Caixa Berezino', 'hint' => 'Como aparece na vitrine de caixas.']) ?>
        <?= admin_campo(['label' => 'Custo (moedas)', 'name' => 'cost_coins', 'type' => 'number', 'value' => '100', 'class' => 'mono', 'attrs' => 'min="0"', 'hint' => 'Ignorado se for diária grátis.']) ?>
        <div class="adm-campo">
            <label class="adm-check"><input type="checkbox" name="is_daily" value="1"> Diária grátis</label>
            <small class="adm-hint">A caixa nasce ativa. Desative depois pela lista, se quiser.</small>
        </div>
        <div class="adm-acoes adm-span-2"><button type="submit" class="btn">Criar caixa</button></div>
    </form>
</div>

<?php if (empty($boxes)): ?>
    <p style="color:var(--dim);">Nenhuma caixa ainda. Crie a primeira acima.</p>
<?php else: ?>
    <table class="admin-table">
        <thead><tr>
            <th>#</th><th>Caixa</th><th>Tipo</th><th>Itens</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($boxes as $b): ?>
            <tr>
                <td class="mono dim"><?= (int)($b['sort_order'] ?? 0) ?></td>
                <td>
                    <?php if (!empty($b['image'])): ?><img src="<?= e($b['image']) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:0.5rem;"><?php endif; ?>
                    <strong style="color:var(--bone);"><?= e($b['name']) ?></strong>
                    <code style="color:var(--dim);font-size:0.75rem;">/<?= e($b['slug']) ?></code>
                </td>
                <td><?= (int)$b['is_daily'] === 1 ? '🆓 Diária' : ('🪙 ' . (int)$b['cost_coins']) ?></td>
                <td><?= (int)$b['item_count'] ?></td>
                <td><?= (int)$b['enabled'] ? '<span style="color:var(--moss)">● ativa</span>' : '<span style="color:var(--dim)">○ off</span>' ?></td>
                <td style="text-align:right;"><a href="/admin/caixas/<?= (int)$b['id'] ?>" class="btn btn-sm">Editar →</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php \App\View::endSection(); ?>
