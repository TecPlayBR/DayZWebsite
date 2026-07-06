<?php
/** @var array $config, $vip; @var array $durations; @var ?array $steam_user; @var int $coins; @var array $active */
?>
<?php \App\View::with('title', 'VIP - ' . ($config['settings']['site_name'] ?? $config['site_name'] ?? 'Loja')); ?>
<?php \App\View::with('description', 'VIP e Passe de Batalha do ' . ($config['settings']['site_name'] ?? $config['site_name'] ?? 'servidor') . ' - vantagens exclusivas no DayZ, ativadas com as moedas do servidor.'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background3.png'); ?>
<?php \App\View::section('content'); ?>
<?php
// Status ativo por type/tier -> "ativo até".
$activeMap = [];
foreach ($active as $a) {
    $k = $a['type'] . ':' . ($a['tier'] ?? '');
    $activeMap[$k] = $a['expiration_date'];
}
$fmtDate = function ($d) {
    if (!$d) return '';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $d;
};
$flashOk = ['bought' => '✓ Compra concluída! O VIP/Passe vai ser aplicado no servidor no próximo ciclo do agent (poucos minutos).',
            'renewed' => '✓ Renovado! Os dias novos foram somados ao que você já tinha. Aplica no próximo ciclo do agent.'];
$err = $_GET['err'] ?? '';
$errMsg = $err === 'csrf' ? 'Sessão expirada, tente de novo.'
        : ($err === 'rate' ? 'Calma - muitas compras seguidas. Aguarde um instante.'
        : ($err !== '' ? \App\Vip::errorMessage($err) : ''));

// Monta a lista de cards: VIP tiers habilitados + battlepass.
$cards = [];
foreach ($vip['tiers'] as $tierKey => $t) {
    if ($t['enabled'] && $t['prices']) {
        $cards[] = ['type' => 'vip', 'tier' => $tierKey, 'label' => $t['label'], 'desc' => $t['desc'], 'prices' => $t['prices'], 'icon' => '⭐'];
    }
}
if ($vip['battlepass']['enabled'] && $vip['battlepass']['prices']) {
    $cards[] = ['type' => 'battlepass', 'tier' => null, 'label' => $vip['battlepass']['label'], 'desc' => $vip['battlepass']['desc'], 'prices' => $vip['battlepass']['prices'], 'icon' => '🎖️'];
}
?>

<section class="hero" style="min-height:34vh;padding-bottom:1.5rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.55) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// PLANOS</span>
        <h1 class="hero-title">Seja <span class="accent">VIP</span></h1>
        <?php if ($steam_user): ?>
            <p style="color:var(--dim);">Logado como <strong style="color:var(--bone);"><?= e($steam_user['display_name'] ?? 'Jogador') ?></strong> · saldo <strong style="color:var(--hazard);"><?= number_format($coins, 0, ',', '.') ?></strong> moedas</p>
        <?php else: ?>
            <p style="color:var(--dim);">Entre com a Steam pra comprar VIP com suas moedas.</p>
            <a href="/auth/steam" class="btn btn-steam">Entrar com Steam</a>
        <?php endif; ?>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width:1100px;">

        <?php if (isset($_GET['ok']) && isset($flashOk[$_GET['ok']])): ?>
            <div style="background:rgba(90,108,78,0.18);border-left:3px solid var(--moss);color:var(--text-success);padding:0.9rem 1.2rem;margin-bottom:1.5rem;border-radius:4px;">
                <?= e($flashOk[$_GET['ok']]) ?>
            </div>
        <?php endif; ?>
        <?php if ($errMsg): ?>
            <div style="background:rgba(231,76,60,0.18);border-left:3px solid var(--rust-2);color:var(--text-danger);padding:0.9rem 1.2rem;margin-bottom:1.5rem;border-radius:4px;">
                <?= e($errMsg) ?>
            </div>
        <?php endif; ?>

        <?php if ($steam_user && !empty($activeMap)): ?>
            <div style="background:var(--bg-1);border:1px solid var(--border);border-radius:6px;padding:1rem 1.2rem;margin-bottom:1.5rem;">
                <strong style="color:var(--bone);">Seus planos ativos:</strong>
                <span style="color:var(--dim);">
                    <?php $parts = [];
                    foreach ($cards as $c) {
                        $k = $c['type'] . ':' . ($c['tier'] ?? '');
                        if (!empty($activeMap[$k])) $parts[] = e($c['label']) . ' (até ' . e($fmtDate($activeMap[$k])) . ')';
                    }
                    echo $parts ? implode(' · ', $parts) : 'nenhum no momento'; ?>
                </span>
            </div>
        <?php endif; ?>

        <p style="color:var(--dim);font-size:0.9rem;margin-bottom:1.5rem;">
            🪙 Pague com as moedas que você já tem. Sem moedas? <a href="/shop" style="color:var(--hazard);">Compre na Loja →</a><br>
            Renovar antes de expirar <strong>soma</strong> os dias - você nunca perde o que pagou.
        </p>

        <?php if (empty($cards)): ?>
            <p style="text-align:center;color:var(--dim);padding:3rem 0;">Nenhum plano disponível no momento.</p>
        <?php else: ?>
        <div class="vip-grid">
            <?php foreach ($cards as $c):
                $k = $c['type'] . ':' . ($c['tier'] ?? '');
                $activeUntil = $activeMap[$k] ?? null;
            ?>
                <div class="vip-card<?= $activeUntil ? ' vip-card-active' : '' ?>">
                    <?php if ($activeUntil): ?><div class="vip-badge-active">ATIVO até <?= e($fmtDate($activeUntil)) ?></div><?php endif; ?>
                    <div class="vip-card-icon"><?= $c['icon'] ?></div>
                    <h3 class="vip-card-title"><?= e($c['label']) ?></h3>
                    <?php if ($c['desc']): ?><p class="vip-card-desc"><?= e($c['desc']) ?></p><?php endif; ?>

                    <div class="vip-durations">
                        <?php foreach ($durations as $d):
                            $price = $c['prices'][(string)$d] ?? null;
                            if ($price === null) continue;
                            $afford = $steam_user && $coins >= $price;
                        ?>
                            <div class="vip-dur-row">
                                <div class="vip-dur-info">
                                    <span class="vip-dur-days"><?= (int)$d ?> dias</span>
                                    <span class="vip-dur-price">🪙 <?= number_format($price, 0, ',', '.') ?></span>
                                </div>
                                <?php if (!$steam_user): ?>
                                    <a href="/auth/steam" class="btn-mini outline">Entrar</a>
                                <?php elseif ($afford): ?>
                                    <form method="POST" action="/vip/buy" style="margin:0;"
                                          onsubmit="return confirm('Comprar <?= e($c['label']) ?> por <?= (int)$d ?> dias gastando <?= number_format($price,0,',','.') ?> moedas?<?= $activeUntil ? ' (soma aos dias que você já tem)' : '' ?>');">
                                        <?= \App\Csrf::field() ?>
                                        <input type="hidden" name="type" value="<?= e($c['type']) ?>">
                                        <?php if ($c['tier']): ?><input type="hidden" name="tier" value="<?= e($c['tier']) ?>"><?php endif; ?>
                                        <input type="hidden" name="days" value="<?= (int)$d ?>">
                                        <button type="submit" class="btn-mini"><?= $activeUntil ? 'Renovar' : 'Comprar' ?></button>
                                    </form>
                                <?php else: ?>
                                    <span class="btn-mini outline" style="opacity:0.45;cursor:not-allowed;" title="Saldo insuficiente">Sem saldo</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.vip-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(min(260px,100%), 1fr)); gap:1.5rem; }
