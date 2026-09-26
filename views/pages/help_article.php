<?php /** @var array $config, $a, $siblings */ ?>
<?php $hsite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'; ?>
<?php \App\View::with('title', $a['title'] . ' - Ajuda ' . $hsite); ?>
<?php \App\View::with('description', $a['summary'] ?: ('Guia: ' . $a['title'] . ' no ' . $hsite . ' DayZ.')); ?>
<?php
// SEO: dado estruturado Article (Google entende como guia/tutorial).
$_hbase = rtrim($config['site_url'] ?? '', '/');
$_hImg  = !empty($a['image']) ? (preg_match('#^https?://#', $a['image']) ? $a['image'] : $_hbase . $a['image']) : null;
\App\View::with('og_type', 'article');
\App\View::with('jsonld', array_filter([
    '@context'         => 'https://schema.org',
    '@type'            => 'Article',
    'headline'         => $a['title'],
    'description'      => $a['summary'] ?: ('Guia: ' . $a['title']),
    'inLanguage'       => 'pt-BR',
    'articleSection'   => \App\Help::catLabel($a['category']),
    'image'            => $_hImg,
    'dateModified'     => !empty($a['updated_at']) ? date('c', strtotime($a['updated_at'])) : null,
    'mainEntityOfPage' => $_hbase ? ($_hbase . '/ajuda/' . $a['slug']) : null,
    'author'           => ['@type' => 'Organization', 'name' => $hsite],
    'publisher'        => ['@type' => 'Organization', 'name' => $hsite],
]));
?>
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
            <?php // So carrega o YouTube quando o jogador clica: antes disso o Google nao recebe nada (Politica, item 5). ?>
            <div class="help-video">
                <button type="button" class="help-video-btn" data-yt-src="<?= e($embed) ?>?autoplay=1" data-yt-title="<?= e($a['title']) ?>">
                    <span class="help-video-play" aria-hidden="true">▶</span>
                    <span class="help-video-rotulo"><?= e(__('ajuda.video_btn')) ?></span>
                    <small class="help-video-aviso"><?= e(__('ajuda.video_aviso')) ?></small>
                </button>
            </div>
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
.help-video-btn { position:absolute; inset:0; width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.6rem; padding:1rem; border:0; cursor:pointer; font:inherit; color:var(--bone); background:linear-gradient(135deg,var(--bg-2),var(--bg-0)); }
.help-video-play { width:60px; height:60px; border-radius:50%; background:rgba(0,0,0,.55); border:2px solid var(--hazard); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.3rem; transition:transform .2s, background .2s; }
.help-video-btn:hover .help-video-play, .help-video-btn:focus-visible .help-video-play { transform:scale(1.08); background:var(--hazard); }
.help-video-rotulo { font-weight:600; }
.help-video-aviso { color:var(--dim); max-width:40ch; text-align:center; font-size:.8rem; line-height:1.45; }
@media (max-width:480px) { .help-video-btn { gap:.4rem; padding:.6rem; } .help-video-play { width:44px; height:44px; font-size:1rem; } .help-video-aviso { font-size:.75rem; line-height:1.35; } }
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
