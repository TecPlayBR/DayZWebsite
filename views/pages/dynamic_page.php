<?php /** @var array $config, $page */ ?>
<?php
// Escolhe título/body baseado no locale corrente
$lang   = locale();
$title  = ($lang === 'en-us' && !empty($page['title_enus'])) ? $page['title_enus'] : $page['title_ptbr'];
$body   = ($lang === 'en-us' && !empty($page['body_enus'])) ? $page['body_enus'] : $page['body_ptbr'];
// SEO: <title> próprio (nome + servidor) e description tirada do corpo. O $title
// local continua servindo o H1; o View::with é o que vai pro <title> do layout.
$_pgSite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor';
\App\View::with('title', $title . ' — ' . $_pgSite);
\App\View::with('description', mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 0, 155));

// SEO: FAQPage schema (rich snippet de accordion no Google) — só pra página de FAQ,
// que usa <details><summary class="faq-q">Pergunta</summary>Resposta</details>.
if (($page['slug'] ?? '') === 'faq'
    && preg_match_all('/<details[^>]*>\s*<summary[^>]*>(.*?)<\/summary>(.*?)<\/details>/is', $body, $faqM, PREG_SET_ORDER)) {
    $faqItems = [];
    foreach ($faqM as $m) {
        $q = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES));
        $a = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[2]), ENT_QUOTES)));
        if ($q !== '' && $a !== '') {
            $faqItems[] = ['@type' => 'Question', 'name' => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => mb_substr($a, 0, 900)]];
        }
    }
    if ($faqItems) {
        \App\View::with('jsonld', ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqItems]);
    }
}
?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background5.png'); // LCP preload sync ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 40vh;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background5.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// <?= e($page['slug']) ?></span>
        <h1 class="hero-title"><?= e($title) ?></h1>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width: 800px;">
        <article class="page-body">
            <?= $body /* admin escreve HTML — confia */ ?>
        </article>
    </div>
</section>

<style>
.page-body { color: var(--bone); font-size: 1rem; line-height: 1.8; }
.page-body h2 { font-family: var(--font-display); color: var(--rust-2); margin: 2rem 0 1rem; font-size: 1.4rem; letter-spacing: 0.04em; }
.page-body h3 { font-family: var(--font-display); color: var(--bone); margin: 1.5rem 0 0.8rem; font-size: 1.15rem; }
.page-body p  { margin-bottom: 1rem; }
.page-body ul, .page-body ol { margin: 1rem 0 1.5rem 1.5rem; }
.page-body li { margin-bottom: 0.5rem; }
.page-body a  { color: var(--hazard); text-decoration: underline; }
.page-body strong { color: var(--bone); }
.page-body em { color: var(--dim); font-style: italic; }
.page-body code { background: var(--bg-2); padding: 0.15rem 0.5rem; border-radius: 2px; font-family: var(--font-mono); color: var(--hazard); font-size: 0.9em; }
.page-body blockquote { border-left: 3px solid var(--rust); padding-left: 1rem; margin: 1rem 0; color: var(--dim); font-style: italic; }
</style>

<?php \App\View::endSection(); ?>
