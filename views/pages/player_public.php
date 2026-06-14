<?php /** @var array $config, $player; @var ?array $stats; @var ?string $avatar; @var string $display_name */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background2.png'); ?>
<?php \App\View::section('content'); ?>
<?php
// Ícones SVG próprios (line, currentColor) — sem emoji.
$icon = function (string $k): string {
    $svgs = [
        'coins'    => '<circle cx="9" cy="9" r="6"/><path d="M14.5 6.2A6 6 0 1 1 9.8 15.5"/>',
        'money'    => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'cart'     => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2l2.4 12.4a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 2-1.6L22 7H6"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'kd'       => '<circle cx="12" cy="12" r="8"/><path d="M12 1v4M12 19v4M1 12h4M19 12h4"/><circle cx="12" cy="12" r="1.6"/>',
        'kills'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="0.8"/>',
        'deaths'   => '<path d="M12 2 3 7v6c0 5 9 9 9 9s9-4 9-9V7z"/><path d="M9.5 9.5l5 5M14.5 9.5l-5 5"/>',
        'shots'    => '<path d="M4 20 20 4M14 4h6v6"/><path d="M9 15l-3 3"/>',
        'accuracy' => '<circle cx="12" cy="12" r="9"/><path d="M12 12 19 5"/><circle cx="12" cy="12" r="1.4"/>',
        'distance' => '<path d="M3 12h18"/><path d="M3 12l3-3M3 12l3 3M21 12l-3-3M21 12l-3 3"/>',
        'skull'    => '<path d="M12 3a8 8 0 0 0-5 14v2a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-2a8 8 0 0 0-5-14z"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/>',
        'weapon'   => '<path d="M3 8h13l3 3v2h-4l-2-2H3z"/><path d="M7 13v3M11 13v2"/>',
    ];
    $p = $svgs[$k] ?? $svgs['kills'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="22" height="22" aria-hidden="true">' . $p . '</svg>';
};

$fmtTime = function ($s): string {
    $s = (int) $s; if ($s <= 0) return '0h';
    $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60);
    return $h . 'h ' . $m . 'm';
};
$ex = is_array($stats['extra'] ?? null) ? $stats['extra'] : [];
?>

