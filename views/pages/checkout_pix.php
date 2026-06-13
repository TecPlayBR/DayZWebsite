<?php
/** @var array $config, $pkg; @var int $purchase_id, $coins_total; @var string $steam_id, $qr_code, $qr_base64, $ticket_url, $expires_at; @var float $price_brl, $discount */
?>
<?php \App\View::with('title', 'Pagamento PIX — ' . ($config['settings']['site_name'] ?? $config['site_name'] ?? 'Loja')); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 60vh; padding-top: 90px;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.96) 100%), url('<?= asset('img/background5.png') ?>');"></div>
    <div class="container hero-content" style="max-width: 760px;">

        <span class="hero-kicker" style="border-left-color: var(--moss); color: var(--moss); background: rgba(90,108,78,0.08);">// PAGAMENTO PIX</span>
        <h1 class="hero-title" style="font-size: 2rem;">Escaneie e pague — <span class="accent" style="color:var(--moss);">sem sair daqui</span></h1>

        <div class="pix-box">
            <div class="pix-left">
                <?php if ($qr_base64): ?>
                    <img class="pix-qr" src="data:image/png;base64,<?= e($qr_base64) ?>" alt="QR Code PIX" width="240" height="240">
                <?php else: ?>
                    <div class="pix-qr pix-qr-empty">QR indisponível — use o código copia-e-cola →</div>
                <?php endif; ?>
                <div class="pix-timer" id="pix-timer">expira em <span id="pix-countdown">30:00</span></div>
            </div>

            <div class="pix-right">
                <div class="pix-summary">
                    <div class="pix-pkg"><?= e($pkg['icon'] ?? '🪙') ?> <?= e($pkg['name']) ?></div>
                    <div class="pix-coins"><strong><?= (int)$coins_total ?></strong> moedas</div>
                    <?php if ($discount > 0): ?>
                        <div class="pix-discount">🎟 desconto de R$ <?= number_format($discount, 2, ',', '.') ?> aplicado</div>
                    <?php endif; ?>
                    <div class="pix-amount">R$ <?= number_format($price_brl, 2, ',', '.') ?></div>
                    <div class="pix-steam">SteamID: <code><?= e($steam_id) ?></code></div>
                </div>

                <label class="pix-cc-label">PIX copia-e-cola</label>
                <textarea id="pix-code" class="pix-cc" readonly rows="3"><?= e($qr_code) ?></textarea>
                <button type="button" id="pix-copy" class="btn" style="width:100%;">📋 Copiar código PIX</button>

                <div class="pix-status" id="pix-status">
                    <span class="pix-spinner"></span> Aguardando pagamento…
                </div>
            </div>
        </div>

        <ol class="pix-steps">
            <li>Abra o app do seu banco e escolha <strong>PIX → Pagar com QR Code</strong> (ou Copia e Cola).</li>
            <li>Confirme o valor de <strong>R$ <?= number_format($price_brl, 2, ',', '.') ?></strong>.</li>
            <li>Assim que aprovar, <strong>esta página leva você direto pro seu perfil</strong> com o saldo novo — não precisa fazer nada.</li>
        </ol>

        <div class="hero-actions" style="justify-content:center; gap:1rem;">
            <a href="/shop/card/<?= (int)$purchase_id ?>" class="btn btn-outline">💳 Pagar com cartão ou boleto</a>
            <a href="/shop" class="btn btn-outline">← Voltar pra loja</a>
        </div>
    </div>
</section>

<style>
.pix-box { display:flex; gap:1.5rem; flex-wrap:wrap; justify-content:center; align-items:flex-start;
    background: var(--bg-1); border:1px solid var(--border); border-left:3px solid var(--moss);
    padding:1.5rem; margin:1.5rem auto; text-align:left; max-width:720px; }
