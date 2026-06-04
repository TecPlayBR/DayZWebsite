<?php /** @var array $config */ ?>
<?php \App\View::extend('layouts.main'); ?>

<?php \App\View::section('content'); ?>
<section class="hero" style="min-height: 60vh;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background5.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// CODE_OF_CONDUCT</span>
        <h1 class="hero-title"><?= e(__('nav.rules')) ?></h1>
        <p class="hero-subtitle">As regras existem pra todos jogarem com prazer. Quebrou, fica de fora.</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width: 800px;">
        <p style="color: var(--dim); font-family: var(--font-mono); font-size: 1.1rem;">
            (Conteúdo das regras vai aqui — admin edita pelo painel quando estiver pronto.)
        </p>
    </div>
</section>
<?php \App\View::endSection(); ?>
