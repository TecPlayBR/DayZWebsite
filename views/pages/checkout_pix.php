<?php
/** @var array $config, $pkg; @var int $purchase_id, $coins_total, $server_id; @var string $steam_id, $qr_code, $qr_base64, $ticket_url, $expires_at; @var float $price_brl, $discount; @var ?string $coupon_code */
?>
<?php \App\View::with('title', 'Pagamento PIX — ' . ($config['settings']['site_name'] ?? $config['site_name'] ?? 'Loja')); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>
<?php
$pubKey = trim($config['mercado_pago']['public_key'] ?? '');
$cardMinBrl = 1.00;                                  // mínimo do MP pra aceitar cartão
$cardAvailable = ($pubKey !== '' && $price_brl >= $cardMinBrl);
$instMinBrl = max(0, \App\Settings::getInt('card_installments_min', 30)); // abaixo disso, só 1x
$allowInstallments = ($price_brl >= $instMinBrl);
// Aba ativa: ao aplicar cupom a página recarrega; volta na aba que o usuário estava.
$activeTab = ((($active_tab ?? 'pix') === 'card') && $cardAvailable) ? 'card' : 'pix';
?>

<section class="hero" style="min-height: 60vh; padding-top: 90px;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.96) 100%), url('<?= asset('img/background5.png') ?>');"></div>
    <div class="container hero-content" style="max-width: 760px;">

        <span class="hero-kicker" style="border-left-color: var(--moss); color: var(--moss); background: rgba(90,108,78,0.08);">// <?= e(__('pix.kicker')) ?></span>
        <h1 class="hero-title" style="font-size: 2rem;"><?= e(__('pix.title_1')) ?> <span class="accent" style="color:var(--moss);"><?= e(__('pix.title_2')) ?></span></h1>

        <!-- Cupom COMPARTILHADO (vale pro Pix E pro Cartão): aplicar regenera o checkout com desconto, sem sair do site -->
        <div class="checkout-coupon">
            <span class="pix-coupon-title">🎟 <?= e(__('pix.coupon_title')) ?></span>
            <form method="POST" action="/shop/checkout" class="pix-coupon-form">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="package_id" value="<?= e($pkg['id']) ?>">
                <input type="hidden" name="steam_id" value="<?= e($steam_id) ?>">
                <input type="hidden" name="server_id" value="<?= (int)$server_id ?>">
                <input type="hidden" name="terms_accepted" value="1">
                <input type="hidden" name="pay_tab" id="coupon-pay-tab" value="<?= e($activeTab) ?>">
                <?php if (!empty($coupon_code)): ?>
                    <div class="pix-coupon-applied">
                        <?= e(__('pix.coupon_label')) ?> <strong><?= e($coupon_code) ?></strong> <?= e(__('pix.coupon_applied_suffix')) ?>
                        <input type="hidden" name="coupon_code" value="">
                        <button type="submit" class="pix-coupon-clear"><?= e(__('pix.coupon_remove')) ?></button>
                    </div>
                <?php else: ?>
                    <div class="pix-coupon-row">
                        <input type="text" name="coupon_code" placeholder="<?= e(__('pix.coupon_ph')) ?>" maxlength="40"
                               oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9_-]/g,'')">
                        <button type="submit"><?= e(__('pix.coupon_apply')) ?></button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <p style="text-align:center; color:var(--dim); font-size:0.8rem; margin:0.2rem 0 1rem;">
