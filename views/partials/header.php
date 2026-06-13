<?php /** @var array $config */ ?>
<header class="site-header">
    <div class="container header-inner">

        <?php $siteName = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'TECPLAY'); ?>
        <a class="brand" href="/">
            <img src="<?= asset('img/logo_semfundo_small.png') ?>" alt="<?= e($siteName) ?>" width="44" height="44" decoding="async">
            <span class="brand-name"><?= e($siteName) ?></span>
        </a>

        <nav>
            <ul class="nav-main">
                <li><a href="/"><?= e(__('nav.home')) ?></a></li>
                <li><a href="/shop"><?= e(__('nav.shop')) ?></a></li>
                <li><a href="/caixas">Caixas</a></li>
                <?php if (\App\Servers::isMulti()): ?>
                    <li><a href="/servidores"><?= e(__('nav.servers') ?: 'Servidores') ?></a></li>
                <?php endif; ?>
                <li><a href="/galeria"><?= e(__('nav.gallery') ?: 'Galeria') ?></a></li>
                <li><a href="/ranking"><?= e(__('nav.ranking') ?: 'Ranking') ?></a></li>
                <li><a href="/rules"><?= e(__('nav.rules')) ?></a></li>
                <?php $discord = $config['settings']['discord_invite'] ?? ''; ?>
                <?php if ($discord): ?>
                    <li><a href="<?= e($discord) ?>" target="_blank" rel="noopener"><?= e(__('nav.discord')) ?></a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="header-actions">
            <?php if (($config['show_language_select'] ?? true)): ?>
                <?php
                $flags = [
                    'pt-br' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 42"><rect width="60" height="42" fill="#009c3b"/><polygon points="30,4 56,21 30,38 4,21" fill="var(--hazard)"/><circle cx="30" cy="21" r="8" fill="#002776"/><path d="M22 22 Q30 18 38 22" stroke="#fff" stroke-width="1.2" fill="none"/></svg>',
                    'en-us' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 42"><rect width="60" height="42" fill="var(--rust)"/><g fill="#fff"><rect y="3.23" width="60" height="3.23"/><rect y="9.69" width="60" height="3.23"/><rect y="16.15" width="60" height="3.23"/><rect y="22.62" width="60" height="3.23"/><rect y="29.08" width="60" height="3.23"/><rect y="35.54" width="60" height="3.23"/></g><rect width="24" height="22.62" fill="#192f5d"/></svg>',
                ];
                $current = locale();
                ?>
                <div class="lang-dropdown" data-lang-dropdown>
                    <button type="button" class="lang-trigger" aria-haspopup="listbox" aria-expanded="false" aria-label="Idioma">
                        <span class="lang-flag-small"><?= $flags[$current] ?? '' ?></span>
                        <span class="lang-code"><?= strtoupper(explode('-', $current)[0]) ?></span>
                        <svg width="10" height="10" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 9 L1 4 h10 z"/></svg>
                    </button>
                    <div class="lang-menu" role="listbox">
                        <?php foreach (['pt-br' => 'PT-BR', 'en-us' => 'EN-US'] as $code => $label): ?>
                            <a class="lang-option <?= $current === $code ? 'active' : '' ?>"
                               href="<?= e(lang_url($code)) ?>"
                               role="option"
                               aria-selected="<?= $current === $code ? 'true' : 'false' ?>">
                                <span class="lang-flag-small"><?= $flags[$code] ?></span>
                                <span><?= $label ?></span>
                                <?php if ($current === $code): ?><span class="lang-check">✓</span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php $steamUser = \App\SteamAuth::user(); ?>
            <?php if ($steamUser): ?>
                <a href="/my-purchases" class="user-pill" title="Minhas compras">
                    <?php $steamName = $steamUser['display_name'] ?? 'Steam User'; ?>
                    <?php if (!empty($steamUser['avatar'])): ?>
                        <img src="<?= e($steamUser['avatar']) ?>" alt="<?= e('Avatar de ' . $steamName) ?>" class="user-pill-avatar"
                             loading="lazy" referrerpolicy="no-referrer"
                             onerror="this.outerHTML='<span class=\'user-pill-avatar user-pill-avatar-fallback\' aria-hidden=\'true\'>&#9881;</span>'">
                    <?php else: ?>
                        <span class="user-pill-avatar user-pill-avatar-fallback" aria-hidden="true">⚙</span>
                    <?php endif; ?>
                    <span class="user-pill-name" title="<?= e($steamName) ?>"><?= e($steamName) ?></span>
                </a>
                <a href="/auth/logout" class="logout-mini" title="Sair">✕</a>
            <?php else: ?>
                <a href="/auth/steam" class="btn btn-steam" title="Login com Steam">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.4rem;"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.72 4.01 10.5 9.39 11.7l3.11-6.7H12c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5v.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5V12c0-2.21-1.79-4-4-4s-4 1.79-4 4 1.79 4 4 4h.14c.86 1.44 2.43 2.41 4.23 2.49L12 23.99C18.63 24 24 18.63 24 12S18.63 0 12 0z"/></svg>
                    Steam
                </a>
            <?php endif; ?>

            <!-- Hamburger (mobile only) -->
            <button class="hamburger" id="hamburger" aria-label="Menu" aria-controls="mobile-drawer" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>

    </div>
</header>

<!-- Mobile drawer (slide-in) -->
<div class="drawer-backdrop" id="drawer-backdrop"></div>
<aside class="mobile-drawer" id="mobile-drawer" aria-hidden="true">
    <ul>
        <li><a href="/" data-close><?= e(__('nav.home')) ?></a></li>
        <li><a href="/shop" data-close><?= e(__('nav.shop')) ?></a></li>
        <li><a href="/caixas" data-close>Caixas</a></li>
        <?php if (\App\Servers::isMulti()): ?>
            <li><a href="/servidores" data-close><?= e(__('nav.servers') ?: 'Servidores') ?></a></li>
        <?php endif; ?>
        <li><a href="/galeria" data-close><?= e(__('nav.gallery') ?: 'Galeria') ?></a></li>
        <li><a href="/ranking" data-close><?= e(__('nav.ranking') ?: 'Ranking') ?></a></li>
        <li><a href="/rules" data-close><?= e(__('nav.rules')) ?></a></li>
        <?php $discord = $config['settings']['discord_invite'] ?? ''; ?>
        <?php if ($discord): ?>
            <li><a href="<?= e($discord) ?>" target="_blank" rel="noopener" data-close><?= e(__('nav.discord')) ?></a></li>
        <?php endif; ?>
    </ul>

    <div class="mobile-drawer-cta">
        <a href="/shop" class="btn" data-close><?= e(__('hero.cta')) ?></a>
        <a href="/page/connect" class="btn btn-outline" data-close><?= e(__('hero.cta_alt')) ?></a>
    </div>

    <!-- Footer compacto do drawer mobile: links rápidos + copyright.
         O seletor de idiomas já está no header (dropdown PT-BR ao lado do hambúrguer),
         então não duplica aqui — usa o espaço pra info útil. -->
    <div class="mobile-drawer-foot">
        <nav class="mobile-drawer-links">
            <a href="/page/terms" data-close><?= e(__('footer.terms')) ?></a>
            <a href="/page/privacy" data-close><?= e(__('footer.privacy')) ?></a>
            <a href="/page/faq" data-close><?= e(__('footer.faq')) ?></a>
        </nav>
        <div class="mobile-drawer-copy">
            &copy; <?= date('Y') ?> <?= e($config['settings']['site_name'] ?? $config['site_name'] ?? 'TECPLAY') ?>
        </div>
    </div>
</aside>
