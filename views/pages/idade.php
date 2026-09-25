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
$termos = '<a href="/terms" target="_blank">Termos de Uso</a> <span aria-hidden="true">·</span> <a href="/privacy" target="_blank">Política de Privacidade</a>';
$hoje = date('Y-m-d');
?>
<section class="hero idade-hero">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.55) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background3.png') ?>');"></div>
    <div class="container hero-content">
        <h1 class="hero-title"><?= e(__('idade.title')) ?></h1>
        <p class="idade-lead"><?= e(__('idade.why')) ?></p>
    </div>
</section>

<section class="section section-bg-2 idade-wrap">
    <div class="container idade-col">

        <?php if ($erro): ?>
            <div class="idade-msg idade-msg-erro" role="alert"><?= e($erro) ?></div>
        <?php endif; ?>
        <?php if ($ok_msg): ?>
            <div class="idade-msg idade-msg-ok" role="status"><?= e($ok_msg) ?></div>
        <?php endif; ?>

        <?php if (!$blocos): ?>
            <div class="idade-card">
                <h2><?= e(__('idade.verified_ok')) ?></h2>
                <p><a class="btn" href="<?= e($return) ?>"><?= e(__('idade.continue')) ?></a></p>
            </div>
        <?php endif; ?>

        <?php if (in_array('menor', $blocos, true)): ?>
            <div class="idade-card idade-card-menor">
                <h2><?= e(__('idade.minor_title')) ?></h2>
                <p><?= e(__('idade.minor_body')) ?></p>
            </div>
        <?php endif; ?>

        <?php if (in_array('declarar', $blocos, true)): ?>
            <form method="POST" action="/idade/declarar" class="idade-card idade-form">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <input type="hidden" name="motivo" value="<?= e($motivo) ?>">
                <h2><?= e(__('idade.confirm_title')) ?></h2>
                <p><?= e(__('idade.confirm_intro')) ?></p>
                <label class="idade-label" for="idade-nasc"><?= e(__('idade.birth_label')) ?> <span class="idade-req" aria-hidden="true">*</span></label>
                <input class="field" type="date" id="idade-nasc" name="nascimento" required max="<?= $hoje ?>" min="1900-01-01">
                <label class="idade-check">
                    <input type="checkbox" name="terms_ok" value="1" required>
                    <span><?= e(__('idade.terms_label')) ?><br><small><?= $termos ?></small></span>
                </label>
                <button type="submit" class="btn"><?= e(__('idade.continue')) ?></button>
            </form>
        <?php endif; ?>

        <?php if (in_array('consentir', $blocos, true)): ?>
            <form method="POST" action="/idade/consentir" class="idade-card idade-form">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <input type="hidden" name="motivo" value="<?= e($motivo) ?>">
                <h2><?= e(__('idade.confirm_title')) ?></h2>
                <p><?= e(__('idade.confirm_intro')) ?></p>
                <label class="idade-check">
                    <input type="checkbox" name="terms_ok" value="1" required>
                    <span><?= e(__('idade.terms_label')) ?><br><small><?= $termos ?></small></span>
                </label>
                <button type="submit" class="btn"><?= e(__('idade.continue')) ?></button>
            </form>
        <?php endif; ?>

        <?php if (in_array('verificar', $blocos, true)): ?>
            <form method="POST" action="/idade/verificar" class="idade-card idade-form" autocomplete="off">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <h2><?= e(__('idade.verify_title')) ?></h2>
                <p><?= e(__('idade.verify_intro')) ?></p>
                <?php if ($status === 'desconhecido' || $status === 'menor'): ?>
                <label class="idade-label" for="idade-nasc2"><?= e(__('idade.birth_label')) ?> <span class="idade-req" aria-hidden="true">*</span></label>
                <input class="field" type="date" id="idade-nasc2" name="nascimento" required max="<?= $hoje ?>" min="1900-01-01">
                <?php endif; ?>
                <label class="idade-label" for="idade-cpf"><?= e(__('idade.cpf_label')) ?> <span class="idade-req" aria-hidden="true">*</span></label>
                <input class="field mono" type="text" id="idade-cpf" name="cpf" inputmode="numeric" autocomplete="off" required maxlength="14" placeholder="000.000.000-00">
                <small class="idade-hint"><?= e(__('idade.cpf_not_stored')) ?> <?= e(__('idade.one_account')) ?></small>
                <?php if (!$tem_consentimento): ?>
                <label class="idade-check">
                    <input type="checkbox" name="terms_ok" value="1" required>
                    <span><?= e(__('idade.terms_label')) ?><br><small><?= $termos ?></small></span>
                </label>
                <?php endif; ?>
                <button type="submit" class="btn"><?= e(__('idade.verify_btn')) ?></button>
            </form>
        <?php endif; ?>

    </div>
</section>

<style>
/* Verificacao de idade: mesma familia visual dos paineis publicos (clan-about, checkout). */
.idade-hero { min-height: 34vh; padding-bottom: 1.5rem; }
.idade-lead { color: var(--dim); max-width: 62ch; margin: 0.8rem auto 0; line-height: 1.55; }
.idade-wrap { padding-top: 2.5rem; }
.idade-col  { max-width: 640px; display: grid; gap: 1.25rem; }
.idade-card {
    background: var(--bg-1);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 1.4rem 1.5rem 1.5rem;
}
.idade-card h2 { font-size: 1.35rem; color: var(--bone); margin: 0 0 0.5rem; letter-spacing: 0.02em; }
.idade-card p  { color: var(--dim); line-height: 1.55; margin: 0 0 1.1rem; }
.idade-card-menor { border-color: var(--hazard-border); background: var(--hazard-overlay); }
.idade-card-menor p { margin-bottom: 0; }
.idade-form { display: grid; gap: 0.55rem; }
.idade-form .btn { justify-self: start; margin-top: 0.6rem; }
.idade-label { color: var(--bone); font-size: 0.9rem; margin-top: 0.45rem; }
.idade-req   { color: var(--hazard); }
.idade-form .field { width: 100%; }
.idade-hint  { display: block; color: var(--dim); font-size: 0.82rem; line-height: 1.5; margin-top: 0.15rem; }
.idade-check { display: flex; gap: 0.7rem; align-items: flex-start; color: var(--bone); font-size: 0.92rem; line-height: 1.45; margin-top: 0.6rem; cursor: pointer; }
.idade-check input { width: 18px; height: 18px; margin-top: 0.15rem; flex: 0 0 auto; }
.idade-check small { color: var(--dim); }
.idade-check a { color: var(--hazard); text-decoration: none; }
.idade-check a:hover { text-decoration: underline; }
.idade-msg { border-radius: 4px; padding: 0.8rem 1.1rem; line-height: 1.5; }
.idade-msg-erro { background: var(--danger-overlay); border: 1px solid var(--danger-border); color: var(--text-danger); }
.idade-msg-ok   { background: rgba(22,163,74,0.14); border: 1px solid rgba(22,163,74,0.35); color: var(--text-success); }
@media (max-width: 560px) {
    .idade-card { padding: 1.1rem 1rem 1.2rem; }
    .idade-form .btn { justify-self: stretch; text-align: center; }
}
</style>
<?php \App\View::endSection(); ?>
