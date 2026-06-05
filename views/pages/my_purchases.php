<?php /** @var array $config; @var ?array $player, $steam_user; @var array $purchases, $achievements, $reviewed_ids, $unlocked */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<?php
$flash = null;
if (!empty($_GET['ok']) && $_GET['ok'] === 'review_submitted') $flash = ['success', __('profile.flash_review_ok')];
if (!empty($_GET['err'])) {
    $flash = ['danger', match($_GET['err']) {
        'invalid_purchase'   => __('profile.flash_invalid'),
        'too_soon'           => __('profile.flash_too_soon'),
        'already_reviewed'   => __('profile.flash_reviewed'),
        default              => __('profile.flash_generic'),
    }];
}
?>
<?php if ($flash): ?>
    <div style="position: sticky; top: 80px; z-index: 50; max-width: 800px; margin: 1rem auto 0; padding: 0.85rem 1.2rem;
                background: <?= $flash[0]==='success' ? 'rgba(90,108,78,0.18)' : 'rgba(231,76,60,0.18)' ?>;
                border-left: 3px solid <?= $flash[0]==='success' ? 'var(--moss)' : 'var(--rust-2)' ?>;
                color: <?= $flash[0]==='success' ? 'var(--text-success)' : 'var(--text-danger)' ?>; font-size: 0.9rem;">
        <?= e($flash[1]) ?>
    </div>
<?php endif; ?>


