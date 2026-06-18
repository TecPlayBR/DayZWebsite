<?php /** @var array $config, $grants */ ?>
<?php $title = 'VIP & BattlePass'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<?php
$badge = [
    'pending'  => ['Pendente', 'var(--hazard)'],
    'applied'  => ['Ativo', 'var(--moss)'],
    'revoked'  => ['Revogando', 'var(--rust-2)'],
    'removed'  => ['Removido', 'var(--dim)'],
    'expired'  => ['Expirado', 'var(--dim)'],
];
?>

<div class="admin-page-head">
    <div>
        <h1>🎟️ VIP &amp; BattlePass</h1>
        <p>Concede VIP/Passe pros players. O <strong>agent</strong> aplica no servidor (escreve no mod Sparda) no próximo ciclo e marca como <strong>Ativo</strong>. Revogar → o agent remove do jogo.</p>
    </div>
</div>

<?php if (isset($_GET['ok'])): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--moss);">
        ✓ <?= $_GET['ok'] === '2' ? 'Revogado — o agent remove no próximo ciclo.' : 'Concedido — o agent aplica no próximo ciclo.' ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--rust);">
        ⚠️ <?= $_GET['err'] === 'steam' ? 'SteamID inválido (precisa ser 17 dígitos, 7656119...).' : 'Erro ao salvar.' ?>
    </div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem; padding:1.4rem;">
    <h2 style="font-size:1rem; margin-bottom:1rem; color:var(--bone);">➕ Conceder</h2>
    <form method="POST" action="/admin/entitlements/grant" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
        <?= \App\Csrf::field() ?>
        <div>
            <label style="display:block; font-size:.8rem; color:var(--dim); margin-bottom:.25rem;">SteamID64</label>
            <input type="text" name="steam_id" required placeholder="7656119..." pattern="7656119[0-9]{10}" style="padding:.55rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); width:200px;">
        </div>
        <div>
            <label style="display:block; font-size:.8rem; color:var(--dim); margin-bottom:.25rem;">Nick (opcional)</label>
            <input type="text" name="nickname" maxlength="120" style="padding:.55rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); width:160px;">
        </div>
        <div>
            <label style="display:block; font-size:.8rem; color:var(--dim); margin-bottom:.25rem;">Tipo</label>
            <select name="type" style="padding:.55rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                <option value="vip">VIP</option>
                <option value="battlepass">BattlePass</option>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:.8rem; color:var(--dim); margin-bottom:.25rem;">Tier <span style="opacity:.6;">(só VIP)</span></label>
            <select name="tier" style="padding:.55rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                <option value="PanelVip1">PanelVip1</option>
                <option value="PanelVip2">PanelVip2</option>
                <option value="PanelVip3">PanelVip3</option>
                <option value="PanelVip4">PanelVip4</option>
                <option value="CUSTOM">CUSTOM</option>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:.8rem; color:var(--dim); margin-bottom:.25rem;">Dias</label>
            <input type="number" name="days" value="30" min="1" max="3650" style="padding:.55rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); width:80px;">
        </div>
        <button type="submit" class="btn">Conceder</button>
    </form>
</div>

<?php if (empty($grants)): ?>
    <div class="stat-card" style="text-align:center; padding:2.5rem 1rem; color:var(--dim);">Nenhum VIP/Passe concedido ainda.</div>
<?php else: ?>
    <div class="stat-card" style="padding:0; overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:.86rem;">
            <thead><tr style="text-align:left; color:var(--dim); border-bottom:1px solid var(--border);">
                <th style="padding:.7rem 1rem;">SteamID</th><th>Nick</th><th>Tipo</th><th>Tier</th><th>Expira</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($grants as $g): ?>
                <?php [$lbl, $cor] = $badge[$g['status']] ?? [$g['status'], 'var(--dim)']; ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:.6rem 1rem; font-family:var(--font-mono);"><?= e($g['steam_id']) ?></td>
                    <td><?= e($g['nickname'] ?? '—') ?></td>
                    <td><?= e($g['type']) ?></td>
                    <td><?= e($g['tier'] ?? '—') ?></td>
                    <td><?= e($g['expiration_date'] ?? '—') ?></td>
                    <td><span style="color:<?= $cor ?>; font-weight:600;"><?= e($lbl) ?></span></td>
                    <td style="text-align:right; padding-right:1rem;">
                        <?php if (in_array($g['status'], ['pending','applied'], true)): ?>
                            <form method="POST" action="/admin/entitlements/revoke" style="display:inline;" onsubmit="return confirm('Revogar este VIP/Passe?');">
                                <?= \App\Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="background:var(--rust);">Revogar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
