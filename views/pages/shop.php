<?php /** @var array $config, $packages, $wishlist, $servers; @var int $bonus_enabled, $selected_server_id; @var bool $is_logged, $is_multi_server; @var ?array $promo_coupon; @var string $promo_label */ ?>
<?php $steamUser = \App\SteamAuth::user(); ?>
<?php $prefillSteam = $steamUser['steam_id'] ?? ''; ?>
<?php \App\View::extend('layouts.main'); ?>

<?php
// SEO: title/desc da loja com keyword "comprar moedas DayZ" + nome do server.
// View::with() propaga pro layout main.php (sobrescreve qualquer default do controller).
$siteName = $config['settings']['site_name'] ?? $config['site_name'] ?? 'DayZ';
\App\View::with(
    'title',
    ($config['settings']['seo_shop_title'] ?? '')
        ?: "Comprar Moedas DayZ — Loja {$siteName} | PIX, Cartão & Boleto"
);
\App\View::with(
    'description',
    ($config['settings']['seo_shop_description'] ?? '')
        ?: "Compre moedas DayZ pra usar no servidor {$siteName}. Pagamento via PIX, cartão ou boleto."
);
// Hero usa background3 — sinaliza pro layout preloadar o certo (LCP fix)
\App\View::with('hero_image', 'img/background3.png');

// SEO: Schema.org ItemList de Product/Offer pros pacotes — rich snippet de preço no Google.
$shopUrl = rtrim($config['site_url'] ?? (($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '')), '/');
$bonusOn = \App\Settings::getInt('bonus_enabled');
$prodItems = [];
foreach ($packages as $i => $pkg) {
    $coinsTotal = (int)$pkg['coins'] + ($bonusOn ? (int)($pkg['bonus_coins'] ?? 0) : 0);
    $prodItems[] = [
        '@type'    => 'ListItem',
        'position' => $i + 1,
        'item'     => [
            '@type'       => 'Product',
            'name'        => $pkg['name'] . ' — ' . $coinsTotal . ' moedas',
            'description' => 'Pacote de ' . $coinsTotal . ' moedas para o servidor ' . $siteName . ' (DayZ). Entrega automática após o pagamento.',
            'category'    => 'Virtual Game Currency',
            'brand'       => ['@type' => 'Brand', 'name' => $siteName],
            'offers'      => [
                '@type'         => 'Offer',
                'price'         => number_format((float)$pkg['price_brl'], 2, '.', ''),
                'priceCurrency' => 'BRL',
                'availability'  => 'https://schema.org/InStock',
                'url'           => $shopUrl . '/shop',
            ],
        ],
    ];
}
if ($prodItems) {
    \App\View::with('jsonld', [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'Pacotes de moedas — ' . $siteName,
        'itemListElement' => $prodItems,
    ]);
}
?>

<?php \App\View::section('content'); ?>

<?php if (!empty($promo_coupon) && !empty($promo_label)): ?>
    <div class="shop-promo-banner">
        <span class="shop-promo-icon">⚡</span>
        <strong><?= e($promo_label) ?></strong>
        <span class="shop-promo-code">cupom auto-aplicado: <code><?= e($promo_coupon['code']) ?></code></span>
    </div>
<?php endif; ?>

<section class="hero" style="min-height: 50vh; padding-bottom: 2rem;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// <?= e(($config['delivery_active'] ?? false) ? __('shop.subtitle') : __('shop.subtitle_manual', [], locale() === 'en-us' ? 'Pay with PIX, card or boleto. Released after confirmation.' : 'Pague com PIX, boleto ou cartão. Liberação após a confirmação.')) ?></span>
        <h1 class="hero-title"><?= e(__('shop.title')) ?></h1>
    </div>
</section>

