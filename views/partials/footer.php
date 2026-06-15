<?php /** @var array $config */ ?>
<?php
$settings = $config['settings'] ?? [];
$socials = [
    'discord'   => ['url' => $settings['social_discord']   ?? '', 'label' => 'Discord'],
    'youtube'   => ['url' => $settings['social_youtube']   ?? '', 'label' => 'YouTube'],
    'twitch'    => ['url' => $settings['social_twitch']    ?? '', 'label' => 'Twitch'],
    'kick'      => ['url' => $settings['social_kick']      ?? '', 'label' => 'Kick'],
    'tiktok'    => ['url' => $settings['social_tiktok']    ?? '', 'label' => 'TikTok'],
    'instagram' => ['url' => $settings['social_instagram'] ?? '', 'label' => 'Instagram'],
    'x'         => ['url' => $settings['social_x']         ?? '', 'label' => 'X (Twitter)'],
    'facebook'  => ['url' => $settings['social_facebook']  ?? '', 'label' => 'Facebook'],
    'whatsapp'  => ['url' => $settings['social_whatsapp']  ?? '', 'label' => 'WhatsApp'],
];
$hasSocial = array_filter($socials, fn($s) => !empty($s['url']));
?>
<footer class="site-footer">
    <div class="container">

        <div class="footer-grid">

            <div class="footer-brand">
                <img src="<?= asset('img/logo_semfundo_small.png') ?>" alt="<?= e($settings['site_name'] ?? 'Logo') ?>" width="60" height="60" loading="lazy" decoding="async">
                <?php
                // Tagline multilang: prefere site_tagline_{locale}, fallback pro site_tagline
                // genérico (PT), depois pro hero.subtitle do arquivo de lang.
                $localeKey = 'site_tagline_' . str_replace('-', '', locale()); // ptbr / enus
                $tagline   = $settings[$localeKey] ?? $settings['site_tagline'] ?? __('hero.subtitle');
                ?>
                <p><?= e($tagline) ?></p>

                <?php if ($hasSocial): ?>
                    <div class="social-icons" aria-label="Redes sociais">
                        <?php foreach ($hasSocial as $name => $s): ?>
                            <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label']) ?>" title="<?= e($s['label']) ?>" class="social-icon social-<?= $name ?>">
                                <?php include __DIR__ . '/social-icons/' . $name . '.svg.php'; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="footer-col">
                <h4><?= e(__('footer.about')) ?></h4>
                <ul>
                    <li><a href="/"><?= e(__('nav.home')) ?></a></li>
                    <li><a href="/shop"><?= e(__('nav.shop')) ?></a></li>
                    <li><a href="/rules"><?= e(__('nav.rules')) ?></a></li>
                    <li><a href="/page/faq"><?= e(__('footer.faq')) ?></a></li>
                    <li><a href="/depoimentos"><?= e(__('footer.testimonials')) ?></a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4><?= e(__('footer.legal')) ?></h4>
                <ul>
                    <li><a href="/page/terms"><?= e(__('footer.terms')) ?></a></li>
                    <li><a href="/page/privacy"><?= e(__('footer.privacy')) ?></a></li>
                    <li><a href="/page/refund"><?= e(__('footer.refund')) ?></a></li>
                </ul>
            </div>

            <?php if (($config['show_payment_methods'] ?? true)): ?>
            <div class="footer-col">
                <h4><?= e(__('footer.payments')) ?></h4>
                <div class="payment-logos">
                    <img src="<?= asset('img/badges/mercadopago.svg') ?>" alt="Mercado Pago" class="pay-logo pay-mp" loading="lazy">
                    <img src="<?= asset('img/badges/pix.png') ?>" alt="PIX" class="pay-logo pay-pix" loading="lazy">
                </div>
                <p class="pay-note">🔒 Pix &amp; Cartão · pagamento 100% seguro</p>
                <div class="security-badges">
                    <img src="<?= asset('img/badges/ssl-seguro.png') ?>" alt="Site SSL Seguro" class="security-badge" loading="lazy">
                </div>
            </div>
            <?php endif; ?>

        </div>

        <?php
        // Newsletter capture: DESLIGADA por padrão (default 0). Ligue com newsletter_enabled = 1.
        // Endpoint POST /api/newsletter-subscribe.
        $newsletterEnabled = (int)($settings['newsletter_enabled'] ?? 0);
        if ($newsletterEnabled):
        ?>
            <div class="newsletter-card" data-newsletter-card>
                <div class="newsletter-card-head">
                    <h3><?= e(__('newsletter.title')) ?></h3>
                    <p><?= e(__('newsletter.subtitle')) ?></p>
                </div>
                <form class="newsletter-card-form" data-newsletter-form action="/api/newsletter-subscribe" method="post" novalidate>
                    <label class="visually-hidden" for="footer-newsletter-email">E-mail</label>
                    <input id="footer-newsletter-email" type="email" name="email" required placeholder="<?= e(__('newsletter.placeholder')) ?>" autocomplete="email">
                    <input type="hidden" name="source" value="footer">
                    <button type="submit" class="btn" data-newsletter-btn><?= e(__('newsletter.submit')) ?></button>
                </form>
                <div class="newsletter-card-msg" data-newsletter-msg aria-live="polite"></div>
            </div>
            <script>
            (function() {
                const card = document.querySelector('[data-newsletter-card]');
                const form = document.querySelector('[data-newsletter-form]');
                const btn  = document.querySelector('[data-newsletter-btn]');
                const msg  = document.querySelector('[data-newsletter-msg]');
                if (!card || !form || !msg || !btn) return;
                const T = {
                    success:     <?= json_encode(__('newsletter.success'), JSON_UNESCAPED_UNICODE) ?>,
                    successHint: <?= json_encode(__('newsletter.success_email_sent'), JSON_UNESCAPED_UNICODE) ?>,
                    invalid: <?= json_encode(__('newsletter.invalid'), JSON_UNESCAPED_UNICODE) ?>,
                    rate:    <?= json_encode(__('newsletter.rate'),    JSON_UNESCAPED_UNICODE) ?>,
                    fail:    <?= json_encode(__('newsletter.fail'),    JSON_UNESCAPED_UNICODE) ?>,
                    offline: <?= json_encode(__('newsletter.offline'), JSON_UNESCAPED_UNICODE) ?>,
                };
                const originalBtnText = btn.textContent;
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    msg.textContent = ''; msg.className = 'newsletter-card-msg';
                    btn.disabled = true; btn.textContent = '…';
                    try {
                        const r = await fetch(form.action, { method: 'POST', body: new FormData(form) });
                        const d = await r.json().catch(() => ({}));
                        if (r.ok && d.ok) {
                            // Sucesso: substitui o card inteiro por estado de confirmação
                            card.classList.add('newsletter-card-success');
                            card.innerHTML = '<div class="newsletter-card-done">'
                                + '<div class="newsletter-card-done-icon">✓</div>'
                                + '<h3>' + T.success + '</h3>'
                                + '<p>' + T.successHint + '</p>'
                                + '</div>';
                            return;
                        }
                        msg.classList.add('error');
                        if      (d.error === 'invalid_email') msg.textContent = T.invalid;
                        else if (d.error === 'rate_limited')  msg.textContent = T.rate;
                        else                                  msg.textContent = T.fail;
                    } catch (err) {
                        msg.classList.add('error');
                        msg.textContent = T.offline;
                    } finally {
                        btn.disabled = false; btn.textContent = originalBtnText;
                    }
                });
            })();
            </script>
            <style>
            /* Newsletter no padrão do site: card com border-left hazard */
            .newsletter-card {
                margin: 2rem 0 0;
                padding: 1.5rem;
                background: var(--bg-1);
                border: 1px solid var(--border);
                border-left: 3px solid var(--hazard);
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }
            .newsletter-card-head h3 {
                margin: 0 0 0.3rem;
                font-family: var(--font-display);
                color: var(--bone);
                font-size: 1.05rem;
                letter-spacing: 0.04em;
            }
            .newsletter-card-head p {
                margin: 0;
                color: var(--dim);
                font-size: 0.85rem;
                line-height: 1.5;
            }
            .newsletter-card-form {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            .newsletter-card-form input[type="email"] {
                flex: 1 1 220px;
                min-width: 0; /* permite shrink em flex pequeno */
                background: var(--bg-0);
                border: 1px solid var(--border);
                color: var(--bone);
                padding: 0.7rem 0.9rem;
                font-family: var(--font-mono);
                font-size: 0.9rem;
                box-sizing: border-box;
            }
            .newsletter-card-form input[type="email"]:focus {
                outline: none;
                border-color: var(--hazard);
            }
            .newsletter-card-form .btn {
                flex: 0 0 auto;
                padding: 0.7rem 1.4rem;
                font-size: 0.9rem;
            }
            .newsletter-card-form .btn:disabled { opacity: 0.6; cursor: wait; }
            .newsletter-card-msg {
                font-family: var(--font-mono);
                font-size: 0.82rem;
                min-height: 1.2em;
                color: var(--dim);
            }
            .newsletter-card-msg.error { color: var(--rust-2); }

            /* Estado "inscrito com sucesso" */
            .newsletter-card-success { border-left-color: var(--moss); background: var(--hazard-overlay); }
            .newsletter-card-done { text-align: center; padding: 0.5rem 0; }
            .newsletter-card-done-icon {
                width: 48px; height: 48px;
                margin: 0 auto 0.8rem;
                border-radius: 50%;
                background: var(--moss);
                color: #0a0405;
                font-size: 1.8rem;
                display: flex; align-items: center; justify-content: center;
                box-shadow: 0 0 18px rgba(74,222,128,0.4);
            }
            .newsletter-card-done h3 {
                margin: 0 0 0.4rem;
                color: var(--text-success);
                font-family: var(--font-display);
                font-size: 1.1rem;
            }
            .newsletter-card-done p {
                margin: 0; color: var(--dim); font-size: 0.85rem; line-height: 1.5;
            }

            /* MOBILE: stack vertical, button full-width */
            @media (max-width: 520px) {
                .newsletter-card-form { flex-direction: column; }
                .newsletter-card-form .btn { width: 100%; }
            }

            .visually-hidden {
                position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
                overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
            }
            </style>
        <?php endif; ?>

        <div class="footer-bottom">
            <div>
                <?= __('footer.copyright', [
                    'year' => date('Y'),
                    'site' => $settings['site_name'] ?? ($config['site_name'] ?? 'TECPLAY')
                ]) ?>
            </div>
            <div class="powered">// <?= e(__('footer.powered')) ?></div>
        </div>
    </div>
</footer>
