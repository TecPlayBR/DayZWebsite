<?php /** @var array $config, $list, $rewards, $recent; @var bool $enabled; @var int $paid_count, $paid_coins */ ?>
<?php $title = 'Recompensa por Conquista'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🏆 Recompensa por Conquista</h1>
        <p>Dá <strong>moedas bônus por conta da casa</strong> quando o jogador desbloqueia uma conquista. Paga <strong>1x por conquista por jogador</strong> (creditado automaticamente quando ele vê o próprio perfil/loga). Não aparece na loja - só credita e registra aqui.</p>
    </div>
</div>

<?php if (isset($_GET['ok'])): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--moss);">✓ Salvo.</div>
<?php endif; ?>

<form method="POST" action="/admin/achievements">
    <?= \App\Csrf::field() ?>
    <div class="stat-card" style="margin-bottom:1.5rem; padding:1.4rem;">
        <label style="display:flex; align-items:center; gap:.6rem; cursor:pointer;">
            <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
            <span style="color:var(--bone); font-weight:600;">Ativar recompensa por conquista</span>
        </label>
        <p style="color:var(--dim); font-size:.82rem; margin:.5rem 0 0;">Desligado, nada é creditado (o histórico abaixo é preservado e não paga de novo).</p>
    </div>

    <div class="stat-card" style="padding:0; overflow-x:auto; margin-bottom:1.2rem;">
        <table class="admin-table" data-nofilter>
            <thead><tr>
                <th data-nosort>Conquista</th><th data-nosort>Descrição</th><th data-nosort style="width:150px;">Moedas (0 = nada)</th>
            </tr></thead>
            <tbody>
            <?php foreach ($list as $a): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= e($a['icon']) ?> <strong><?= e($a['name']) ?></strong></td>
                    <td class="dim"><?= e($a['description']) ?></td>
                    <td><input type="number" name="reward[<?= e($a['slug']) ?>]" min="0" max="100000" value="<?= (int)($rewards[$a['slug']] ?? 0) ?>" style="width:110px;"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="submit" class="btn">Salvar</button>
</form>

<h2 style="margin:2rem 0 1rem; color:var(--bone); font-size:1.1rem;">📜 Já pago (<?= (int)$paid_count ?> recompensas · <?= number_format((int)$paid_coins, 0, ',', '.') ?> moedas)</h2>
<?php if (empty($recent)): ?>
    <div class="stat-card" style="text-align:center; padding:2rem 1rem; color:var(--dim);">Nada pago ainda.</div>
<?php else: ?>
    <div class="stat-card" style="padding:0; overflow-x:auto;">
        <table class="admin-table">
            <thead><tr>
                <th>Quando</th><th>SteamID</th><th>Conquista</th><th>Moedas</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td class="dim"><?= e(fmt_dt($r['created_at'])) ?></td>
                    <td class="mono"><a href="/player/<?= e($r['steam_id']) ?>" style="color:var(--rust-2);"><?= e($r['steam_id']) ?></a></td>
                    <td><?= e($r['slug']) ?></td>
                    <td style="color:var(--moss); font-weight:600;">+<?= (int)$r['coins'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