<section class="hero" style="min-height: 34vh; padding-bottom: 1.5rem;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background2.png') ?>');"></div>
    <div class="container hero-content">
        <div class="pp-head">
            <div class="pp-avatar-wrap">
                <?php if (!empty($avatar)): ?>
                    <img class="pp-avatar" src="<?= e($avatar) ?>" alt="<?= e(__('profile.pub_avatar_alt', ['name' => $display_name])) ?>"
                         referrerpolicy="no-referrer"
                         onerror="this.outerHTML='<div class=\'pp-avatar pp-avatar-fb\'>&#9881;</div>'">
                <?php else: ?>
                    <div class="pp-avatar pp-avatar-fb">⚙</div>
                <?php endif; ?>
            </div>
            <div>
                <span class="hero-kicker">// <?= e(__('profile.pub_kicker')) ?></span>
                <h1 class="hero-title" style="font-size: clamp(1.6rem, 4vw, 2.4rem); margin:.2rem 0;"><?= e($display_name) ?></h1>
                <div class="pp-steamid" title="<?= e(__('profile.pub_steamid_title')) ?>">🎮 <span style="user-select:all;"><?= e($player['steam_id']) ?></span></div>
                <a class="pp-steam-link" href="https://steamcommunity.com/profiles/<?= e($player['steam_id']) ?>" target="_blank" rel="noopener"><?= e(__('profile.pub_view_steam')) ?> →</a>
            </div>
        </div>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width: 980px;">

        <!-- Perfil PÚBLICO: só dados não-sensíveis (combate + atividade).
             Financeiro (saldo, investido, compras, transações) é PRIVADO:
             o dono vê em /my-purchases; o staff vê em /admin/players. LGPD. -->
        <p class="pp-lastseen">
            <span class="pp-ic" style="color:var(--bone);display:inline-flex;vertical-align:-5px;"><?= $icon('clock') ?></span>
            <?= e(__('profile.last_seen')) ?>: <strong><?= e(time_ago($player['last_seen_at'] ?? null, 'nunca')) ?></strong>
        </p>

        <!-- Estatísticas de gameplay -->
        <h2 class="pp-section-title"><?= $icon('kills') ?> <?= e(__('profile.pub_combat_stats')) ?></h2>

        <?php if ($stats): ?>
            <div class="pp-grid">
                <div class="pp-card"><div class="pp-ic" style="color:var(--hazard)"><?= $icon('kd') ?></div><div class="pp-label"><?= e(__('profile.pub_kd')) ?></div><div class="pp-value"><?= number_format((float)$stats['kdratio'], 2, ',', '.') ?></div></div>
                <div class="pp-card"><div class="pp-ic" style="color:var(--rust-2)"><?= $icon('kills') ?></div><div class="pp-label"><?= e(__('profile.pub_kills_players')) ?></div><div class="pp-value"><?= (int)$stats['kills'] ?></div></div>
                <div class="pp-card"><div class="pp-ic" style="color:var(--bone)"><?= $icon('skull') ?></div><div class="pp-label"><?= e(__('profile.pub_deaths')) ?></div><div class="pp-value"><?= (int)$stats['deaths'] ?></div></div>
                <div class="pp-card"><div class="pp-ic" style="color:var(--moss)"><?= $icon('clock') ?></div><div class="pp-label"><?= e(__('profile.pub_playtime')) ?></div><div class="pp-value" style="font-size:1.05rem;"><?= e($fmtTime($stats['playtime_seconds'])) ?></div></div>
                <div class="pp-card"><div class="pp-ic"><?= $icon('kills') ?></div><div class="pp-label"><?= e(__('profile.pub_kills_infected')) ?></div><div class="pp-value"><?= (int)$stats['kills_infected'] ?></div></div>
                <div class="pp-card"><div class="pp-ic"><?= $icon('distance') ?></div><div class="pp-label"><?= e(__('profile.pub_longest_kill')) ?></div><div class="pp-value"><?= (int)$stats['longest_kill_m'] ?> m</div></div>
                <?php if (isset($ex['accuracy_pct'])): ?>
                    <div class="pp-card"><div class="pp-ic" style="color:var(--hazard)"><?= $icon('accuracy') ?></div><div class="pp-label"><?= e(__('profile.pub_accuracy')) ?></div><div class="pp-value"><?= number_format((float)$ex['accuracy_pct'], 1, ',', '.') ?>%</div></div>
                <?php endif; ?>
                <?php if (isset($ex['distance_km'])): ?>
                    <div class="pp-card"><div class="pp-ic"><?= $icon('distance') ?></div><div class="pp-label"><?= e(__('profile.pub_distance')) ?></div><div class="pp-value"><?= number_format((float)$ex['distance_km'], 1, ',', '.') ?> km</div></div>
                <?php endif; ?>
            </div>

            <?php if (!empty($ex['top_weapons']) && is_array($ex['top_weapons'])): ?>
                <h2 class="pp-section-title"><?= $icon('weapon') ?> <?= e(__('profile.pub_top_weapons')) ?></h2>
                <div class="pp-weapons">
                    <?php foreach (array_slice($ex['top_weapons'], 0, 5) as $i => $w): ?>
                        <div class="pp-weapon">
                            <span class="pp-weapon-rank">#<?= $i + 1 ?></span>
                            <span class="pp-weapon-name"><?= e($w['name'] ?? $w['classname'] ?? '—') ?></span>
                            <span class="pp-weapon-meta"><?= e(__('profile.pub_weapon_kills')) ?> <strong><?= (int)($w['kills'] ?? 0) ?></strong><?php if (isset($w['damage'])): ?> · <?= e(__('profile.pub_weapon_damage')) ?> <strong><?= (int)$w['damage'] ?></strong><?php endif; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="pp-updated"><?= e(__('profile.pub_updated', ['t' => time_ago($stats['updated_at'] ?? null)])) ?></p>
        <?php else: ?>
            <div class="pp-soon">
                <div class="pp-ic" style="color:var(--dim); margin-bottom:.6rem;"><?= $icon('kd') ?></div>
                <p><?= e(__('profile.pub_no_stats')) ?></p>
                <p style="color:var(--dim); font-size:.85rem; margin-top:.4rem;"><?= e(__('profile.pub_no_stats_hint')) ?></p>
            </div>
        <?php endif; ?>

        <p style="text-align:center; margin-top:2.5rem;">
            <a href="/ranking" class="btn btn-outline">← <?= e(__('profile.pub_view_ranking')) ?></a>
        </p>
    </div>
