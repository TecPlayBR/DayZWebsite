<?php
/** @var array $config, $boxes; @var ?array $steam_user; @var int $coins */
?>
<?php \App\View::with('title', 'Caixas — ' . ($config['settings']['site_name'] ?? $config['site_name'] ?? 'Loja')); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background3.png'); ?>
<?php \App\View::section('content'); ?>
<?php
$rarityColor = [
    'common' => '#9aa0a6', 'uncommon' => '#4a9d5b', 'rare' => '#3b7ddd',
    'epic' => '#9b4dca', 'legendary' => '#d4a017',
];
?>

<section class="hero" style="min-height:34vh;padding-bottom:1.5rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.55) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// CAIXAS</span>
        <h1 class="hero-title">Abra. Torça. <span class="accent">Leve pro jogo.</span></h1>
        <?php if ($steam_user): ?>
            <p style="color:var(--dim);">Logado como <strong style="color:var(--bone);"><?= e($steam_user['display_name'] ?? 'Sobrevivente') ?></strong> · saldo: <strong style="color:var(--hazard);" id="coins-balance"><?= number_format($coins,0,',','.') ?></strong> moedas</p>
        <?php else: ?>
            <p style="color:var(--dim);">Entre com Steam pra abrir caixas — o item cai direto no seu personagem.</p>
            <a href="/auth/steam" class="btn btn-steam">Entrar com Steam</a>
        <?php endif; ?>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container">
        <?php if (empty($boxes)): ?>
            <p style="text-align:center;color:var(--dim);padding:3rem 0;">Nenhuma caixa disponível no momento.</p>
        <?php else: ?>
        <div class="caixas-grid">
            <?php foreach ($boxes as $b):
                $daily = (int)$b['is_daily'] === 1;
                $wait = (int)($b['daily_wait'] ?? 0);
            ?>
                <div class="caixa-card">
                    <?php if ($daily): ?><div class="caixa-tag caixa-tag-free">DIÁRIA</div><?php endif; ?>
                    <div class="caixa-img">
                        <?php if (!empty($b['image'])): ?>
                            <img src="<?= e($b['image']) ?>" alt="<?= e($b['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="caixa-img-ph">🎁</div>
                        <?php endif; ?>
                    </div>
                    <h3 class="caixa-name"><?= e($b['name']) ?></h3>
                    <?php if (!empty($b['description'])): ?><p class="caixa-desc"><?= e($b['description']) ?></p><?php endif; ?>
                    <div class="caixa-cost"><?= $daily ? '🆓 Grátis' : ('🪙 ' . (int)$b['cost_coins'] . ' moedas') ?></div>
                    <?php if (!$steam_user): ?>
                        <a href="/auth/steam" class="btn caixa-open">Entrar pra abrir</a>
                    <?php elseif ($daily && $wait > 0): ?>
                        <button class="btn caixa-open" disabled>Volta em <?= $wait >= 3600 ? floor($wait/3600).'h' : ceil($wait/60).'min' ?></button>
                    <?php else: ?>
                        <button class="btn caixa-open" data-slug="<?= e($b['slug']) ?>" data-name="<?= e($b['name']) ?>" data-cost="<?= $daily?0:(int)$b['cost_coins'] ?>">Abrir caixa</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Overlay de abertura -->
<div id="box-overlay" class="box-overlay" hidden>
    <div class="box-modal">
        <div class="box-modal-title" id="box-modal-title">Abrindo…</div>
        <div class="box-reel-wrap">
            <div class="box-reel-marker"></div>
            <div class="box-reel" id="box-reel"></div>
        </div>
        <div class="box-result" id="box-result" hidden></div>
        <button class="btn" id="box-close" hidden>Fechar</button>
    </div>
</div>

<style>
/* CRÍTICO: o atributo hidden precisa vencer o display:flex/.btn abaixo,
   senão o overlay/result/botão ficam sempre visíveis. */
