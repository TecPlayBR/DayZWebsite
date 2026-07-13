<?php /** @var array $config, $vip; @var array $durations */ ?>
<?php $title = 'Venda de VIP'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<?php
$field = 'width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);';
$mono  = $field . ' font-family:var(--font-mono);';
// Linhas: 4 tiers de VIP + BattlePass. Cada uma com a config atual.
$rows = [];
foreach (\App\Vip::VIP_TIERS as $key) {
    $rows[] = ['cfg' => $vip['tiers'][$key], 'badge' => $key, 'en' => "en_{$key}", 'lbl' => "label_{$key}", 'desc' => "desc_{$key}", 'pre' => "price_{$key}_", 'img' => "image_{$key}", 'perks' => "perks_{$key}"];
}
$rows[] = ['cfg' => $vip['battlepass'], 'badge' => 'BattlePass', 'en' => 'en_bp', 'lbl' => 'label_bp', 'desc' => 'desc_bp', 'pre' => 'price_bp_', 'img' => 'image_bp', 'perks' => 'perks_bp'];
?>

<div class="admin-page-head">
    <div>
        <h1>🪙 Venda de VIP / Passe</h1>
        <p>Preço <strong>em moedas</strong> de cada plano por duração. O jogador compra na aba <a href="/vip" target="_blank" style="color:var(--hazard);">/vip</a> e o agent aplica no Sparda - igual à concessão em <a href="/admin/entitlements" style="color:var(--hazard);">VIP/Passe</a>.</p>
    </div>
    <a href="/admin/entitlements" class="btn-mini outline">Conceder manual →</a>
</div>

<?php if (isset($_GET['ok'])): ?>
    <div class="stat-card" style="margin-bottom:1.2rem; border-left:3px solid var(--moss);">✓ Preços salvos.</div>
<?php endif; ?>

<form method="POST" action="/admin/vip" enctype="multipart/form-data" style="max-width:820px;">
    <?= \App\Csrf::field() ?>

    <div class="stat-card" style="margin-bottom:1.2rem;">
        <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer;">
            <input type="checkbox" name="enabled" value="1" <?= $vip['enabled'] ? 'checked' : '' ?> style="width:18px; height:18px;">
            <span style="font-size:1rem; color:var(--bone); font-weight:600;">Ligar a venda de VIP/Passe por moedas</span>
        </label>
        <p style="color:var(--dim); font-size:0.8rem; margin:0.5rem 0 0 1.8rem;">Desligado, a aba <code>/vip</code> some do site. Cada plano também tem seu liga/desliga e só aparece se tiver ao menos um preço &gt; 0. Durações: 30/60/90 dias, qualquer tier. Preço 0/vazio = não vende aquela opção.</p>
    </div>

    <?php foreach ($rows as $r): $c = $r['cfg']; ?>
        <div class="stat-card" style="margin-bottom:1rem;">
            <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; margin-bottom:1rem;">
                <input type="checkbox" name="<?= $r['en'] ?>" value="1" <?= $c['enabled'] ? 'checked' : '' ?> style="width:16px; height:16px;">
                <span class="label" style="margin:0;"><?= e($r['badge']) ?></span>
            </label>

            <div style="display:grid; grid-template-columns:1fr 2fr; gap:1rem; margin-bottom:1rem;">
                <div>
                    <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Nome exibido</label>
                    <input type="text" name="<?= $r['lbl'] ?>" maxlength="60" value="<?= e($c['label']) ?>" style="<?= $field ?>">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Descrição curta</label>
                    <input type="text" name="<?= $r['desc'] ?>" maxlength="200" value="<?= e($c['desc']) ?>" placeholder="ex: kit inicial + 2 slots de garagem" style="<?= $field ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:120px 1fr; gap:1rem; margin-bottom:1rem; align-items:start;">
                <div>
                    <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Imagem</label>
                    <?php if (!empty($c['image'])): ?>
                        <img src="<?= e($c['image']) ?>" alt="" style="width:110px; height:110px; object-fit:contain; border:1px solid var(--border); border-radius:6px; background:var(--bg-0); margin-bottom:0.4rem;">
                    <?php else: ?>
                        <div style="width:110px; height:110px; border:1px dashed var(--border); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--dim); font-size:1.8rem;">🖼️</div>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="display:block; font-size:0.72rem; color:var(--dim); margin-bottom:0.25rem;">Enviar imagem do plano (PNG/WEBP/JPG)</label>
                    <input type="file" name="<?= $r['img'] ?>_file" accept="image/png,image/webp,image/jpeg" style="color:var(--bone); font-size:0.85rem; margin-bottom:0.6rem;">
                    <label style="display:block; font-size:0.72rem; color:var(--dim); margin-bottom:0.25rem;">ou cole uma URL de imagem</label>
                    <input type="text" name="<?= $r['img'] ?>" maxlength="255" value="<?= e($c['image'] ?? '') ?>" placeholder="https://... ou /assets/img/vip/arquivo.png" style="<?= $field ?>">
                    <p style="color:var(--dim); font-size:0.7rem; margin:0.35rem 0 0;">Deixe os dois em branco pra manter a imagem atual.</p>
                </div>
            </div>

            <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Benefícios (1 por linha, até 8) - aparecem em lista no card</label>
            <textarea name="<?= $r['perks'] ?>" rows="4" placeholder="Kit inicial exclusivo&#10;2 slots de garagem&#10;Acesso ao canal VIP" style="<?= $field ?> font-family:var(--font-mono); font-size:0.82rem; margin-bottom:1rem;"><?= e(implode("\n", $c['perks'] ?? [])) ?></textarea>

            <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.4rem;">Preço por duração (🪙 moedas)</label>
            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem;">
                <?php foreach ($durations as $d): ?>
                    <div>
                        <label style="display:block; font-size:0.72rem; color:var(--dim); margin-bottom:0.25rem;"><?= (int)$d ?> dias</label>
                        <input type="number" min="0" name="<?= $r['pre'] . $d ?>" value="<?= $c['prices'][(string)$d] ?? '' ?>" placeholder="0" style="<?= $mono ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn-mini" style="padding:0.7rem 1.6rem;">Salvar preços</button>
</form>

<?php \App\View::endSection(); ?>
