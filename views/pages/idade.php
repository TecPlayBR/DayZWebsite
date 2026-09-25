<?php
/** @var array $config; @var string $status; @var string $motivo; @var string $return; @var ?string $erro; @var ?string $ok_msg */
?>
<?php \App\View::with('title', __('idade.title') . ' - ' . site_name('Loja')); ?>
<?php \App\View::with('description', __('idade.why')); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>
<?php
$precisaDeclarar = $status === 'desconhecido';
$precisaVerificar = $motivo === 'caixa' && $status !== 'adulto_verificado' && $status !== 'menor';
$ehMenor = $status === 'menor';
$campo = 'width:100%; padding:.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);';
?>
<section class="section section-bg-2" style="min-height:60vh;">
    <div class="container" style="max-width:640px;">
        <?php if ($erro): ?>
            <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--danger-border); background:var(--danger-overlay);"><?= e($erro) ?></div>
        <?php endif; ?>
        <?php if ($ok_msg): ?>
            <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--moss);"><?= e($ok_msg) ?></div>
        <?php endif; ?>

        <?php if ($ehMenor): ?>
            <h1 class="hero-title" style="font-size:1.8rem;"><?= e(__('idade.minor_title')) ?></h1>
            <p style="color:var(--dim); margin:.8rem 0 1.4rem;"><?= e(__('idade.minor_body')) ?></p>
        <?php endif; ?>

        <?php if ($precisaDeclarar && !$ehMenor): ?>
            <h1 class="hero-title" style="font-size:1.8rem;"><?= e(__('idade.confirm_title')) ?></h1>
            <p style="color:var(--dim); margin:.8rem 0 1.2rem;"><?= e(__('idade.confirm_intro')) ?></p>
            <form method="POST" action="/idade/declarar" class="stat-card" style="display:grid; gap:1rem;">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <input type="hidden" name="motivo" value="<?= e($motivo) ?>">
                <label style="display:block;">
                    <span style="display:block; margin-bottom:.35rem; color:var(--bone);"><?= e(__('idade.birth_label')) ?> <span style="color:var(--hazard);">*</span></span>
                    <input type="date" name="nascimento" required max="<?= date('Y-m-d') ?>" min="1900-01-01" style="<?= $campo ?>">
                </label>
                <label style="display:flex; gap:.6rem; align-items:flex-start; color:var(--bone);">
                    <input type="checkbox" name="terms_ok" value="1" required style="width:18px; height:18px; margin-top:.15rem;">
                    <span><?= e(__('idade.terms_label')) ?> (<a href="/terms" target="_blank" style="color:var(--hazard);">Termos</a>, <a href="/privacy" target="_blank" style="color:var(--hazard);">Privacidade</a>)</span>
                </label>
                <button type="submit" class="btn"><?= e(__('idade.continue')) ?></button>
            </form>
        <?php endif; ?>

        <?php if ($precisaVerificar || $ehMenor): ?>
            <h2 style="font-size:1.4rem; margin-top:1.6rem; color:var(--bone);"><?= e(__('idade.verify_title')) ?></h2>
            <p style="color:var(--dim); margin:.6rem 0 1rem;"><?= e(__('idade.verify_intro')) ?></p>
            <form method="POST" action="/idade/verificar" class="stat-card" style="display:grid; gap:1rem;" autocomplete="off">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <?php if ($precisaDeclarar || $ehMenor): ?>
                <label style="display:block;">
                    <span style="display:block; margin-bottom:.35rem; color:var(--bone);"><?= e(__('idade.birth_label')) ?> <span style="color:var(--hazard);">*</span></span>
                    <input type="date" name="nascimento" required max="<?= date('Y-m-d') ?>" min="1900-01-01" style="<?= $campo ?>">
                </label>
                <?php endif; ?>
                <label style="display:block;">
                    <span style="display:block; margin-bottom:.35rem; color:var(--bone);"><?= e(__('idade.cpf_label')) ?> <span style="color:var(--hazard);">*</span></span>
                    <input type="text" name="cpf" inputmode="numeric" autocomplete="off" required maxlength="14" placeholder="000.000.000-00" style="<?= $campo ?>">
                    <small style="display:block; margin-top:.4rem; color:var(--dim);"><?= e(__('idade.cpf_not_stored')) ?> <?= e(__('idade.one_account')) ?></small>
                </label>
                <button type="submit" class="btn"><?= e(__('idade.verify_btn')) ?></button>
            </form>
        <?php endif; ?>

        <p style="margin-top:1.6rem; font-size:.85rem; color:var(--dim);"><?= e(__('idade.why')) ?></p>
    </div>
</section>
<?php \App\View::endSection(); ?>
