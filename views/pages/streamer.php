<?php
/** @var array $config; @var ?array $streamer; @var array $photos; @var array $videos;
 *  @var ?array $steam_user; @var ?string $my_streamer_code; @var bool $affiliate_on */
$siteName = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'Servidor');
?>
<?php \App\View::with('title', ($streamer ? $streamer['name'] : 'Streamer') . ' - ' . $siteName); ?>
<?php \App\View::with('description', $streamer ? ('Apoie ' . $streamer['name'] . ' no ' . $siteName . '. Fotos, canal e apoio direto.') : 'Streamer'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="container" style="padding:7rem 0 3rem; max-width:960px;">
<?php if (!$streamer): ?>
    <div style="text-align:center; padding:3rem 0;">
        <h1 style="color:var(--bone);">Streamer não encontrado</h1>
        <p style="color:var(--dim);">Esse código de streamer não existe ou está inativo.</p>
        <a href="/" class="btn btn-outline">Voltar</a>
    </div>
<?php else:
    $aff = $_GET['aff'] ?? '';
    $flash = [
        'ok'       => ['moss',   'Pronto! Agora você apoia ' . e($streamer['name']) . '. 🎉'],
        'switched' => ['moss',   'Trocado! Agora você apoia ' . e($streamer['name']) . '.'],
        'already'  => ['dim',    'Você já apoia ' . e($streamer['name']) . '.'],
        'blocked'  => ['hazard', 'Você já apoia outro streamer (vínculo fixo - fale com a staff).'],
        'invalid'  => ['hazard', 'Código inválido.'],
    ][$aff] ?? null;
    $alreadyMine = $my_streamer_code && strcasecmp($my_streamer_code, $streamer['code']) === 0;
?>
    <?php if ($flash): ?>
        <div style="margin-bottom:1.4rem; padding:0.8rem 1rem; border-radius:8px; border:1px solid var(--border);
                    border-left:3px solid var(--<?= $flash[0] ?>); color:var(--bone); background:var(--bg-2);">
            <?= $flash[1] ?>
        </div>
    <?php endif; ?>

    <div style="display:flex; gap:1.5rem; align-items:center; flex-wrap:wrap; margin-bottom:1.8rem;">
        <?php if (!empty($streamer['avatar_url'])): ?>
            <img src="<?= e($streamer['avatar_url']) ?>" alt="<?= e($streamer['name']) ?>"
                 style="width:120px; height:120px; border-radius:14px; object-fit:cover; border:2px solid var(--hazard);">
        <?php endif; ?>
        <div style="flex:1; min-width:240px;">
            <span style="color:var(--hazard); font-weight:700; letter-spacing:.1em; font-size:.8rem;">// STREAMER PARCEIRO</span>
            <h1 style="color:var(--bone); margin:.2rem 0 .4rem; font-size:2rem;">🎮 <?= e($streamer['name']) ?></h1>
            <?php if (!empty($streamer['bio'])): ?>
                <p style="color:var(--dim); margin:0; line-height:1.6;"><?= nl2br(e($streamer['bio'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex; gap:.8rem; flex-wrap:wrap; margin-bottom:2rem;">
        <?php if (!empty($streamer['channel_url'])): ?>
            <a href="<?= e($streamer['channel_url']) ?>" target="_blank" rel="noopener" class="btn">📺 Ver o canal</a>
        <?php endif; ?>
        <?php if ($affiliate_on): ?>
            <?php if ($alreadyMine): ?>
                <span class="btn btn-outline" style="cursor:default;">✓ Você já apoia</span>
            <?php elseif (!$steam_user): ?>
                <a href="/auth/steam" class="btn btn-outline">Entrar pra apoiar</a>
            <?php else: ?>
                <form method="POST" action="/apoiar-streamer" style="margin:0;">
                    <?= \App\Csrf::field() ?>
                    <input type="hidden" name="affiliate_code" value="<?= e($streamer['code']) ?>">
                    <input type="hidden" name="back" value="/streamer/<?= e(strtolower($streamer['code'])) ?>">
                    <button type="submit" class="btn">❤ Apoiar <?= e($streamer['name']) ?></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($photos): ?>
        <h2 style="color:var(--bone); font-size:1.2rem; margin:0 0 1rem;">Galeria</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:1rem; margin-bottom:2rem;">
            <?php foreach ($photos as $ph): ?>
                <a href="<?= e($ph) ?>" target="_blank" rel="noopener">
                    <img src="<?= e($ph) ?>" alt="<?= e($streamer['name']) ?>" loading="lazy"
                         style="width:100%; aspect-ratio:16/10; object-fit:cover; border-radius:10px; border:1px solid var(--border);">
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($videos): ?>
        <h2 style="color:var(--bone); font-size:1.2rem; margin:0 0 1rem;">Vídeos</h2>
        <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:.5rem;">
            <?php foreach ($videos as $v): ?>
                <li><a href="<?= e($v) ?>" target="_blank" rel="noopener" style="color:var(--hazard);">▶ <?= e($v) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>
</section>

<?php \App\View::endSection(); ?>
