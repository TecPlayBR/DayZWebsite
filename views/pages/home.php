<?php /** @var array $config */ ?>
<?php \App\View::extend('layouts.main'); ?>

<?php
// SEO: title/description otimizados pra home (keywords BR DayZ). Settings podem
// override (seo_home_title / seo_home_description) — senão monta default com
// nome do servidor + tagline. View::with() propaga pro layout main.php.
$siteName    = $config['settings']['site_name'] ?? $config['site_name'] ?? 'DayZ';
$tagline     = $config['settings']['site_tagline'] ?? $config['site_tagline'] ?? '';
$seoTitle    = ($config['settings']['seo_home_title'] ?? '')
    ?: "Servidor DayZ BR — {$siteName} | PVP Hardcore, Loja & Comunidade";
$seoDesc     = ($config['settings']['seo_home_description'] ?? '')
    ?: ($tagline ?: "Servidor DayZ brasileiro com PVP hardcore, loja de moedas via PIX, eventos semanais e comunidade ativa. Entre no {$siteName}.");
\App\View::with('title',       $seoTitle);
\App\View::with('description', $seoDesc);
?>

<?php \App\View::section('content'); ?>

<!-- ============ ANÚNCIOS ============ -->
<?php if (!empty($announcements)): ?>
    <div class="announcements-strip" data-announcements>
        <?php foreach ($announcements as $a): ?>
            <div class="announcement announcement-<?= e($a['kind']) ?>" data-announcement-id="<?= (int)$a['id'] ?>">
                <div class="announcement-content">
                    <strong class="announcement-title"><?= e($a['title']) ?></strong>
                    <?php if (!empty($a['body'])): ?>
                        <span class="announcement-body"><?= e($a['body']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($a['cta_url']) && !empty($a['cta_label'])): ?>
                    <a href="<?= e($a['cta_url']) ?>" class="announcement-cta"><?= e($a['cta_label']) ?> →</a>
                <?php endif; ?>
                <button type="button" class="announcement-close" aria-label="Fechar este aviso" title="Fechar até o próximo login">×</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ============ HERO ============ -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// <?= e(__('hero.kicker')) ?></span>

        <?php
        // Quebra o title em duas linhas, sendo a segunda em accent
        $title = __('hero.title');
        $parts = explode('.', $title, 2);
        $first = trim($parts[0] ?? $title);
        $second = trim($parts[1] ?? '');
        ?>
        <h1 class="hero-title">
            <?= e($first) ?>.<br>
            <span class="accent"><?= e($second ?: '') ?></span>
        </h1>

        <?php
        // Subkicker SEO: keyword "servidor DayZ Brasil" no markup pro Google capturar
        // sem queimar o impacto criativo do H1. Lang-driven pra cliente custom.
        $subkicker = __('hero.subkicker');
        if ($subkicker && $subkicker !== 'hero.subkicker'):
        ?>
            <p class="hero-subkicker"><?= $subkicker /* permite <strong> via lang */ ?></p>
        <?php endif; ?>

        <p class="hero-subtitle"><?= e(__('hero.subtitle')) ?></p>

        <div class="hero-actions">
            <a href="#shop" class="btn"><?= e(__('hero.cta')) ?></a>
            <a href="/page/connect" class="btn btn-outline"><?= e(__('hero.cta_alt')) ?></a>
        </div>
    </div>

    <!-- Wipe Countdown (se configurado) -->
    <?php
    $wipeAt = $config['settings']['next_wipe_at'] ?? '';
    $wipeLabel = $config['settings']['wipe_label'] ?? 'Próximo wipe';
    $wipeTs = $wipeAt ? strtotime($wipeAt) : 0;
    if ($wipeTs && $wipeTs > time()):
    ?>
        <div class="hero-wipe" data-wipe-target="<?= $wipeTs ?>" aria-live="polite">
            <span class="wipe-label"><?= e($wipeLabel) ?></span>
            <span class="wipe-time" data-wipe-countdown>—</span>
        </div>
    <?php endif; ?>

    <!-- Status do servidor (live via BattleMetrics API)
         quando offline, mostra "voltando em breve"
         em vez de Offline cru + CTA Discord. Rank BM só aparece se <= bm_rank_threshold
         (default 500) — não queima credibilidade com rank ruim. -->
    <?php
    $ss = $server_status ?? ['configured' => false];
    $bmRankMax    = (int)($config['settings']['bm_rank_threshold'] ?? 500);
    $discordUrl   = $config['settings']['social_discord'] ?? '';
    $showRank     = !empty($ss['rank']) && (int)$ss['rank'] > 0 && (int)$ss['rank'] <= $bmRankMax;
    ?>
    <?php if (!empty($ss['configured'])): ?>
        <div class="hero-status hero-status-<?= $ss['online'] ? 'online' : 'offline' ?>" aria-live="polite">
            <span class="dot"></span>
            <?php if ($ss['online']): ?>
                <span><?= e(__('hero.status_online')) ?></span>
                <span style="color: var(--dim);">&middot;</span>
                <span><strong><?= (int)$ss['players'] ?></strong>/<?= (int)$ss['max'] ?: 60 ?> <?= e(__('hero.status_players')) ?></span>
                <?php if ($showRank): ?>
                    <span style="color: var(--dim);">&middot;</span>
                    <span title="Ranking BattleMetrics" style="color: var(--hazard);">#<?= (int)$ss['rank'] ?></span>
                <?php endif; ?>
            <?php else: ?>
                <span><?= e(__('hero.status_voltando')) ?></span>
                <?php if (!empty($discordUrl)): ?>
                    <span style="color: var(--dim);">&middot;</span>
                    <a href="<?= e($discordUrl) ?>" target="_blank" rel="noopener" style="color: var(--moss); font-weight: 600;"><?= e(__('hero.status_avise_discord')) ?></a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="/server-status" style="margin-left: 0.6rem; color: var(--dim); font-size: 0.75rem;"><?= e(__('hero.status_detalhes')) ?></a>
        </div>
    <?php endif; ?>

    <!-- Social proof: stats agregados (jogadores cadastrados, compras semana, online agora).
         Esconde sozinho se controller não passou home_stats. -->
    <?php
    $hs       = $home_stats ?? null;
    $onlineNow = !empty($ss['configured']) && $ss['online'] ? (int)$ss['players'] : null;
    $hasAnyStat = $hs && ((int)($hs['players_total'] ?? 0) > 0 || (int)($hs['purchases_week'] ?? 0) > 0);
    if ($hasAnyStat):
    ?>
        <div class="hero-stats" aria-label="Estatísticas da comunidade">
            <?php if ($onlineNow !== null): ?>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= $onlineNow ?></span>
                    <span class="hero-stat-label"><?= e(__('hero_stats.playing_now')) ?></span>
                </div>
            <?php endif; ?>
            <?php if ((int)$hs['players_total'] > 0): ?>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= (int)$hs['players_total'] ?></span>
                    <span class="hero-stat-label"><?= e(__('hero_stats.registered')) ?></span>
                </div>
            <?php endif; ?>
            <?php if ((int)$hs['purchases_week'] > 0): ?>
                <div class="hero-stat">
                    <span class="hero-stat-num"><?= (int)$hs['purchases_week'] ?></span>
                    <span class="hero-stat-label"><?= e(__('hero_stats.week_sales')) ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<style>
