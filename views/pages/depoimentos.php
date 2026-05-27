<?php /** @var array $config, $reviews; @var float $avg_rating; @var int $total_reviews */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 40vh; padding-bottom: 2rem;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(5,6,8,0.5) 0%, rgba(5,6,8,0.95) 100%), url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// FEEDBACK DA COMUNIDADE</span>
        <h1 class="hero-title">Depoimentos<br><span class="accent">Reais.</span></h1>
        <?php if ($total_reviews > 0): ?>
            <p class="hero-subtitle">
                <?php $stars = round($avg_rating); ?>
                <span style="font-size: 1.6rem; color: var(--hazard); letter-spacing: 0.1em;"><?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) ?></span>
                <strong style="color: var(--hazard); font-size: 1.4rem;"><?= number_format($avg_rating, 1, ',', '') ?></strong>
                de 5
                <span style="color: var(--dim);">(<?= $total_reviews ?> <?= $total_reviews === 1 ? 'avaliação' : 'avaliações' ?>)</span>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width: 1000px;">

        <?php if (empty($reviews)): ?>
            <div style="text-align: center; padding: 4rem 1rem; color: var(--dim);">
                <p>Nenhum depoimento ainda. <a href="/shop" style="color: var(--rust-2);">Seja o primeiro a comprar e avaliar →</a></p>
            </div>
        <?php else: ?>
            <div class="reviews-grid">
                <?php foreach ($reviews as $r): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div>
                                <strong class="review-author"><?= e($r['display_name'] ?? 'Sobrevivente') ?></strong>
                                <span class="review-date"><?= date('d/m/Y', strtotime($r['created_at'])) ?></span>
                            </div>
                            <div class="review-stars" aria-label="<?= (int)$r['rating'] ?> de 5 estrelas">
                                <?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?>
                            </div>
                        </div>
                        <?php if (!empty($r['body'])): ?>
                            <p class="review-body">"<?= e($r['body']) ?>"</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p style="text-align: center; margin-top: 3rem; color: var(--dim); font-size: 0.9rem;">
            Quer compartilhar sua experiência? <a href="/my-purchases" style="color: var(--hazard);">Acesse suas compras</a> e avalie pacotes entregues há mais de 7 dias.
        </p>
    </div>
</section>

<style>
.reviews-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.2rem;
}
.review-card {
    background: linear-gradient(180deg, var(--bg-2) 0%, var(--bg-1) 100%);
    border: 1px solid var(--border);
    border-left: 3px solid var(--moss);
    padding: 1.5rem;
    transition: transform .2s, border-color .2s;
}
.review-card:hover {
    transform: translateY(-2px);
    border-left-color: var(--hazard);
}
.review-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 1rem; margin-bottom: 0.8rem;
}
.review-author {
    font-family: var(--font-mono); font-size: 1rem;
    color: var(--bone); display: block;
}
.review-date {
    font-size: 0.75rem; color: var(--dim); font-family: var(--font-mono);
}
.review-stars {
    color: var(--hazard); font-size: 1.1rem; letter-spacing: 0.08em;
    text-shadow: 0 0 8px rgba(212,160,23,0.3);
    white-space: nowrap;
}
.review-body {
    color: var(--bone); font-size: 0.95rem; line-height: 1.6;
    margin: 0; font-style: italic; opacity: 0.95;
}
</style>

<?php \App\View::endSection(); ?>
