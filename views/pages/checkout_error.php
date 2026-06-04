<?php /** @var array $config; @var string $msg */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 60vh;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background4.png') ?>');"></div>
    <div class="container hero-content" style="text-align: center; max-width: 100%;">
        <span class="hero-kicker" style="margin-left:auto; margin-right:auto; border-left-color: var(--rust-2); color: var(--rust-2);">// ERRO</span>
        <h1 class="hero-title"><span class="accent">Não Deu.</span></h1>
        <p class="hero-subtitle" style="margin: 0 auto;"><?= e($msg) ?></p>
        <div class="hero-actions" style="justify-content: center;">
            <a href="/shop" class="btn">Tentar de novo</a>
        </div>
    </div>
</section>

<?php \App\View::endSection(); ?>
