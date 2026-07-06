<?php
/** @var array $config, $events, $scores; @var ?array $edit */
$e   = $edit ?: [];
$val = fn($k, $d = '') => e((string)($e[$k] ?? $d));
$dt  = fn($k) => !empty($e[$k]) ? e(date('Y-m-d\TH:i', strtotime((string)$e[$k]))) : '';
$inp = 'width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);';
?>
<?php $title = 'Eventos de Clã'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🛡 Eventos de Clã</h1>
        <p>Competições entre clãs (ex: "Mate o máximo de zumbis"). O placar conta só o que rolar <strong>dentro</strong> do evento - foto no início, congela no fim. Aparece na aba <code>/ranking → Clãs</code> (só pra quem tem clã). Só o <strong>líder</strong> inscreve.</p>
    </div>
</div>

<?php if (($_GET['ok'] ?? '') !== ''): ?><div class="alert-toast"><?= ($_GET['ok']==='rewarded') ? '🏆 Premiação creditada aos membros do clã vencedor!' : 'Salvo!' ?></div><?php endif; ?>
<?php if (($_GET['err'] ?? '') !== ''): ?>
    <div class="alert-toast" style="background:var(--danger-overlay);color:var(--text-danger);"><?= e(match($_GET['err']){'title'=>'Informe o título.','dates'=>'Datas inválidas (o fim tem que ser depois do início).','slug'=>'Já existe um evento com esse slug.','csrf'=>'Sessão expirada.','already'=>'Esse evento já foi premiado.','not_frozen'=>'O evento ainda não terminou (precisa congelar primeiro).','no_prize'=>'Defina as moedas de prêmio antes de premiar.','no_winner'=>'Esse evento não tem vencedor.',default=>'Erro.'}) ?></div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <div class="label"><?= $edit ? 'Editar evento de clã' : 'Novo evento de clã' ?></div>
    <form method="POST" action="/admin/clan-events/save" style="margin-top:0.8rem;display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <?= \App\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
        <label>Título<input type="text" name="title" value="<?= $val('title') ?>" required placeholder="Mate o Máximo de Zumbis" style="<?= $inp ?>"></label>
        <label>Slug (URL)<input type="text" name="slug" value="<?= $val('slug') ?>" placeholder="auto se vazio" style="<?= $inp ?>"></label>
        <label>Métrica
            <select name="metric" style="<?= $inp ?>">
                <?php foreach (\App\ClanEvent::METRICS as $mk => $ml): ?>
                    <option value="<?= e($mk) ?>" <?= ($e['metric'] ?? 'kills_infected')===$mk?'selected':'' ?>><?= e($ml) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Prêmio (texto/descrição)<input type="text" name="prize" value="<?= $val('prize') ?>" placeholder="ex: 10.000 moedas + destaque" style="<?= $inp ?>"></label>
        <label>Moedas por membro <small style="color:var(--dim);">(o botão Premiar credita isso a CADA membro do clã vencedor)</small><input type="number" name="prize_coins" min="0" value="<?= (int)($e['prize_coins'] ?? 0) ?>" style="<?= $inp ?>"></label>
        <label style="grid-column:1/3;">Descrição<textarea name="description" rows="3" style="<?= $inp ?>"><?= $val('description') ?></textarea></label>
        <label>Começa em<input type="datetime-local" name="starts_at" value="<?= $dt('starts_at') ?>" required style="<?= $inp ?>"></label>
        <label>Termina em<input type="datetime-local" name="ends_at" value="<?= $dt('ends_at') ?>" required style="<?= $inp ?>"></label>
        <label style="display:flex;align-items:center;gap:0.4rem;"><input type="checkbox" name="enabled" value="1" <?= !isset($e['enabled']) || $e['enabled'] ? 'checked' : '' ?>> Visível</label>
        <label>Ordem<input type="number" name="sort_order" value="<?= (int)($e['sort_order'] ?? 0) ?>" style="<?= $inp ?>"></label>
        <div style="grid-column:1/3;display:flex;gap:0.6rem;">
            <button type="submit" class="btn"><?= $edit ? 'Salvar' : 'Criar evento' ?></button>
            <?php if ($edit): ?><a href="/admin/clan-events" class="btn btn-outline">+ Novo</a><?php endif; ?>
        </div>
    </form>
</div>