.pix-left { text-align:center; }
.pix-qr { background:#fff; padding:8px; border-radius:6px; display:block; }
.pix-qr-empty { width:240px; height:240px; display:flex; align-items:center; justify-content:center; color:var(--dim); font-size:0.85rem; background:var(--bg-0); }
.pix-timer { margin-top:0.6rem; font-family:var(--font-mono); font-size:0.8rem; color:var(--dim); }
.pix-timer.urgent { color:var(--rust-2); }
.pix-right { flex:1; min-width:260px; display:flex; flex-direction:column; gap:0.6rem; }
.pix-summary { border-bottom:1px solid var(--border); padding-bottom:0.8rem; margin-bottom:0.4rem; }
.pix-pkg { font-family:var(--font-display); color:var(--bone); letter-spacing:0.04em; }
.pix-coins { color:var(--hazard); font-size:1.1rem; margin:0.2rem 0; }
.pix-discount { color:var(--moss); font-size:0.8rem; }
.pix-amount { font-family:var(--font-display); font-size:2rem; color:var(--moss); margin:0.3rem 0; }
.pix-steam { font-size:0.75rem; color:var(--dim); }
.pix-steam code { color:var(--bone); }
.pix-cc-label { font-size:0.78rem; color:var(--dim); text-transform:uppercase; letter-spacing:0.08em; }
.pix-cc { width:100%; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);
    font-family:var(--font-mono); font-size:0.72rem; padding:0.6rem; resize:none; word-break:break-all; }
.pix-status { display:flex; align-items:center; gap:0.5rem; font-size:0.9rem; color:var(--dim); margin-top:0.3rem; }
.pix-status.paid { color:var(--moss); font-weight:700; }
.pix-status.expired { color:var(--rust-2); }
.pix-spinner { width:14px; height:14px; border:2px solid var(--border); border-top-color:var(--moss);
    border-radius:50%; animation:pix-spin 0.8s linear infinite; display:inline-block; }
.pix-status.paid .pix-spinner, .pix-status.expired .pix-spinner { display:none; }
@keyframes pix-spin { to { transform:rotate(360deg); } }
.pix-steps { max-width:620px; margin:1rem auto; color:var(--bone); font-size:0.9rem; line-height:1.7; padding-left:1.4rem; }
.pix-steps strong { color:var(--hazard); }
</style>

<script>
(function(){
    var PURCHASE = <?= (int)$purchase_id ?>;
    var EXPIRES  = <?= json_encode($expires_at) ?>;

    // Copia-e-cola
    var code = document.getElementById('pix-code');
    var copyBtn = document.getElementById('pix-copy');
    copyBtn.addEventListener('click', function(){
        code.select(); code.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(code.value).then(function(){
            copyBtn.textContent = '✓ Código copiado!';
            setTimeout(function(){ copyBtn.textContent = '📋 Copiar código PIX'; }, 1800);
        }).catch(function(){ document.execCommand('copy'); });
    });

    // Contagem regressiva até expirar
    var expMs = Date.parse(EXPIRES);
    var cd = document.getElementById('pix-countdown');
    var timerBox = document.getElementById('pix-timer');
    var statusBox = document.getElementById('pix-status');
    var expired = false;
    function tick(){
        if (expired) return;
        var left = Math.max(0, Math.floor((expMs - Date.now())/1000));
        var m = Math.floor(left/60), s = left%60;
        cd.textContent = (m<10?'0':'')+m+':'+(s<10?'0':'')+s;
        if (left <= 60) timerBox.classList.add('urgent');
        if (left <= 0){
            expired = true;
            statusBox.className = 'pix-status expired';
            statusBox.innerHTML = '⏱ PIX expirado. <a href="/shop" style="color:var(--hazard)">Gerar novo</a>';
        }
    }
    tick(); var tInt = setInterval(tick, 1000);

    // Polling do pagamento
    var done = false;
    function poll(){
        if (done || expired) return;
        fetch('/shop/status/' + PURCHASE, {cache:'no-store'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d && d.paid && d.redirect){
                    done = true; clearInterval(tInt); clearInterval(pInt);
                    statusBox.className = 'pix-status paid';
                    statusBox.innerHTML = '✓ Pagamento confirmado! Indo pro seu perfil…';
                    setTimeout(function(){ window.location.href = d.redirect; }, 1200);
                }
            })
            .catch(function(){});
    }
    var pInt = setInterval(poll, 4000);
    poll();
})();
</script>

<?php \App\View::endSection(); ?>
