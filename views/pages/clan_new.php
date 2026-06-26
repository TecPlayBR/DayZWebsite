<?php /** @var array $config */ ?>
<?php \App\View::with('title', 'Criar clã'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background2.png'); ?>
<?php \App\View::section('content'); ?>
<?php
$errMsg = match($_GET['err'] ?? '') {
    'csrf' => 'Sessão expirada, tente de novo.',
    'rate' => 'Calma — muitas tentativas. Aguarde um pouco.',
    '' => '',
    default => \App\Clan::errorMessage($_GET['err']),
};
?>
<section class="hero" style="min-height:26vh;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.6) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background2.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// NOVO CLÃ</span>
        <h1 class="hero-title">Criar <span class="accent">clã</span></h1>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width:640px;">
        <?php if ($errMsg): ?><div style="background:rgba(231,76,60,0.18);border-left:3px solid var(--rust-2);color:var(--text-danger);padding:.8rem 1.1rem;margin-bottom:1.2rem;border-radius:4px;"><?= e($errMsg) ?></div><?php endif; ?>

        <form method="POST" action="/clans/create" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:1rem;">
            <?= \App\Csrf::field() ?>
            <div class="clan-new-namerow">
                <div>
                    <label style="display:block;font-size:.78rem;color:var(--dim);margin-bottom:.3rem;">Nome do clã</label>
                    <input type="text" name="name" required minlength="3" maxlength="60" placeholder="Renascer Hardcore" class="field" style="width:100%;">
                </div>
                <div>
                    <label style="display:block;font-size:.78rem;color:var(--dim);margin-bottom:.3rem;">TAG</label>
                    <input type="text" name="tag" required minlength="2" maxlength="6" placeholder="RVH" oninput="this.value=this.value.replace(/[^A-Za-z0-9]/g,'').toUpperCase()" class="field mono upper" style="width:100%;text-align:center;">
                </div>
            </div>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--dim);margin-bottom:.3rem;">Descrição <small>(opcional)</small></label>
                <textarea name="description" rows="3" maxlength="500" placeholder="Estilo do clã, requisitos pra entrar, horários..." class="field" style="width:100%;"></textarea>
            </div>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--dim);margin-bottom:.3rem;">Discord do clã <small>(opcional)</small></label>
                <input type="url" name="discord_url" placeholder="https://discord.gg/..." class="field" style="width:100%;">
            </div>
            <div>
                <label style="display:block;font-size:.78rem;color:var(--dim);margin-bottom:.3rem;">Logo <small>(opcional, PNG/JPG/WEBP)</small></label>
                <input type="file" name="logo_file" accept="image/png,image/webp,image/jpeg" style="color:var(--bone);font-size:.85rem;">
            </div>

            <div style="background:var(--bg-1);border:1px solid var(--border);border-left:3px solid var(--hazard);border-radius:5px;padding:.9rem 1.1rem;font-size:.82rem;color:var(--dim);line-height:1.5;">
                ⚠ <strong style="color:var(--bone);">Regras do clã:</strong> nada de conteúdo pornográfico, de ódio, político, ou uso de <strong>marcas/logos de terceiros</strong>. Conteúdo impróprio é removido e pode dar ban. Ao criar, você é responsável pelo nome, TAG, logo e descrição (Termos de Uso).
            </div>

            <button type="submit" class="btn" style="align-self:flex-start;">Criar clã</button>
        </form>
    </div>
</section>
<style>
.clan-new-namerow { display:grid; grid-template-columns:1fr 140px; gap:1rem; }
@media (max-width:440px) { .clan-new-namerow { grid-template-columns:1fr; } }
</style>
<?php \App\View::endSection(); ?>
