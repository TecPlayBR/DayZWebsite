<?php /** @var array $config */ ?>
<?php \App\View::extend('layouts.main'); ?>

<?php \App\View::section('content'); ?>
<section class="hero" style="min-height: 80vh;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(5,6,8,0.6) 0%, rgba(5,6,8,0.95) 100%), url('<?= asset('img/background4.png') ?>');"></div>
    <div class="container hero-content" style="text-align: center; max-width: 100%;">
        <span class="hero-kicker" style="margin-left: auto; margin-right: auto;">// ERR_NO_SIGNAL</span>
        <h1 class="hero-title" style="font-size: clamp(4rem, 12vw, 9rem);">
            <span class="accent">404</span>
        </h1>
        <p class="hero-subtitle" style="margin-left: auto; margin-right: auto;">
            Esta área foi tomada pela infecção. Volte enquanto pode.<br>
            <em style="color: var(--dim);">This area is overrun. Get out while you can.</em>
        </p>
        <div class="hero-actions" style="justify-content: center;">
            <a href="/" class="btn">← <?= e(__('nav.home')) ?></a>
        </div>
    </div>
</section>
<?php \App\View::endSection(); ?>
