<?php /** @var array $config, $a, $siblings */ ?>
<?php $hsite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'; ?>
<?php \App\View::with('title', $a['title'] . ' — Ajuda ' . $hsite); ?>
<?php \App\View::with('description', $a['summary'] ?: ('Guia: ' . $a['title'] . ' no ' . $hsite . ' DayZ.')); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background2.png'); ?>
<?php \App\View::section('content'); ?>
<?php $embed = youtube_embed_url($a['video_url'] ?? null); ?>

<section class="hero" style="min-height:24vh;padding-bottom:1.2rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.6) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background2.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// <?= e(mb_strtoupper(\App\Help::catLabel($a['category']))) ?></span>
        <h1 class="hero-title" style="font-size:clamp(1.6rem,4vw,2.4rem);"><?= e($a['title']) ?></h1>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width:780px;">
        <p style="margin-bottom:1.2rem;"><a href="/ajuda" style="color:var(--hazard);text-decoration:none;">← Central de Ajuda</a></p>

        <?php if (!empty($a['summary'])): ?>
            <p style="color:var(--bone);font-size:1.05rem;line-height:1.6;margin-bottom:1.5rem;opacity:.95;"><?= e($a['summary']) ?></p>
        <?php endif; ?>

        <?php if ($embed): ?>
            <div class="help-video"><iframe src="<?= e($embed) ?>" title="<?= e($a['title']) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe></div>
        <?php elseif (!empty($a['video_url'])): ?>
            <p style="color:var(--dim);font-size:.85rem;">🎥 Vídeo: <a href="<?= e($a['video_url']) ?>" target="_blank" rel="noopener" style="color:var(--hazard);">assistir</a></p>
        <?php endif; ?>

        <?php if (!empty($a['image'])): ?>
            <img src="<?= e($a['image']) ?>" alt="<?= e($a['title']) ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;margin:0 0 1.5rem;">
        <?php endif; ?>

        <div class="help-body"><?= $a['body'] ?: '<p style="color:var(--dim);">Conteúdo em breve.</p>' ?></div>

        <?php if (!empty($siblings)): ?>
            <h3 style="font-family:var(--font-display);color:var(--bone);margin:2.5rem 0 1rem;border-top:1px solid var(--border);padding-top:1.5rem;">Veja também</h3>
            <ul class="help-siblings">
                <?php foreach ($siblings as $s): ?>
                    <li><a href="/ajuda/<?= e($s['slug']) ?>">→ <?= e($s['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<style>
.help-video { position:relative; padding-bottom:56.25%; height:0; margin:0 0 1.5rem; border-radius:8px; overflow:hidden; border:1px solid var(--border); }
.help-video iframe { position:absolute; top:0; left:0; width:100%; height:100%; }
.help-body { color:var(--bone); line-height:1.75; font-size:1rem; overflow-wrap:break-word; }
.help-body pre { overflow-x:auto; background:var(--bg-0); padding:.8rem 1rem; border-radius:4px; }
.help-body table { display:block; overflow-x:auto; max-width:100%; }
.help-body h2, .help-body h3 { font-family:var(--font-display); color:var(--bone); margin:1.6rem 0 .7rem; letter-spacing:.02em; }
.help-body p { margin:0 0 1rem; }
.help-body ul, .help-body ol { margin:0 0 1rem 1.4rem; }
.help-body li { margin:.3rem 0; }
.help-body a { color:var(--hazard); }
.help-body img { max-width:100%; border-radius:6px; border:1px solid var(--border); }
.help-body strong { color:#fff; }
.help-body code { background:var(--bg-0); padding:.1rem .4rem; border-radius:3px; font-family:var(--font-mono); font-size:.9em; }
.help-siblings { list-style:none; margin:0; padding:0; }
.help-siblings li { margin:.4rem 0; }
.help-siblings a { color:var(--bone); text-decoration:none; }
.help-siblings a:hover { color:var(--hazard); }
</style>
<?php \App\View::endSection(); ?>
