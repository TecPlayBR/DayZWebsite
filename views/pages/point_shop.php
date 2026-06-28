<?php /** @var array $config, $by_cat; @var ?array $steam_user; @var int $points; @var bool $is_vip */ ?>
<?php $psSite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'; ?>
<?php \App\View::with('title', 'Loja de Pontos — ' . $psSite); ?>
<?php \App\View::with('description', 'Troque seus pontos por itens in-game no ' . $psSite . ' — armas, kits e mais, entregues direto no seu personagem (DayZ).'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background3.png'); ?>
<?php \App\View::section('content'); ?>
<?php
$ok = match($_GET['ok'] ?? '') {
    'entregue' => '✅ Comprado e entregue in-game! Confere teu personagem.',
    'fila'     => '✅ Comprado! Será entregue assim que você entrar no servidor (ou perto do restart passar).',
    default => '',
};
$err = match($_GET['err'] ?? '') {
    'no_points' => 'Pontos insuficientes pra esse item.',
    'vip_only'  => 'Esse item é exclusivo pra VIP.',
    'not_found' => 'Item indisponível.',
    'rate'      => 'Calma — muitas compras seguidas. Aguarde um pouco.',
    'csrf'      => 'Sessão expirada, tente de novo.',
    default => '',
};
?>

<section class="hero" style="min-height:32vh;padding-bottom:1.5rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.55) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// LOJA DE PONTOS</span>
        <h1 class="hero-title">Troque seus <span class="accent">Pontos</span></h1>
        <?php if ($steam_user): ?>
            <p style="color:var(--dim);">Seu saldo: <strong style="color:var(--moss);">⭐ <?= number_format($points,0,',','.') ?> pontos</strong><?php if ($is_vip): ?> · <span style="color:var(--hazard);">VIP ativo 🔓</span><?php endif; ?></p>
        <?php else: ?>
            <p style="color:var(--dim);">Entre com Steam pra ver seu saldo e comprar.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width:1100px;">
        <?php if ($ok): ?><div class="ps-flash ok"><?= e($ok) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="ps-flash err"><?= e($err) ?></div><?php endif; ?>

        <!-- FAQ / como funciona -->
        <details class="ps-faq">
            <summary>⭐ O que são pontos e como ganhar?</summary>
            <div class="ps-faq-body">
                <p><strong>Pontos</strong> são uma moeda separada das moedas da loja. Você ganha <strong>abrindo caixas</strong> (cada caixa concede pontos — a grátis diária também). Aqui você gasta esses pontos em itens que são <strong>entregues direto no seu personagem in-game</strong>.</p>
                <p>Se você estiver <strong>online</strong>, o item cai na hora. Se estiver offline, fica na fila e entra quando você logar. Itens com 🔒 são só pra quem tem <strong>VIP</strong>.</p>
                <p><a href="/caixas" style="color:var(--hazard);">→ Abrir caixas pra ganhar pontos</a></p>
            </div>
        </details>

        <?php if (empty($by_cat)): ?>
            <div style="text-align:center;color:var(--dim);padding:3rem 1rem;background:var(--bg-1);border:1px dashed var(--border);border-radius:6px;">
                A loja de pontos ainda não tem itens. Volte em breve. ⭐
            </div>
        <?php else: ?>
            <?php foreach ($by_cat as $cat => $list): ?>
                <h2 class="ps-cat"><?= e($cat) ?></h2>
                <div class="ps-grid">
                    <?php foreach ($list as $it):
                        $cost = (int)$it['point_cost'];
                        $locked = (int)$it['vip_only'] === 1 && !$is_vip;
                        $broke  = $steam_user && $points < $cost;
                    ?>
                        <div class="ps-card<?= (int)$it['vip_only']?' ps-vip':'' ?>">
                            <?php if ((int)$it['vip_only']): ?><span class="ps-badge">🔒 VIP</span><?php endif; ?>
                            <div class="ps-img">
                                <?php if (!empty($it['image'])): ?><img src="<?= e($it['image']) ?>" alt="<?= e($it['name']) ?>" loading="lazy"><?php else: ?><span class="ps-img-fb">⭐</span><?php endif; ?>
                            </div>
                            <h3 class="ps-name"><?= e($it['name']) ?></h3>
                            <?php if (!empty($it['description'])): ?><p class="ps-desc"><?= e($it['description']) ?></p><?php endif; ?>
                            <?php if (!empty($it['attachments'])): ?>
                                <p class="ps-kit">🔧 Kit: <?= e(implode(', ', array_map(fn($a) => $a['classname'], $it['attachments']))) ?></p>
                            <?php endif; ?>
                            <div class="ps-foot">
                                <span class="ps-cost">⭐ <?= number_format($cost,0,',','.') ?></span>
                                <?php if (!$steam_user): ?>
                                    <a href="/auth/steam" class="btn btn-sm btn-steam">Entrar</a>
                                <?php elseif ($locked): ?>
                                    <span class="ps-lock">Só VIP</span>
                                <?php elseif ($broke): ?>
                                    <span class="ps-lock">Sem pontos</span>
                                <?php else: ?>
                                    <form method="POST" action="/pontos/comprar" onsubmit="return confirm('Comprar <?= e(addslashes($it['name'])) ?> por <?= $cost ?> pontos?');" style="margin:0;">
                                        <?= \App\Csrf::field() ?><input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                                        <button class="btn btn-sm">Comprar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<style>
.ps-flash { padding:.8rem 1.1rem; border-radius:4px; margin-bottom:1.2rem; }
.ps-flash.ok { background:rgba(90,108,78,0.18); border-left:3px solid var(--moss); color:var(--text-success); }
.ps-flash.err { background:rgba(231,76,60,0.18); border-left:3px solid var(--rust-2); color:var(--text-danger); }
.ps-faq { background:var(--bg-1); border:1px solid var(--border); border-radius:6px; margin-bottom:2rem; }
.ps-faq summary { cursor:pointer; padding:1rem 1.2rem; font-family:var(--font-display); color:var(--bone); }
.ps-faq-body { padding:0 1.2rem 1.2rem; color:var(--dim); line-height:1.6; font-size:.9rem; }
.ps-cat { font-family:var(--font-display); color:var(--bone); font-size:1.15rem; border-bottom:2px solid var(--rust); padding-bottom:.4rem; margin:2rem 0 1.2rem; }
.ps-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(min(240px,100%),1fr)); gap:1.2rem; }
.ps-card { position:relative; background:var(--bg-1); border:1px solid var(--border); border-radius:8px; padding:1.1rem; display:flex; flex-direction:column; }
.ps-card.ps-vip { border-color:var(--hazard-border); }
.ps-badge { position:absolute; top:.6rem; right:.6rem; background:var(--hazard-border); color:var(--hazard); font-size:.7rem; padding:.2rem .5rem; border-radius:2px; font-family:var(--font-mono); }
.ps-img { height:120px; display:flex; align-items:center; justify-content:center; margin-bottom:.7rem; }
.ps-img img { max-width:100%; max-height:120px; object-fit:contain; }
.ps-img-fb { font-size:3rem; opacity:.5; }
.ps-name { font-family:var(--font-display); color:var(--bone); font-size:1rem; margin:0 0 .3rem; }
.ps-desc { color:var(--dim); font-size:.82rem; line-height:1.4; margin:0 0 .5rem; }
.ps-kit { color:var(--dim); font-size:.72rem; font-family:var(--font-mono); margin:0 0 .6rem; overflow-wrap:break-word; }
.ps-foot { margin-top:auto; display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding-top:.6rem; }
.ps-cost { font-family:var(--font-mono); color:var(--moss); font-weight:700; }
.ps-lock { color:var(--dim); font-size:.8rem; font-style:italic; }
</style>
<?php \App\View::endSection(); ?>
