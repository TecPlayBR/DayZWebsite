<?php /** @var array $config, $hf */ ?>
<?php $title = 'Seção da Home'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<?php
$field = 'width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);';
$cards = $hf['cards'] ?? [];
// Sempre 8 linhas: preenche com os cards atuais, resto vazio (linha sem título = descartada).
while (count($cards) < 8) $cards[] = ['icon' => '', 'title' => '', 'text' => ''];
?>

<div class="admin-page-head">
    <div>
        <h1>🏠 Seção "O Que Você Vai Encontrar"</h1>
        <p>Os cards que aparecem na home (abaixo dos depoimentos). Venda o que o servidor tem de bom. Linha <strong>sem título é ignorada</strong> — deixe vazia pra ter menos cards.</p>
    </div>
    <a href="/" target="_blank" class="btn-mini outline">Ver home →</a>
</div>

<?php if (isset($_GET['ok'])): ?>
    <div class="stat-card" style="margin-bottom:1.2rem; border-left:3px solid var(--moss);">✓ Seção salva.</div>
<?php endif; ?>

<form method="POST" action="/admin/home-features" style="max-width:880px;">
    <?= \App\Csrf::field() ?>

    <div class="stat-card" style="margin-bottom:1.2rem;">
        <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; margin-bottom:1rem;">
            <input type="checkbox" name="enabled" value="1" <?= !empty($hf['enabled']) ? 'checked' : '' ?> style="width:18px; height:18px;">
            <strong style="color:var(--bone);">Mostrar a seção na home</strong>
        </label>
        <div style="margin-bottom:1rem;">
            <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Título</label>
            <input type="text" name="title" maxlength="80" value="<?= e($hf['title'] ?? '') ?>" style="<?= $field ?>">
        </div>
        <div>
            <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Subtítulo</label>
            <input type="text" name="subtitle" maxlength="240" value="<?= e($hf['subtitle'] ?? '') ?>" style="<?= $field ?>">
        </div>
    </div>

    <div class="stat-card">
        <div class="label" style="margin-bottom:0.4rem;">Cards (até 8)</div>
        <p style="color:var(--dim); font-size:0.78rem; margin-bottom:1rem;">Ícone = 1 emoji (ex: 🔫 💣 🏗️ 💰 🏪 📦). Texto curto, focado no benefício pro jogador.</p>
        <?php foreach ($cards as $i => $c): ?>
            <div style="display:grid; grid-template-columns:70px 1fr; gap:0.8rem; margin-bottom:0.9rem; padding-bottom:0.9rem; border-bottom:1px solid var(--border);">
                <div>
                    <label style="display:block; font-size:0.7rem; color:var(--dim); margin-bottom:0.25rem;">Ícone</label>
                    <input type="text" name="card_icon[]" maxlength="8" value="<?= e($c['icon'] ?? '') ?>" style="<?= $field ?> text-align:center; font-size:1.2rem;">
                </div>
                <div>
                    <label style="display:block; font-size:0.7rem; color:var(--dim); margin-bottom:0.25rem;">Título do card <?= $i + 1 ?></label>
                    <input type="text" name="card_title[]" maxlength="60" value="<?= e($c['title'] ?? '') ?>" placeholder="(vazio = card ignorado)" style="<?= $field ?> margin-bottom:0.5rem;">
                    <textarea name="card_text[]" rows="2" maxlength="240" placeholder="Descrição curta (benefício)" style="<?= $field ?> resize:vertical;"><?= e($c['text'] ?? '') ?></textarea>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="submit" class="btn-mini" style="padding:0.7rem 1.6rem; margin-top:1rem;">Salvar seção</button>
</form>

<?php \App\View::endSection(); ?>
