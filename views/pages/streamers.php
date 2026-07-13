<?php
/** @var array $config; @var array $streamers */
$siteName = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'Servidor');
?>
<?php \App\View::with('title', 'Streamers - ' . $siteName); ?>
<?php \App\View::with('description', 'Streamers parceiros do ' . $siteName . '. Apoie seu favorito - suas compras ajudam ele direto.'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="container" style="padding:7rem 0 3rem;">
    <div class="section-header" style="text-align:center; margin-bottom:2rem;">
        <h1 style="color:var(--bone);">🎮 Streamers Parceiros</h1>
        <p style="color:var(--dim);">Apoie um streamer - suas compras ajudam ele direto.</p>
    </div>

    <?php if (empty($streamers)): ?>
        <div style="text-align:center; color:var(--dim); padding:2rem 0;">Nenhum streamer parceiro ainda.</div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1.2rem;">
            <?php foreach ($streamers as $s): ?>
                <a href="/streamer/<?= e(strtolower($s['code'])) ?>"
                   style="display:flex; align-items:center; gap:1rem; background:linear-gradient(180deg,var(--bg-2),var(--bg-1));
                          border:1px solid var(--border); border-left:3px solid var(--hazard); border-radius:10px;
                          padding:1rem 1.1rem; text-decoration:none;">
                    <?php if (!empty($s['avatar_url'])): ?>
                        <img src="<?= e($s['avatar_url']) ?>" alt="<?= e($s['name']) ?>" loading="lazy"
                             style="width:72px; height:72px; border-radius:12px; object-fit:cover; flex:0 0 72px; border:1px solid var(--border);">
                    <?php endif; ?>
                    <div>
                        <div style="font-family:var(--font-display); color:var(--bone); font-size:1.15rem;"><?= e($s['name']) ?></div>
                        <div style="color:var(--hazard); font-size:0.85rem; margin-top:0.2rem;">Ver / Apoiar →</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php \App\View::endSection(); ?>
