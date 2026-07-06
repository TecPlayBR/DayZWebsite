<?php
/** @var array $config, $events; @var ?array $edit */
$e = $edit ?: [];
$val = fn($k, $d = '') => e((string)($e[$k] ?? $d));
$dt  = fn($k) => !empty($e[$k]) ? e(date('Y-m-d\TH:i', strtotime((string)$e[$k]))) : '';
?>
<?php $title = 'Eventos'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🗓 Eventos & Sorteios</h1>
        <p>Competições e sorteios que aparecem em <code>/eventos</code> e no destaque da home. O status (próximo / ativo / encerrado) é calculado pelas datas.</p>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert-toast">Salvo!</div><?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <div class="label"><?= $edit ? 'Editar evento' : 'Novo evento' ?></div>
    <form method="POST" action="/admin/eventos/save" style="margin-top:0.8rem;display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <?= \App\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
        <label>Título<input type="text" name="title" value="<?= $val('title') ?>" required style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Slug (URL)<input type="text" name="slug" value="<?= $val('slug') ?>" placeholder="auto se vazio" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Tipo
            <select name="type" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
                <option value="event"  <?= ($e['type'] ?? '')==='event'?'selected':'' ?>>Evento</option>
                <option value="raffle" <?= ($e['type'] ?? '')==='raffle'?'selected':'' ?>>Sorteio</option>
            </select>
        </label>
        <label>Prêmio<input type="text" name="prize" value="<?= $val('prize') ?>" placeholder="ex: 5000 moedas + AKM" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="grid-column:1/3;">Imagem (URL)<input type="text" name="image" value="<?= $val('image') ?>" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="grid-column:1/3;">Descrição<textarea name="description" rows="3" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"><?= $val('description') ?></textarea></label>
        <label>Começa em<input type="datetime-local" name="starts_at" value="<?= $dt('starts_at') ?>" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Termina em<input type="datetime-local" name="ends_at" value="<?= $dt('ends_at') ?>" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Vencedor - SteamID (sorteio)<input type="text" name="winner_steam_id" value="<?= $val('winner_steam_id') ?>" placeholder="7656119..." style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Vencedor - Nome<input type="text" name="winner_name" value="<?= $val('winner_name') ?>" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="display:flex;align-items:center;gap:0.4rem;"><input type="checkbox" name="enabled" value="1" <?= !isset($e['enabled']) || $e['enabled'] ? 'checked' : '' ?>> Visível</label>
        <label>Ordem<input type="number" name="sort_order" value="<?= (int)($e['sort_order'] ?? 0) ?>" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <div style="grid-column:1/3;display:flex;gap:0.6rem;">
            <button type="submit" class="btn"><?= $edit ? 'Salvar' : 'Criar evento' ?></button>
            <?php if ($edit): ?><a href="/admin/eventos" class="btn btn-outline">+ Novo</a><?php endif; ?>
        </div>
    </form>
</div>

<?php if (!empty($events)): ?>
    <table class="admin-table">
        <thead><tr>
            <th>Evento</th><th>Tipo</th><th>Quando</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($events as $ev): $st = \App\Events::status($ev);
            $stTxt = ['active'=>'🟢 Ativo','upcoming'=>'🔵 Em breve','ended'=>'⚫ Encerrado'][$st]; ?>
            <tr<?= (int)$ev['enabled']?'':' style="opacity:0.5;"' ?>>
                <td><strong style="color:var(--bone);"><?= e($ev['title']) ?></strong> <code style="color:var(--dim);font-size:0.72rem;">/<?= e($ev['slug']) ?></code></td>
                <td><?= $ev['type']==='raffle'?'🎟 Sorteio':'🗓 Evento' ?></td>
                <td class="dim"><?= !empty($ev['starts_at'])?e(date('d/m H:i',strtotime((string)$ev['starts_at']))):'-' ?><?= !empty($ev['ends_at'])?' → '.e(date('d/m H:i',strtotime((string)$ev['ends_at']))):'' ?></td>
                <td><?= $stTxt ?></td>
                <td style="text-align:right;white-space:nowrap;">
                    <a href="/admin/eventos/<?= (int)$ev['id'] ?>" class="btn btn-sm">Editar</a>
                    <form method="POST" action="/admin/eventos/<?= (int)$ev['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Excluir este evento?');">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" style="background:none;border:none;color:var(--rust-2);cursor:pointer;">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p style="color:var(--dim);">Nenhum evento ainda. Crie o primeiro acima.</p>
<?php endif; ?>

<?php \App\View::endSection(); ?>