.hero-subkicker {
    color: var(--bone);
    font-family: var(--font-mono);
    font-size: 0.92rem;
    letter-spacing: 0.03em;
    margin: 0.4rem 0 1.2rem;
    opacity: 0.85;
}
.hero-subkicker strong { color: var(--hazard); font-weight: 700; }
.hero-stats {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 1.8rem;
    padding: 1rem 1.5rem;
    background: rgba(0,0,0,0.45);
    border: 1px solid var(--border);
    border-radius: 4px;
    max-width: 720px;
    margin-left: auto; margin-right: auto;
}
.hero-stat { display: flex; flex-direction: column; align-items: center; min-width: 100px; }
.hero-stat-num {
    font-family: var(--font-display);
    font-size: 2rem;
    color: var(--hazard);
    line-height: 1;
}
.hero-stat-label {
    color: var(--dim);
    font-family: var(--font-mono);
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-top: 0.3rem;
    text-align: center;
}
@media (max-width: 540px) {
    .hero-stats { gap: 1rem; padding: 0.7rem 0.8rem; }
    .hero-stat-num { font-size: 1.5rem; }
    .hero-stat-label { font-size: 0.65rem; }
}
</style>

<!-- ============ MURAL DE VENDAS AO VIVO (opcional via settings) ============ -->
<?php if ((int)($config['settings']['live_purchases_enabled'] ?? 0)): ?>
<section class="live-purchases" id="live-purchases" hidden aria-live="polite">
    <div class="container">
        <div class="live-purchases-strip">
            <span class="live-purchases-icon" title="ao vivo">●</span>
            <span class="live-purchases-label"><?= e(__('live_purchases.label')) ?></span>
            <div class="live-purchases-rail" id="live-purchases-rail"></div>
        </div>
    </div>
