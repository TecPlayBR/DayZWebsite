<?php /** @var array $config, $player; @var ?array $stats; @var ?string $avatar; @var string $display_name; @var bool $is_owner */ ?>
<?php
// Perfil UNIFICADO: público pra todos (combate + conquistas); privado (saldo, compras,
// caixas, loja in-game, streamer) só pro DONO logado ($is_owner). Substitui o antigo
// /my-purchases (que agora redireciona pra cá). Financeiro NUNCA aparece pra visitante.
$is_owner       = $is_owner       ?? false;
$achievements   = $achievements   ?? [];
$unlocked       = $unlocked       ?? [];
$purchases      = $purchases      ?? [];
$box_openings   = $box_openings   ?? [];
$shop_spends    = $shop_spends    ?? [];
$reward_payouts = $reward_payouts ?? [];
$achievement_payouts = $achievement_payouts ?? [];
$clan           = $clan           ?? null;
$clan_invites   = $clan_invites   ?? [];
?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background2.png'); ?>
<?php \App\View::section('content'); ?>
<?php
$icon = function (string $k): string {
    $svgs = [
        'coins'    => '<circle cx="9" cy="9" r="6"/><path d="M14.5 6.2A6 6 0 1 1 9.8 15.5"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'kd'       => '<circle cx="12" cy="12" r="8"/><path d="M12 1v4M12 19v4M1 12h4M19 12h4"/><circle cx="12" cy="12" r="1.6"/>',
        'kills'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="0.8"/>',
        'skull'    => '<path d="M12 3a8 8 0 0 0-5 14v2a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-2a8 8 0 0 0-5-14z"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/>',
        'weapon'   => '<path d="M3 8h13l3 3v2h-4l-2-2H3z"/><path d="M7 13v3M11 13v2"/>',
        'distance' => '<path d="M3 12h18"/><path d="M3 12l3-3M3 12l3 3M21 12l-3-3M21 12l-3 3"/>',
        'accuracy' => '<circle cx="12" cy="12" r="9"/><path d="M12 12 19 5"/><circle cx="12" cy="12" r="1.4"/>',
    ];
    $p = $svgs[$k] ?? $svgs['kills'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="22" height="22" aria-hidden="true">' . $p . '</svg>';
};
$fmtTime = function ($s): string {
    $s = (int) $s; if ($s <= 0) return '0h';
    return intdiv($s, 3600) . 'h ' . intdiv($s % 3600, 60) . 'm';
};
$ex = is_array($stats['extra'] ?? null) ? $stats['extra'] : [];

// Flash (só relevante pro dono - vêm dos redirects de review/caixa/streamer).
// Mostrado em banner no TOPO (visível na hora), não enterrado nas seções.
$flash = null;
if (!empty($_GET['ok']) && $_GET['ok'] === 'review_submitted') {
    $flash = ['success', __('profile.flash_review_ok')];
} elseif (!empty($_GET['box'])) {
    $flash = match($_GET['box']) {
        'ok'          => ['success', '✓ Item liberado! Vai cair no chão perto de você no servidor em instantes.'],
        'wait'        => ['danger',  '⚠ Pra receber agora você precisa estar ONLINE no servidor (e fora da janela de restart). Entra no jogo e clica Receber de novo.'],
        'already'     => ['success', 'Esse item já tinha sido entregue.'],
        'ratelimited' => ['danger',  'Calma - muitos resgates seguidos. Tenta de novo em instantes.'],
        'invalid'     => ['danger',  'Item inválido.'],
        default       => ['danger',  'Não consegui resgatar esse item agora.'],
    };
} elseif (!empty($_GET['err'])) {
    $flash = ['danger', match($_GET['err']) {
        'invalid_purchase' => __('profile.flash_invalid'),
        'too_soon'         => __('profile.flash_too_soon'),
        'already_reviewed' => __('profile.flash_reviewed'),
        default            => __('profile.flash_generic'),
    }];
}
?>

<?php if ($is_owner && $flash): $isOk = $flash[0] === 'success'; ?>
    <div id="pp-flash" class="pp-toast <?= $isOk ? 'pp-toast-ok' : 'pp-toast-err' ?>" role="alert">
        <span class="pp-toast-ico" aria-hidden="true"><?= $isOk ? '✓' : '⚠' ?></span>
        <span class="pp-toast-msg"><?= e($flash[1]) ?></span>
        <button type="button" class="pp-toast-close" aria-label="Fechar">&times;</button>
    </div>
    <style>
    .pp-toast {
        position: fixed; top: 88px; right: 20px; z-index: 100000;
        width: min(380px, calc(100vw - 40px));
        display: flex; align-items: flex-start; gap: 0.6rem;
        padding: 0.9rem 1rem; border-radius: 8px;
        font-size: 0.92rem; line-height: 1.45; font-weight: 500;
        box-shadow: 0 10px 34px rgba(0,0,0,0.55);
        animation: ppToastIn 0.3s ease both;
    }
    .pp-toast-ok  { background: #2f3a26; border: 1px solid #7e9b5e; color: #eef5e6; }
    .pp-toast-err { background: #5c1f18; border: 1px solid #e0573f; color: #ffe6e0; }
    .pp-toast-ico { font-size: 1.05rem; line-height: 1.3; flex-shrink: 0; }
    .pp-toast-msg { flex: 1; }
    .pp-toast-close { background: none; border: none; color: inherit; font-size: 1.25rem; line-height: 1; cursor: pointer; opacity: 0.75; padding: 0; flex-shrink: 0; }
    .pp-toast-close:hover { opacity: 1; }
    .pp-toast.pp-hide { animation: ppToastOut 0.4s ease forwards; }
    @keyframes ppToastIn  { from { opacity: 0; transform: translateX(28px); } to { opacity: 1; transform: none; } }
    @keyframes ppToastOut { to { opacity: 0; transform: translateX(28px); } }
    @media (max-width: 560px) { .pp-toast { top: 74px; left: 12px; right: 12px; width: auto; } }
    </style>
    <script>
    (function () {
        var t = document.getElementById('pp-flash');
        if (!t) return;
        var ttl = t.classList.contains('pp-toast-err') ? 9000 : 6000; // erro/aviso fica mais tempo
        function close() { t.classList.add('pp-hide'); setTimeout(function () { if (t.parentNode) t.remove(); }, 400); }
        var timer = setTimeout(close, ttl);
        t.querySelector('.pp-toast-close').addEventListener('click', function () { clearTimeout(timer); close(); });
    })();
    </script>
<?php endif; ?>

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
                <span class="hero-kicker">// <?= $is_owner ? e(__('profile.kicker')) : e(__('profile.pub_kicker')) ?></span>
                <h1 class="hero-title" style="font-size: clamp(1.6rem, 4vw, 2.4rem); margin:.2rem 0;"><?= clan_tag($player['steam_id']) ?><?= e($display_name) ?></h1>
                <div class="pp-steamid" title="<?= e(__('profile.pub_steamid_title')) ?>">🎮 <span style="user-select:all;"><?= e($player['steam_id']) ?></span></div>
                <a class="pp-steam-link" href="https://steamcommunity.com/profiles/<?= e($player['steam_id']) ?>" target="_blank" rel="noopener"><?= e(__('profile.pub_view_steam')) ?> →</a>
                <?php if ($clan): ?>
                    <a href="/clan/<?= (int)$clan['id'] ?>" class="pp-clan-head">
                        <?php if (!empty($clan['logo'])): ?><img src="<?= e($clan['logo']) ?>" alt="" onerror="this.remove()"><?php endif; ?>
                        <span>🛡 [<?= e($clan['tag']) ?>] <?= e($clan['name']) ?></span>
                    </a>
                <?php elseif ($is_owner): ?>
                    <a href="/clans" class="pp-clan-head pp-clan-head-none">🛡 Entrar num clã →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width: 980px;">

        <?php if ($is_owner): ?>
        <!-- ===== PRIVADO (só o dono): resumo financeiro ===== -->
        <div class="profile-grid">
            <div class="profile-card">
                <div class="profile-card-label"><?= e(__('profile.balance')) ?></div>
                <div class="profile-card-value" style="color: var(--hazard);"><?= number_format((int)($player['coins'] ?? 0), 0, ',', '.') ?></div>
                <div class="profile-card-suffix"><?= e(__('profile.coins')) ?></div>
            </div>
            <div class="profile-card">
                <div class="profile-card-label"><?= e(__('profile.invested')) ?></div>
                <div class="profile-card-value" style="color: var(--moss);">R$ <?= number_format((float)($player['total_spent_brl'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="profile-card">
                <div class="profile-card-label"><?= e(__('profile.purchases')) ?></div>
                <div class="profile-card-value"><?= count($purchases) ?></div>
            </div>
            <div class="profile-card">
                <div class="profile-card-label"><?= e(__('profile.last_seen')) ?></div>
                <div class="profile-card-value" style="font-size: 1rem;"><?= e(time_ago($player['last_seen_at'] ?? null)) ?></div>
            </div>
        </div>
        <?php else: ?>
        <p class="pp-lastseen">
            <span class="pp-ic" style="color:var(--bone);display:inline-flex;vertical-align:-5px;"><?= $icon('clock') ?></span>
            <?= e(__('profile.last_seen')) ?>: <strong><?= e(time_ago($player['last_seen_at'] ?? null, 'nunca')) ?></strong>
        </p>
        <?php endif; ?>

        <!-- ===== PÚBLICO: estatísticas de gameplay ===== -->
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
                            <span class="pp-weapon-name"><?= e($w['name'] ?? $w['classname'] ?? '-') ?></span>
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

        <!-- ===== PÚBLICO: conquistas ===== -->
        <?php if (!empty($achievements)): ?>
        <h2 class="pp-section-title"><?= $icon('kd') ?> <?= e(__('profile.achievements')) ?></h2>
        <div class="achievements-grid">
            <?php foreach ($achievements as $a):
                $isUnlocked = !empty($unlocked[$a['slug']]);
            ?>
                <div class="achievement <?= $isUnlocked ? 'unlocked' : 'locked' ?>" title="<?= e($a['description']) ?>">
                    <div class="achievement-icon"><?= e($a['icon']) ?></div>
                    <div class="achievement-name"><?= e($a['name']) ?></div>
                    <div class="achievement-desc"><?= e($a['description']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($is_owner): ?>
        <!-- ============================================================ -->
        <!-- ===== PRIVADO (só o dono): histórico de compras + caixas ==== -->
        <!-- ============================================================ -->
        <?php /* Históricos em dropdown FECHADO por padrão (a página não estica). Mostra até 25. */ $cap = 25; ?>

        <?php if (!empty($clan_invites)): ?>
        <div class="pp-invites">
            <strong style="color:var(--bone);">🛡 Convites de clã:</strong>
            <?php foreach ($clan_invites as $inv): ?>
                <div class="pp-invite-row">
                    <span>[<?= e($inv['tag']) ?>] <?= e($inv['name']) ?></span>
                    <span style="display:flex;gap:.4rem;">
                        <form method="POST" action="/clan-invite/accept" style="margin:0;"><?= \App\Csrf::field() ?><input type="hidden" name="clan_id" value="<?= (int)$inv['clan_id'] ?>"><button class="btn-mini">Aceitar</button></form>
                        <form method="POST" action="/clan-invite/reject" style="margin:0;"><?= \App\Csrf::field() ?><input type="hidden" name="clan_id" value="<?= (int)$inv['clan_id'] ?>"><button class="btn-mini outline">Recusar</button></form>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (empty($purchases)): ?>
            <h2 class="pp-section-title"><?= $icon('coins') ?> <?= e(__('profile.history')) ?></h2>
            <div style="text-align: center; padding: 2rem 1rem;">
                <p style="color: var(--dim); margin-bottom: 1.2rem;"><?= e(__('profile.no_purchases')) ?></p>
                <a href="/shop" class="btn"><?= e(__('nav.shop')) ?> →</a>
            </div>
        <?php else: ?>
            <details class="pp-acc">
                <summary><?= $icon('coins') ?> <?= e(__('profile.history')) ?> <span class="pp-acc-count"><?= count($purchases) ?></span></summary>
                <div class="pp-acc-body">
                    <table class="purchases-table">
                        <thead><tr><th>Data</th><th>Pacote</th><th>Moedas</th><th>Valor</th><th class="hide-mobile"><?= e(__('profile.payment_method')) ?></th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($purchases, 0, $cap) as $p): ?>
                                <tr>
                                    <td class="dim"><?= e(fmt_dt($p['created_at'])) ?></td>
                                    <td><strong><?= e($p['package_id']) ?></strong></td>
                                    <td class="mono"><?= (int)$p['coins_total'] ?><?php if ((int)$p['coins_bonus'] > 0): ?> <small style="color: var(--moss);">(+<?= (int)$p['coins_bonus'] ?>)</small><?php endif; ?></td>
                                    <td>R$ <?= number_format((float)$p['price_brl'], 2, ',', '.') ?></td>
                                    <td class="hide-mobile dim"><?= e($p['payment_method'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $cls = match($p['mp_status']) {
                                            'approved' => 'badge-success',
                                            'rejected','cancelled','refunded' => 'badge-danger',
                                            'pending' => 'badge-warning',
                                            default => 'badge-info'
                                        };
                                        $statusKey = 'purchase_status.' . ($p['mp_status'] ?? 'unknown');
                                        $statusTr  = __($statusKey);
                                        $label = ($statusTr === $statusKey) ? ($p['mp_status'] ?? '-') : $statusTr;
                                        ?>
                                        <span class="purchase-badge <?= $cls ?>"><?= $label ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($purchases) > $cap): ?><p class="pp-acc-more">Mostrando as <?= $cap ?> compras mais recentes.</p><?php endif; ?>
                </div>
            </details>
        <?php endif; ?>

        <?php
        if (!empty($affiliate_on)):
            $affFlash = $_GET['aff'] ?? '';
            $affMsg = [
                'ok'=>['✓ Pronto! Agora você apoia esse streamer.','var(--moss)'],
                'switched'=>['✓ Streamer trocado com sucesso.','var(--moss)'],
                'already'=>['Você já apoia esse streamer.','var(--dim)'],
                'blocked'=>['Você já apoia um streamer e a troca está desativada.','var(--hazard)'],
                'invalid'=>['Código de streamer inválido.','var(--rust-2)'],
            ][$affFlash] ?? null;
        ?>
        <div class="stat-card" style="margin: 2rem 0 0; padding: 1.4rem; border-left: 3px solid var(--moss);">
            <div style="font-family: var(--font-display); color: var(--bone); font-size: 1.1rem; margin-bottom: 0.5rem;">🎮 Apoie seu Streamer</div>
            <?php if ($affMsg): ?><p style="color: <?= $affMsg[1] ?>; font-size: 0.85rem; margin: 0 0 0.8rem;"><?= e($affMsg[0]) ?></p><?php endif; ?>
            <?php if (!empty($my_streamer_name)): ?>
                <p style="color: var(--dim); font-size: 0.9rem; margin: 0 0 0.8rem;">Você apoia <strong style="color: var(--moss);">🎮 <?= e($my_streamer_name) ?></strong>. Suas compras ajudam ele.
                    <?php if (empty($affiliate_allow_switch)): ?><br><span style="font-size: 0.8rem;">(vínculo fixo - fale com a staff pra trocar)</span><?php endif; ?></p>
                <?php if (!empty($affiliate_allow_switch)): ?>
                    <form method="POST" action="/apoiar-streamer" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                        <?= \App\Csrf::field() ?>
                        <input type="text" name="affiliate_code" placeholder="Trocar pra outro código" required class="field mono upper grow">
                        <button type="submit" class="btn btn-sm">Trocar</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p style="color: var(--dim); font-size: 0.9rem; margin: 0 0 0.8rem;">Tem um streamer favorito? Digite o código dele pra apoiar - suas compras ajudam ele direto. <strong>Escolha uma vez só.</strong></p>
                <form method="POST" action="/apoiar-streamer" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                    <?= \App\Csrf::field() ?>
                    <input type="text" name="affiliate_code" placeholder="Código do streamer (ex: MEUSTREAMER)" required class="field mono upper grow">
                    <button type="submit" class="btn btn-sm">Apoiar</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php
        $rarPt = ['common'=>'Comum','uncommon'=>'Incomum','rare'=>'Raro','epic'=>'Épico','legendary'=>'Lendário'];
        $rarColor = ['common'=>'var(--dim)','uncommon'=>'var(--moss)','rare'=>'#4a90d9','epic'=>'#a855f7','legendary'=>'var(--hazard)'];
        if (!empty($box_openings)):
            $boxPending = 0;
            foreach ($box_openings as $o) { if (!((($o['status'] ?? '') === 'delivered') || !empty($o['delivered_at']))) $boxPending++; }
        ?>
        <details id="caixas" class="pp-acc<?= $boxPending ? ' pp-acc-pending' : '' ?>"<?= $boxPending ? ' open' : '' ?>>
            <summary>🎁 Histórico de Caixas <span class="pp-acc-count"><?= $boxPending ? ('⏳ ' . $boxPending . ' a receber') : count($box_openings) ?></span></summary>
            <div class="pp-acc-body">
        <table class="purchases-table">
            <thead><tr><th>Data</th><th>Item</th><th class="hide-mobile">Raridade</th><th>Qtd</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($box_openings, 0, $cap) as $o):
                    $delivered = (($o['status'] ?? '') === 'delivered') || !empty($o['delivered_at']);
                    $rk = strtolower($o['rarity'] ?? 'common');
                ?>
                    <tr>
                        <td class="dim"><?= e(fmt_dt($o['created_at'])) ?></td>
                        <td><strong><?= e($o['item_name'] ?: ($o['classname'] ?: '-')) ?></strong><?php if (!empty($o['classname'])): ?><code class="dim" style="font-size:0.72rem; display:block;"><?= e($o['classname']) ?></code><?php endif; ?></td>
                        <td class="hide-mobile"><span style="color:<?= $rarColor[$rk] ?? 'var(--dim)' ?>; font-weight:600; font-size:0.8rem;"><?= e($rarPt[$rk] ?? $rk) ?></span></td>
                        <td class="mono"><?= (int)$o['quantity'] ?>x</td>
                        <td>
                            <?php if ($delivered): ?>
                                <span class="purchase-badge badge-success">✓ Entregue</span>
                            <?php else: ?>
                                <span class="purchase-badge badge-warning">⏳ Pendente</span>
                                <form method="POST" action="/claim-box/<?= (int)$o['id'] ?>" style="display:inline; margin-left:0.4rem;">
                                    <?= \App\Csrf::field() ?>
                                    <button type="submit" class="btn" style="padding:0.25rem 0.7rem; font-size:0.76rem;" title="Receber agora (você precisa estar online no servidor)">📥 Receber</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="color: var(--dim); font-size: 0.8rem; margin-top: 0.6rem;">
            <?php if (!empty($box_claim_enabled)): ?>📥 <strong>Pendente</strong> = clique <strong>Receber</strong> quando estiver <strong>online no servidor</strong>, num lugar seguro. O item cai no chão perto de você.
            <?php else: ?>⏳ <strong>Pendente</strong> = entra automaticamente assim que você estiver <strong>online no servidor</strong> (ou clique <strong>Receber</strong> pra forçar).<?php endif; ?>
        </p>
        <?php if (count($box_openings) > $cap): ?><p class="pp-acc-more">Mostrando as <?= $cap ?> aberturas mais recentes.</p><?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if (!empty($shop_spends)): ?>
        <details class="pp-acc">
            <summary>🎮 Loja in-game (/loja) <span class="pp-acc-count"><?= count($shop_spends) ?></span></summary>
            <div class="pp-acc-body">
        <table class="purchases-table">
            <thead><tr><th>Data</th><th>Item</th><th>Moedas</th><th class="hide-mobile">Saldo após</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($shop_spends, 0, $cap) as $s): ?>
                    <tr>
                        <td class="dim"><?= e(fmt_dt($s['created_at'])) ?></td>
                        <td><strong><?= e(($s['item_icon'] ? $s['item_icon'] . ' ' : '') . ($s['item_name'] ?: $s['sku'])) ?></strong><?php if (!empty($s['item_name']) && $s['item_name'] !== $s['sku']): ?><code class="dim" style="font-size:0.72rem; display:block;"><?= e($s['sku']) ?></code><?php endif; ?></td>
                        <td class="mono" style="color: var(--hazard); font-weight:600;">−<?= number_format((int)$s['coins_spent'], 0, ',', '.') ?></td>
                        <td class="mono hide-mobile"><?= number_format((int)$s['new_balance'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="color: var(--dim); font-size: 0.8rem; margin-top: 0.6rem;">🎮 Compras feitas pelo comando <strong>/loja</strong> no Discord, entregues direto no seu personagem.</p>
        <?php if (count($shop_spends) > $cap): ?><p class="pp-acc-more">Mostrando os <?= $cap ?> mais recentes.</p><?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if (!empty($reward_payouts)):
            $rwCat = ['kills'=>'Kills','kills_infected'=>'Zumbis','kdratio'=>'K/D','playtime'=>'Tempo online','longest_kill'=>'Kill mais longa'];
        ?>
        <details class="pp-acc">
            <summary>🏆 Premiações do ranking <span class="pp-acc-count"><?= count($reward_payouts) ?></span></summary>
            <div class="pp-acc-body">
        <table class="purchases-table">
            <thead><tr><th>Quando</th><th>Categoria</th><th>Lugar</th><th>Moedas</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($reward_payouts, 0, $cap) as $rw): ?>
                    <tr>
                        <td class="dim"><?= e(fmt_dt($rw['created_at'])) ?></td>
                        <td><?= e($rwCat[$rw['category']] ?? $rw['category']) ?></td>
                        <td><strong><?= (int)$rw['place'] ?>º</strong></td>
                        <td class="mono" style="color:var(--moss);font-weight:600;">+<?= number_format((int)$rw['coins'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="color: var(--dim); font-size: 0.8rem; margin-top: 0.6rem;">🏆 Moedas que você ganhou ficando no topo do ranking - caem direto no seu saldo.</p>
        <?php if (count($reward_payouts) > $cap): ?><p class="pp-acc-more">Mostrando as <?= $cap ?> mais recentes.</p><?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <?php if (!empty($achievement_payouts)): ?>
        <details class="pp-acc">
            <summary>🏅 Bônus de conquistas <span class="pp-acc-count"><?= count($achievement_payouts) ?></span></summary>
            <div class="pp-acc-body">
        <table class="purchases-table">
            <thead><tr><th>Quando</th><th>Conquista</th><th>Moedas</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($achievement_payouts, 0, $cap) as $ap): ?>
                    <tr>
                        <td class="dim"><?= e(fmt_dt($ap['created_at'])) ?></td>
                        <td><?= e($ap['name'] ?? $ap['achievement']) ?></td>
                        <td class="mono" style="color:var(--moss);font-weight:600;">+<?= number_format((int)$ap['coins'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="color: var(--dim); font-size: 0.8rem; margin-top: 0.6rem;">🏅 Moedas que você ganhou desbloqueando conquistas - creditadas uma vez por conquista.</p>
        <?php if (count($achievement_payouts) > $cap): ?><p class="pp-acc-more">Mostrando os <?= $cap ?> mais recentes.</p><?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <p style="margin-top: 2rem; color: var(--dim); font-size: 0.85rem;"><?= e(__('profile.support_question')) ?></p>
        <?php endif; /* is_owner */ ?>

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
/* Badge do clã no TOPO do perfil (header) + convites */
.pp-clan-head { display:inline-flex; align-items:center; gap:.5rem; margin-top:.6rem; padding:.4rem .8rem; background:rgba(0,0,0,.35); border:1px solid var(--hazard); border-radius:20px; color:var(--hazard); text-decoration:none; font-size:.85rem; font-weight:600; }
.pp-clan-head:hover { background:var(--hazard); color:#0a0a0a; }
.pp-clan-head img { width:24px; height:24px; border-radius:50%; object-fit:cover; }
.pp-clan-head-none { color:var(--dim); border-color:var(--border); font-weight:400; }
.pp-clan-head-none:hover { background:var(--bg-1); color:var(--bone); }
.pp-invites { background:var(--bg-1); border:1px solid var(--hazard); border-radius:6px; padding:1rem 1.1rem; margin:1rem 0; }
.pp-invite-row { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.5rem 0; border-top:1px solid var(--border); margin-top:.5rem; color:var(--bone); }
/* Históricos em dropdown (fechados por padrão) - perfil não estica mais. */
.pp-acc { border:1px solid var(--border); border-radius:6px; background:var(--bg-1); margin:1rem 0; overflow:hidden; }
.pp-acc > summary { cursor:pointer; list-style:none; display:flex; align-items:center; gap:.55rem; padding:.95rem 1.1rem; font-family:var(--font-display); color:var(--bone); font-size:1.05rem; letter-spacing:.03em; }
.pp-acc > summary::-webkit-details-marker { display:none; }
.pp-acc > summary svg { color:var(--rust); flex:0 0 auto; }
.pp-acc > summary::after { content:'▾'; margin-left:auto; color:var(--dim); transition:transform .2s; }
.pp-acc[open] > summary::after { transform:rotate(180deg); }
.pp-acc > summary:hover { color:var(--hazard); }
.pp-acc-count { font-family:var(--font-mono); font-size:.78rem; color:var(--dim); font-weight:400; }
.pp-acc-body { padding:0 1.1rem 1.1rem; }
.pp-acc-more { color:var(--dim); font-size:.78rem; margin-top:.6rem; text-align:center; opacity:.8; }
/* caixa com item a receber: borda de alerta + contador destacado (já abre aberta) */
.pp-acc-pending { border-color:var(--hazard); box-shadow:0 0 0 1px var(--hazard) inset; }
.pp-acc-pending > summary .pp-acc-count { color:var(--hazard); }
.pp-weapons { display:flex; flex-direction:column; gap:.5rem; }
.pp-weapon { display:flex; align-items:center; gap:.8rem; background:var(--bg-1); border:1px solid var(--border); border-left:3px solid var(--rust); padding:.7rem 1rem; }
.pp-weapon-rank { font-family:var(--font-display); color:var(--hazard); }
.pp-weapon-name { color:var(--bone); font-weight:700; flex:1; }
.pp-weapon-meta { color:var(--dim); font-size:.85rem; }
.pp-updated { text-align:center; color:var(--dim); font-size:.78rem; margin-top:1.2rem; }
.pp-soon { text-align:center; padding:2.5rem 1rem; background:var(--bg-1); border:1px dashed var(--border); border-radius:4px; color:var(--bone); }
.pp-soon .pp-ic { justify-content:center; }
@media (max-width:520px){ .pp-head{ flex-direction:column; text-align:center; } .pp-ic{ justify-content:flex-start; } }

/* Privado (dono) */
.profile-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; }
.profile-card { background:linear-gradient(180deg, var(--bg-2) 0%, var(--bg-1) 100%); border:1px solid var(--border); border-left:3px solid var(--rust); padding:1.2rem 1.4rem; }
.profile-card-label { font-size:0.7rem; color:var(--dim); text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.6rem; }
.profile-card-value { font-family:var(--font-display); font-size:1.8rem; color:var(--bone); line-height:1; }
.profile-card-suffix { color:var(--dim); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.08em; margin-top:0.3rem; }
.purchases-table { width:100%; border-collapse:collapse; background:var(--bg-1); border:1px solid var(--border); font-size:0.9rem; }
.purchases-table th { background:var(--bg-2); color:var(--dim); text-align:left; padding:0.85rem 1rem; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.08em; border-bottom:2px solid var(--rust); }
.purchases-table td { padding:0.85rem 1rem; border-bottom:1px solid var(--border); color:var(--bone); }
.purchases-table .dim { color:var(--dim); }
.purchases-table .mono { font-family:var(--font-mono); }
.purchase-badge { display:inline-block; padding:0.25rem 0.6rem; font-size:0.75rem; font-weight:600; border-radius:2px; letter-spacing:0.04em; }
.badge-success { background:rgba(22,163,74,0.2); color:var(--text-success); }
.badge-warning { background:var(--hazard-border); color:var(--hazard); }
.badge-danger { background:var(--danger-overlay); color:var(--text-danger); }
.badge-info { background:rgba(160,160,160,0.2); color:var(--dim); }
@media (max-width: 760px) { .hide-mobile { display:none; } }
.review-modal { position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.85); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; padding:1rem; }
.review-modal[hidden] { display:none !important; }
.review-modal-card { background:var(--bg-1); border:1px solid var(--border); border-left:3px solid var(--hazard); padding:2rem; max-width:480px; width:100%; border-radius:2px; }
.review-stars-pick { display:flex; gap:0.3rem; margin-bottom:1.2rem; }
.review-stars-pick button { background:transparent; border:none; cursor:pointer; font-size:2.2rem; color:var(--dim); transition:color .15s, transform .15s; line-height:1; }
.review-stars-pick button:hover { transform:scale(1.15); }
.review-stars-pick button.active { color:var(--hazard); text-shadow:0 0 8px rgba(212,160,23,0.5); }
.achievements-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; }
.achievement { background:var(--bg-1); border:1px solid var(--border); padding:1.2rem 1rem; text-align:center; transition:transform .2s, border-color .2s; }
.achievement.unlocked { border-left:3px solid var(--hazard); background:linear-gradient(180deg, var(--hazard-overlay), var(--bg-1)); }
.achievement.unlocked:hover { transform:translateY(-2px); border-color:var(--hazard); }
.achievement.locked { opacity:0.4; filter:grayscale(0.7); }
.achievement-icon { font-size:2.5rem; line-height:1; margin-bottom:0.6rem; }
.achievement.unlocked .achievement-icon { color:var(--hazard); text-shadow:0 0 12px var(--hazard-border); }
.achievement-name { font-family:var(--font-display); color:var(--bone); font-size:0.95rem; margin-bottom:0.3rem; letter-spacing:0.03em; }
.achievement-desc { color:var(--dim); font-size:0.75rem; line-height:1.4; }
</style>

<?php \App\View::endSection(); ?>