<section class="hero" style="min-height: 35vh; padding-bottom: 2rem;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// <?= e(__('profile.kicker')) ?></span>
        <h1 class="hero-title"><?= e($steam_user['display_name'] ?? __('profile.fallback_name')) ?></h1>
        <p class="hero-subtitle" style="font-family: var(--font-mono); font-size: 0.9rem; color: var(--dim);">
            <?= e($steam_user['steam_id'] ?? '') ?>
        </p>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container">

        <!-- Cards de resumo -->
        <div class="profile-grid">
            <div class="profile-card">
                <div class="profile-card-label"><?= e(__('profile.balance')) ?></div>
                <div class="profile-card-value" style="color: var(--hazard);"><?= number_format((int)($player['coins'] ?? 0), 0, ',', '.') ?></div>
                <div class="profile-card-suffix"><?= e(__('profile.coins')) ?></div>
            </div>
            <div class="profile-card">
                <div class="profile-card-label"><?= e(__('profile.invested')) ?></div>
                <div class="profile-card-value" style="color: var(--moss);">R$ <?= number_format((float)($player['total_spent_brl'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="profile-card">
                <div class="profile-card-label"><?= e(__('profile.purchases')) ?></div>
                <div class="profile-card-value"><?= count($purchases) ?></div>
            </div>
            <div class="profile-card">
                <div class="profile-card-label"><?= e(__('profile.last_seen')) ?></div>
                <div class="profile-card-value" style="font-size: 1rem;"><?= e($player['last_seen_at'] ?? '—') ?></div>
            </div>
        </div>

        <h2 style="font-family: var(--font-display); color: var(--bone); font-size: 1.4rem; margin: 3rem 0 1.5rem; letter-spacing: 0.04em;">
            <?= e(__('profile.achievements')) ?>
        </h2>
        <div class="achievements-grid">
            <?php foreach (($achievements ?? []) as $a):
                $isUnlocked = !empty($unlocked[$a['slug']]);
            ?>
                <div class="achievement <?= $isUnlocked ? 'unlocked' : 'locked' ?>" title="<?= e($a['description']) ?>">
                    <div class="achievement-icon"><?= e($a['icon']) ?></div>
                    <div class="achievement-name"><?= e($a['name']) ?></div>
                    <div class="achievement-desc"><?= e($a['description']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 style="font-family: var(--font-display); color: var(--bone); font-size: 1.4rem; margin: 3rem 0 1.5rem; letter-spacing: 0.04em;">
            <?= e(__('profile.history')) ?>
        </h2>

        <?php if (empty($purchases)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--dim);">
                <p><?= e(__('profile.no_purchases')) ?> <a href="/shop" style="color: var(--rust-2);">→</a></p>
            </div>
        <?php else: ?>
            <table class="purchases-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Pacote</th>
                        <th>Moedas</th>
                        <th>Valor</th>
                        <th class="hide-mobile"><?= e(__('profile.payment_method')) ?></th>
                        <th>Status</th>
                        <th>Avaliar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $p):
                        $canReview = $p['mp_status'] === 'approved'
                                  && !empty($p['delivered_at'])
                                  && strtotime($p['delivered_at']) <= (time() - 7 * 86400)
                                  && empty($reviewed_ids[(int)$p['id']]);
                        $alreadyReviewed = !empty($reviewed_ids[(int)$p['id']]);
                    ?>
                        <tr>
                            <td class="dim"><?= e($p['created_at']) ?></td>
                            <td><strong><?= e($p['package_id']) ?></strong></td>
                            <td class="mono">
                                <?= (int)$p['coins_total'] ?>
                                <?php if ((int)$p['coins_bonus'] > 0): ?>
                                    <small style="color: var(--moss);">(+<?= (int)$p['coins_bonus'] ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format((float)$p['price_brl'], 2, ',', '.') ?></td>
                            <td class="hide-mobile dim"><?= e($p['payment_method'] ?? '—') ?></td>
                            <td>
                                <?php
                                $cls = match($p['mp_status']) {
                                    'approved' => 'badge-success',
                                    'rejected','cancelled','refunded' => 'badge-danger',
                                    'pending' => 'badge-warning',
                                    default => 'badge-info'
                                };
                                // Status traduzido via lang ('purchase_status.<status>')
                                $statusKey = 'purchase_status.' . ($p['mp_status'] ?? 'unknown');
                                $statusTr  = __($statusKey);
                                $label = ($statusTr === $statusKey) ? ($p['mp_status'] ?? '—') : $statusTr;
                                ?>
                                <span class="purchase-badge <?= $cls ?>"><?= $label ?></span>
                            </td>
                            <td>
                                <?php if ($alreadyReviewed): ?>
                                    <span class="dim" style="font-size: 0.8rem;">★ avaliado</span>
                                <?php elseif ($canReview): ?>
                                    <button type="button" class="btn-mini" style="padding: 0.3rem 0.7rem; font-size: 0.75rem;"
                                            onclick="openReviewForm(<?= (int)$p['id'] ?>, '<?= e($p['package_id']) ?>')">Avaliar</button>
                                <?php else: ?>
                                    <span class="dim" style="font-size: 0.75rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modal de review -->
            <div id="review-modal" class="review-modal" hidden>
                <div class="review-modal-card">
                    <form method="POST" action="/reviews/submit">
                        <?= \App\Csrf::field() ?>
                        <h3 style="font-family: var(--font-display); color: var(--bone); margin-bottom: 0.5rem;"><?= e(__('profile.review_title')) ?></h3>
                        <p style="color: var(--dim); font-size: 0.85rem; margin-bottom: 1.2rem;">
                            <?= e(__('profile.pack_label')) ?> <strong id="review-pkg-name" style="color: var(--hazard);">—</strong>
                        </p>
                        <input type="hidden" name="purchase_id" id="review-purchase-id" value="">
                        <input type="hidden" name="rating" id="review-rating-value" value="5">

                        <div class="review-stars-pick" id="review-stars-pick" aria-label="Nota">
                            <button type="button" data-r="1">★</button>
                            <button type="button" data-r="2">★</button>
                            <button type="button" data-r="3">★</button>
                            <button type="button" data-r="4">★</button>
                            <button type="button" data-r="5" class="active">★</button>
                        </div>

                        <textarea name="body" rows="4" maxlength="500"
                                  placeholder="<?= e(__('profile.review_ph')) ?>"
                                  style="width:100%; padding:0.7rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit; resize:vertical; margin-bottom: 1rem;"></textarea>

                        <p style="font-size: 0.75rem; color: var(--dim); margin-bottom: 1.2rem;">
                            <?= e(__('profile.moderation_note')) ?>
                        </p>

                        <div style="display: flex; gap: 0.6rem; justify-content: flex-end;">
                            <button type="button" class="btn-mini outline" onclick="closeReviewForm()">Cancelar</button>
                            <button type="submit" class="btn-mini"><?= e(__('profile.review_send')) ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function openReviewForm(id, pkg) {
                    document.getElementById('review-purchase-id').value = id;
                    document.getElementById('review-pkg-name').textContent = pkg;
                    document.getElementById('review-modal').hidden = false;
                    setRating(5);
                }
                function closeReviewForm() {
                    document.getElementById('review-modal').hidden = true;
                }
                function setRating(r) {
                    document.getElementById('review-rating-value').value = r;
                    document.querySelectorAll('#review-stars-pick button').forEach(b => {
                        b.classList.toggle('active', parseInt(b.dataset.r) <= r);
                    });
                }
                document.querySelectorAll('#review-stars-pick button').forEach(b => {
                    b.addEventListener('click', () => setRating(parseInt(b.dataset.r)));
                });
                document.getElementById('review-modal')?.addEventListener('click', e => {
                    if (e.target.id === 'review-modal') closeReviewForm();
                });
            </script>
        <?php endif; ?>

        <p style="margin-top: 2rem; color: var(--dim); font-size: 0.85rem;">
            <?= e(__('profile.support_question')) ?>
        </p>
    </div>
</section>

<style>
.profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}
.profile-card {
    background: linear-gradient(180deg, var(--bg-2) 0%, var(--bg-1) 100%);
    border: 1px solid var(--border);
    border-left: 3px solid var(--rust);
    padding: 1.2rem 1.4rem;
}
.profile-card-label {
    font-size: 0.7rem; color: var(--dim); text-transform: uppercase;
    letter-spacing: 0.1em; margin-bottom: 0.6rem;
}
.profile-card-value {
    font-family: var(--font-display); font-size: 1.8rem; color: var(--bone); line-height: 1;
}
.profile-card-suffix { color: var(--dim); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 0.3rem; }

.purchases-table {
    width: 100%; border-collapse: collapse;
    background: var(--bg-1); border: 1px solid var(--border);
    font-size: 0.9rem;
}
.purchases-table th {
    background: var(--bg-2); color: var(--dim); text-align: left;
    padding: 0.85rem 1rem; font-size: 0.75rem;
    text-transform: uppercase; letter-spacing: 0.08em;
    border-bottom: 2px solid var(--rust);
}
.purchases-table td {
    padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); color: var(--bone);
}
.purchases-table .dim  { color: var(--dim); }
.purchases-table .mono { font-family: var(--font-mono); }
.purchase-badge {
    display: inline-block; padding: 0.25rem 0.6rem;
    font-size: 0.75rem; font-weight: 600; border-radius: 2px;
    letter-spacing: 0.04em;
}
.badge-success { background: rgba(22,163,74,0.2);  color: var(--text-success); }
.badge-warning { background: var(--hazard-border); color: var(--hazard); }
.badge-danger  { background: var(--danger-overlay);  color: var(--text-danger); }
.badge-info    { background: rgba(160,160,160,0.2);color: var(--dim); }

@media (max-width: 600px) { .hide-mobile { display: none; } }

.review-modal {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.85); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.review-modal[hidden] { display: none !important; }
.review-modal-card {
    background: var(--bg-1); border: 1px solid var(--border);
    border-left: 3px solid var(--hazard);
    padding: 2rem; max-width: 480px; width: 100%;
    border-radius: 2px;
}
.review-stars-pick { display: flex; gap: 0.3rem; margin-bottom: 1.2rem; }
.review-stars-pick button {
    background: transparent; border: none; cursor: pointer;
    font-size: 2.2rem; color: var(--dim);
    transition: color .15s, transform .15s; line-height: 1;
}
.review-stars-pick button:hover { transform: scale(1.15); }
.review-stars-pick button.active { color: var(--hazard); text-shadow: 0 0 8px rgba(212,160,23,0.5); }

/* Achievements */
.achievements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}
.achievement {
    background: var(--bg-1);
    border: 1px solid var(--border);
    padding: 1.2rem 1rem;
    text-align: center;
    transition: transform .2s, border-color .2s;
}
.achievement.unlocked {
    border-left: 3px solid var(--hazard);
    background: linear-gradient(180deg, var(--hazard-overlay), var(--bg-1));
}
.achievement.unlocked:hover { transform: translateY(-2px); border-color: var(--hazard); }
.achievement.locked  { opacity: 0.4; filter: grayscale(0.7); }
.achievement-icon { font-size: 2.5rem; line-height: 1; margin-bottom: 0.6rem; }
.achievement.unlocked .achievement-icon { color: var(--hazard); text-shadow: 0 0 12px var(--hazard-border); }
.achievement-name { font-family: var(--font-display); color: var(--bone); font-size: 0.95rem; margin-bottom: 0.3rem; letter-spacing: 0.03em; }
.achievement-desc { color: var(--dim); font-size: 0.75rem; line-height: 1.4; }
</style>

<?php \App\View::endSection(); ?>
