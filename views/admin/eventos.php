<?php
/** @var array $config, $events; @var ?array $edit */
$e = $edit ?: [];
$v  = fn($k, $d = '') => (string) ($e[$k] ?? $d);
$dt = fn($k) => !empty($e[$k]) ? date('Y-m-d\TH:i', strtotime((string) $e[$k])) : '';
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
<?php if (($_GET['err'] ?? '') === 'title'): ?>
    <div class="stat-card" style="margin-bottom:1.2rem; border-left:3px solid var(--danger-border); background:var(--danger-overlay);"><strong>Não salvou.</strong> O título é obrigatório.</div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <div class="label"><?= $edit ? 'Editar evento' : 'Novo evento' ?></div>
    <p class="adm-legenda"><span class="adm-req">*</span> obrigatório</p>
    <form method="POST" action="/admin/eventos/save" enctype="multipart/form-data" class="adm-grid-2" data-adm-form>
        <?= \App\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
        <?= admin_campo(['label' => 'Título', 'name' => 'title', 'value' => $v('title'), 'required' => true, 'hint' => 'Como aparece na lista e no destaque da home.']) ?>
        <?= admin_campo(['label' => 'Slug (URL)', 'name' => 'slug', 'value' => $v('slug'), 'hint' => 'Vazio = gerado do título. Só letras, números e hífen.']) ?>
        <?= admin_campo(['label' => 'Tipo', 'name' => 'type', 'type' => 'select', 'value' => $v('type', 'event'), 'options' => ['event' => 'Evento', 'raffle' => 'Sorteio']]) ?>
        <?= admin_campo(['label' => 'Prêmio', 'name' => 'prize', 'value' => $v('prize'), 'hint' => 'Ex.: 5000 moedas + AKM. Aparece no card.']) ?>
        <div class="adm-span-2"><?= admin_campo_imagem(['label' => 'Imagem do evento', 'name' => 'image', 'value' => $v('image')]) ?></div>
        <div class="adm-span-2"><?= admin_campo(['label' => 'Descrição', 'name' => 'description', 'type' => 'textarea', 'rows' => 3, 'value' => $v('description'), 'hint' => 'Regras, horário, como participar. Texto simples.']) ?></div>
        <?= admin_campo(['label' => 'Começa em', 'name' => 'starts_at', 'type' => 'datetime-local', 'value' => $dt('starts_at'), 'hint' => 'Vazio = sem data de início (fica como "em breve").']) ?>
        <?= admin_campo(['label' => 'Termina em', 'name' => 'ends_at', 'type' => 'datetime-local', 'value' => $dt('ends_at'), 'hint' => 'Depois desta data o evento aparece como encerrado.']) ?>
        <?= admin_campo(['label' => 'Vencedor: SteamID (sorteio)', 'name' => 'winner_steam_id', 'value' => $v('winner_steam_id'), 'placeholder' => '7656119...', 'hint' => '17 dígitos. Preencha quando o sorteio terminar.']) ?>
        <?= admin_campo(['label' => 'Vencedor: nome', 'name' => 'winner_name', 'value' => $v('winner_name')]) ?>
        <label class="adm-check"><input type="checkbox" name="enabled" value="1" <?= !isset($e['enabled']) || $e['enabled'] ? 'checked' : '' ?>> Visível no site</label>
        <?= admin_campo(['label' => 'Ordem', 'name' => 'sort_order', 'type' => 'number', 'value' => (string) (int) ($e['sort_order'] ?? 0), 'hint' => 'Menor aparece primeiro.']) ?>
        <div class="adm-span-2" style="display:flex; gap:0.6rem;">
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
