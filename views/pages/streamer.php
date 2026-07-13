<?php
/** @var array $config; @var ?array $streamer; @var array $photos; @var array $videos; @var array $socials;
 *  @var ?array $steam_user; @var ?string $my_streamer_code; @var bool $affiliate_on */
$siteName = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'Servidor');
?>
<?php \App\View::with('title', ($streamer ? $streamer['name'] : 'Streamer') . ' - ' . $siteName); ?>
<?php \App\View::with('description', $streamer ? ('Apoie ' . $streamer['name'] . ' no ' . $siteName . '. Fotos, canais e apoio direto.') : 'Streamer'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="st-wrap">
<?php if (!$streamer): ?>
    <div class="st-empty">
        <h1>Streamer não encontrado</h1>
        <p>Esse código de streamer não existe ou está inativo.</p>
        <a href="/streamers" class="btn btn-outline">Ver streamers</a>
    </div>
<?php else:
    $aff = $_GET['aff'] ?? '';
    $flash = [
        'ok'       => ['moss',   'Pronto! Agora você apoia ' . e($streamer['name']) . '.'],
        'switched' => ['moss',   'Trocado! Agora você apoia ' . e($streamer['name']) . '.'],
        'already'  => ['dim',    'Você já apoia ' . e($streamer['name']) . '.'],
        'blocked'  => ['hazard', 'Você já apoia outro streamer (vínculo fixo - fale com a staff).'],
        'invalid'  => ['hazard', 'Código inválido.'],
    ][$aff] ?? null;
    $linkedCoupon = trim((string) ($streamer['coupon_code'] ?? ''));
    $alreadyMine = $my_streamer_code && (
        strcasecmp($my_streamer_code, $streamer['code']) === 0
        || ($linkedCoupon !== '' && strcasecmp($my_streamer_code, $linkedCoupon) === 0)
    );
    $supCount = \App\Streamer::supporterCount($streamer);
    // Niveis do contador de apoiadores (placas estilo YouTube). Marcos: 10/50/100/250/500/1000.
    $supTiers = [
        ['min' => 1000, 'key' => 'diamante',  'label' => 'Diamante'],
        ['min' => 500,  'key' => 'rubi',      'label' => 'Rubi'],
        ['min' => 250,  'key' => 'esmeralda', 'label' => 'Esmeralda'],
        ['min' => 100,  'key' => 'ouro',      'label' => 'Ouro'],
        ['min' => 50,   'key' => 'prata',     'label' => 'Prata'],
        ['min' => 10,   'key' => 'bronze',    'label' => 'Bronze'],
        ['min' => 1,    'key' => 'rosa',      'label' => ''],
    ];
    $supTier = 'rosa'; $supTierLabel = '';
    foreach ($supTiers as $st) { if ($supCount >= $st['min']) { $supTier = $st['key']; $supTierLabel = $st['label']; break; } }
    // Cor de marca por plataforma (botoes de rede)
    $brand = [
        'youtube' => '#ff0033', 'twitch' => '#9146ff', 'kick' => '#53fc18', 'discord' => '#5865f2',
        'instagram' => '#e1306c', 'tiktok' => '#25f4ee', 'twitter' => '#1d9bf0', 'facebook' => '#1877f2',
    ];
?>
    <?php if ($flash): ?>
        <div class="st-flash" style="border-left-color:var(--<?= $flash[0] ?>);"><?= $flash[1] ?></div>
    <?php endif; ?>

    <div class="st-hero">
        <?php if (!empty($streamer['avatar_url'])): ?>
            <div class="st-avatar" style="background-image:url('<?= e($streamer['avatar_url']) ?>');"></div>
        <?php endif; ?>
        <div class="st-info">
            <span class="st-kicker">// STREAMER PARCEIRO</span>
            <h1 class="st-name"><?= e($streamer['name']) ?></h1>
            <?php if ($supCount > 0): ?>
                <div class="st-supporters st-tier-<?= $supTier ?>"<?= $supTierLabel ? ' title="Nivel ' . e($supTierLabel) . '"' : '' ?>>
                    <span class="st-sup-heart">&#10084;</span>
                    <span class="st-sup-count"><?= number_format($supCount, 0, ',', '.') ?></span>
                    <span class="st-sup-word"><?= $supCount === 1 ? 'apoiador' : 'apoiadores' ?></span>
                    <?php if ($supTierLabel): ?><span class="st-tier-label"><?= e($supTierLabel) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($streamer['bio'])): ?>
                <p class="st-bio"><?= nl2br(e($streamer['bio'])) ?></p>
            <?php endif; ?>

            <?php if ($affiliate_on): ?>
                <div class="st-actions">
                    <?php if ($alreadyMine): ?>
                        <span class="st-btn st-btn-mine">&#10004; Você já apoia</span>
                    <?php elseif (!$steam_user): ?>
                        <a href="/auth/steam" class="st-btn st-btn-primary">Entrar pra apoiar</a>
                    <?php else: ?>
                        <form method="POST" action="/apoiar-streamer" style="margin:0;">
                            <?= \App\Csrf::field() ?>
                            <input type="hidden" name="affiliate_code" value="<?= e($streamer['code']) ?>">
                            <input type="hidden" name="back" value="/streamer/<?= e(strtolower($streamer['code'])) ?>">
                            <button type="submit" class="st-btn st-btn-primary">&#10084; Apoiar <?= e($streamer['name']) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($socials)): ?>
                <div class="st-socials">
                    <?php foreach ($socials as $key => $soc): $col = $brand[$key] ?? 'var(--hazard)'; ?>
                        <a href="<?= e($soc['url']) ?>" target="_blank" rel="noopener" class="st-soc" style="--sc:<?= $col ?>;">
                            <span class="st-soc-dot"></span><?= e($soc['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($photos): ?>
        <h2 class="st-h2">Galeria</h2>
        <div class="st-gallery" id="st-gallery">
            <?php foreach ($photos as $idx => $ph): ?>
                <a href="<?= e($ph) ?>" class="st-shot" data-idx="<?= $idx ?>" style="background-image:url('<?= e($ph) ?>');" aria-label="Foto <?= $idx + 1 ?>"></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($videos): ?>
        <h2 class="st-h2">Vídeos</h2>
        <div class="st-videos">
            <?php foreach ($videos as $v): ?>
                <a href="<?= e($v) ?>" target="_blank" rel="noopener" class="st-video">&#9654; <?= e($v) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($photos): ?>
    <!-- Lightbox -->
    <div class="st-lb" id="st-lb" aria-hidden="true">
        <button class="st-lb-close" id="st-lb-close" aria-label="Fechar">&times;</button>
        <button class="st-lb-nav st-lb-prev" id="st-lb-prev" aria-label="Anterior">&#8249;</button>
        <img class="st-lb-img" id="st-lb-img" src="" alt="">
        <button class="st-lb-nav st-lb-next" id="st-lb-next" aria-label="Proxima">&#8250;</button>
    </div>
    <?php endif; ?>
<?php endif; ?>
</section>

<style>
.st-wrap { max-width:960px; margin:0 auto; padding:7rem 1.2rem 3.5rem; }
.st-empty { text-align:center; padding:3rem 0; }
.st-empty h1 { color:var(--bone); }
.st-empty p { color:var(--dim); margin:.5rem 0 1.2rem; }
.st-flash { margin-bottom:1.6rem; padding:.85rem 1.1rem; border-radius:10px; border:1px solid var(--border); border-left:3px solid var(--hazard); color:var(--bone); background:var(--bg-2); }

.st-hero { display:flex; gap:2rem; align-items:flex-start; background:linear-gradient(135deg,var(--bg-2),var(--bg-1)); border:1px solid var(--border); border-radius:16px; padding:2rem; box-shadow:0 18px 50px rgba(0,0,0,.45); }
.st-avatar { flex:0 0 168px; width:168px; height:168px; border-radius:16px; background-size:cover; background-position:center; border:2px solid var(--hazard); box-shadow:0 0 0 4px rgba(0,0,0,.3); }
.st-info { flex:1; min-width:0; }
.st-kicker { font-family:var(--font-mono,monospace); font-size:.74rem; letter-spacing:.16em; color:var(--hazard); }
.st-name { font-family:var(--font-display); color:var(--bone); font-size:2.4rem; line-height:1.05; margin:.25rem 0 .35rem; }
.st-supporters { --tc:#ff4d6d; display:inline-flex; align-items:center; gap:.4rem; color:var(--tc); font-weight:800; font-size:.9rem; background:rgba(0,0,0,.28); border:1px solid var(--tc); padding:.28rem .75rem; border-radius:20px; margin-bottom:.85rem; box-shadow:0 0 10px -3px var(--tc); transition:box-shadow .2s, transform .2s; }
.st-supporters:hover { transform:translateY(-1px); box-shadow:0 0 16px -1px var(--tc); }
.st-sup-heart { animation:st-heart 1.6s ease-in-out infinite; }
.st-sup-count { font-family:var(--font-mono,monospace); }
.st-sup-word { font-weight:600; opacity:.9; }
.st-tier-label { font-size:.62rem; letter-spacing:.09em; text-transform:uppercase; background:var(--tc); color:#0a0a0a; padding:.1rem .42rem; border-radius:10px; font-weight:800; margin-left:.15rem; }
@keyframes st-heart { 0%,100%{ transform:scale(1);} 50%{ transform:scale(1.18);} }
/* Placas / niveis (marcos 10/50/100/250/500/1000) */
.st-tier-rosa      { --tc:#ff4d6d; }
.st-tier-bronze    { --tc:#cd7f32; }
.st-tier-prata     { --tc:#cfd8e3; }
.st-tier-ouro      { --tc:#ffcf33; }
.st-tier-esmeralda { --tc:#2ec76a; }
.st-tier-rubi      { --tc:#e0115f; }
.st-tier-ouro, .st-tier-esmeralda, .st-tier-rubi { animation:st-glow 2.4s ease-in-out infinite; }
@keyframes st-glow { 0%,100%{ box-shadow:0 0 8px -3px var(--tc);} 50%{ box-shadow:0 0 18px 0 var(--tc);} }
.st-tier-diamante { --tc:#8fe9ff; color:#dff8ff; border-color:#bff3ff; background:linear-gradient(110deg, rgba(143,233,255,.15), rgba(180,160,255,.15), rgba(143,233,255,.15)); background-size:220% 100%; animation:st-shimmer 3s linear infinite, st-glow 2.4s ease-in-out infinite; }
.st-tier-diamante .st-tier-label { background:linear-gradient(110deg,#8fe9ff,#c9b8ff); }
@keyframes st-shimmer { 0%{ background-position:0% 0;} 100%{ background-position:220% 0;} }
@media (prefers-reduced-motion: reduce){ .st-sup-heart, .st-supporters { animation:none !important; } }
.st-bio { color:var(--dim); font-size:.98rem; line-height:1.65; margin:.2rem 0 1.2rem; }

.st-actions { margin-bottom:1.1rem; }
.st-btn { display:inline-flex; align-items:center; gap:.4rem; padding:.8rem 1.6rem; border-radius:10px; font-weight:700; font-size:.95rem; text-decoration:none; cursor:pointer; border:none; font-family:inherit; transition:transform .15s, box-shadow .15s, background .15s; }
.st-btn-primary { background:linear-gradient(135deg,var(--hazard),var(--rust,#b23a2e)); color:#fff; box-shadow:0 8px 22px rgba(178,58,46,.35); }
.st-btn-primary:hover { transform:translateY(-2px); box-shadow:0 12px 28px rgba(178,58,46,.5); }
.st-btn-mine { background:rgba(107,142,90,0.14); color:var(--moss); border:1px solid rgba(107,142,90,0.4); cursor:default; }

.st-socials { display:flex; gap:.6rem; flex-wrap:wrap; }
.st-soc { display:inline-flex; align-items:center; gap:.5rem; padding:.5rem .95rem; border-radius:9px; text-decoration:none; font-size:.85rem; font-weight:700; color:var(--bone); background:var(--bg-0,#0d0a0b); border:1px solid var(--border); transition:background .15s, border-color .15s, color .15s, transform .15s; }
.st-soc-dot { width:9px; height:9px; border-radius:50%; background:var(--sc); box-shadow:0 0 8px var(--sc); }
.st-soc:hover { background:var(--sc); border-color:var(--sc); color:#fff; transform:translateY(-2px); }
.st-soc:hover .st-soc-dot { background:#fff; box-shadow:none; }

.st-h2 { color:var(--bone); font-family:var(--font-display); font-size:1.3rem; margin:2.2rem 0 1rem; }
.st-gallery { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:1rem; }
.st-shot { display:block; aspect-ratio:16/10; border-radius:12px; background-size:cover; background-position:center; border:1px solid var(--border); cursor:zoom-in; transition:transform .18s, border-color .18s; }
.st-shot:hover { transform:scale(1.02); border-color:var(--hazard); }
.st-videos { display:flex; flex-direction:column; gap:.5rem; }
.st-video { color:var(--hazard); text-decoration:none; font-size:.9rem; }
.st-video:hover { text-decoration:underline; }

.st-lb { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.92); align-items:center; justify-content:center; }
.st-lb.open { display:flex; }
.st-lb-img { max-width:90vw; max-height:86vh; border-radius:10px; box-shadow:0 20px 60px rgba(0,0,0,.6); }
.st-lb-close { position:absolute; top:18px; right:24px; background:none; border:none; color:#fff; font-size:2.6rem; line-height:1; cursor:pointer; opacity:.8; }
.st-lb-close:hover { opacity:1; }
.st-lb-nav { position:absolute; top:50%; transform:translateY(-50%); background:rgba(0,0,0,.4); border:1px solid rgba(255,255,255,.2); color:#fff; width:52px; height:52px; border-radius:50%; font-size:2rem; line-height:1; cursor:pointer; opacity:.85; }
.st-lb-nav:hover { opacity:1; background:var(--hazard); border-color:var(--hazard); }
.st-lb-prev { left:20px; } .st-lb-next { right:20px; }

@media (max-width:600px){
  .st-hero { flex-direction:column; align-items:center; text-align:center; padding:1.5rem; }
  .st-avatar { flex-basis:140px; width:140px; height:140px; }
  .st-name { font-size:1.9rem; }
  .st-socials, .st-actions { justify-content:center; }
  .st-lb-nav { width:44px; height:44px; }
}
</style>
<script>
(function(){
  var gal=document.getElementById('st-gallery'); if(!gal) return;
  var shots=[].slice.call(gal.querySelectorAll('.st-shot'));
  var srcs=shots.map(function(a){ return a.getAttribute('href'); });
  var lb=document.getElementById('st-lb'), img=document.getElementById('st-lb-img');
  var cur=0;
  function open(i){ cur=(i+srcs.length)%srcs.length; img.src=srcs[cur]; lb.classList.add('open'); lb.setAttribute('aria-hidden','false'); }
  function close(){ lb.classList.remove('open'); lb.setAttribute('aria-hidden','true'); img.src=''; }
  shots.forEach(function(a,i){ a.addEventListener('click', function(e){ e.preventDefault(); open(i); }); });
  document.getElementById('st-lb-close').addEventListener('click', close);
  document.getElementById('st-lb-prev').addEventListener('click', function(e){ e.stopPropagation(); open(cur-1); });
  document.getElementById('st-lb-next').addEventListener('click', function(e){ e.stopPropagation(); open(cur+1); });
  lb.addEventListener('click', function(e){ if(e.target===lb) close(); });
  document.addEventListener('keydown', function(e){ if(!lb.classList.contains('open')) return;
    if(e.key==='Escape') close(); else if(e.key==='ArrowLeft') open(cur-1); else if(e.key==='ArrowRight') open(cur+1); });
})();
</script>

<?php \App\View::endSection(); ?>