<?= __('pix.terms_note', [
                'terms'  => '<a href="/page/terms" target="_blank" rel="noopener" style="color:var(--hazard);">' . e(__('pix.terms_terms')) . '</a>',
                'refund' => '<a href="/page/refund" target="_blank" rel="noopener" style="color:var(--hazard);">' . e(__('pix.terms_refund')) . '</a>',
            ]) ?>
        </p>

        <?php if ($cardAvailable): ?>
        <div class="pay-tabs">
            <button type="button" class="pay-tab-btn <?= $activeTab==='pix'?'active':'' ?>" data-tab="pix">⚡ Pix</button>
            <button type="button" class="pay-tab-btn <?= $activeTab==='card'?'active':'' ?>" data-tab="card">💳 Cartão</button>
        </div>
        <?php endif; ?>

        <div id="tab-pix" class="pay-panel" <?= $activeTab==='card'?'hidden':'' ?>>
        <div class="pix-box">
            <div class="pix-left">
                <?php if ($qr_base64): ?>
                    <img class="pix-qr" src="data:image/png;base64,<?= e($qr_base64) ?>" alt="<?= e(__('pix.qr_alt')) ?>" width="240" height="240">
                <?php else: ?>
                    <div class="pix-qr pix-qr-empty"><?= e(__('pix.qr_unavailable')) ?></div>
                <?php endif; ?>
                <div class="pix-timer" id="pix-timer"><?= e(__('pix.expires_in')) ?> <span id="pix-countdown">30:00</span></div>
            </div>

            <div class="pix-right">
                <div class="pix-summary">
                    <div class="pix-pkg"><?= e($pkg['icon'] ?? '🪙') ?> <?= e($pkg['name']) ?></div>
                    <div class="pix-coins"><strong><?= (int)$coins_total ?></strong> <?= e(__('pix.coins_word')) ?></div>
                    <?php if ($discount > 0): ?>
                        <div class="pix-discount">🎟 <?= e(__('pix.discount_applied', ['v' => number_format($discount, 2, ',', '.')])) ?></div>
                    <?php endif; ?>
                    <div class="pix-amount">R$ <?= number_format($price_brl, 2, ',', '.') ?></div>
                    <div class="pix-steam"><?= e(__('pix.steamid_label')) ?> <code><?= e($steam_id) ?></code></div>
                </div>

                <label class="pix-cc-label"><?= e(__('pix.cc_label')) ?></label>
                <textarea id="pix-code" class="pix-cc" readonly rows="3"><?= e($qr_code) ?></textarea>
                <button type="button" id="pix-copy" class="btn" style="width:100%;">📋 <?= e(__('pix.copy_btn')) ?></button>

                <div class="pix-status" id="pix-status">
                    <span class="pix-spinner"></span> <?= e(__('pix.awaiting')) ?>
                </div>
            </div>
        </div>

        <ol class="pix-steps">
            <li><?= __('pix.step1') ?></li>
            <li><?= __('pix.step2', ['v' => 'R$ ' . number_format($price_brl, 2, ',', '.')]) ?></li>
            <li><?= __('pix.step3') ?></li>
        </ol>
        </div><!-- /tab-pix -->

        <?php if ($cardAvailable): ?>
        <div id="tab-card" class="pay-panel" <?= $activeTab==='card'?'':'hidden' ?>>
            <form id="card-form" class="card-form">
                <?php if (empty($coupon_code)): ?>
                <div class="cf-coupon-warn">💡 Tem cupom? <strong>Aplique o cupom ali em cima antes</strong> de preencher o cartão — ao aplicar, a página recarrega e limpa os campos.</div>
                <?php endif; ?>
                <div class="cf-row">
                    <label>Número do cartão</label>
                    <input type="text" id="cf-number" class="cf-input" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000" maxlength="23">
                </div>
                <div class="cf-grid2">
                    <div><label>Validade</label><input type="text" id="cf-exp" class="cf-input" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/AA" maxlength="7"></div>
                    <div><label>Cód. de segurança</label><input type="text" id="cf-cvv" class="cf-input" inputmode="numeric" autocomplete="cc-csc" placeholder="CVV" maxlength="4"></div>
                </div>
                <div class="cf-row"><label>Nome impresso no cartão</label><input type="text" id="cf-name" class="cf-input" autocomplete="cc-name" placeholder="Como está no cartão"></div>
                <div class="cf-grid2">
                    <div><label>Documento</label><input type="text" class="cf-input" value="CPF" readonly style="opacity:0.7;cursor:default;"></div>
                    <div><label>CPF do titular</label><input type="text" id="cf-docnumber" class="cf-input" inputmode="numeric" placeholder="000.000.000-00" maxlength="14"></div>
                </div>
                <!-- Tipo de documento fixo em CPF (pessoa física) — não mapeado no cardForm pra o MP não trocar -->
                <select id="cf-doctype" style="display:none;"><option value="CPF" selected>CPF</option></select>
                <div class="cf-row"><label>E-mail (recibo)</label><input type="email" id="cf-email" class="cf-input" autocomplete="email" placeholder="voce@email.com"></div>
                <!-- Banco emissor é detectado automaticamente pelo MP (pelo nº do cartão); escondido pra não confundir -->
                <div style="display:none;"><select id="cf-issuer"></select></div>
                <div class="cf-row"><label>Parcelas</label>
                    <?php if ($allowInstallments): ?>
                        <select id="cf-installments" class="cf-input"><option value="1">1x</option></select>
                        <small style="display:block;color:var(--dim);font-size:0.72rem;margin-top:0.3rem;">As opções de parcelamento aparecem depois de preencher o número do cartão.</small>
                    <?php else: ?>
                        <select id="cf-installments" class="cf-input" disabled style="opacity:0.55;cursor:not-allowed;"><option value="1">1x (à vista)</option></select>
                        <small style="display:block;color:var(--dim);font-size:0.72rem;margin-top:0.3rem;">Parcelamento disponível a partir de R$ <?= number_format($instMinBrl, 2, ',', '.') ?>.</small>
                    <?php endif; ?>
                </div>
                <div class="cf-amount">Total: <strong>R$ <?= number_format($price_brl, 2, ',', '.') ?></strong></div>
                <button type="submit" id="cf-submit" class="btn" style="width:100%;" disabled>💳 Pagar com cartão</button>
                <div class="cf-status" id="cf-status"></div>
                <p class="cf-secure">🔒 Os dados do cartão são processados direto pelo Mercado Pago — não passam pelo nosso servidor.</p>
            </form>
        </div>
        <?php endif; ?>

        <div class="hero-actions" style="justify-content:center; gap:1rem;">
            <a href="/shop" class="btn btn-outline">← <?= e(__('pix.back_shop')) ?></a>
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
.pix-coupon-box { background:rgba(212,160,23,0.10); border:1px solid var(--hazard); border-radius:6px; padding:0.7rem 0.85rem; margin:0.4rem 0; }
.pix-coupon-title { display:block; font-size:0.82rem; color:var(--hazard); font-weight:700; letter-spacing:0.02em; margin-bottom:0.5rem; }
.pix-coupon-form { margin:0; }
.pix-coupon-row { display:flex; gap:0.4rem; }
.pix-coupon-row input { flex:1; background:var(--bg-0); border:1px solid var(--border); color:var(--hazard);
    font-family:var(--font-mono); font-size:0.8rem; padding:0.45rem 0.6rem; text-transform:uppercase; letter-spacing:0.05em; }
