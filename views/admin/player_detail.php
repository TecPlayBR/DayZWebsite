<?php /** @var array $config, $player, $history, $balance_log */ ?>
<?php $title = 'Jogador ' . $player['steam_id']; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1><?= e($player['display_name'] ?? 'Sem nome') ?></h1>
        <p class="mono dim" style="font-family: var(--font-mono); user-select: all;"><?= e($player['steam_id']) ?></p>
        <p style="margin-top:.5rem; display:flex; gap:.6rem; flex-wrap:wrap;">
            <a href="/player/<?= e($player['steam_id']) ?>" target="_blank" rel="noopener" class="btn-mini outline">👤 Perfil público (foto/nick) ↗</a>
            <a href="https://steamcommunity.com/profiles/<?= e($player['steam_id']) ?>" target="_blank" rel="noopener" class="btn-mini outline">🎮 Ver na Steam ↗</a>
        </p>
    </div>
    <a href="/admin/players" class="btn-mini outline">← Voltar</a>
</div>

<div class="stats-grid">
    <div class="stat-card accent">
        <div class="label">Moedas atuais</div>
        <div class="value"><?= number_format((int)$player['coins'], 0, ',', '.') ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Total gasto</div>
        <div class="value">R$ <?= number_format((float)$player['total_spent_brl'], 2, ',', '.') ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Origem</div>
        <div class="value" style="font-size: 1.2rem; text-transform: uppercase;"><?= e($player['origin']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Última atividade</div>
        <div class="value" style="font-size: 1rem;"><?= e($player['last_seen_at'] ?? 'nunca') ?></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">

    <div class="stat-card">
        <div class="label" style="margin-bottom: 1rem;">Ajustar moedas</div>
        <form method="POST" action="/admin/players/<?= (int)$player['id'] ?>/coins" class="inline-form" style="gap: 0.6rem; flex-wrap: wrap;">
            <?= \App\Csrf::field() ?>
            <input type="hidden" name="_back" value="/admin/players/<?= (int)$player['id'] ?>">
            <input type="number" name="coins" value="<?= (int)$player['coins'] ?>" min="0" max="999999" style="width: 140px; padding: 0.5rem; background: var(--bg-0); border: 1px solid var(--border); color: var(--bone);">
            <button type="submit" class="btn-mini">Salvar</button>
        </form>
        <p style="margin-top: 0.6rem; font-size: 0.8rem; color: var(--dim);">
            Ajuste manual. Vai como <code>origin=panel</code>. Agent propaga pro JSON em até 15s.
        </p>
    </div>

    <div class="stat-card">
        <div class="label" style="margin-bottom: 1rem;">Anotações internas</div>
        <form method="POST" action="/admin/players/<?= (int)$player['id'] ?>/notes">
            <?= \App\Csrf::field() ?>
            <textarea name="notes" rows="3" placeholder="Anotações sobre este jogador (privado, só admin vê)" style="width:100%; padding:0.5rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit; font-size:0.85rem; resize:vertical;"><?= e($player['notes'] ?? '') ?></textarea>
            <button type="submit" class="btn-mini" style="margin-top: 0.5rem;">Salvar</button>
        </form>
    </div>

</div>

<h2 style="font-family: var(--font-display); color: var(--bone); font-size: 1.2rem; margin-bottom: 1rem;">
    Histórico de compras (<?= count($history) ?>)
</h2>

<table class="admin-table">
    <thead>
        <tr>
            <th>Data</th>
            <th>Pacote</th>
            <th>Moedas</th>
            <th>Valor</th>
            <th>Método</th>
            <th>Status</th>
            <th>MP ID</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($history)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--dim);padding:2rem;">Nenhuma compra registrada.</td></tr>
        <?php else: foreach ($history as $p): ?>
            <tr>
                <td class="dim"><?= e($p['created_at']) ?></td>
                <td><strong><?= e($p['package_id']) ?></strong></td>
                <td class="mono"><?= (int)$p['coins_total'] ?></td>
                <td>R$ <?= number_format($p['price_brl'], 2, ',', '.') ?></td>
                <td class="dim"><?= e($p['payment_method'] ?? '-') ?></td>
                <td>
                    <?php $cls = match($p['mp_status']) {
                        'approved' => 'success',
                        'rejected','cancelled','refunded' => 'danger',
                        'pending' => 'warning',
                        default => 'info'
                    }; ?>
                    <span class="badge <?= $cls ?>"><?= e($p['mp_status']) ?></span>
                </td>
                <td class="mono dim" style="font-size: 0.75rem;"><?= e($p['mp_payment_id'] ?? '-') ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>


<h2 style="font-family: var(--font-display); color: var(--bone); font-size: 1.2rem; margin: 2.5rem 0 1rem;">
    Histórico de saldo (<?= count($balance_log) ?>)
</h2>

<table class="admin-table">
    <thead>
        <tr>
            <th>Quando</th>
            <th>Origem</th>
            <th>Delta</th>
            <th>Saldo antes</th>
            <th>Saldo depois</th>
            <th>Referência</th>
            <th>Notas</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($balance_log)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--dim);padding:1.5rem;">Sem mudanças de saldo registradas ainda.</td></tr>
        <?php else: foreach ($balance_log as $b): ?>
            <tr>
                <td class="dim mono" style="font-size: 0.78rem; white-space: nowrap;"><?= e($b['created_at']) ?></td>
                <td>
                    <?php $srcCls = match($b['source']) {
                        'payment' => 'success', 'admin' => 'warning',
                        'agent' => 'info', 'refund' => 'danger', default => 'info'
                    }; ?>
                    <span class="badge <?= $srcCls ?>"><?= e($b['source']) ?></span>
                </td>
                <td class="mono" style="font-family: var(--font-display); font-size: 1.05rem;
                    color: <?= (int)$b['delta'] > 0 ? 'var(--moss)' : 'var(--rust-2)' ?>;">
                    <?= (int)$b['delta'] > 0 ? '+' : '' ?><?= (int)$b['delta'] ?>
                </td>
                <td class="mono dim"><?= (int)$b['balance_before'] ?></td>
                <td class="mono"><strong><?= (int)$b['balance_after'] ?></strong></td>
                <td class="mono dim" style="font-size: 0.78rem;">
                    <?= $b['ref_type'] ? e($b['ref_type']) . ':' . e($b['ref_id'] ?? '') : '-' ?>
                </td>
                <td class="dim" style="font-size: 0.85rem;"><?= e($b['notes'] ?? '-') ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php \App\View::endSection(); ?>