</section>

<style>
.pp-head { display:flex; align-items:center; gap:1.2rem; }
.pp-avatar, .pp-avatar-fb { width:88px; height:88px; border-radius:6px; border:2px solid var(--rust); object-fit:cover; display:block; }
.pp-avatar-fb { display:flex; align-items:center; justify-content:center; font-size:2.2rem; color:var(--dim); background:var(--bg-2); }
.pp-steamid { font-family:var(--font-mono); color:var(--dim); font-size:.8rem; margin:.2rem 0 .3rem; }
.pp-steam-link { color:var(--rust-2); font-size:.85rem; text-decoration:none; }
.pp-steam-link:hover { text-decoration:underline; }
.pp-lastseen { color:var(--dim); font-size:0.9rem; margin:0 0 0.5rem; }
.pp-lastseen strong { color:var(--bone); }
.pp-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.pp-card { background:var(--bg-1); border:1px solid var(--border); border-radius:4px; padding:1.1rem; text-align:left; }
.pp-ic { color:var(--rust-2); display:flex; align-items:center; }
.pp-label { color:var(--dim); font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; margin:.5rem 0 .2rem; }
.pp-value { color:var(--bone); font-family:var(--font-display); font-size:1.5rem; }
.pp-section-title { display:flex; align-items:center; gap:.5rem; font-family:var(--font-display); color:var(--bone); font-size:1.2rem; margin:2.5rem 0 1rem; border-bottom:2px solid var(--rust); padding-bottom:.5rem; }
.pp-section-title svg { color:var(--rust); }
.pp-weapons { display:flex; flex-direction:column; gap:.5rem; }
.pp-weapon { display:flex; align-items:center; gap:.8rem; background:var(--bg-1); border:1px solid var(--border); border-left:3px solid var(--rust); padding:.7rem 1rem; }
.pp-weapon-rank { font-family:var(--font-display); color:var(--hazard); }
.pp-weapon-name { color:var(--bone); font-weight:700; flex:1; }
.pp-weapon-meta { color:var(--dim); font-size:.85rem; }
.pp-tx { display:flex; flex-direction:column; gap:.4rem; }
.pp-tx-row { display:flex; align-items:center; gap:.8rem; background:var(--bg-1); border:1px solid var(--border); border-left:3px solid var(--moss); padding:.6rem 1rem; flex-wrap:wrap; }
.pp-tx-icon { font-size:1.2rem; }
.pp-tx-name { color:var(--bone); font-weight:700; flex:1; min-width:120px; }
.pp-tx-coins { color:var(--hazard); font-family:var(--font-mono); font-size:.85rem; }
.pp-tx-price { color:var(--moss); font-family:var(--font-display); }
.pp-tx-date { color:var(--dim); font-size:.78rem; font-family:var(--font-mono); min-width:120px; text-align:right; }
@media (max-width:520px){ .pp-tx-date{ text-align:left; } }
.pp-updated { text-align:center; color:var(--dim); font-size:.78rem; margin-top:1.2rem; }
.pp-soon { text-align:center; padding:2.5rem 1rem; background:var(--bg-1); border:1px dashed var(--border); border-radius:4px; color:var(--bone); }
.pp-soon .pp-ic { justify-content:center; }
@media (max-width:520px){ .pp-head{ flex-direction:column; text-align:center; } .pp-ic{ justify-content:flex-start; } }
</style>

<?php \App\View::endSection(); ?>
