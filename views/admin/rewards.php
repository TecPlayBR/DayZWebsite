<?php /** @var array $config, $categories, $rewards, $history; @var bool $cftools_on, $awarded_period; @var int $last_awarded; @var string $period_label */ ?>
<?php $title = 'Recompensas'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Recompensas do Leaderboard</h1>
        <p>Premie os melhores do servidor em moedas. Você escolhe quais categorias premiar, quanto vai pro 1º/2º/3º, a cadência (manual/semanal/mensal) e se credita automático.</p>
    </div>
</div>

<?php if (($_GET['ok'] ?? '') === 'award'): $n = (int)($_GET['n'] ?? 0); $sk = (int)($_GET['sk'] ?? 0); ?>
    <?php if ($n > 0): ?>
        <div class="alert-toast">✓ Premiação rodada - <?= $n ?> colocação(ões) premiada(s). As moedas já caíram no saldo dos vencedores (aparece no perfil de cada um, em "🏆 Premiações do ranking").<?= $sk > 0 ? ' ⚠ ' . $sk . ' vencedor(es) NÃO recebeu(ram): a conta dele ainda não está vinculada (ele precisa abrir o site/perfil 1x logado pra vincular a Steam ao CFTools).' : '' ?></div>
    <?php else: ?>
        <div class="alert-toast" style="background:var(--bg-1);border-left:3px solid var(--hazard);color:var(--bone);">
            Premiação rodada, mas <strong>nada novo foi creditado</strong> - ou o período <code><?= e($period_label) ?></code> já tinha sido premiado (não paga 2x no mesmo período)<?= $sk > 0 ? ', ou os ' . $sk . ' vencedor(es) do leaderboard ainda não vincularam a conta no site (precisam abrir o perfil 1x logado pra mapear a Steam↔CFTools)' : ', ou o leaderboard CFTools ainda não tem vencedor elegível' ?>. Quem já recebeu vê no próprio perfil.
        </div>
    <?php endif; ?>
<?php elseif (($_GET['ok'] ?? '') !== ''): ?>
    <div class="alert-toast">Recompensas salvas.</div>
<?php elseif (($_GET['err'] ?? '') !== ''): ?>
    <div class="alert-toast" style="background:var(--rust);"><?= e($_GET['err']) ?></div>
<?php endif; ?>

