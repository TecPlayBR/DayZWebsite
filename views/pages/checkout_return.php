<?php /** @var array $config; @var string $status */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 70vh;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background5.png') ?>');"></div>
    <div class="container hero-content" style="text-align: center; max-width: 100%;">
        <?php if ($status === 'success'): ?>
            <span class="hero-kicker" style="margin-left:auto; margin-right:auto; border-left-color: var(--moss); color: var(--moss); background: rgba(90,108,78,0.08);">// <?= e(__('checkout.success_kicker')) ?></span>
            <h1 class="hero-title"><?= e(__('checkout.success_title1')) ?><br><span class="accent" style="color: var(--moss);"><?= e(__('checkout.success_title2')) ?></span></h1>
            <p class="hero-subtitle" style="margin: 0 auto;">
                <?= ($config['delivery_active'] ?? false) ? __('checkout.success_text') : __('checkout.success_text_manual', [], locale() === 'en-us' ? 'Payment confirmed and coins credited! In-game release is handled by the server (Agent/Bot) after confirmation.' : 'Pagamento confirmado e moedas creditadas! A liberação dentro do jogo é feita pelo servidor (Agent/Bot) após a confirmação.') ?><br>
                <em style="color: var(--dim);"><?= __('checkout.success_relog') ?></em>
            </p>
        <?php elseif ($status === 'pending'): ?>
            <span class="hero-kicker" style="margin-left:auto; margin-right:auto;">// <?= e(__('checkout.pending_kicker')) ?></span>
            <h1 class="hero-title"><?= e(__('checkout.pending_title1')) ?><br><span class="accent"><?= e(__('checkout.pending_title2')) ?></span></h1>
            <p class="hero-subtitle" style="margin: 0 auto;">
                <?= e(__('checkout.pending_text')) ?><br>
                <?= e(__('checkout.pending_text2')) ?>
            </p>
        <?php else: ?>
            <span class="hero-kicker" style="margin-left:auto; margin-right:auto; border-left-color: var(--rust-2); color: var(--rust-2);">// <?= e(__('checkout.fail_kicker')) ?></span>
            <h1 class="hero-title"><?= e(__('checkout.fail_title1')) ?><br><span class="accent"><?= e(__('checkout.fail_title2')) ?></span></h1>
            <p class="hero-subtitle" style="margin: 0 auto;">
                <?= e(__('checkout.fail_text')) ?><br>
                <?= e(__('checkout.fail_text2')) ?>
            </p>
        <?php endif; ?>

        <div class="hero-actions" style="justify-content: center;">
            <a href="/shop" class="btn"><?= e(__('checkout.back_shop')) ?></a>
            <a href="/" class="btn btn-outline"><?= e(__('checkout.home')) ?></a>
        </div>
    </div>
</section>

<?php \App\View::endSection(); ?>