<?php if ($edit): ?>
    <?php $ph = \App\ClanEvent::phase($edit); ?>
    <div class="stat-card" style="margin-bottom:1.5rem;">
        <div class="label">Placar - <?= e(\App\ClanEvent::phaseLabel($ph)) ?>
            <?php if ($edit['baseline_taken']): ?> · <span style="color:var(--text-success);">baseline tirado ✓</span><?php endif; ?>
            <?php if (!empty($edit['frozen_at'])): ?> · <span style="color:var(--dim);">congelado em <?= e(date('d/m H:i', strtotime($edit['frozen_at']))) ?></span><?php endif; ?>
        </div>
        <?php if (!empty($edit['winner_name'])): ?>
            <p style="color:var(--hazard);margin:.5rem 0;">🏆 Vencedor: <strong><?= e($edit['winner_name']) ?></strong></p>
            <?php if (!empty($edit['rewarded_at'])): ?>
                <p style="color:var(--text-success);font-size:.9rem;">✓ Premiação já entregue em <?= e(date('d/m H:i', strtotime($edit['rewarded_at']))) ?> (<?= (int)$edit['prize_coins'] ?> moedas por membro).</p>
            <?php elseif (\App\ClanEvent::canReward($edit)): ?>
                <form method="POST" action="/admin/clan-events/<?= (int)$edit['id'] ?>/reward" onsubmit="return confirm('Creditar <?= (int)$edit['prize_coins'] ?> moedas pra CADA membro do clã <?= e(addslashes($edit['winner_name'])) ?>? Não dá pra desfazer.');" style="margin:.6rem 0;">
                    <?= \App\Csrf::field() ?>
                    <button type="submit" class="btn">🏆 Premiar clã vencedor (<?= (int)$edit['prize_coins'] ?> moedas/membro)</button>
                </form>
            <?php elseif ((int)($edit['prize_coins'] ?? 0) <= 0): ?>
                <p style="color:var(--dim);font-size:.82rem;">Defina as <strong>moedas por membro</strong> no form acima pra liberar o botão Premiar.</p>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($scores)): ?>
            <table class="admin-table" data-nofilter style="margin-top:.6rem;">
                <thead><tr><th>#</th><th>Clã</th><th style="text-align:right;"><?= e(\App\ClanEvent::metricLabel($edit['metric'])) ?></th></tr></thead>
                <tbody>
                <?php foreach ($scores as $i => $s): ?>
                    <tr><td><?= $i+1 ?>º</td><td><a href="/clan/<?= (int)$s['clan_id'] ?>" style="color:var(--bone);">[<?= e($s['tag']) ?>] <?= e($s['name']) ?></a></td><td class="mono" style="text-align:right;"><?= number_format((int)$s['score'],0,',','.') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:var(--dim);margin-top:.5rem;"><?= $ph==='scheduled' ? 'Aguardando inscrições e o início do evento.' : 'Nenhum clã inscrito.' ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($events)): ?>
    <table class="admin-table">
        <thead><tr>
            <th>Evento</th><th>Métrica</th><th>Quando</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($events as $ev): $st = \App\ClanEvent::phase($ev);
            $stTxt = ['active'=>'🟢 Acontecendo','scheduled'=>'🔵 Em breve','ended'=>'⚫ Encerrado'][$st] ?? $st; ?>
            <tr<?= (int)$ev['enabled']?'':' style="opacity:0.5;"' ?>>
                <td><strong style="color:var(--bone);"><?= e($ev['title']) ?></strong> <code style="color:var(--dim);font-size:0.72rem;">/<?= e($ev['slug']) ?></code></td>
                <td><?= e(\App\ClanEvent::metricLabel($ev['metric'])) ?></td>
                <td class="dim"><?= e(date('d/m H:i',strtotime((string)$ev['starts_at']))) ?> → <?= e(date('d/m H:i',strtotime((string)$ev['ends_at']))) ?></td>
                <td><?= $stTxt ?></td>
                <td style="text-align:right;white-space:nowrap;">
                    <a href="/admin/clan-events/<?= (int)$ev['id'] ?>" class="btn btn-sm">Editar</a>
                    <form method="POST" action="/admin/clan-events/<?= (int)$ev['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Excluir este evento de clã e todos os dados de placar dele?');">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" style="background:none;border:none;color:var(--rust-2);cursor:pointer;">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p style="color:var(--dim);">Nenhum evento de clã ainda. Crie o primeiro acima.</p>
<?php endif; ?>

<?php \App\View::endSection(); ?>
