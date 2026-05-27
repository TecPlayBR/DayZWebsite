<?php /** @var array $config */ ?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#050608">
    <title><?= e($title ?? ($config['site_name'] ?? 'DayZ Server')) ?></title>
    <meta name="description" content="<?= e($description ?? ($config['site_tagline'] ?? '')) ?>">

    <link rel="icon" type="image/png" href="<?= asset('img/logo.png') ?>">

    <?php
    // ============ Meta tags sociais (OG/Twitter) + canonical ============
    $siteName  = $config['settings']['site_name'] ?? $config['site_name'] ?? 'DayZ Server';
    $pageTitle = $title ?? $siteName;
    $pageDesc  = $description ?? ($config['settings']['site_tagline'] ?? $config['site_tagline'] ?? 'Sobreviva. Construa. Domine. A apocalipse não espera.');
    // URL absoluta: site_url do config + path atual
    $siteUrl   = rtrim($config['site_url'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
    $pagePath  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $pageUrl   = $siteUrl . $pagePath;
    // Imagem social: $og_image > settings.og_image > background.png
    $ogImage   = $og_image ?? ($config['settings']['og_image'] ?? null) ?? asset('img/background.png');
    if (!preg_match('#^https?://#', $ogImage)) $ogImage = $siteUrl . $ogImage;
    ?>
    <meta name="author" content="<?= e($siteName) ?>">
    <link rel="canonical" href="<?= e($pageUrl) ?>">

    <!-- Open Graph (Facebook, Discord, WhatsApp, LinkedIn) -->
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:type"      content="<?= e($og_type ?? 'website') ?>">
    <meta property="og:locale"    content="<?= e(str_replace('-', '_', locale())) ?>">
    <meta property="og:url"       content="<?= e($pageUrl) ?>">
    <meta property="og:title"     content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:image"     content="<?= e($ogImage) ?>">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e($pageTitle) ?>">

    <!-- Twitter card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDesc) ?>">
    <meta name="twitter:image"       content="<?= e($ogImage) ?>">

    <!-- JSON-LD: identidade do site pra Google entender -->
    <script type="application/ld+json"><?= json_encode([
        '@context'    => 'https://schema.org',
        '@type'       => 'WebSite',
        'name'        => $siteName,
        'url'         => $siteUrl,
        'description' => $pageDesc,
        'inLanguage'  => locale(),
        'image'       => $ogImage,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php if (!empty($jsonld)): // página filha pode adicionar JSON-LD próprio (ex: Product no shop) ?>
        <script type="application/ld+json"><?= json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>

    <!-- Fonts (display=swap evita FOIT — texto aparece com fallback enquanto a fonte custom carrega) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;600;700&family=VT323&display=swap" rel="stylesheet" media="all">

    <!-- Preload do hero pra LCP (Largest Contentful Paint). Pulado em páginas sem hero. -->
    <?php if (!($skip_hero_preload ?? false)): ?>
        <link rel="preload" as="image" href="<?= asset('img/background.png') ?>" fetchpriority="high">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
</head>
<body>

<a href="#main" class="skip-link">Pular para o conteúdo</a>

<?php partial('partials.header', ['config' => $config]); ?>

<main id="main">
    <?= \App\View::yield('content') ?>
</main>

<?php partial('partials.footer', ['config' => $config]); ?>

<!-- Cookie banner LGPD -->
<div id="cookie-banner" class="cookie-banner" hidden role="dialog" aria-label="Aviso de cookies">
    <div class="cookie-banner-text">
        🍪 <strong>Este site usa cookies essenciais</strong> pra funcionar (sessão de login, idioma).
        Não usamos cookies de rastreamento de terceiros.
        <a href="/page/privacy">Saiba mais →</a>
    </div>
    <button type="button" id="cookie-banner-ok" class="btn-mini" style="flex-shrink: 0;">Entendi</button>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