[hidden] { display: none !important; }
.caixas-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:1.4rem; }
.caixa-card { background:linear-gradient(180deg,var(--bg-2),var(--bg-1)); border:1px solid var(--border); border-radius:8px; padding:1.4rem 1.2rem; text-align:center; position:relative; display:flex; flex-direction:column; overflow:hidden; transition:transform .25s, border-color .25s, box-shadow .25s; }
.caixa-card:hover { transform:translateY(-6px); border-color:var(--hazard); box-shadow:0 14px 34px rgba(0,0,0,0.55), 0 0 26px var(--hazard-border); }
/* brilho diagonal que varre no hover */
.caixa-card::after { content:''; position:absolute; top:0; left:-70%; width:45%; height:100%; background:linear-gradient(120deg,transparent,rgba(255,255,255,0.10),transparent); transform:skewX(-20deg); transition:left .6s ease; pointer-events:none; z-index:3; }
.caixa-card:hover::after { left:130%; }
.caixa-tag { position:absolute; top:0.6rem; left:0.6rem; z-index:4; font-size:0.65rem; font-family:var(--font-mono); padding:0.15rem 0.5rem; border-radius:3px; letter-spacing:0.05em; }
.caixa-tag-free { background:var(--moss); color:#fff; box-shadow:0 0 12px rgba(74,157,91,0.5); }
.caixa-img { position:relative; height:150px; display:flex; align-items:center; justify-content:center; margin-bottom:0.8rem; }
/* glow radial pulsante atrás da caixa */
.caixa-img::before { content:''; position:absolute; inset:0; margin:auto; width:130px; height:130px; border-radius:50%; background:radial-gradient(circle, var(--hazard-overlay, rgba(212,160,23,0.28)) 0%, transparent 70%); filter:blur(6px); z-index:0; animation:caixa-glow 4s ease-in-out infinite; }
.caixa-img img, .caixa-img-ph { position:relative; z-index:1; animation:caixa-float 3.6s ease-in-out infinite; transition:transform .25s; }
.caixa-img img { max-height:150px; max-width:90%; object-fit:contain; filter:drop-shadow(0 8px 18px rgba(0,0,0,0.6)); }
.caixa-card:hover .caixa-img img, .caixa-card:hover .caixa-img-ph { transform:scale(1.08); }
.caixa-img-ph { font-size:4.2rem; }
@keyframes caixa-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-7px)} }
@keyframes caixa-glow { 0%,100%{opacity:0.55; transform:scale(0.95)} 50%{opacity:1; transform:scale(1.05)} }
@media (prefers-reduced-motion: reduce) { .caixa-img img, .caixa-img-ph, .caixa-img::before { animation:none; } }
.caixa-name { font-family:var(--font-display); color:var(--bone); font-size:1.05rem; letter-spacing:0.04em; margin-bottom:0.3rem; }
.caixa-desc { font-size:0.8rem; color:var(--dim); margin-bottom:0.6rem; flex:1; }
.caixa-cost { color:var(--hazard); font-family:var(--font-mono); margin:0.5rem 0 0.9rem; }
.caixa-open { width:100%; margin-top:auto; }
.caixa-open:disabled { opacity:0.5; cursor:not-allowed; }

.box-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:9999; display:flex; align-items:center; justify-content:center; }
.box-modal { width:min(900px,94vw); text-align:center; }
.box-modal-title { font-family:var(--font-display); color:var(--bone); letter-spacing:0.2em; text-transform:uppercase; font-size:1.1rem; margin-bottom:1.5rem; }
.box-reel-wrap { position:relative; overflow:hidden; border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding:1rem 0; background:linear-gradient(90deg,rgba(0,0,0,0.6),transparent 15%,transparent 85%,rgba(0,0,0,0.6)); }
.box-reel-marker { position:absolute; top:0; bottom:0; left:50%; width:3px; background:var(--hazard); transform:translateX(-50%); z-index:2; box-shadow:0 0 12px var(--hazard); }
.box-reel { display:flex; gap:8px; will-change:transform; }
.box-cell { flex:0 0 150px; height:150px; border:1px solid var(--border); border-radius:6px; background:var(--bg-1); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:0.4rem; }
.box-cell img { max-height:80px; max-width:80%; object-fit:contain; }
.box-cell-ph { font-size:2.5rem; }
.box-cell-name { font-size:0.72rem; color:var(--bone); padding:0 0.3rem; text-align:center; }
.box-result { margin-top:1.5rem; }
.box-result-card { display:inline-flex; flex-direction:column; align-items:center; gap:0.5rem; padding:1.5rem 2.5rem; border-radius:8px; border:2px solid; background:var(--bg-1); }
.box-result-won { font-family:var(--font-display); color:var(--bone); font-size:1.4rem; }
.box-result-status { font-size:0.85rem; margin-top:0.8rem; }
#box-close { margin-top:1.5rem; }
</style>