<section class="section section-bg-2" id="packs">
    <div class="container">

        <?php if ($is_multi_server): ?>
            <div class="server-selector" id="server-selector">
                <div class="server-selector-label">
                    <span class="server-selector-icon">▣</span>
                    <strong>Servidor de destino:</strong>
                    <small>as moedas chegam no servidor escolhido.</small>
                </div>
                <div class="server-selector-pills">
                    <?php foreach ($servers as $sv): ?>
                        <button type="button"
                                class="server-pill <?= (int)$sv['id'] === $selected_server_id ? 'active' : '' ?>"
                                data-server-id="<?= (int)$sv['id'] ?>"
                                data-server-name="<?= e($sv['name']) ?>">
                            <?= e($sv['name']) ?>
                            <?php if (!empty($sv['map'])): ?>
                                <small><?= e($sv['map']) ?></small>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>


        <div class="packs-grid">
            <?php foreach ($packages as $pkg):
                $coinsBase  = (int)$pkg['coins'];
                $coinsBonus = $bonus_enabled ? (int)$pkg['bonus_coins'] : 0;
                $coinsTotal = $coinsBase + $coinsBonus;
                $perks      = json_decode($pkg['perks_json'] ?? '[]', true) ?: [];
                $bonusPerks = json_decode($pkg['bonus_perks_json'] ?? '[]', true) ?: [];
                $featured   = (int)$pkg['featured'];
            ?>
            <div class="pack-card <?= $featured ? 'pack-featured' : '' ?>">
                <?php if ($featured && !empty($pkg['ribbon'])): ?>
                    <div class="pack-ribbon"><?= e($pkg['ribbon']) ?></div>
                <?php endif; ?>

                <?php $isWished = !empty($wishlist[$pkg['id']]); ?>
                <button type="button" class="pack-wish <?= $isWished ? 'wished' : '' ?>"
                        data-wish="<?= e($pkg['id']) ?>"
                        title="<?= $isWished ? 'Remover da lista de desejos' : 'Adicionar à lista de desejos' ?>"
                        aria-pressed="<?= $isWished ? 'true' : 'false' ?>"
                        aria-label="<?= e(($isWished ? 'Remover ' : 'Favoritar ') . $pkg['name'] . ' na lista de desejos') ?>"><span aria-hidden="true"><?= $isWished ? '♥' : '♡' ?></span></button>

                <?php if ($coinsBonus > 0 && !empty($pkg['bonus_badge'])): ?>
                    <div class="pack-badge"><?= e($pkg['bonus_badge']) ?></div>
                <?php elseif (!empty($pkg['badge'])): ?>
                    <div class="pack-badge"><?= e($pkg['badge']) ?></div>
                <?php endif; ?>

                <div class="pack-icon">
                    <?php if (!empty($pkg['image'])): ?>
                        <img class="pack-img" src="<?= preg_match('#^https?://#i', $pkg['image']) ? e($pkg['image']) : asset('img/packages/' . $pkg['image']) ?>" alt="<?= e($pkg['name']) ?> — pacote de moedas DayZ" width="200" height="200" loading="lazy" decoding="async">
                    <?php else: ?>
                        <?= e($pkg['icon'] ?? '🪙') ?>
                    <?php endif; ?>
                </div>
                <h3 class="pack-name"><?= e($pkg['name']) ?></h3>

                <div class="pack-coins">
                    <span class="pack-coins-total"><?= $coinsTotal ?></span>
                    <span class="pack-coins-label">moedas</span>
                    <?php if ($coinsBonus > 0): ?>
                        <div class="pack-coins-detail">
                            <?= $coinsBase ?> + <span class="bonus">bônus <?= $coinsBonus ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <ul class="pack-perks">
                    <?php foreach ($perks as $p): ?>
                        <li><?= e($p) ?></li>
                    <?php endforeach; ?>
                    <?php if ($coinsBonus > 0): foreach ($bonusPerks as $bp): ?>
                        <li class="perk-bonus"><?= e($bp) ?></li>
                    <?php endforeach; endif; ?>
                </ul>

                <?php
                $originalPrice = (float)$pkg['price_brl'];
                $finalPrice = $originalPrice;
                $discountPct = 0;
                if (!empty($promo_coupon)) {
                    [$d, $finalPrice] = \App\Coupon::applyDiscount($promo_coupon, $originalPrice);
                    if ($originalPrice > 0) {
                        $discountPct = round(($d / $originalPrice) * 100);
                    }
                }
                ?>
                <div class="pack-price">
                    <?php if ($discountPct > 0): ?>
                        <span class="pack-price-original">R$ <?= number_format($originalPrice, 2, ',', '.') ?></span>
                        <span class="pack-price-now">R$ <?= number_format($finalPrice, 2, ',', '.') ?></span>
                        <span class="pack-price-off">−<?= $discountPct ?>%</span>
                    <?php else: ?>
                        R$ <?= number_format($originalPrice, 2, ',', '.') ?>
                    <?php endif; ?>
                </div>

                <form method="POST" action="/shop/checkout" class="pack-form" data-shop-form>
                    <?= \App\Csrf::field() ?>
                    <input type="hidden" name="package_id" value="<?= e($pkg['id']) ?>">
                    <input type="hidden" name="server_id" value="<?= (int)$selected_server_id ?>" data-server-input>
                    <input type="hidden" name="terms_accepted" value="1">
                    <input type="hidden" name="coupon_code" value="" data-coupon-flag>
                    <?php if ($prefillSteam): ?>
                        <input type="hidden" name="steam_id" value="<?= e($prefillSteam) ?>">
                        <div class="pack-steam-locked" title="Login Steam ativo">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px;margin-right:4px;"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.72 4.01 10.5 9.39 11.7l3.11-6.7H12c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5v.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5V12c0-2.21-1.79-4-4-4s-4 1.79-4 4 1.79 4 4 4h.14c.86 1.44 2.43 2.41 4.23 2.49L12 23.99C18.63 24 24 18.63 24 12S18.63 0 12 0z"/></svg>
                            <?= e(substr($prefillSteam, 0, 10) . '...') ?>
                        </div>
                    <?php else: ?>
                        <input type="text" name="steam_id" placeholder="SteamID64 (17 dígitos) *" pattern="7656119[0-9]{10}" required aria-required="true" maxlength="17" class="pack-input">
                    <?php endif; ?>
                    <button type="submit" class="btn pack-buy" aria-label="<?= e(__('shop.buy')) ?> <?= e($pkg['name']) ?> — <?= $coinsTotal ?> moedas por R$ <?= number_format($finalPrice, 2, ',', '.') ?>"><?= e(__('shop.buy')) ?></button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>


        <p class="shop-note">
            🔒 <strong>Mercado Pago</strong><?= ($config['delivery_active'] ?? false) ? __('shop.auto_delivery') : __('shop.delivery_manual', [], locale() === 'en-us' ? ' — in-game coin delivery is handled by the server (Agent/Bot) after confirmation.' : ' — a entrega das moedas no jogo é feita pelo servidor (Agent/Bot) após a confirmação.') ?><br>
            <?= __('shop.steamid_warning') ?>
        </p>

        <p class="shop-crosssell">
            🎁 <?= e(__('shop.with_coins', [], 'Com suas moedas, abra também as')) ?> <a href="/caixas"><?= e(__('shop.try_boxes', [], 'Caixas de Loot')) ?> →</a>
        </p>
    </div>
