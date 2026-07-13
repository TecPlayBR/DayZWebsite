<?php /** @var array $config */ ?>
<?php \App\View::extend('layouts.main'); ?>

<?php
// SEO: title/description otimizados pra home (keywords BR DayZ). Settings podem
// override (seo_home_title / seo_home_description) - senão monta default com
// nome do servidor + tagline. View::with() propaga pro layout main.php.
$siteName    = $config['settings']['site_name'] ?? $config['site_name'] ?? 'DayZ';
$tagline     = $config['settings']['site_tagline'] ?? $config['site_tagline'] ?? '';
$seoTitle    = ($config['settings']['seo_home_title'] ?? '')
    ?: "Servidor DayZ BR - {$siteName} | PVP Hardcore, Loja & Comunidade";
$seoDesc     = ($config['settings']['seo_home_description'] ?? '')
    ?: ($tagline ?: "Servidor DayZ brasileiro com PVP hardcore, loja de moedas via PIX, eventos semanais e comunidade ativa. Entre no {$siteName}.");
\App\View::with('title',       $seoTitle);
\App\View::with('description', $seoDesc);
?>

<?php \App\View::section('content'); ?>

<!-- ============ ANÚNCIOS ============ -->
<?php if (!empty($announcements)): $annIcons = ['info' => '◆', 'success' => '✓', 'warning' => '⚠', 'danger' => '⚡']; ?>
    <div class="announcements-strip" data-announcements>
        <?php foreach ($announcements as $a): ?>
            <div class="announcement announcement-<?= e($a['kind']) ?>" data-announcement-id="<?= (int)$a['id'] ?>">
                <div class="announcement-content">
                    <span class="announcement-icon" aria-hidden="true"><?= $annIcons[$a['kind']] ?? '◆' ?></span>
                    <strong class="announcement-title"><?= e($a['title']) ?></strong>
                    <?php if (!empty($a['body'])): ?>
                        <span class="announcement-body"><?= e($a['body']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($a['cta_url'])): ?>
                    <a href="<?= e($a['cta_url']) ?>" class="announcement-cta"><?= e($a['cta_label'] ?: 'Saiba mais') ?> →</a>
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
            <span class="wipe-time" data-wipe-countdown>-</span>
        </div>
    <?php endif; ?>

    <!-- Status do servidor (live via BattleMetrics API)
         quando offline, mostra "voltando em breve"
         em vez de Offline cru + CTA Discord. Rank BM só aparece se <= bm_rank_threshold
         (default 500) - não queima credibilidade com rank ruim. -->
    <?php
    $ss = $server_status ?? ['configured' => false];
    $bmRankMax    = (int)($config['settings']['bm_rank_threshold'] ?? 500);
    $discordUrl   = $config['settings']['social_discord'] ?? '';
    $showRank     = !empty($ss['rank']) && (int)$ss['rank'] > 0 && (int)$ss['rank'] <= $bmRankMax;
    ?>
    <div class="hero-chips">
    <?php if (!empty($ss['configured'])): ?>
        <div class="hero-status hero-status-<?= $ss['online'] ? 'online' : 'offline' ?>" aria-live="polite">
            <span class="dot"></span>
            <?php if ($ss['online']): ?>
                <span><?= e(__('hero.status_online')) ?></span>
                <span style="color: var(--dim);">&middot;</span>
                <span><strong><?= (int)$ss['players'] ?></strong><?php if ((int)$ss['max'] > 0): ?>/<?= (int)$ss['max'] ?><?php endif; ?> <?= e(__('hero.status_players')) ?></span>
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
            <a href="/server-status" class="hs-detalhes"><?= e(__('hero.status_detalhes')) ?></a>
        </div>
    <?php endif; ?>

    <?php $rs = $config['restart'] ?? null; if ($rs): ?>
        <div class="hero-restart hero-restart-ok" id="hero-restart" aria-live="polite"
             data-next="<?= (int)$rs['next_ts'] ?>" data-warn="<?= (int)($rs['warn_min'] ?? 5) ?>" data-at="<?= e($rs['at']) ?>">
            <span class="dot"></span><span class="hr-text">🔄 <?= e(__('restart.next')) ?>: <strong><?= e($rs['at']) ?></strong></span>
        </div>
        <script>
        (function(){
            var el = document.getElementById('hero-restart'); if (!el) return;
            var next = +el.dataset.next, warn = +el.dataset.warn || 5, at = el.dataset.at, reloaded = false;
            var T = { next: <?= json_encode(__('restart.next')) ?>, inTpl: <?= json_encode(__('restart.in', ['t' => '%T'])) ?>, soonTpl: <?= json_encode(__('restart.soon', ['m' => '%M'])) ?>, restarting: <?= json_encode(__('restart.restarting')) ?> };
            var txt = el.querySelector('.hr-text');
            function rel(m){ var h = Math.floor(m/60), mm = m%60; return h > 0 ? (h+'h '+mm+'min') : (mm+'min'); }
            function tick(){
                var rem = Math.floor((next*1000 - Date.now())/1000), mins = Math.max(0, Math.ceil(rem/60)), cls, html;
                if (rem <= 0) { cls='red'; html='🔄 '+T.restarting; if (!reloaded && rem <= -20){ reloaded=true; setTimeout(function(){ location.reload(); }, 2500); } }
                else if (mins <= warn) { cls='amber'; html='🔄 '+T.soonTpl.replace('%M', mins); }
                else { cls='ok'; html='🔄 '+T.next+': <strong>'+at+'</strong> <span style="opacity:.6">'+T.inTpl.replace('%T', rel(mins))+'</span>'; }
                el.className = 'hero-restart hero-restart-'+cls; txt.innerHTML = html;
            }
            tick(); setInterval(tick, 1000);
        })();
        </script>
    <?php endif; ?>
    </div><!-- /hero-chips -->
    <style>
    /* Status do servidor + Próximo restart EMPILHADOS num container flex no canto.
       Antes eram 2 absolutos com offsets fixos (bottom:2rem e 5.6rem) e se sobrepunham
       quando o conteúdo crescia. Agora o flex cuida do empilhamento - nunca colidem. */
    .hero-chips { position:absolute; bottom:2rem; right:2rem; z-index:2; display:flex; flex-direction:column; gap:0.7rem; align-items:stretch; }
    .hero-chips .hero-status { position:static; bottom:auto; right:auto; flex-wrap:wrap; }
    .hero-status, .hero-restart { min-width:360px; box-sizing:border-box; border-radius:5px; box-shadow:0 8px 24px rgba(0,0,0,0.5); }
    .hero-status .hs-detalhes { margin-left:auto; padding-left:0.6rem; color:var(--dim); font-size:0.75rem; text-decoration:none; opacity:0.8; transition:opacity .2s, color .2s; }
    .hero-status .hs-detalhes:hover { opacity:1; color:var(--hazard); }
    .hero-restart { background:rgba(13,16,20,0.85); border:1px solid var(--border); border-left:3px solid var(--moss);
        padding:0.8rem 1.2rem; font-family:var(--font-mono); font-size:0.92rem; color:var(--bone);
        display:inline-flex; align-items:center; gap:0.5rem; white-space:nowrap; }
    .hero-restart .dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .hero-restart-ok { border-left-color:var(--moss); } .hero-restart-ok .dot { background:var(--moss); box-shadow:0 0 8px var(--moss); }
    .hero-restart-amber { border-left-color:#f0a500; } .hero-restart-amber .dot { background:#f0a500; box-shadow:0 0 8px #f0a500; animation:pulse 1.5s infinite; }
    .hero-restart-red { border-left-color:var(--rust-2); } .hero-restart-red .dot { background:var(--rust-2); box-shadow:0 0 8px var(--rust-2); animation:pulse .8s infinite; }
    @media (max-width:768px){ .hero-chips { position:static; bottom:auto; right:auto; margin:1rem auto 0; max-width:360px; } .hero-status, .hero-restart { min-width:0; width:100%; justify-content:center; } }
    </style>

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
    // Cria um <span class> com texto seguro (textContent escapa - evita XSS:
    // it.name/it.package vêm do nome Steam do jogador, que é controlável).
    function span(cls, text) {
        const s = document.createElement('span');
        s.className = cls;
        s.textContent = text;
        return s;
    }
    function txt(t) { return document.createTextNode(t); }
    function renderItem(it) {
        const el = document.createElement('div');
        el.className = 'live-purchase-item';
        el.appendChild(span('live-purchase-icon', it.icon || '🪙'));
        el.appendChild(span('live-purchase-name', it.name || ''));
        el.appendChild(txt(' '));
        el.appendChild(span('live-purchase-meta', 'comprou'));
        el.appendChild(txt(' '));
        el.appendChild(span('live-purchase-coins', (Number(it.coins) || 0) + ' moedas'));
        el.appendChild(txt(' '));
        el.appendChild(span('live-purchase-meta', '(' + (it.package || '') + ')'));
        if (it.price) {
            el.appendChild(span('live-purchase-meta', ' - R$ ' + (Number(it.price) || 0).toFixed(2).replace('.', ',')));
        }
        el.appendChild(span('live-purchase-ago', fmtAgo(it.ago_secs)));
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

<!-- ============ TESTIMONIALS (Social Proof) - reviews REAIS aprovadas ============
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
                        <?php $av = trim((string)($r['avatar'] ?? '')); $letter = e(mb_strtoupper(mb_substr($name, 0, 1))); ?>
                        <?php if ($av !== '' && preg_match('#^https?://#i', $av)): ?>
                            <img class="testimonial-avatar" src="<?= e($av) ?>" alt="<?= e($name) ?>" loading="lazy" referrerpolicy="no-referrer"
                                 onerror="this.outerHTML='<span class=\'testimonial-avatar testimonial-avatar-letter\'><?= $letter ?></span>'">
                        <?php else: ?>
                            <span class="testimonial-avatar testimonial-avatar-letter"><?= $letter ?></span>
                        <?php endif; ?>
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
    display: flex;
    flex-direction: column;
}
.testimonial-text {
    color: var(--bone);
    font-size: 0.95rem;
    line-height: 1.55;
    margin: 0 0 1.2rem;
    font-style: italic;
    overflow-wrap: break-word;
    word-break: break-word;
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
.testimonial-meta { display: flex; align-items: center; gap: 0.75rem; font-style: normal; margin-top: auto; padding-top: 0.4rem; }
.testimonial-avatar {
    width: 42px; height: 42px;
    flex-shrink: 0;
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
.testimonial-author { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
.testimonial-author strong { color: var(--bone); font-size: 0.9rem; overflow-wrap: break-word; word-break: break-word; }
.testimonial-author em { color: var(--dim); font-size: 0.78rem; font-style: normal; }
</style>
<?php endif; ?>

<!-- ============ FEATURES ============ -->
<?php $hf = home_features(); ?>
<?php if ($hf['enabled'] && !empty($hf['cards'])): ?>
<section class="section section-bg-2" id="features">
    <div class="container">
        <div class="section-header">
            <h2><?= e($hf['title']) ?></h2>
            <?php if (($hf['subtitle'] ?? '') !== ''): ?><p><?= e($hf['subtitle']) ?></p><?php endif; ?>
        </div>

        <div class="features-grid">
            <?php foreach ($hf['cards'] as $card): ?>
                <div class="feature">
                    <div class="feature-icon"><?= e($card['icon']) ?></div>
                    <h3><?= e($card['title']) ?></h3>
                    <?php if (($card['text'] ?? '') !== ''): ?><p><?= e($card['text']) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ TEASER DE EVENTO (destaque) ============ -->
<?php if (!empty($featured_event)): $fe = $featured_event; ?>
<section class="section section-bg-2" style="padding-top:3rem;padding-bottom:3rem;">
    <div class="container">
        <a href="/eventos" class="home-event <?= $fe['status']==='active' ? 'home-event-live' : '' ?>">
            <?php if (!empty($fe['image'])): ?>
                <div class="home-event-img" style="background-image:linear-gradient(90deg,rgba(0,0,0,0.6),rgba(0,0,0,0.2)),url('<?= e($fe['image']) ?>');"></div>
            <?php endif; ?>
            <div class="home-event-body">
                <span class="home-event-kicker"><?= $fe['status']==='active' ? '🟢 ' . e(__('eventos.home_live')) : e(__('eventos.home_kicker')) ?></span>
                <h3 class="home-event-title"><?= e($fe['title']) ?></h3>
                <?php if (!empty($fe['prize'])): ?><div class="home-event-prize">🏆 <?= e($fe['prize']) ?></div><?php endif; ?>
                <span class="home-event-cta"><?= e(__('eventos.home_cta')) ?> →</span>
            </div>
        </a>
    </div>
</section>
<style>
.home-event { display:flex; align-items:stretch; gap:0; background:linear-gradient(180deg,var(--bg-2),var(--bg-1)); border:1px solid var(--border); border-left:3px solid var(--hazard); border-radius:8px; overflow:hidden; text-decoration:none; transition:transform .2s,box-shadow .2s,border-color .2s; }
.home-event:hover { transform:translateY(-3px); box-shadow:0 12px 30px rgba(0,0,0,0.5); border-color:var(--hazard); }
.home-event-live { border-left-color:var(--moss); }
.home-event-img { flex:0 0 220px; background-size:cover; background-position:center; min-height:130px; }
.home-event-body { padding:1.2rem 1.4rem; display:flex; flex-direction:column; justify-content:center; gap:0.35rem; }
.home-event-kicker { font-family:var(--font-mono); font-size:0.72rem; letter-spacing:0.12em; color:var(--hazard); }
.home-event-live .home-event-kicker { color:var(--moss); }
.home-event-title { font-family:var(--font-display); color:var(--bone); font-size:1.3rem; letter-spacing:0.03em; }
.home-event-prize { color:var(--hazard); font-weight:600; font-size:0.92rem; }
.home-event-cta { color:var(--bone); font-size:0.85rem; margin-top:0.3rem; opacity:0.85; }
@media (max-width:560px){ .home-event{flex-direction:column;} .home-event-img{flex-basis:140px;width:100%;} }
</style>
<?php endif; ?>

<!-- ============ STREAMERS EM DESTAQUE ============ -->
<?php if (!empty($featured_streamers)): ?>
<section class="section section-bg-2" style="padding-top:3rem;padding-bottom:3rem;">
    <div class="container">
        <div class="section-header">
            <h2>🎮 Streamers Parceiros</h2>
            <p>Apoie um streamer - suas compras ajudam ele direto.</p>
        </div>
        <div class="home-streamers">
            <?php foreach (array_slice($featured_streamers, 0, 4) as $st): ?>
                <a href="/streamer/<?= e(strtolower($st['code'])) ?>" class="home-streamer">
                    <?php if (!empty($st['avatar_url'])): ?>
                        <img src="<?= e($st['avatar_url']) ?>" alt="<?= e($st['name']) ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="home-streamer-body">
                        <span class="home-streamer-name">🎮 <?= e($st['name']) ?></span>
                        <span class="home-streamer-cta">Ver / Apoiar →</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<style>
.home-streamers { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1rem; }
.home-streamer { display:flex; align-items:center; gap:0.9rem; background:linear-gradient(180deg,var(--bg-2),var(--bg-1)); border:1px solid var(--border); border-left:3px solid var(--hazard); border-radius:8px; padding:0.9rem 1rem; text-decoration:none; transition:transform .2s,box-shadow .2s,border-color .2s; }
.home-streamer:hover { transform:translateY(-3px); box-shadow:0 12px 30px rgba(0,0,0,0.5); border-color:var(--hazard); }
.home-streamer img { width:64px; height:64px; border-radius:10px; object-fit:cover; flex:0 0 64px; border:1px solid var(--border); }
.home-streamer-body { display:flex; flex-direction:column; gap:0.25rem; }
.home-streamer-name { font-family:var(--font-display); color:var(--bone); font-size:1.05rem; letter-spacing:0.03em; }
.home-streamer-cta { color:var(--hazard); font-size:0.82rem; }
</style>
<?php endif; ?>

<!-- ============ SHOP TEASER (3 pacotes destaque) ============ -->
<section class="section section-bg-3" id="shop">
    <div class="container">
        <div class="section-header">
            <h2><?= e(__('shop.title')) ?></h2>
            <p><?= e(__('shop.subtitle')) ?></p>
        </div>

        <div class="features-grid" style="margin-bottom: 2rem;">
            <?php
            // Teaser: 3 pacotes - controller já entrega featured primeiro.
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

                    <div class="home-pack-icon">
                        <?php if (!empty($pkg['image'])): ?>
                            <img class="home-pack-img" src="<?= preg_match('#^https?://#i', $pkg['image']) ? e($pkg['image']) : asset('img/packages/' . $pkg['image']) ?>" alt="<?= e($pkg['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <?= e($pkg['icon'] ?? '🪙') ?>
                        <?php endif; ?>
                    </div>
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
            overflow: visible !important; /* override .feature overflow:hidden - ribbon precisa estourar */
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
        .home-pack-icon { font-size: 2.2rem; margin: 0.5rem 0; min-height: 50px; display:flex; align-items:center; justify-content:center; }
        .home-pack-img { width: 100px; height: 100px; object-fit: contain; filter: drop-shadow(0 6px 14px rgba(0,0,0,0.5)); transition: transform .25s; }
        .home-pack:hover .home-pack-img { transform: scale(1.06); }
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
