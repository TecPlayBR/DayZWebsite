<?php /** @var array $config */ ?>
<?php
$settings = $config['settings'] ?? [];
$socials = [
    'discord'   => ['url' => $settings['social_discord']   ?? '', 'label' => 'Discord'],
    'youtube'   => ['url' => $settings['social_youtube']   ?? '', 'label' => 'YouTube'],
    'instagram' => ['url' => $settings['social_instagram'] ?? '', 'label' => 'Instagram'],
    'facebook'  => ['url' => $settings['social_facebook']  ?? '', 'label' => 'Facebook'],
    'whatsapp'  => ['url' => $settings['social_whatsapp']  ?? '', 'label' => 'WhatsApp'],
];
$hasSocial = array_filter($socials, fn($s) => !empty($s['url']));
?>
<footer class="site-footer">
    <div class="container">

        <div class="footer-grid">

            <div class="footer-brand">
                <img src="<?= asset('img/logo_semfundo.png') ?>" alt="" loading="lazy" decoding="async">
                <p><?= e($settings['site_tagline'] ?? __('hero.subtitle')) ?></p>

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
                    <li><a href="/page/faq">FAQ</a></li>
                    <li><a href="/depoimentos">Depoimentos</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Legal</h4>
                <ul>
                    <li><a href="/page/terms">Termos de Uso</a></li>
                    <li><a href="/page/privacy">Privacidade</a></li>
                    <li><a href="/page/refund">Reembolso</a></li>
                </ul>
            </div>

            <?php if (($config['show_payment_methods'] ?? true)): ?>
            <div class="footer-col">
                <h4><?= e(__('footer.payments')) ?></h4>
                <div class="payment-logos">
                    <img src="<?= asset('img/badges/mercadopago.svg') ?>" alt="Mercado Pago" class="pay-logo pay-mp" loading="lazy">
                    <img src="<?= asset('img/badges/pix.png') ?>" alt="PIX" class="pay-logo pay-pix" loading="lazy">
                </div>
                <div class="security-badges">
                    <img src="<?= asset('img/badges/ssl-seguro.png') ?>" alt="Site SSL Seguro" class="security-badge" loading="lazy">
                </div>
            </div>
            <?php endif; ?>

        </div>

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