</section>

<style>
/* Cards de pacotes — escopo local */
.packs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}
.shop-crosssell { text-align:center; margin:1.5rem 0 0; color:var(--dim); font-size:0.92rem; }
.shop-crosssell a { color:var(--hazard); font-weight:600; text-decoration:none; }
.shop-crosssell a:hover { text-decoration:underline; }
.pack-card {
    background: linear-gradient(180deg, var(--bg-2) 0%, var(--bg-1) 100%);
    border: 1px solid var(--border);
    padding: 2rem 1.5rem;
    text-align: center;
    position: relative;
    transition: transform .25s, border-color .2s;
    /* Coluna flex: força todos os cards a terem mesma altura na grid e
       empurra o form (botão) sempre pro rodapé independente do conteúdo */
    display: flex;
    flex-direction: column;
}
.packs-grid {
    /* align-items: stretch é o default, mas garantido aqui — cards ficam
       todos com mesma altura via height: 100% no grid item */
    align-items: stretch;
}
.pack-card:hover {
    transform: translateY(-4px);
    border-color: rgba(193,68,14,0.4);
}
.pack-featured {
    border-color: var(--hazard);
    box-shadow: 0 0 30px var(--hazard-border);
    transform: scale(1.02);
}
.pack-ribbon {
    position: absolute;
    top: -1px; left: 50%;
    transform: translateX(-50%);
    background: var(--hazard);
    color: var(--bg-0);
    padding: 0.3rem 1.2rem;
    font-family: var(--font-display);
    font-size: 0.75rem;
    letter-spacing: 0.1em;
}
.pack-badge {
    position: absolute;
    top: 0.8rem; right: 0.8rem;
    background: var(--rust);
    color: var(--bone);
    padding: 0.25rem 0.6rem;
    font-family: var(--font-mono);
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}
.pack-wish {
    position: absolute;
    top: 0.6rem; left: 0.6rem;
    background: rgba(0,0,0,0.7);
    border: 1px solid var(--border);
    color: var(--dim);
    width: 32px; height: 32px;
    cursor: pointer;
    font-size: 1.2rem; line-height: 1;
    border-radius: 2px;
    transition: color .2s, border-color .2s, transform .15s;
}
.pack-wish:hover { transform: scale(1.1); color: var(--rust-2); border-color: var(--rust-2); }
.pack-wish.wished { color: var(--rust-2); border-color: var(--rust-2); background: var(--danger-overlay); }
.pack-icon { font-size: 2.5rem; margin: 0.5rem 0 1rem; filter: drop-shadow(0 0 8px var(--hazard-border)); min-height: 56px; display:flex; align-items:center; justify-content:center; }
.pack-img { width: 120px; height: 120px; object-fit: contain; filter: drop-shadow(0 8px 16px rgba(0,0,0,0.55)); transition: transform .25s; animation: pack-float 3.6s ease-in-out infinite; }
.pack-card:hover .pack-img { transform: scale(1.07); }
@keyframes pack-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
@media (prefers-reduced-motion: reduce){ .pack-img{ animation:none; } }
.pack-name { font-family: var(--font-display); color: var(--bone); font-size: 1rem; letter-spacing: 0.06em; margin-bottom: 0.8rem; }

