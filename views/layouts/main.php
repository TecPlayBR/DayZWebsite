<?php /** @var array $config */ ?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? ($config['site_name'] ?? 'DayZ Server')) ?></title>
    <meta name="description" content="<?= e($description ?? ($config['settings']['site_tagline'] ?? $config['site_tagline'] ?? 'Sobreviva. Construa. Domine. A apocalipse não espera.')) ?>">

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
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="googlebot" content="index, follow">
    <meta name="theme-color" content="#0a0405">

    <?php
    // SEO keywords — admin pode override via settings.seo_keywords; senão usa default rico do nicho DayZ BR
    $seoKeywords = $config['settings']['seo_keywords'] ?? '';
    if (empty($seoKeywords)) {
        $seoKeywords = 'servidor dayz brasileiro, servidor dayz br, dayz pvp, dayz hardcore, '
            . 'comprar moedas dayz, pix dayz, dayz raid, dayz base building, dayz comunidade, '
            . 'servidor dayz chernarus, dayz vanilla plus, dayz mod servidor, '
            . 'comunidade dayz br, servidor dayz com loja, anti-cheat dayz, dayz brasil pvp';
    }
    ?>
    <meta name="keywords" content="<?= e($seoKeywords) ?>">

    <!-- Schema.org JSON-LD: GameServer + Organization pra rich snippets do Google -->
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                'name' => $siteName,
                'url' => $siteUrl,
                'logo' => $siteUrl . asset('img/logo.png'),
                'sameAs' => array_values(array_filter([
                    $config['settings']['social_discord']   ?? null,
                    $config['settings']['social_youtube']   ?? null,
                    $config['settings']['social_instagram'] ?? null,
                    $config['settings']['social_tiktok']    ?? null,
                    $config['settings']['social_twitch']    ?? null,
                    $config['settings']['social_x']         ?? null,
                    $config['settings']['social_facebook']  ?? null,
                ])),
            ],
            [
                '@type' => 'VideoGameSeries',
                'name' => $siteName . ' DayZ Server',
                'gameItem' => [
                    '@type' => 'VideoGame',
                    'name' => 'DayZ',
                    'gamePlatform' => 'PC (Steam)',
                ],
                'url' => $siteUrl,
                'description' => $pageDesc,
                'inLanguage' => 'pt-BR',
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>

    <link rel="canonical" href="<?= e($pageUrl) ?>">
    <!-- hreflang: o Google sabe qual versão de idioma servir (pt-br é o padrão / x-default) -->
    <link rel="alternate" hreflang="pt-BR"     href="<?= e($pageUrl) ?>?lang=pt-br">
    <link rel="alternate" hreflang="en-US"     href="<?= e($pageUrl) ?>?lang=en-us">
    <link rel="alternate" hreflang="x-default" href="<?= e($pageUrl) ?>">

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
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    <?php if (!empty($jsonld)): // página filha pode adicionar JSON-LD próprio (ex: Product no shop) ?>
        <script type="application/ld+json"><?= json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    <?php endif; ?>

    <?php
    // AggregateRating Schema.org (Headers de segurança).
    // SO renderiza se houver reviews REAIS aprovadas (Google policy: rating
    // numerico verificado, nao mock). Reviews vem de purchases entregues.
    try {
        if (class_exists('\App\Database')) {
            $agg = \App\Database::fetchOne(
                "SELECT COUNT(*) AS cnt, AVG(rating) AS avg_rating
                   FROM reviews WHERE approved = 1"
            );
            $reviewCount = (int)($agg['cnt'] ?? 0);
            if ($reviewCount > 0):
                $ratingValue = round((float)$agg['avg_rating'], 2);
    ?>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'VideoGameSeries',
        'name'     => $siteName . ' DayZ Server',
        'url'      => $siteUrl,
        'aggregateRating' => [
            '@type'       => 'AggregateRating',
            'ratingValue' => $ratingValue,
            'reviewCount' => $reviewCount,
            'bestRating'  => 5,
            'worstRating' => 1,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
    <?php
            endif;
        }
    } catch (\Throwable $e) { /* DB indisponivel ou tabela reviews ausente: skip */ }
    ?>

    <!-- Fonts: self-hosted . Elimina 780ms render-blocking + ~1300ms
         cadeia de dependencia cross-origin do Google Fonts CSS/woff2.
         Preload das 3 woff2 latin pra paralelizar download antes do CSS parser. -->
    <link rel="preload" as="font" type="font/woff2" href="<?= asset('fonts/inter.woff2') ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= asset('fonts/black-ops-one-400.woff2') ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= asset('fonts/vt323-400.woff2') ?>" crossorigin>
    <link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">

    <!-- Preload do hero pra LCP (Largest Contentful Paint).
         CRITICAL: URL precisa BATER com a do CSS/inline style pro browser reusar.
         aceita $hero_image setado via View::with(). Shop usa background3,
         rules usa background5, etc. Sem isso, preload baixa errado e LCP regride.
         Default home: background.png. -->
    <?php if (!($skip_hero_preload ?? false)):
        $heroImg = $hero_image ?? 'img/background.png';
    ?>
        <link rel="preload" as="image" href="<?= asset($heroImg) ?>" fetchpriority="high">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <?= theme_override_tag() ?>
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
