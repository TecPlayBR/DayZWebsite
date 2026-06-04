<?php /** @var array $config; @var string $status */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 70vh;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background5.png') ?>');"></div>
    <div class="container hero-content" style="text-align: center; max-width: 100%;">
        <?php if ($status === 'success'): ?>
            <span class="hero-kicker" style="margin-left:auto; margin-right:auto; border-left-color: var(--moss); color: var(--moss); background: rgba(90,108,78,0.08);">// PAGAMENTO RECEBIDO</span>
            <h1 class="hero-title">Suprimentos<br><span class="accent" style="color: var(--moss);">A Caminho.</span></h1>
            <p class="hero-subtitle" style="margin: 0 auto;">
                Pagamento confirmado. As moedas vão chegar no jogo em até <strong>15 segundos</strong> após a confirmação do Mercado Pago.<br>
                <em style="color: var(--dim);">Se já está conectado no servidor, faça <strong>relog</strong> pra atualizar o saldo.</em>
            </p>
        <?php elseif ($status === 'pending'): ?>
            <span class="hero-kicker" style="margin-left:auto; margin-right:auto;">// AGUARDANDO CONFIRMAÇÃO</span>
            <h1 class="hero-title">Pagamento<br><span class="accent">Pendente.</span></h1>
            <p class="hero-subtitle" style="margin: 0 auto;">
                Seu pagamento está em análise (boleto ou PIX em processamento).<br>
                Quando confirmar, as moedas chegam automaticamente.
            </p>
        <?php else: ?>
            <span class="hero-kicker" style="margin-left:auto; margin-right:auto; border-left-color: var(--rust-2); color: var(--rust-2);">// FALHA</span>
            <h1 class="hero-title">Pagamento<br><span class="accent">Recusado.</span></h1>
            <p class="hero-subtitle" style="margin: 0 auto;">
                Algo deu errado com o pagamento. Tenta de novo ou usa outra forma.<br>
                Nada foi cobrado.
            </p>
        <?php endif; ?>

        <div class="hero-actions" style="justify-content: center;">
            <a href="/shop" class="btn">Voltar pra loja</a>
            <a href="/" class="btn btn-outline">Página inicial</a>
        </div>
    </div>
</section>

<?php \App\View::endSection(); ?>