.pack-coins {
    margin: 1rem 0 1.5rem;
    min-height: 95px; /* altura fixa pra alinhar mesmo cards sem bonus_detail */
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.pack-coins-total { font-family: var(--font-display); font-size: 3rem; color: var(--hazard); line-height: 1; }
.pack-coins-label { display: block; font-size: 0.85rem; color: var(--dim); margin-top: 0.2rem; text-transform: uppercase; letter-spacing: 0.1em; }
.pack-coins-detail { margin-top: 0.4rem; font-family: var(--font-mono); font-size: 0.85rem; color: var(--dim); }
.pack-coins-detail .bonus { color: var(--moss); }

.pack-perks {
    list-style: none;
    padding: 0;
    margin: 0 0 1.5rem;
    text-align: left;
    font-size: 0.85rem;
    color: var(--bone);
    /* CRITICAL pra alinhamento: ocupa todo espaço livre, empurrando o
       preço+form pro rodapé. Cards com poucas perks ficam com espaço
       vazio embaixo da lista; cards com muitas, expandem aqui. */
    flex: 1;
    min-height: 100px;
}
.pack-perks li {
    padding: 0.3rem 0 0.3rem 1.4rem;
    position: relative;
    border-bottom: 1px solid var(--border);
}
.pack-perks li::before {
    content: '▸';
    position: absolute;
    left: 0;
    color: var(--rust);
}
.pack-perks li.perk-bonus { color: var(--moss); }
.pack-perks li.perk-bonus::before { color: var(--moss); content: '+'; font-weight: 700; }

.pack-price {
    font-family: var(--font-display);
    font-size: 1.8rem;
    color: var(--rust-2);
    margin-bottom: 1.2rem;
    text-shadow: 1px 1px 0 rgba(0,0,0,0.6);
    display: flex; flex-direction: column; align-items: center; gap: 0.2rem;
}
.pack-price-original {
    font-size: 0.95rem; color: var(--dim);
    text-decoration: line-through; text-decoration-thickness: 2px;
    text-decoration-color: var(--rust-2);
}
.pack-price-now { color: var(--moss); }
.pack-price-off {
    background: var(--rust); color: var(--bone);
    font-family: var(--font-display);
    font-size: 0.75rem; letter-spacing: 0.1em;
    padding: 0.15rem 0.5rem; border-radius: 2px;
    display: inline-block;
}

.shop-promo-banner {
    background: linear-gradient(90deg, var(--rust) 0%, var(--rust-2) 50%, var(--rust) 100%);
    color: var(--bone);
    padding: 0.6rem 1.2rem;
    text-align: center;
    margin-top: 70px;
    font-size: 0.95rem;
    display: flex; align-items: center; justify-content: center; gap: 0.8rem; flex-wrap: wrap;
    animation: promo-glow 3s ease-in-out infinite;
}
@keyframes promo-glow {
    0%, 100% { box-shadow: 0 0 0 rgba(231,76,60,0); }
    50%      { box-shadow: 0 4px 24px var(--danger-border); }
}
.shop-promo-icon { font-size: 1.3rem; }
.shop-promo-code { font-family: var(--font-mono); font-size: 0.8rem; opacity: 0.9; }
.shop-promo-code code { background: rgba(0,0,0,0.3); padding: 0.1rem 0.4rem; border-radius: 2px; }

.pack-form { display: flex; flex-direction: column; gap: 0.6rem; }
.pack-input {
    padding: 0.65rem 0.8rem;
    background: var(--bg-0);
    border: 1px solid var(--border);
    color: var(--bone);
    font-family: var(--font-mono);
    font-size: 0.85rem;
    text-align: center;
    letter-spacing: 0.05em;
}
.pack-input:focus { outline: none; border-color: var(--rust); }
.pack-steam-locked {
    background: rgba(102,192,244,0.08);
    border: 1px solid rgba(102,192,244,0.3);
    color: #66c0f4;
    padding: 0.55rem 0.8rem;
    font-family: var(--font-mono);
    font-size: 0.8rem;
    text-align: center;
    letter-spacing: 0.05em;
}
.pack-buy { width: 100%; }
.pack-input.invalid { border-color: var(--rust-2) !important; box-shadow: 0 0 0 2px var(--danger-overlay, rgba(231,57,70,0.2)); }
.btn.loading { position: relative; color: transparent !important; pointer-events: none; opacity: 0.85; }
.btn.loading::after {
    content: ''; position: absolute; top: 50%; left: 50%;
    width: 16px; height: 16px; margin: -8px 0 0 -8px;
    border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff;
    border-radius: 50%; animation: btn-spin 0.7s linear infinite;
}
@keyframes btn-spin { to { transform: rotate(360deg); } }

.shop-note {
    text-align: center;
    color: var(--dim);
    font-size: 0.85rem;
    max-width: 700px;
    margin: 0 auto;
    line-height: 1.8;
}

/* Server selector (multi-server) */
.server-selector {
    background: linear-gradient(135deg, var(--hazard-overlay), rgba(74,93,58,0.06));
    border: 1px solid var(--border);
    border-left: 3px solid var(--hazard);
    padding: 1rem 1.25rem;
    margin: 0 auto 2rem;
    max-width: 1100px;
}
.server-selector-label {
    display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap;
    margin-bottom: 0.75rem;
    color: var(--bone);
    font-family: var(--font-mono);
    font-size: 0.85rem;
}
.server-selector-label small { color: var(--dim); }
.server-selector-icon { color: var(--hazard); font-size: 1.1rem; }
.server-selector-pills {
    display: flex; gap: 0.5rem; flex-wrap: wrap;
}
.server-pill {
    background: var(--bg-1);
    border: 1px solid var(--border);
    color: var(--dim);
    padding: 0.55rem 1rem;
    cursor: pointer;
    font-family: var(--font-mono);
    font-size: 0.85rem;
    text-align: left;
    transition: all 0.15s;
}
.server-pill:hover { border-color: var(--hazard); color: var(--bone); }
.server-pill.active {
    background: var(--hazard);
    color: var(--bg-0);
    border-color: var(--hazard);
    font-weight: 700;
}
.server-pill small {
    display: block;
    font-size: 0.7rem;
    opacity: 0.8;
    margin-top: 0.15rem;
}
</style>
<script>
// Server selector (multi-server) — sincroniza hidden input em todos os forms
(function() {
    const pills = document.querySelectorAll('.server-pill');
    if (!pills.length) return;
    pills.forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.serverId;
            pills.forEach(p => p.classList.toggle('active', p === btn));
            document.querySelectorAll('[data-server-input]').forEach(i => { i.value = id; });
            // Persiste seleção na URL pra sobreviver a F5
            const url = new URL(window.location);
            url.searchParams.set('server', id);
            window.history.replaceState({}, '', url);
        });
    });
})();

