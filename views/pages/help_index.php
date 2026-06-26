<?php /** @var array $config, $by_cat */ ?>
<?php $hsite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'; ?>
<?php \App\View::with('title', 'Central de Ajuda — ' . $hsite . ' DayZ'); ?>
<?php \App\View::with('description', 'Guias, tutoriais e tudo que você precisa saber pra jogar no ' . $hsite . ': como conectar, mecânicas, eventos, economia e mais.'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background2.png'); ?>
<?php \App\View::section('content'); ?>
<?php $total = 0; foreach ($by_cat as $arr) $total += count($arr); ?>

<section class="hero" style="min-height:30vh;padding-bottom:1.5rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.55) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background2.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// CENTRAL DE AJUDA</span>
        <h1 class="hero-title">Guia do <span class="accent">Sobrevivente</span></h1>
        <p style="color:var(--dim);max-width:640px;">Tudo o que você precisa pra começar e dominar: como conectar, mecânicas, eventos, economia e mais. Cada início vira uma jornada.</p>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width:1000px;">
        <?php if ($total === 0): ?>
            <p style="text-align:center;color:var(--dim);padding:3rem 0;">Os guias estão sendo preparados — volte em breve. 🚧</p>
        <?php else: ?>
            <?php foreach (\App\Help::CATEGORIES as $catKey => $catLabel): $arts = $by_cat[$catKey] ?? []; if (empty($arts)) continue; ?>
                <h2 class="help-cat-title"><?= e($catLabel) ?></h2>
                <div class="help-grid">
                    <?php foreach ($arts as $a): ?>
                        <a class="help-card" href="/ajuda/<?= e($a['slug']) ?>">
                            <?php if (!empty($a['image'])): ?>
                                <div class="help-card-img" style="background-image:url('<?= e($a['image']) ?>');"></div>
                            <?php elseif (!empty($a['video_url'])): ?>
                                <div class="help-card-img help-card-vid">▶</div>
                            <?php endif; ?>
                            <div class="help-card-body">
                                <h3 class="help-card-title"><?= e($a['title']) ?></h3>
                                <?php if (!empty($a['summary'])): ?><p class="help-card-sum"><?= e($a['summary']) ?></p><?php endif; ?>
                                <?php if (!empty($a['video_url'])): ?><span class="help-card-tag">🎥 com vídeo</span><?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<style>
.help-cat-title { font-family:var(--font-display); color:var(--bone); font-size:1.25rem; letter-spacing:.03em; border-bottom:2px solid var(--rust); padding-bottom:.5rem; margin:2.2rem 0 1.2rem; }
.help-cat-title:first-of-type { margin-top:0; }
.help-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(min(260px,100%),1fr)); gap:1.1rem; margin-bottom:1rem; }
.help-card { display:flex; flex-direction:column; background:var(--bg-1); border:1px solid var(--border); border-radius:8px; overflow:hidden; text-decoration:none; transition:transform .2s, border-color .2s; }
.help-card:hover { transform:translateY(-3px); border-color:var(--hazard); }
.help-card-img { height:120px; background-size:cover; background-position:center; background-color:var(--bg-2); display:flex; align-items:center; justify-content:center; color:var(--hazard); font-size:2rem; }
.help-card-vid { background:linear-gradient(135deg,var(--bg-2),var(--bg-0)); }
.help-card-body { padding:1rem 1.1rem; }
.help-card-title { font-family:var(--font-display); color:var(--bone); font-size:1rem; margin:0 0 .4rem; letter-spacing:.02em; }
.help-card-sum { color:var(--dim); font-size:.83rem; line-height:1.45; margin:0 0 .5rem; }
.help-card-tag { font-size:.72rem; color:var(--hazard); font-family:var(--font-mono); }
</style>
<?php \App\View::endSection(); ?>