</section>
<style>
.live-purchases {
    background: linear-gradient(90deg, rgba(74,93,58,0.08), rgba(193,68,14,0.04));
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 0.65rem 0;
}
.live-purchases[hidden] { display: none !important; }
.live-purchases-strip {
    display: flex; align-items: center; gap: 1rem;
    font-family: var(--font-mono);
    font-size: 0.82rem;
    color: var(--bone);
}
.live-purchases-icon {
    color: var(--moss);
    font-size: 0.8rem;
    animation: live-pulse 1.6s ease-in-out infinite;
}
@keyframes live-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
.live-purchases-label {
    color: var(--dim);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.7rem;
    white-space: nowrap;
}
.live-purchases-rail {
    flex: 1;
    overflow: hidden;
    position: relative;
    height: 1.5em;
}
.live-purchase-item {
    position: absolute; inset: 0;
    display: flex; align-items: center; gap: 0.5rem;
    opacity: 0;
    transform: translateY(8px);
    transition: opacity .4s, transform .4s;
}
.live-purchase-item.show { opacity: 1; transform: translateY(0); }
.live-purchase-icon { font-size: 1.1rem; }
.live-purchase-name { color: var(--hazard); font-weight: 700; }
.live-purchase-meta { color: var(--dim); }
.live-purchase-coins { color: var(--bone); font-weight: 700; }
.live-purchase-ago { color: var(--dim); margin-left: auto; font-size: 0.75rem; }
</style>
<script>
(function() {
    const section = document.getElementById('live-purchases');
    const rail = document.getElementById('live-purchases-rail');
    if (!section || !rail) return;

    let items = [];
    let idx = 0;
    let currentEl = null;

    function fmtAgo(s) {
        if (s < 60)   return 'agora';
        if (s < 3600) return Math.floor(s / 60)   + 'min atrás';
        if (s < 86400)return Math.floor(s / 3600) + 'h atrás';
        return Math.floor(s / 86400) + 'd atrás';
    }
    function renderItem(it) {
        const el = document.createElement('div');
        el.className = 'live-purchase-item';
        const price = it.price ? ` <span class="live-purchase-meta">— R$ ${it.price.toFixed(2).replace('.', ',')}</span>` : '';
        el.innerHTML =
            `<span class="live-purchase-icon">${it.icon || '🪙'}</span>` +
            `<span class="live-purchase-name">${it.name}</span>` +
            ` <span class="live-purchase-meta">comprou</span> ` +
            `<span class="live-purchase-coins">${it.coins} moedas</span>` +
            ` <span class="live-purchase-meta">(${it.package})</span>${price}` +
            `<span class="live-purchase-ago">${fmtAgo(it.ago_secs)}</span>`;
        return el;
    }
    function rotate() {
        if (!items.length) return;
        const next = renderItem(items[idx % items.length]);
        rail.appendChild(next);
        requestAnimationFrame(() => next.classList.add('show'));
        if (currentEl) {
            currentEl.classList.remove('show');
            const old = currentEl;
            setTimeout(() => old.remove(), 500);
        }
        currentEl = next;
        idx++;
        // Incrementa o ago_secs local pra não ficar "agora" eterno
        items.forEach(i => i.ago_secs += 4);
    }
    async function fetchItems() {
        try {
            const r = await fetch('/api/recent-purchases.json');
            const d = await r.json();
            if (!d.enabled || !Array.isArray(d.items) || !d.items.length) {
                section.hidden = true; return;
            }
            items = d.items;
            section.hidden = false;
            if (!currentEl) rotate();
        } catch (e) { /* silent */ }
    }
    fetchItems();
    setInterval(rotate, 4000);
    setInterval(fetchItems, 60000); // refresh do dataset a cada 1min
})();
</script>
<?php endif; ?>

<!-- ============ TESTIMONIALS (Social Proof) — reviews REAIS aprovadas ============
     Vem de /admin/reviews (rating >= 4 + approved=1). Esconde sozinho se vazio. -->
