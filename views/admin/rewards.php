<?php /** @var array $config, $categories, $rewards; @var bool $cftools_on */ ?>
<?php $title = 'Recompensas'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Recompensas do Leaderboard</h1>
        <p>Premie os melhores do servidor em moedas. Você escolhe quais categorias premiar, quanto vai pro 1º/2º/3º, e pode deixar só o 1º lugar.</p>
    </div>
</div>

<?php if (($_GET['ok'] ?? '') !== ''): ?>
    <div class="alert-toast">Recompensas salvas.</div>
<?php endif; ?>

<?php if (!$cftools_on): ?>
    <div style="background:var(--hazard-overlay,rgba(217,164,65,.12));border-left:3px solid var(--hazard);padding:.9rem 1.1rem;margin-bottom:1.5rem;color:var(--bone);font-size:.9rem;">
        ⚠ O <strong>CFTools</strong> ainda não está configurado (veja o README → "Ativar leaderboard"). Você pode configurar as recompensas agora — elas aparecem no ranking assim que o leaderboard estiver ligado.
    </div>
<?php endif; ?>

<?php
$master = !empty($rewards['enabled']);
$cats   = $rewards['cats'] ?? [];
function _rw($cats, $key, $place) { return (int)($cats[$key]['coins'][(string)$place] ?? 0); }
?>

<form method="POST" action="/admin/rewards">
    <?= \App\Csrf::field() ?>

    <div class="stat-card" style="padding:1.2rem 1.5rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem;">
        <label style="display:flex; align-items:center; gap:.6rem; cursor:pointer; margin:0;">
            <input type="checkbox" name="master_enabled" value="1" <?= $master ? 'checked' : '' ?>>
            <span style="font-family:var(--font-display); color:var(--bone);">Ativar premiações no ranking</span>
        </label>
        <span style="color:var(--dim); font-size:.85rem;">Desligue pra esconder todas as premiações de uma vez (sem perder a configuração).</span>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Categoria</th>
                <th style="width:90px;">Premiar?</th>
                <th>🥇 1º (moedas)</th>
                <th>🥈 2º (moedas)</th>
                <th>🥉 3º (moedas)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $key => $label):
                $on = !empty($cats[$key]['enabled']);
            ?>
                <tr>
                    <td><strong><?= e($label) ?></strong></td>
                    <td>
                        <label style="cursor:pointer;">
                            <input type="checkbox" name="cat_<?= e($key) ?>_enabled" value="1" <?= $on ? 'checked' : '' ?>>
                        </label>
                    </td>
                    <td><input type="number" min="0" name="cat_<?= e($key) ?>_1" value="<?= _rw($cats,$key,1) ?>" style="width:100px;padding:.4rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);font-family:var(--font-mono);"></td>
                    <td><input type="number" min="0" name="cat_<?= e($key) ?>_2" value="<?= _rw($cats,$key,2) ?>" style="width:100px;padding:.4rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);font-family:var(--font-mono);"></td>
                    <td><input type="number" min="0" name="cat_<?= e($key) ?>_3" value="<?= _rw($cats,$key,3) ?>" style="width:100px;padding:.4rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);font-family:var(--font-mono);"></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="color:var(--dim); font-size:.82rem; margin:1rem 0 1.5rem;">
        💡 Deixe <strong>0</strong> num lugar pra não premiar aquele lugar (ex: só 1º lugar = preencha o 1º e deixe 2º/3º em 0).
        As moedas são creditadas no saldo do jogador no site (e entregues no jogo pelo agent, como qualquer compra).
        <br>🦌 <em>Mortes de animais</em> não entram aqui: o CFTools não fornece ranking de animais (só aparece no perfil individual do jogador).
    </p>

    <button type="submit" class="btn-mini" style="padding:.7rem 1.6rem;">Salvar recompensas</button>
</form>

<?php \App\View::endSection(); ?>
