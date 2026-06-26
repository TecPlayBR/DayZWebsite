<?php /** @var array $config, $clans; @var ?array $my_clan, $steam_user */ ?>
<?php \App\View::with('title', 'Clãs — ' . ($config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor')); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background2.png'); ?>
<?php \App\View::section('content'); ?>
<?php
$okMsg = match($_GET['ok'] ?? '') {
    'left' => 'Você saiu do clã.', 'disbanded' => 'Clã dissolvido.', default => '',
};
?>
<section class="hero" style="min-height:32vh;padding-bottom:1.5rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.55) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background2.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// FACÇÕES</span>
        <h1 class="hero-title">Clãs do <span class="accent">Servidor</span></h1>
        <p style="color:var(--dim);max-width:620px;">Encontre uma facção, peça pra entrar e domine Chernarus em grupo. Líderes: registrem o clã aqui pra organizar membros e participar dos eventos de clã.</p>
        <div class="hero-actions">
            <?php if ($my_clan): ?>
                <a href="/clan/<?= (int)$my_clan['id'] ?>" class="btn">Meu clã: [<?= e($my_clan['tag']) ?>] <?= e($my_clan['name']) ?></a>
            <?php elseif ($steam_user): ?>
                <a href="/clans/new" class="btn">+ Criar meu clã</a>
            <?php else: ?>
                <a href="/auth/steam" class="btn btn-steam">Entrar com Steam pra criar/entrar</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width:1100px;">
        <?php if ($okMsg): ?><div style="background:rgba(90,108,78,0.18);border-left:3px solid var(--moss);color:var(--text-success);padding:0.8rem 1.1rem;margin-bottom:1.5rem;border-radius:4px;"><?= e($okMsg) ?></div><?php endif; ?>

        <?php if (empty($clans)): ?>
            <p style="text-align:center;color:var(--dim);padding:3rem 0;">Nenhum clã registrado ainda. <?php if ($steam_user): ?>Seja o primeiro — <a href="/clans/new" style="color:var(--hazard);">crie o seu</a >.<?php endif; ?></p>
        <?php else: ?>
        <div class="clan-grid">
            <?php foreach ($clans as $c): ?>
                <a class="clan-card" href="/clan/<?= (int)$c['id'] ?>">
                    <div class="clan-logo">
                        <?php if (!empty($c['logo'])): ?>
                            <img src="<?= e($c['logo']) ?>" alt="<?= e($c['name']) ?>" loading="lazy" decoding="async" onerror="this.outerHTML='<span class=\'clan-logo-fb\'><?= e(mb_strtoupper(mb_substr($c['tag'],0,2))) ?></span>'">
                        <?php else: ?>
                            <span class="clan-logo-fb"><?= e(mb_strtoupper(mb_substr($c['tag'],0,2))) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="clan-card-body">
                        <h3 class="clan-card-name">[<?= e($c['tag']) ?>] <?= e($c['name']) ?></h3>
                        <?php if (!empty($c['description'])): ?><p class="clan-card-desc"><?= e(mb_strimwidth($c['description'], 0, 110, '…')) ?></p><?php endif; ?>
                        <div class="clan-card-meta">👥 <?= (int)$c['member_count'] ?>/<?= (int)$c['member_cap'] ?> membros</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
.clan-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(min(300px,100%), 1fr)); gap:1.2rem; }
.clan-card { display:flex; gap:1rem; align-items:center; background:var(--bg-1); border:1px solid var(--border); border-radius:8px; padding:1.1rem; text-decoration:none; transition:transform .2s, border-color .2s; }
.clan-card:hover { transform:translateY(-3px); border-color:var(--hazard); }
.clan-logo { flex:0 0 auto; width:64px; height:64px; }
.clan-logo img, .clan-logo-fb { width:64px; height:64px; border-radius:8px; object-fit:cover; background:var(--bg-0); border:1px solid var(--border); }
.clan-logo-fb { display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:1.4rem; color:var(--hazard); }
.clan-card-body { min-width:0; }
.clan-card-name { font-family:var(--font-display); color:var(--bone); font-size:1.05rem; letter-spacing:.02em; margin:0 0 .3rem; overflow-wrap:break-word; }
.clan-card-desc { color:var(--dim); font-size:.82rem; line-height:1.4; margin:0 0 .5rem; }
.clan-card-meta { color:var(--hazard); font-family:var(--font-mono); font-size:.8rem; }
</style>
<?php \App\View::endSection(); ?>