<?php if (!empty($home_reviews)): ?>
<section class="section section-testimonials" id="testimonials">
    <div class="container">
        <div class="section-header">
            <h2><?= e(__('testimonials.title')) ?></h2>
            <p><?= e(__('testimonials.subtitle')) ?> <?= e(__('testimonials.see_all')) ?> <a href="/depoimentos" style="color: var(--hazard);">/depoimentos</a>.</p>
        </div>

        <div class="testimonials-grid">
            <?php foreach ($home_reviews as $r):
                $name = $r['display_name'] ?? __('profile.fallback_name');
                $rating = (int)$r['rating'];
                $roleKey = $r['source'] === 'purchase' ? 'testimonials.verified' : 'testimonials.member';
            ?>
                <figure class="testimonial">
                    <div class="testimonial-stars" aria-label="<?= $rating ?> / 5">
                        <?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) ?>
                    </div>
                    <blockquote class="testimonial-text">
                        <span class="testimonial-quote">"</span>
                        <?= e($r['body']) ?>
                    </blockquote>
                    <figcaption class="testimonial-meta">
                        <span class="testimonial-avatar testimonial-avatar-letter"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                        <span class="testimonial-author">
                            <strong><?= e($name) ?></strong>
                            <em><?= e(__($roleKey)) ?></em>
                        </span>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<style>
.section-testimonials {
    background: linear-gradient(180deg, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.6) 100%);
    border-top: 1px solid var(--border);
}
.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}
.testimonial {
    background: var(--bg-1);
    border: 1px solid var(--border);
    padding: 1.6rem 1.5rem;
    margin: 0;
    position: relative;
}
.testimonial-text {
    color: var(--bone);
    font-size: 0.95rem;
    line-height: 1.55;
    margin: 0 0 1.2rem;
    font-style: italic;
}
.testimonial-quote {
    color: var(--hazard);
    font-family: var(--font-display);
    font-size: 2rem;
    line-height: 0;
    vertical-align: -0.4rem;
    margin-right: 0.2rem;
}
.testimonial-stars {
    color: var(--hazard);
    font-size: 1rem;
    letter-spacing: 0.1em;
    margin-bottom: 0.8rem;
    text-shadow: 0 0 6px var(--hazard-border);
}
.testimonial-meta { display: flex; align-items: center; gap: 0.75rem; font-style: normal; }
.testimonial-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: var(--bg-2);
    border: 1px solid var(--border);
    object-fit: cover;
    display: inline-flex; align-items: center; justify-content: center;
    color: var(--hazard);
    font-family: var(--font-display);
    font-size: 1.1rem;
}
.testimonial-avatar-letter { background: rgba(193,68,14,0.18); }
.testimonial-author { display: flex; flex-direction: column; line-height: 1.3; }
.testimonial-author strong { color: var(--bone); font-size: 0.9rem; }
.testimonial-author em { color: var(--dim); font-size: 0.78rem; font-style: normal; }
</style>
<?php endif; ?>

<!-- ============ FEATURES ============ -->
<section class="section section-bg-2" id="features">
    <div class="container">
        <div class="section-header">
            <h2><?= e(__('features.title')) ?></h2>
            <p><?= e(__('features.subtitle')) ?></p>
        </div>

        <div class="features-grid">
            <?php
            $featureIcons = [
                'survival'  => '☣',
                'economy'   => '⛁',
                'pvp'       => '⚔',
                'community' => '✦',
            ];
            foreach (['survival', 'economy', 'pvp', 'community'] as $key):
                $item = __('features.items.' . $key . '.title');
                $text = __('features.items.' . $key . '.text');
            ?>
                <div class="feature">
                    <div class="feature-icon"><?= $featureIcons[$key] ?></div>
                    <h3><?= e($item) ?></h3>
                    <p><?= e($text) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ SHOP TEASER (3 pacotes destaque) ============ -->