<?php if (!$cftools_on): ?>
    <div style="background:var(--hazard-overlay,rgba(217,164,65,.12));border-left:3px solid var(--hazard);padding:.9rem 1.1rem;margin-bottom:1.5rem;color:var(--bone);font-size:.9rem;">
        ⚠ O <strong>CFTools</strong> ainda não está configurado (veja o README → "Ativar leaderboard"). Você pode configurar as recompensas agora - elas aparecem no ranking assim que o leaderboard estiver ligado.
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

    <?php $cadence = $rewards['cadence'] ?? 'manual'; $auto = !empty($rewards['auto']); ?>
    <div class="stat-card" style="padding:1.2rem 1.5rem; margin-bottom:1.5rem;">
        <div class="label" style="margin-bottom:.7rem;">⏱ Quando credita</div>
        <div style="display:flex; gap:1.5rem; align-items:center; flex-wrap:wrap;">
            <label style="display:flex; align-items:center; gap:.5rem;">
                <span style="color:var(--dim); font-size:.85rem;">Cadência</span>
                <select name="cadence" style="padding:.45rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
                    <option value="manual"  <?= $cadence==='manual'?'selected':'' ?>>Manual (só no botão)</option>
                    <option value="weekly"  <?= $cadence==='weekly'?'selected':'' ?>>Semanal</option>
                    <option value="monthly" <?= $cadence==='monthly'?'selected':'' ?>>Mensal</option>
                </select>
            </label>
            <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
                <input type="checkbox" name="auto" value="1" <?= $auto?'checked':'' ?>>
                <span style="color:var(--bone);">Creditar automático na virada do período</span>
            </label>
        </div>
        <p style="color:var(--dim); font-size:.8rem; margin:.7rem 0 0;">
            Auto exige um cron chamando <code>/api/award-rewards.php?token=SEU_AGENT_TOKEN</code> (te passo o comando). Sem cron, use o botão <strong>Premiar agora</strong> abaixo.
            <?php if ($last_awarded > 0): ?><br>Última premiação: <strong style="color:var(--bone);"><?= e(date('d/m/Y H:i', $last_awarded)) ?></strong>.<?php endif; ?>
        </p>
    </div>

    <?php $tabsCfg = $rewards['tabs'] ?? []; $tabVisV = function($k) use ($tabsCfg){ return !array_key_exists($k,$tabsCfg) || !empty($tabsCfg[$k]); }; ?>
    <div class="stat-card" style="padding:1.2rem 1.5rem; margin-bottom:1.5rem;">
        <div class="label" style="margin-bottom:.5rem;">👁 Abas visíveis no /ranking</div>
        <p style="color:var(--dim); font-size:.82rem; margin:0 0 .9rem;">Desmarque pra <strong>esconder a aba inteira</strong> do ranking público (não tem a ver com premiação). Ex: servidor sem sistema de zumbi esconde "Zumbis"; quem não quer expor "quem mais gastou" esconde "Investimento".</p>
        <div style="display:flex; flex-wrap:wrap; gap:1rem;">
            <label style="display:flex; align-items:center; gap:.4rem; cursor:pointer;"><input type="checkbox" name="tab_invest" value="1" <?= $tabVisV('invest')?'checked':'' ?>> Investimento <span style="color:var(--dim);">(quem mais gastou)</span></label>
            <?php foreach ($categories as $key => $label): ?>
                <label style="display:flex; align-items:center; gap:.4rem; cursor:pointer;"><input type="checkbox" name="tab_<?= e($key) ?>" value="1" <?= $tabVisV($key)?'checked':'' ?>> <?= e($label) ?></label>
            <?php endforeach; ?>
        </div>
        <p style="color:var(--dim); font-size:.78rem; margin:.7rem 0 0;">As abas de gameplay precisam do CFTools ligado pra aparecer. "Investimento" é dado do site (sempre disponível).</p>
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

<div class="stat-card" style="margin-top:2rem; border-left:3px solid var(--moss);">
    <div class="label">🏆 Premiar agora</div>
    <p style="color:var(--dim); font-size:.85rem; margin:.6rem 0 1rem;">
        Credita o top atual de cada categoria habilitada <strong>imediatamente</strong>, no período <code><?= e($period_label) ?></code>.
        É seguro clicar mais de uma vez: cada posição/período só é paga <strong>uma vez</strong> (sem crédito duplo).
        <?php if ($awarded_period): ?><br>⚠ O período <code><?= e($period_label) ?></code> já teve premiação - clicar de novo não credita de novo (só posições ainda não pagas).<?php endif; ?>
    </p>
    <form method="POST" action="/admin/rewards/award-now" onsubmit="return confirm('Creditar as moedas do top atual agora? As posições já pagas neste período são ignoradas.');">
        <?= \App\Csrf::field() ?>
        <button type="submit" class="btn" <?= $cftools_on ? '' : 'disabled title="CFTools off"' ?>>🏆 Premiar agora</button>
    </form>
</div>

<?php if (!empty($history)): ?>
<div class="stat-card" style="margin-top:1.5rem;">
    <div class="label">Histórico de premiações (últimas 20)</div>
    <table class="admin-table" data-nofilter style="margin-top:.8rem;">
        <thead><tr>
            <th>Quando</th><th>Período</th><th>Categoria</th><th>Posição</th><th>Jogador</th><th>Moedas</th>
        </tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr>
                <td class="mono dim"><?= e(date('d/m H:i', strtotime((string)$h['created_at']))) ?></td>
                <td><code style="font-size:.75rem;"><?= e($h['period_label']) ?></code></td>
                <td><?= e($categories[$h['category']] ?? $h['category']) ?></td>
                <td><?= (int)$h['place'] ?>º</td>
                <td><a href="/player/<?= e($h['steam_id']) ?>" style="color:var(--bone);"><?= e($h['player_name'] ?: $h['steam_id']) ?></a></td>
                <td style="color:var(--hazard);font-weight:600;">+<?= (int)$h['coins'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php \App\View::endSection(); ?>
