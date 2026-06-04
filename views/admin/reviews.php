<?php /** @var array $config, $reviews, $counts; @var string $filter */ ?>
<?php $title = 'Avaliações'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Avaliações de jogadores</h1>
        <p>Modere as reviews antes de aparecerem em <code>/depoimentos</code>.</p>
    </div>
    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
        <a href="/admin/reviews?filter=pending"  class="btn-mini outline">Pendentes (<?= (int)$counts['pending'] ?>)</a>
        <a href="/admin/reviews?filter=approved" class="btn-mini outline">Aprovadas (<?= (int)$counts['approved'] ?>)</a>
        <a href="/admin/reviews?filter=all"      class="btn-mini outline">Todas (<?= (int)$counts['total'] ?>)</a>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert-toast">Atualizado.</div><?php endif; ?>

<?php if (empty($reviews)): ?>
    <div style="text-align: center; padding: 3rem 1rem; color: var(--dim);">
        Nenhuma avaliação <?= $filter === 'pending' ? 'pendente' : ($filter === 'approved' ? 'aprovada' : '') ?> no momento.
    </div>
<?php else: ?>
    <div style="display: grid; gap: 1rem;">
        <?php foreach ($reviews as $r): ?>
            <div class="stat-card" style="padding: 1.5rem; border-left-color: <?= (int)$r['approved'] ? 'var(--moss)' : 'var(--hazard)' ?>;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.8rem;">
                    <div>
                        <strong style="color: var(--bone); font-family: var(--font-mono);"><?= e($r['display_name'] ?? 'Anônimo') ?></strong>
                        <span class="dim" style="font-family: var(--font-mono); font-size: 0.75rem; margin-left: 0.5rem;"><?= e($r['steam_id']) ?></span>
                        <div style="color: var(--dim); font-size: 0.75rem; margin-top: 0.2rem;">
                            Pacote <strong style="color: var(--bone);"><?= e($r['package_id']) ?></strong> · R$ <?= number_format((float)$r['price_brl'], 2, ',', '.') ?>
                            · <?= e($r['created_at']) ?>
                        </div>
                    </div>
                    <div style="color: var(--hazard); font-size: 1.2rem; letter-spacing: 0.05em;">
                        <?= str_repeat('★', (int)$r['rating']) . '<span style="opacity:0.3;">' . str_repeat('★', 5 - (int)$r['rating']) . '</span>' ?>
                    </div>
                </div>
                <?php if (!empty($r['body'])): ?>
                    <p style="color: var(--bone); margin-bottom: 1rem; font-style: italic; opacity: 0.95;">"<?= e($r['body']) ?>"</p>
                <?php endif; ?>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <form method="POST" action="/admin/reviews/<?= (int)$r['id'] ?>/toggle" style="display: inline;">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini <?= (int)$r['approved'] ? 'outline' : '' ?>">
                            <?= (int)$r['approved'] ? '⏸ Despublicar' : '✓ Aprovar' ?>
                        </button>
                    </form>
                    <form method="POST" action="/admin/reviews/<?= (int)$r['id'] ?>/delete" style="display: inline;"
                          onsubmit="return confirm('Apagar essa avaliação?');">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline" style="border-color: var(--danger-border); color: var(--text-danger);">✕ Apagar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php \App\View::endSection(); ?>