.vip-card { position:relative; background:var(--bg-1); border:1px solid var(--border); border-radius:8px; padding:1.6rem 1.4rem; display:flex; flex-direction:column; }
.vip-card-active { border-color:var(--moss); box-shadow:0 0 0 1px var(--moss) inset; }
.vip-badge-active { position:absolute; top:0; right:0; background:var(--moss); color:#0a0a0a; font-size:0.66rem; font-weight:700; letter-spacing:0.05em; padding:0.25rem 0.6rem; border-radius:0 8px 0 8px; }
.vip-card-icon { font-size:2.6rem; line-height:1; margin-bottom:0.5rem; }
.vip-card-title { font-family:var(--font-display); color:var(--bone); font-size:1.25rem; letter-spacing:0.03em; margin-bottom:0.4rem; }
.vip-card-desc { color:var(--dim); font-size:0.85rem; line-height:1.5; margin-bottom:1rem; flex-grow:1; }
.vip-durations { display:flex; flex-direction:column; gap:0.6rem; margin-top:auto; }
.vip-dur-row { display:flex; align-items:center; justify-content:space-between; gap:0.8rem; padding:0.6rem 0.8rem; background:var(--bg-0); border:1px solid var(--border); border-radius:5px; }
.vip-dur-info { display:flex; flex-direction:column; }
.vip-dur-days { color:var(--bone); font-weight:600; font-size:0.9rem; }
.vip-dur-price { color:var(--hazard); font-family:var(--font-mono); font-size:0.82rem; }
</style>

<?php \App\View::endSection(); ?>