.pix-coupon-row input:focus { outline:none; border-color:var(--rust); }
.pix-coupon-row button { background:var(--rust); color:var(--bone); border:none; padding:0.45rem 0.9rem; cursor:pointer; font-size:0.8rem; }
.pix-coupon-applied { font-size:0.82rem; color:var(--moss); display:flex; align-items:center; gap:0.5rem; }
.pix-coupon-clear { background:none; border:none; color:var(--dim); text-decoration:underline; cursor:pointer; font-size:0.75rem; }
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
/* Cupom compartilhado acima das abas */
.checkout-coupon { max-width:720px; margin:1.2rem auto 0; background:rgba(212,160,23,0.10); border:1px solid var(--hazard);
    border-radius:6px; padding:0.7rem 0.85rem; text-align:left; }
/* Abas Pix/Cartão + formulário de cartão transparente */
.pay-tabs { display:flex; gap:0.5rem; max-width:720px; margin:1.2rem auto 0; }
.pay-tab-btn { flex:1; background:var(--bg-1); border:1px solid var(--border); color:var(--dim);
    padding:0.65rem; cursor:pointer; font-family:var(--font-display); letter-spacing:0.05em; font-size:0.9rem; }
.pay-tab-btn.active { color:var(--moss); border-color:var(--moss); border-bottom:3px solid var(--moss); background:var(--bg-0); }
.card-form { background:var(--bg-1); border:1px solid var(--border); border-left:3px solid var(--moss);
    padding:1.4rem; max-width:720px; margin:1.5rem auto; text-align:left; }
