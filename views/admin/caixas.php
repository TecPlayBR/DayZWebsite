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

<?php if ($pending > 0): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--hazard);">
        ⏳ <strong><?= $pending ?></strong> entrega(s) pendente(s) — caem quando o player estiver online (longe do restart).
    </div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <div class="label">Nova caixa</div>
    <form method="POST" action="/admin/caixas/save" style="margin-top:0.8rem; display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:0.6rem; align-items:end;">
        <?= \App\Csrf::field() ?>
        <div>
            <label style="display:block;font-size:0.78rem;color:var(--dim);">Nome</label>
            <input type="text" name="name" required placeholder="Caixa Berezino" style="width:100%;padding:0.55rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
        </div>
        <div>
            <label style="display:block;font-size:0.78rem;color:var(--dim);">Custo (moedas)</label>
            <input type="number" name="cost_coins" value="100" min="0" style="width:100%;padding:0.55rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
        </div>
        <div>
            <label style="display:flex;align-items:center;gap:0.3rem;font-size:0.78rem;color:var(--dim);"><input type="checkbox" name="is_daily" value="1"> Diária grátis</label>
            <small style="display:block;color:var(--dim);font-size:0.7rem;margin-top:0.3rem;">A caixa nasce ativa — desative depois pela lista, se quiser.</small>
        </div>
        <button type="submit" class="btn">Criar</button>
    </form>
</div>

<?php if (empty($boxes)): ?>
    <p style="color:var(--dim);">Nenhuma caixa ainda. Crie a primeira acima.</p>
<?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
        <thead><tr style="text-align:left;color:var(--dim);border-bottom:1px solid var(--border);">
            <th style="padding:0.6rem 0.4rem;">#</th><th>Caixa</th><th>Tipo</th><th>Itens</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($boxes as $b): ?>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:0.6rem 0.4rem;color:var(--dim);font-family:var(--font-mono);"><?= (int)($b['sort_order'] ?? 0) ?></td>
                <td style="padding:0.6rem 0.4rem;">
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
