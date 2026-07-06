<?php
/** @var array $config, $groups */
?>
<?php \App\View::with('title', 'Eventos & Sorteios - ' . ($config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor')); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background2.png'); ?>
<?php \App\View::section('content'); ?>
<?php
$card = function(array $ev) use ($config) {
    $img = $ev['image'] ?? '';
    ob_start(); ?>
    <div class="ev-card ev-<?= e($ev['status']) ?>">
        <div class="ev-media">
            <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($ev['title']) ?>" loading="lazy"><?php else: ?><div class="ev-media-ph"><?= $ev['type']==='raffle'?'🎟':'🗓' ?></div><?php endif; ?>
            <span class="ev-badge ev-badge-<?= e($ev['status']) ?>">
                <?= e(['active'=>__('eventos.live'),'upcoming'=>__('eventos.soon'),'ended'=>__('eventos.ended_badge')][$ev['status']]) ?>
            </span>
        </div>
        <div class="ev-body">
            <h3 class="ev-title"><?= e($ev['title']) ?></h3>
            <?php if (!empty($ev['description'])): ?><p class="ev-desc"><?= e($ev['description']) ?></p><?php endif; ?>
            <?php if (!empty($ev['prize'])): ?><div class="ev-prize">🏆 <?= e($ev['prize']) ?></div><?php endif; ?>
            <?php if (!empty($ev['starts_at'])): ?>
                <div class="ev-when">🕒 <?= e(date('d/m/Y H:i', strtotime((string)$ev['starts_at']))) ?><?= !empty($ev['ends_at']) ? ' → ' . e(date('d/m H:i', strtotime((string)$ev['ends_at']))) : '' ?></div>
            <?php endif; ?>
            <?php if ($ev['status'] !== 'ended'):
                $evDisc = ($config['settings']['social_discord'] ?? '') ?: ($config['settings']['discord_invite'] ?? '');
            ?>
                <div style="margin-top:0.8rem; border-top:1px solid var(--border); padding-top:0.7rem;">
                    <p style="font-size:0.8rem; color:var(--dim); margin:0 0 0.5rem;"><?= e(__('eventos.how_to_join', [], 'Como participar: conecte ao servidor e jogue. Quanto mais tempo online, mais chances.')) ?></p>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <a href="/shop" class="btn" style="padding:0.3rem 0.8rem; font-size:0.78rem;">🪙 <?= e(__('eventos.cta_shop', [], 'Comprar moedas')) ?></a>
                        <?php if ($evDisc): ?><a href="<?= e($evDisc) ?>" target="_blank" rel="noopener" class="btn btn-outline" style="padding:0.3rem 0.8rem; font-size:0.78rem;">📢 Discord</a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($ev['status'] === 'ended' && !empty($ev['winner_name'])): ?>
                <div class="ev-winner">🥇 <?= e(__('eventos.winner')) ?> <?php if (!empty($ev['winner_steam_id'])): ?><a href="/player/<?= e($ev['winner_steam_id']) ?>"><?= e($ev['winner_name']) ?></a><?php else: ?><strong><?= e($ev['winner_name']) ?></strong><?php endif; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
};
$hasAny = $groups['active'] || $groups['upcoming'] || $groups['ended'];
?>

<section class="hero" style="min-height:32vh;padding-bottom:1.5rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.55) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background2.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// <?= e(__('eventos.kicker')) ?></span>
        <h1 class="hero-title" style="font-size:clamp(1.8rem,4vw,2.6rem);"><?= e(__('eventos.title_1')) ?> <span class="accent"><?= e(__('eventos.title_2')) ?></span></h1>
        <p style="color:var(--dim);max-width:600px;"><?= e(__('eventos.subtitle')) ?></p>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container">
        <?php if (!$hasAny): ?>
            <div style="text-align:center;padding:4rem 1rem;color:var(--dim);">
                <div style="font-size:3rem;opacity:0.4;">🗓</div>
                <p><?= e(__('eventos.empty')) ?></p>
            </div>
        <?php else: ?>
            <?php foreach ([['active','🟢 ' . __('eventos.sec_active')],['upcoming','🔵 ' . __('eventos.sec_upcoming')],['ended','⚫ ' . __('eventos.sec_ended')]] as [$k,$label]): ?>
                <?php if (!empty($groups[$k])): ?>
                    <h2 class="ev-section"><?= $label ?> <span>(<?= count($groups[$k]) ?>)</span></h2>
                    <div class="ev-grid">
                        <?php foreach ($groups[$k] as $ev) echo $card($ev); ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<style>
.ev-section { display:flex; align-items:center; gap:.5rem; font-family:var(--font-display); color:var(--bone); font-size:1.2rem; margin:2.2rem 0 1rem; border-bottom:2px solid var(--rust); padding-bottom:.5rem; }
.ev-section span { color:var(--dim); font-family:var(--font-mono); font-size:0.9rem; }
.ev-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.3rem; margin-bottom:1rem; }
.ev-card { background:linear-gradient(180deg,var(--bg-2),var(--bg-1)); border:1px solid var(--border); border-radius:8px; overflow:hidden; display:flex; flex-direction:column; transition:transform .2s,border-color .2s,box-shadow .2s; }
.ev-card:hover { transform:translateY(-4px); border-color:var(--hazard); box-shadow:0 10px 26px rgba(0,0,0,0.5); }
.ev-active { border-left:3px solid var(--moss); }
.ev-ended { opacity:0.82; }
.ev-media { position:relative; height:150px; background:var(--bg-0); display:flex; align-items:center; justify-content:center; }
.ev-media img { width:100%; height:100%; object-fit:cover; }
.ev-media-ph { font-size:3.5rem; }
.ev-badge { position:absolute; top:0.6rem; right:0.6rem; font-family:var(--font-mono); font-size:0.65rem; letter-spacing:0.08em; padding:0.2rem 0.55rem; border-radius:3px; color:#fff; }
.ev-badge-active { background:var(--moss); box-shadow:0 0 10px rgba(74,157,91,0.6); }
.ev-badge-upcoming { background:#3b7ddd; }
.ev-badge-ended { background:#555; }
.ev-body { padding:1rem 1.1rem 1.2rem; display:flex; flex-direction:column; gap:0.4rem; }
.ev-title { font-family:var(--font-display); color:var(--bone); font-size:1.05rem; letter-spacing:0.03em; }
.ev-desc { font-size:0.85rem; color:var(--dim); }
.ev-prize { color:var(--hazard); font-weight:600; font-size:0.9rem; }
.ev-when { font-size:0.8rem; color:var(--dim); font-family:var(--font-mono); }
.ev-winner { font-size:0.85rem; color:var(--moss); margin-top:0.2rem; }
.ev-winner a { color:var(--moss); }
</style>

<?php \App\View::endSection(); ?>
