<?php /** @var array $config, $by_cat */ ?>
<?php $hsite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'; ?>
<?php \App\View::with('title', 'Central de Ajuda - ' . $hsite . ' DayZ'); ?>
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
            <p style="text-align:center;color:var(--dim);padding:3rem 0;">Os guias estão sendo preparados - volte em breve. 🚧</p>
        <?php else: ?>
            <?php
            $ytid = function ($url) {
                if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/|shorts/))([A-Za-z0-9_-]{6,})~', (string) $url, $m)) return $m[1];
                return '';
            };
            $catIcon = ['comecando' => '🧭', 'mecanicas' => '⚙️', 'eventos' => '📅', 'economia' => '💰', 'suporte' => '🛟'];
            ?>
            <?php foreach (\App\Help::CATEGORIES as $catKey => $catLabel): $arts = $by_cat[$catKey] ?? []; if (empty($arts)) continue; ?>
                <h2 class="help-cat-title"><?= e($catLabel) ?></h2>
                <div class="help-grid">
                    <?php foreach ($arts as $a):
                        $img = trim((string) ($a['image'] ?? ''));
                        $vid = $ytid($a['video_url'] ?? '');
                    ?>
                        <a class="help-card" href="/ajuda/<?= e($a['slug']) ?>">
                            <?php if ($img): ?>
                                <div class="help-card-img" style="background-image:url('<?= e($img) ?>');"></div>
                            <?php elseif ($vid): ?>
                                <div class="help-card-img help-card-vid" style="background-image:url('https://img.youtube.com/vi/<?= e($vid) ?>/hqdefault.jpg');"><span class="help-play">▶</span></div>
                            <?php else: ?>
                                <div class="help-card-img help-card-ph"><span><?= $catIcon[$a['category']] ?? '📄' ?></span></div>
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
.help-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(min(260px,100%),1fr)); gap:1.2rem; margin-bottom:1rem; align-items:stretch; }
.help-card { display:flex; flex-direction:column; background:var(--bg-1); border:1px solid var(--border); border-radius:10px; overflow:hidden; text-decoration:none; transition:transform .2s, border-color .2s, box-shadow .2s; }
.help-card:hover { transform:translateY(-4px); border-color:var(--hazard); box-shadow:0 10px 26px rgba(0,0,0,.4); }
/* area de midia SEMPRE presente (padroniza a altura dos titulos) */
.help-card-img { position:relative; height:130px; background-size:cover; background-position:center; background-color:var(--bg-2); display:flex; align-items:center; justify-content:center; border-bottom:2px solid var(--hazard); }
.help-card-vid::before { content:''; position:absolute; inset:0; background:rgba(0,0,0,.35); }
.help-play { position:relative; z-index:1; width:52px; height:52px; border-radius:50%; background:rgba(0,0,0,.55); border:2px solid var(--hazard); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.2rem; padding-left:3px; transition:transform .2s, background .2s; }
.help-card:hover .help-play { transform:scale(1.1); background:var(--hazard); }
.help-card-ph { background:linear-gradient(135deg,var(--bg-2),var(--bg-0)); }
.help-card-ph span { font-size:2.6rem; opacity:.35; filter:grayscale(.2); }
.help-card-body { padding:1rem 1.1rem; display:flex; flex-direction:column; flex-grow:1; }
.help-card-title { font-family:var(--font-display); color:var(--bone); font-size:1.15rem; line-height:1.2; margin:0 0 .45rem; letter-spacing:.02em; transition:color .2s; }
.help-card:hover .help-card-title { color:var(--hazard); }
.help-card-sum { color:var(--dim); font-size:.85rem; line-height:1.5; margin:0 0 .6rem; flex-grow:1; }
.help-card-tag { font-size:.72rem; color:var(--hazard); font-family:var(--font-mono); margin-top:auto; }
</style>
<?php \App\View::endSection(); ?>
