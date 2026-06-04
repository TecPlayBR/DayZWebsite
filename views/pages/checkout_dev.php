<?php /** @var array $config, $pkg; @var int $purchase_id; @var string $steam_id; @var int $coins_total; @var float $price_brl */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 70vh;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker" style="border-left-color: var(--hazard); color: var(--hazard);">// MODO DEV — MP NÃO CONFIGURADO</span>
        <h1 class="hero-title"><span class="accent">Pedido</span><br>Registrado.</h1>
        <p class="hero-subtitle">
            Sua compra foi criada como <code>pending</code> no banco (id #<?= (int)$purchase_id ?>). Em <strong>produção</strong>, o cliente seria redirecionado pro Mercado Pago aqui.
        </p>

        <div style="background: rgba(0,0,0,0.7); border-left: 3px solid var(--hazard); padding: 1.5rem; margin: 1.5rem 0; max-width: 600px;">
            <table style="width: 100%; color: var(--bone); font-family: var(--font-mono); font-size: 0.9rem; border-collapse: collapse;">
                <tr><td style="padding: 0.4rem 0; color: var(--dim);">Pacote:</td><td><?= e($pkg['name']) ?></td></tr>
                <tr><td style="padding: 0.4rem 0; color: var(--dim);">SteamID:</td><td><?= e($steam_id) ?></td></tr>
                <tr><td style="padding: 0.4rem 0; color: var(--dim);">Moedas:</td><td><strong><?= (int)$coins_total ?></strong></td></tr>
                <tr><td style="padding: 0.4rem 0; color: var(--dim);">Valor:</td><td><strong style="color: var(--hazard);">R$ <?= number_format((float)$price_brl, 2, ',', '.') ?></strong></td></tr>
            </table>
        </div>

        <p style="color: var(--dim); font-size: 0.85rem; max-width: 600px;">
            Pra ativar o Mercado Pago: configure o <code>access_token</code> no <code>config.php</code> ou via Admin → Settings. <br>Depois o webhook em <code>/api/mp-webhook.php</code> credita os coins automaticamente.
        </p>

        <div class="hero-actions">
            <a href="/shop" class="btn">Voltar pra loja</a>
            <a href="/admin/purchases" class="btn btn-outline">Ver no admin</a>
        </div>
    </div>
</section>

<?php \App\View::endSection(); ?>