.card-form label { display:block; font-size:0.7rem; color:var(--dim); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:0.25rem; }
.cf-row { margin-bottom:0.8rem; }
.cf-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:0.8rem; }
.cf-field { height:42px; background:var(--bg-0); border:1px solid var(--border); padding:0 0.6rem; display:flex; align-items:center; }
.cf-input { width:100%; height:42px; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);
    padding:0 0.6rem; font-family:var(--font-mono); font-size:0.85rem; }
.cf-input:focus { outline:none; border-color:var(--moss); }
.cf-amount { font-family:var(--font-display); font-size:1.15rem; color:var(--moss); margin:0.7rem 0; }
.cf-status { font-size:0.85rem; margin-top:0.6rem; min-height:1.2em; color:var(--dim); }
.cf-status.err { color:var(--rust-2); }
.cf-status.ok { color:var(--moss); font-weight:700; }
.cf-secure { font-size:0.72rem; color:var(--dim); margin-top:0.7rem; }
.cf-coupon-warn { background:rgba(212,160,23,0.10); border:1px solid var(--hazard); border-radius:5px;
    padding:0.55rem 0.7rem; font-size:0.78rem; color:var(--hazard); margin-bottom:1rem; }
</style>

<script>
(function(){
    var PURCHASE = <?= (int)$purchase_id ?>;
    var EXPIRES  = <?= json_encode($expires_at) ?>;
    var T = {
        copyDone:  <?= json_encode('✓ ' . __('pix.copy_done')) ?>,
        copyBtn:   <?= json_encode('📋 ' . __('pix.copy_btn')) ?>,
        expired:   <?= json_encode(__('pix.expired')) ?>,
        genNew:    <?= json_encode(__('pix.generate_new')) ?>,
        confirmed: <?= json_encode(__('pix.confirmed')) ?>
    };

    // Copia-e-cola
    var code = document.getElementById('pix-code');
    var copyBtn = document.getElementById('pix-copy');
    copyBtn.addEventListener('click', function(){
        code.select(); code.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(code.value).then(function(){
            copyBtn.textContent = T.copyDone;
            setTimeout(function(){ copyBtn.textContent = T.copyBtn; }, 1800);
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
            statusBox.textContent = '⏱ ' + T.expired + ' ';
            var a = document.createElement('a'); a.href = '/shop'; a.style.color = 'var(--hazard)'; a.textContent = T.genNew;
            statusBox.appendChild(a);
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
                    statusBox.textContent = '✓ ' + T.confirmed;
                    setTimeout(function(){ window.location.href = d.redirect; }, 1200);
                }
            })
            .catch(function(){});
    }
    var pInt = setInterval(poll, 4000);
    poll();
})();
</script>