// Wishlist toggle (Steam login required)
const CSRF_TOKEN = <?= json_encode(\App\Csrf::token()) ?>;
document.querySelectorAll('[data-wish]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const pkg = btn.dataset.wish;
        const formData = new FormData();
        formData.append('package_id', pkg);
        formData.append('_csrf', CSRF_TOKEN);
        try {
            const r = await fetch('/wishlist/toggle', { method: 'POST', body: formData });
            if (r.status === 401) {
                const d = await r.json();
                if (confirm('Faça login com Steam pra usar a wishlist. Ir agora?')) {
                    window.location.href = d.login_url;
                }
                return;
            }
            const d = await r.json();
            if (d.ok) {
                btn.classList.toggle('wished', d.action === 'added');
                btn.textContent = d.action === 'added' ? '♥' : '♡';
            }
        } catch (e) { console.warn(e); }
    });
});

// Submit dos cards: trava duplo-clique + aplica a promo sazonal. O aceite dos termos
// agora é por AÇÃO (com aviso na tela de checkout), sem checkbox de fricção na vitrine.
(function() {
    document.querySelectorAll('[data-shop-form]').forEach(f => {
        f.addEventListener('submit', e => {
            // Anti duplo-clique: se já está enviando, ignora.
            if (f.dataset.submitting === '1') { e.preventDefault(); return false; }
            // Cupom: aplica só a promo sazonal automaticamente (se houver). O cupom
            // manual é digitado na tela de checkout, não aqui na loja.
            const couponFlag = f.querySelector('[data-coupon-flag]');
            const promoCode = <?= !empty($promo_coupon) ? json_encode($promo_coupon['code']) : '""' ?>;
            if (couponFlag) couponFlag.value = promoCode;
            // Estado de carregamento (feedback + trava duplo-submit). Usa classe, não
            // disabled, pra não atrapalhar o envio do form.
            f.dataset.submitting = '1';
            const buyBtn = f.querySelector('button[type="submit"]');
            if (buyBtn) { buyBtn.classList.add('loading'); buyBtn.setAttribute('aria-busy', 'true'); }
        });
    });

    // Validação inline do SteamID — feedback imediato (SteamID errado = entrega no player errado).
    document.querySelectorAll('.pack-input[name="steam_id"]').forEach(inp => {
        inp.addEventListener('input', () => {
            const v = inp.value.trim();
            inp.classList.toggle('invalid', v !== '' && !/^7656119\d{10}$/.test(v));
        });
    });
})();
</script>

<?php \App\View::endSection(); ?>