<section class="section section-bg-3" id="shop">
    <div class="container">
        <div class="section-header">
            <h2><?= e(__('shop.title')) ?></h2>
            <p><?= e(__('shop.subtitle')) ?></p>
        </div>

        <div class="features-grid" style="margin-bottom: 2rem;">
            <?php
            // Teaser: 3 pacotes — controller já entrega featured primeiro.
            $teaser = array_slice($packages ?? [], 0, 3);
            foreach ($teaser as $pkg):
                $coinsBase  = (int)$pkg['coins'];
                $coinsBonus = ($bonus_enabled ?? 1) ? (int)$pkg['bonus_coins'] : 0;
                $coinsTotal = $coinsBase + $coinsBonus;
                $featured   = (int)$pkg['featured'];
                // Preço com promo (mesma lógica da loja)
                $originalPrice = (float)$pkg['price_brl'];
                $finalPrice    = $originalPrice;
                $discountPct   = 0;
                if (!empty($promo_coupon)) {
                    [$d, $finalPrice] = \App\Coupon::applyDiscount($promo_coupon, $originalPrice);
                    if ($originalPrice > 0) $discountPct = round(($d / $originalPrice) * 100);
                }
            ?>
                <div class="feature home-pack <?= $featured ? 'home-pack-featured' : '' ?>">
                    <?php if ($featured && !empty($pkg['ribbon'])): ?>
                        <div class="home-pack-ribbon"><?= e($pkg['ribbon']) ?></div>
                    <?php endif; ?>

                    <?php if ($coinsBonus > 0 && !empty($pkg['bonus_badge'])): ?>
                        <div class="home-pack-badge"><?= e($pkg['bonus_badge']) ?></div>
                    <?php elseif (!empty($pkg['badge'])): ?>
                        <div class="home-pack-badge"><?= e($pkg['badge']) ?></div>
                    <?php endif; ?>

                    <div class="home-pack-icon"><?= e($pkg['icon'] ?? '🪙') ?></div>
                    <h3><?= e($pkg['name']) ?></h3>

                    <div class="home-pack-coins-total"><?= $coinsTotal ?></div>
                    <div class="home-pack-coins-label">moedas</div>
                    <?php if ($coinsBonus > 0): ?>
                        <div class="home-pack-coins-detail">
                            <?= $coinsBase ?> + <span class="bonus">bônus <?= $coinsBonus ?></span>
                        </div>
                    <?php else: ?>
                        <div class="home-pack-coins-detail home-pack-coins-detail-empty">&nbsp;</div>
                    <?php endif; ?>

                    <div class="home-pack-price">
                        <?php if ($discountPct > 0): ?>
                            <span class="home-pack-price-original">R$ <?= number_format($originalPrice, 2, ',', '.') ?></span>
                            <span class="home-pack-price-now">R$ <?= number_format($finalPrice, 2, ',', '.') ?></span>
                            <span class="home-pack-price-off">−<?= $discountPct ?>%</span>
                        <?php else: ?>
                            R$ <?= number_format($originalPrice, 2, ',', '.') ?>
                        <?php endif; ?>
                    </div>

                    <a href="/shop" class="btn home-pack-buy"><?= e(__('shop.buy')) ?></a>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
        .home-pack {
            text-align: center;
            position: relative;
            display: flex;
            flex-direction: column;
            padding: 2.5rem 1.5rem 2rem;
            overflow: visible !important; /* override .feature overflow:hidden — ribbon precisa estourar */
        }
        .home-pack-featured { border-color: var(--hazard); }
        .home-pack-ribbon {
            position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
            background: var(--hazard); color: var(--bg-0);
            padding: 0.3rem 1rem;
            font-family: var(--font-display);
            font-size: 0.75rem; letter-spacing: 0.1em;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.35);
            z-index: 2;
        }
        .home-pack-badge {
            position: absolute; top: 0.7rem; right: 0.7rem;
            background: rgba(74,93,58,0.25);
            color: var(--moss);
            border: 1px solid var(--moss);
            padding: 0.15rem 0.5rem;
            font-family: var(--font-mono);
            font-size: 0.65rem;
            letter-spacing: 0.05em;
        }
        .home-pack-icon { font-size: 2.2rem; margin: 0.5rem 0; }
        .home-pack-coins-total {
            font-family: var(--font-display);
            font-size: 2.4rem;
            color: var(--hazard);
            margin: 0.8rem 0 0.3rem;
            line-height: 1;
        }
        .home-pack-coins-label {
            color: var(--dim);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .home-pack-coins-detail {
            color: var(--dim);
            font-family: var(--font-mono);
            font-size: 0.78rem;
            margin: 0.4rem 0 0.8rem;
        }
        .home-pack-coins-detail .bonus { color: var(--moss); font-weight: 700; }
        .home-pack-coins-detail-empty { visibility: hidden; }
        .home-pack-price {
            font-family: var(--font-display);
            color: var(--rust-2);
            font-size: 1.4rem;
            margin-bottom: 1rem;
            display: flex; align-items: baseline; justify-content: center; gap: 0.5rem;
            flex-wrap: wrap;
        }
        .home-pack-price-original {
            color: var(--dim);
            font-size: 0.95rem;
            text-decoration: line-through;
            font-family: var(--font-mono);
        }
        .home-pack-price-now { color: var(--rust-2); }
        .home-pack-price-off {
            background: var(--rust-2); color: var(--bg-0);
            font-family: var(--font-mono);
            font-size: 0.7rem;
            padding: 0.15rem 0.45rem;
            letter-spacing: 0.05em;
        }
        .home-pack-buy {
            width: 100%; box-sizing: border-box;
            margin-top: auto;
        }
        </style>

        <div style="text-align: center;">
            <a href="/shop" class="btn btn-outline">Ver todos os pacotes →</a>
        </div>
    </div>
</section>

<?php \App\View::endSection(); ?>