<?php if ($cardAvailable): ?>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
(function(){
    // Troca de abas Pix <-> Cartão
    var btns = document.querySelectorAll('.pay-tab-btn');
    var panels = { pix: document.getElementById('tab-pix'), card: document.getElementById('tab-card') };
    var couponTab = document.getElementById('coupon-pay-tab');
    btns.forEach(function(b){ b.addEventListener('click', function(){
        btns.forEach(function(x){ x.classList.remove('active'); }); b.classList.add('active');
        Object.keys(panels).forEach(function(k){ if (panels[k]) panels[k].hidden = (k !== b.dataset.tab); });
        if (couponTab) couponTab.value = b.dataset.tab; // ao aplicar cupom, volta nesta aba
    }); });

    var PUBKEY   = <?= json_encode($pubKey) ?>;
    var AMOUNT   = <?= json_encode(number_format($price_brl, 2, '.', '')) ?>;
    var PURCHASE = <?= (int)$purchase_id ?>;
    var CSRF     = <?= json_encode(\App\Csrf::token()) ?>;
    var REDIRECT = <?= json_encode('/player/' . $steam_id) ?>;
    var statusEl = document.getElementById('cf-status');
    var submitBtn = document.getElementById('cf-submit');
    if (!window.MercadoPago) { if (statusEl) statusEl.textContent = 'Não foi possível carregar o pagamento por cartão. Use o Pix.'; return; }

    var mp = new MercadoPago(PUBKEY, { locale: 'pt-BR' });
    var cardForm = mp.cardForm({
        amount: AMOUNT,
        iframe: false, // campos nativos -> autofill do navegador funciona; o cartão ainda é tokenizado no browser e vai direto pro MP (PAN não toca o servidor)
        form: {
            id: 'card-form',
            cardNumber:           { id: 'cf-number', placeholder: '0000 0000 0000 0000' },
            expirationDate:       { id: 'cf-exp',    placeholder: 'MM/AA' },
            securityCode:         { id: 'cf-cvv',    placeholder: 'CVV' },
            cardholderName:       { id: 'cf-name',   placeholder: 'Nome no cartão' },
            issuer:               { id: 'cf-issuer' },
            <?php if ($allowInstallments): ?>installments: { id: 'cf-installments' },<?php endif; ?>
            identificationNumber: { id: 'cf-docnumber', placeholder: 'CPF' },
            cardholderEmail:      { id: 'cf-email',  placeholder: 'voce@email.com' }
        },
        callbacks: {
            onFormMounted: function(error){
                if (error) { statusEl.className='cf-status err'; statusEl.textContent='Erro ao montar o formulário. Recarregue a página.'; return; }
                submitBtn.disabled = false;
            },
            onSubmit: function(event){
                event.preventDefault();
                submitBtn.disabled = true; statusEl.className = 'cf-status'; statusEl.textContent = 'Processando…';
                var d = cardForm.getCardFormData();
                if (!d || !d.token) { statusEl.className='cf-status err'; statusEl.textContent='Revise os dados do cartão.'; submitBtn.disabled=false; return; }
                var body = new URLSearchParams({
                    token: d.token, payment_method_id: d.paymentMethodId, issuer_id: d.issuerId || '',
                    installments: d.installments || '1', doc_type: 'CPF',
                    doc_number: (d.identificationNumber || document.getElementById('cf-docnumber').value || ''),
                    email: (document.getElementById('cf-email').value || ''), _csrf: CSRF
                });
                fetch('/shop/card-pay/' + PURCHASE, {
                    method:'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString()
                })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res.ok && res.status === 'approved') {
                        statusEl.className='cf-status ok'; statusEl.textContent='✓ Aprovado! Creditando suas moedas…';
                        // Confirma a entrega (webhook credita) antes de redirecionar.
                        var tries = 0;
                        var ci = setInterval(function(){
                            tries++;
                            fetch('/shop/status/' + PURCHASE, {cache:'no-store'}).then(function(r){return r.json();}).then(function(s){
                                if (s && s.paid) { clearInterval(ci); window.location.href = (res.redirect || REDIRECT); }
                            }).catch(function(){});
                            if (tries >= 8) { clearInterval(ci); window.location.href = (res.redirect || REDIRECT); }
                        }, 2000);
                    } else if (res.ok && (res.status === 'in_process' || res.status === 'pending')) {
                        statusEl.className='cf-status'; statusEl.textContent = res.msg || 'Pagamento em análise. Assim que aprovar, suas moedas entram automaticamente.';
                    } else {
                        statusEl.className='cf-status err'; statusEl.textContent = res.error || 'Não foi possível processar o cartão.'; submitBtn.disabled=false;
                    }
                })
                .catch(function(){ statusEl.className='cf-status err'; statusEl.textContent='Erro de conexão. Tente de novo.'; submitBtn.disabled=false; });
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php \App\View::endSection(); ?>
