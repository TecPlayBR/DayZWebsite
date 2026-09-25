<?php
/** @var array $config; @var string $status; @var string $motivo; @var string $return; @var ?string $erro; @var ?string $ok_msg; @var bool $tem_consentimento */
?>
<?php \App\View::with('title', __('idade.title') . ' - ' . site_name('Loja')); ?>
<?php \App\View::with('description', __('idade.why')); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>
<?php
// Quais blocos aparecem e decidido na regra pura (AgeGate::telas), nao aqui: a revisao pegou
// um caso (verificado por CPF antes de declarar) em que a tela ficava vazia.
$blocos = \App\AgeGate::telas($status, $motivo, (bool) ($tem_consentimento ?? false));
$campo = 'width:100%; padding:.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);';
$termos = '<a href="/terms" target="_blank" style="color:var(--hazard);">Termos</a>, <a href="/privacy" target="_blank" style="color:var(--hazard);">Privacidade</a>';
?>
<section class="section section-bg-2" style="min-height:60vh;">
    <div class="container" style="max-width:640px;">
        <?php if ($erro): ?>
            <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--danger-border); background:var(--danger-overlay);"><?= e($erro) ?></div>
        <?php endif; ?>
        <?php if ($ok_msg): ?>
            <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--moss);"><?= e($ok_msg) ?></div>
        <?php endif; ?>
        <?php if (!$blocos): ?>
            <h1 class="hero-title" style="font-size:1.8rem;"><?= e(__('idade.title')) ?></h1>
            <p style="color:var(--dim); margin:.8rem 0 1.4rem;"><?= e(__('idade.verified_ok')) ?> <a href="<?= e($return) ?>" style="color:var(--hazard);"><?= e(__('idade.continue')) ?> →</a></p>
        <?php endif; ?>

        <?php if (in_array('menor', $blocos, true)): ?>
            <h1 class="hero-title" style="font-size:1.8rem;"><?= e(__('idade.minor_title')) ?></h1>
            <p style="color:var(--dim); margin:.8rem 0 1.4rem;"><?= e(__('idade.minor_body')) ?></p>
        <?php endif; ?>

        <?php if (in_array('declarar', $blocos, true)): ?>
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
                    <span><?= e(__('idade.terms_label')) ?> (<?= $termos ?>)</span>
                </label>
                <button type="submit" class="btn"><?= e(__('idade.continue')) ?></button>
            </form>
        <?php endif; ?>

        <?php if (in_array('consentir', $blocos, true)): ?>
            <h1 class="hero-title" style="font-size:1.8rem;"><?= e(__('idade.confirm_title')) ?></h1>
            <p style="color:var(--dim); margin:.8rem 0 1.2rem;"><?= e(__('idade.confirm_intro')) ?></p>
            <form method="POST" action="/idade/consentir" class="stat-card" style="display:grid; gap:1rem;">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <input type="hidden" name="motivo" value="<?= e($motivo) ?>">
                <label style="display:flex; gap:.6rem; align-items:flex-start; color:var(--bone);">
                    <input type="checkbox" name="terms_ok" value="1" required style="width:18px; height:18px; margin-top:.15rem;">
                    <span><?= e(__('idade.terms_label')) ?> (<?= $termos ?>)</span>
                </label>
                <button type="submit" class="btn"><?= e(__('idade.continue')) ?></button>
            </form>
        <?php endif; ?>

        <?php if (in_array('verificar', $blocos, true)): ?>
            <h2 style="font-size:1.4rem; margin-top:1.6rem; color:var(--bone);"><?= e(__('idade.verify_title')) ?></h2>
            <p style="color:var(--dim); margin:.6rem 0 1rem;"><?= e(__('idade.verify_intro')) ?></p>
            <form method="POST" action="/idade/verificar" class="stat-card" style="display:grid; gap:1rem;" autocomplete="off">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <?php if ($status === 'desconhecido' || $status === 'menor'): ?>
                <label style="display:block;">
                    <span style="display:block; margin-bottom:.35rem; color:var(--bone);"><?= e(__('idade.birth_label')) ?> <span style="color:var(--hazard);">*</span></span>
                    <input type="date" name="nascimento" required max="<?= date('Y-m-d') ?>" min="1900-01-01" style="<?= $campo ?>">
                </label>
                <?php endif; ?>
                <?php if (!$tem_consentimento): ?>
                <label style="display:flex; gap:.6rem; align-items:flex-start; color:var(--bone);">
                    <input type="checkbox" name="terms_ok" value="1" required style="width:18px; height:18px; margin-top:.15rem;">
                    <span><?= e(__('idade.terms_label')) ?> (<?= $termos ?>)</span>
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
