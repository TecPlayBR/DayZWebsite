<?php /** @var array $config, $releases */ ?>
<?php $rlSite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'; ?>
<?php \App\View::with('title', 'Novidades & Atualizações - ' . $rlSite); ?>
<?php \App\View::with('description', 'Notas de atualização do ' . $rlSite . ' - o que mudou no servidor DayZ: updates de mods, correções e novidades.'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background3.png'); ?>
<?php \App\View::section('content'); ?>
<?php
$releases = $releases ?? [];
// Cor do selo por categoria.
$rlColor = static function (string $c): string {
    return [
        'novo'        => 'var(--moss)',
        'atualizacao' => 'var(--hazard)',
        'correcao'    => 'var(--rust)',
        'hotfix'      => 'var(--rust-2)',
    ][$c] ?? 'var(--dim)';
};
?>

<section class="hero" style="min-height:32vh;padding-bottom:1.5rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.55) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// NOVIDADES</span>
        <h1 class="hero-title">O que <span class="accent">mudou</span></h1>
        <p class="hero-subtitle">Updates de mods, correções e novidades do <?= e($rlSite) ?>. Fica por dentro do que rolou em cada atualização.</p>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width:820px;">
        <?php if (empty($releases)): ?>
            <div style="text-align:center;color:var(--dim);padding:3rem 1rem;background:var(--bg-1);border:1px dashed var(--border);border-radius:6px;">
                Ainda não há novidades publicadas. Volte em breve. 📢
            </div>
        <?php else: ?>
            <p class="rl-hint">Clique numa atualização pra abrir e ver os detalhes.</p>
            <div class="rl-timeline">
            <?php foreach ($releases as $i => $r):
                $cat  = $r['category'];
                $col  = $rlColor($cat);
                $date = !empty($r['released_at']) ? date('d/m/Y', strtotime($r['released_at'])) : date('d/m/Y', strtotime($r['created_at']));
            ?>
                <details class="rl-card"<?= $i === 0 ? ' open' : '' ?>>
                    <summary class="rl-summary">
                        <div class="rl-head">
                            <span class="rl-badge" style="color:<?= $col ?>;border-color:<?= $col ?>;"><?= e(\App\Releases::catEmoji($cat)) ?> <?= e(\App\Releases::catLabel($cat)) ?></span>
                            <?php if (!empty($r['version'])): ?><span class="rl-ver"><?= e($r['version']) ?></span><?php endif; ?>
                            <span class="rl-date"><?= e($date) ?></span>
                        </div>
                        <div class="rl-titlerow">
                            <h2 class="rl-title"><?= e($r['title']) ?></h2>
                            <span class="rl-toggle" aria-hidden="true">▾</span>
                        </div>
                    </summary>
                    <?php if (!empty($r['body'])): ?><div class="rl-body"><?= $r['body'] // já sanitizado no save ?></div><?php else: ?><div class="rl-body" style="color:var(--dim);">Sem detalhes.</div><?php endif; ?>
                </details>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.rl-hint { color:var(--dim); font-size:.82rem; margin:0 0 1rem; text-align:center; }
.rl-timeline { display:flex; flex-direction:column; gap:1.2rem; }
.rl-card { background:var(--bg-1); border:1px solid var(--border); border-left:3px solid var(--rust); border-radius:8px; overflow:hidden; }
.rl-summary { list-style:none; cursor:pointer; padding:1.1rem 1.4rem; display:block; transition:background .15s; -webkit-tap-highlight-color:transparent; }
.rl-summary::-webkit-details-marker { display:none; }
.rl-summary::marker { content:''; }
.rl-summary:hover { background:var(--bg-2); }
.rl-titlerow { display:flex; align-items:center; justify-content:space-between; gap:.7rem; }
.rl-toggle { color:var(--dim); font-size:1.1rem; line-height:1; flex-shrink:0; transition:transform .2s ease; }
details[open] .rl-toggle { transform:rotate(180deg); color:var(--hazard); }
.rl-summary:hover .rl-title { color:var(--hazard); }
.rl-head { display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; margin-bottom:.5rem; }
.rl-badge { font-family:var(--font-mono); font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; padding:.2rem .55rem; border:1px solid; border-radius:3px; }
.rl-ver { font-family:var(--font-mono); font-size:.8rem; color:var(--bone); background:var(--bg-2); padding:.15rem .5rem; border-radius:3px; }
.rl-date { font-family:var(--font-mono); font-size:.75rem; color:var(--dim); margin-left:auto; }
.rl-title { font-family:var(--font-display); color:var(--bone); font-size:1.2rem; margin:0; transition:color .15s; }
.rl-body { color:var(--dim); line-height:1.65; font-size:.92rem; padding:0 1.4rem 1.3rem; border-top:1px solid var(--border); padding-top:1.1rem; margin-top:.2rem; }
.rl-body h3 { color:var(--bone); font-size:1rem; margin:1rem 0 .4rem; }
.rl-body h4 { color:var(--bone); font-size:.95rem; margin:1.1rem 0 .35rem; }
.rl-body img { max-width:100%; height:auto; display:block; margin:.9rem auto; border-radius:8px; border:1px solid var(--border, rgba(255,255,255,.08)); }
.rl-body ul { margin:.4rem 0 .4rem 1.2rem; }
.rl-body li { margin:.25rem 0; }
.rl-body strong { color:var(--bone); }
.rl-body a { color:var(--hazard); }
@media (max-width:600px){ .rl-date { margin-left:0; width:100%; } }
</style>
<?php \App\View::endSection(); ?>
