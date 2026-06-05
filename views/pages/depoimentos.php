<?php /** @var array $config, $reviews; @var float $avg_rating; @var int $total_reviews */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background3.png'); // LCP preload sync ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 40vh; padding-bottom: 2rem;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// <?= e(__('depoimentos_page.kicker')) ?></span>
        <h1 class="hero-title"><?= e(__('depoimentos_page.title_1')) ?><br><span class="accent"><?= e(__('depoimentos_page.title_2')) ?></span></h1>
        <?php if ($total_reviews > 0): ?>
            <p class="hero-subtitle">
                <?php $stars = round($avg_rating); ?>
                <span style="font-size: 1.6rem; color: var(--hazard); letter-spacing: 0.1em;"><?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) ?></span>
                <strong style="color: var(--hazard); font-size: 1.4rem;"><?= number_format($avg_rating, 1, ',', '') ?></strong>
                <?= e(__('depoimentos_page.rating_of_5')) ?>
                <span style="color: var(--dim);">(<?= $total_reviews ?> <?= e($total_reviews === 1 ? __('depoimentos_page.review_singular') : __('depoimentos_page.review_plural')) ?>)</span>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width: 1000px;">

        <?php if (empty($reviews)): ?>
            <div style="text-align: center; padding: 4rem 1rem; color: var(--dim);">
                <p><?= e(__('depoimentos_page.empty')) ?> <a href="/shop" style="color: var(--rust-2);"><?= e(__('depoimentos_page.be_first')) ?></a></p>
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

        <!-- Form público de avaliação (qualquer um pode enviar; admin modera) -->
        <div class="public-review-form">
            <h2><?= e(__('depoimentos_page.form_title')) ?></h2>
            <p class="public-review-intro">
                <?= e(__('depoimentos_page.form_intro_1')) ?>
                <?= e(__('depoimentos_page.form_intro_2')) ?> <a href="/my-purchases" style="color: var(--hazard);"><?= e(__('depoimentos_page.my_purchases')) ?></a>.
            </p>

            <?php
            $okMsg = $_GET['ok'] ?? '';
            $errCode = $_GET['err'] ?? '';
            $errKeys = [
                'rate_limited'  => 'depoimentos_page.err_rate',
                'csrf'          => 'depoimentos_page.err_csrf',
                'invalid_name'  => 'depoimentos_page.err_invalid_name',
                'invalid_body'  => 'depoimentos_page.err_invalid_body',
            ];
            ?>
            <?php if ($okMsg === 'submitted'): ?>
                <div class="review-flash success"><?= e(__('depoimentos_page.flash_ok')) ?></div>
            <?php elseif ($errCode && isset($errKeys[$errCode])): ?>
                <div class="review-flash danger"><?= e(__($errKeys[$errCode])) ?></div>
            <?php endif; ?>

            <form method="POST" action="/reviews/public-submit" class="public-review-fields">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="rating" id="public-rating-value" value="5">

                <label>
                    <span><?= e(__('depoimentos_page.label_name')) ?></span>
                    <input type="text" name="name" maxlength="60" required minlength="2"
                           placeholder="<?= e(__('depoimentos_page.ph_name')) ?>"
                           value="<?= e($_POST['name'] ?? '') ?>">
                </label>

                <label>
                    <span><?= e(__('depoimentos_page.label_rating')) ?></span>
                    <div class="public-review-stars" id="public-review-stars" aria-label="<?= e(__('depoimentos_page.label_rating')) ?>">
                        <button type="button" data-r="1">★</button>
                        <button type="button" data-r="2">★</button>
                        <button type="button" data-r="3">★</button>
                        <button type="button" data-r="4">★</button>
                        <button type="button" data-r="5">★</button>
                    </div>
                </label>

                <label>
                    <span><?= e(__('depoimentos_page.label_review')) ?></span>
                    <textarea name="body" rows="4" maxlength="500" minlength="10" required
                              placeholder="<?= e(__('depoimentos_page.ph_review')) ?>"></textarea>
                </label>

                <button type="submit" class="btn"><?= e(__('depoimentos_page.submit')) ?></button>
            </form>
        </div>
    </div>
</section>

<script>
// Star picker: cinza por default. Hover ilumina preview 1-N. Click fixa o rating.
(function() {
    const wrap = document.getElementById('public-review-stars');
    const input = document.getElementById('public-rating-value');
    if (!wrap || !input) return;

    const buttons = [...wrap.querySelectorAll('button[data-r]')];
    let selected = 0; // rating fixado pelo último clique (0 = nenhum)

    function paint(level) {
        buttons.forEach(b => b.classList.toggle('active', +b.dataset.r <= level));
    }

    buttons.forEach(b => {
        b.addEventListener('mouseenter', () => paint(+b.dataset.r));
        b.addEventListener('click', () => {
            selected = +b.dataset.r;
            input.value = selected;
            paint(selected);
        });
    });
    // Mouse sai do container: volta pro rating fixado (ou cinza se nada clicado)
    wrap.addEventListener('mouseleave', () => paint(selected));
})();
</script>

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
    text-shadow: 0 0 8px var(--hazard-border);
    white-space: nowrap;
}
.review-body {
    color: var(--bone); font-size: 0.95rem; line-height: 1.6;
    margin: 0; font-style: italic; opacity: 0.95;
}

/* Form público de avaliação */
.public-review-form {
    margin-top: 3.5rem;
    padding: 2rem;
    background: var(--bg-1);
    border: 1px solid var(--border);
    border-left: 3px solid var(--hazard);
    max-width: 640px;
    margin-left: auto; margin-right: auto;
}
.public-review-form h2 {
    font-family: var(--font-display);
    color: var(--bone);
    font-size: 1.3rem;
    margin: 0 0 0.5rem;
    letter-spacing: 0.04em;
}
.public-review-intro { color: var(--dim); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.5rem; }
.public-review-fields { display: flex; flex-direction: column; gap: 1rem; }
.public-review-fields label { display: flex; flex-direction: column; gap: 0.4rem; }
.public-review-fields label span { color: var(--bone); font-size: 0.85rem; font-family: var(--font-mono); }
.public-review-fields input[type="text"],
.public-review-fields textarea {
    background: var(--bg-0);
    border: 1px solid var(--border);
    color: var(--bone);
    padding: 0.7rem 0.85rem;
    font-family: inherit;
    font-size: 0.95rem;
    resize: vertical;
}
.public-review-fields input[type="text"]:focus,
.public-review-fields textarea:focus { outline: none; border-color: var(--hazard); }
.public-review-fields .btn { align-self: flex-start; min-width: 180px; }

.public-review-stars {
    display: flex; gap: 0.25rem;
}
.public-review-stars button {
    background: none; border: none; cursor: pointer;
    color: var(--dim); font-size: 1.8rem; padding: 0.1rem 0.15rem;
    transition: color .15s;
}
.public-review-stars button.active { color: var(--hazard); text-shadow: 0 0 8px var(--hazard-border); }
.public-review-stars button:hover { color: var(--hazard); }

.review-flash {
    padding: 0.9rem 1.1rem;
    margin-bottom: 1.2rem;
    font-size: 0.9rem;
    border-left: 3px solid;
}
.review-flash.success { background: var(--hazard-overlay); border-left-color: var(--moss); color: var(--text-success); }
.review-flash.danger  { background: var(--danger-overlay); border-left-color: var(--rust-2); color: var(--text-danger); }
</style>

<?php \App\View::endSection(); ?>