<script>
(function(){
    const RC = <?= json_encode($rarityColor) ?>;
    const CSRF = <?= json_encode(\App\Csrf::token()) ?>;
    const overlay = document.getElementById('box-overlay');
    const reel = document.getElementById('box-reel');
    const title = document.getElementById('box-modal-title');
    const result = document.getElementById('box-result');
    const closeBtn = document.getElementById('box-close');
    let busy = false;

    function cell(item){
        const c = document.createElement('div');
        c.className = 'box-cell';
        c.style.borderBottom = '3px solid ' + (RC[item.rarity]||RC.common);
        c.innerHTML = (item.image ? '<img src="'+item.image+'" alt="">' : '<div class="box-cell-ph">🎁</div>')
                    + '<div class="box-cell-name">'+ (item.name||'?') +'</div>';
        return c;
    }
    function pick(pool){ return pool[Math.floor(Math.random()*pool.length)]; }

    async function openBox(btn){
        if (busy) return; busy = true;
        const slug = btn.dataset.slug;
        overlay.hidden = false; result.hidden = true; closeBtn.hidden = true;
        title.textContent = 'SORTEANDO PRÊMIO…'; reel.innerHTML = ''; reel.style.transition='none'; reel.style.transform='translateX(0)';

        let data;
        try {
            const fd = new FormData(); fd.append('_csrf', CSRF);
            const r = await fetch('/caixas/'+slug+'/open', {method:'POST', body:fd});
            data = await r.json();
        } catch(e){ data = {ok:false, error:'Erro de conexão.'}; }

        if (!data.ok){
            if (data.error === 'login'){ window.location.href = data.login_url; return; }
            title.textContent = 'Ops'; result.hidden=false;
            result.innerHTML = '<p style="color:var(--rust-2)">'+ (data.error||'Erro') +'</p>';
            closeBtn.hidden=false; busy=false; return;
        }

        // monta a fita: ~50 células aleatórias + o item ganho na posição 45
        const pool = (data.pool && data.pool.length) ? data.pool : [data.won];
        const WIN_AT = 45, TOTAL = 52;
        const cellW = 158; // 150 + gap 8
        for (let i=0;i<TOTAL;i++){
            reel.appendChild(cell(i===WIN_AT ? data.won : pick(pool)));
        }
        // centraliza a célula vencedora sob o marcador
        const wrapW = reel.parentElement.clientWidth;
        const target = WIN_AT*cellW - (wrapW/2) + (150/2) + (Math.random()*40-20);
        reel.getBoundingClientRect(); // reflow
        requestAnimationFrame(()=>{
            reel.style.transition = 'transform 5s cubic-bezier(0.12,0.7,0.1,1)';
            reel.style.transform = 'translateX(-'+target+'px)';
        });
        setTimeout(()=>{
            title.textContent = 'VOCÊ GANHOU!';
            const col = RC[data.won.rarity]||RC.common;
            const statusTxt = data.status === 'delivered'
                ? '<span style="color:var(--moss)">✓ Entregue no seu personagem in-game!</span>'
                : '<span style="color:var(--hazard)">⏳ Vai cair quando você estiver online (longe do restart).</span>';
            result.innerHTML = '<div class="box-result-card" style="border-color:'+col+'">'
                + (data.won.image?'<img src="'+data.won.image+'" style="max-height:90px">':'<div style="font-size:3rem">🎁</div>')
                + '<div class="box-result-won">'+ data.won.name + (data.won.quantity>1?(' x'+data.won.quantity):'') +'</div>'
                + '<div style="color:'+col+';font-size:0.8rem;text-transform:uppercase;letter-spacing:0.1em">'+data.won.rarity+'</div>'
                + '</div><div class="box-result-status">'+statusTxt+'</div>';
            result.hidden=false; closeBtn.hidden=false;
            if (typeof data.coins==='number'){ const cb=document.getElementById('coins-balance'); if(cb) cb.textContent = data.coins.toLocaleString('pt-BR'); }
            busy=false;
        }, 5200);
    }

    document.querySelectorAll('.caixa-open[data-slug]').forEach(b=>b.addEventListener('click',()=>openBox(b)));
    closeBtn.addEventListener('click',()=>{ overlay.hidden=true; window.location.reload(); });
})();
</script>

<?php \App\View::endSection(); ?>
