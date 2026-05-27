<?php /** @var array $config */ ?>
<?php \App\View::extend('layouts.main'); ?>

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

        <p class="hero-subtitle"><?= e(__('hero.subtitle')) ?></p>

        <div class="hero-actions">
            <a href="#shop" class="btn"><?= e(__('hero.cta')) ?></a>
            <a href="/rules" class="btn btn-outline"><?= e(__('hero.cta_alt')) ?></a>
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

    <!-- Status do servidor (live via BattleMetrics API) -->
    <?php $ss = $server_status ?? ['configured' => false]; ?>
    <?php if (!empty($ss['configured'])): ?>
        <div class="hero-status hero-status-<?= $ss['online'] ? 'online' : 'offline' ?>" aria-live="polite">
            <span class="dot"></span>
            <?php if ($ss['online']): ?>
                <span><?= e(__('hero.status_online')) ?></span>
                <span style="color: var(--dim);">&middot;</span>
                <span><strong><?= (int)$ss['players'] ?></strong>/<?= (int)$ss['max'] ?: 60 ?> <?= e(__('hero.status_players')) ?></span>
                <?php if (!empty($ss['rank'])): ?>
                    <span style="color: var(--dim);">&middot;</span>
                    <span title="Ranking BattleMetrics" style="color: var(--hazard);">#<?= (int)$ss['rank'] ?></span>
                <?php endif; ?>
            <?php else: ?>
                <span><?= e(__('hero.status_offline')) ?></span>
            <?php endif; ?>
            <a href="/server-status" style="margin-left: 0.6rem; color: var(--dim); font-size: 0.75rem;">detalhes →</a>
        </div>
    <?php endif; ?>
</section>

<!-- ============ MURAL DE VENDAS AO VIVO (opcional via settings) ============ -->
<?php if ((int)($config['settings']['live_purchases_enabled'] ?? 0)): ?>
<section class="live-purchases" id="live-purchases" hidden aria-live="polite">
    <div class="container">
        <div class="live-purchases-strip">
            <span class="live-purchases-icon" title="ao vivo">●</span>
            <span class="live-purchases-label">ÚLTIMAS COMPRAS</span>
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
