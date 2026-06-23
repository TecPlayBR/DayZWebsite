<?php /** @var array $config, $vip; @var array $durations */ ?>
<?php $title = 'Venda de VIP'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<?php
$inp = 'width:110px;padding:.45rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);font-family:var(--font-mono);';
$txt = 'width:100%;padding:.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);';
?>

<div class="admin-page-head">
    <div>
        <h1>🪙 Venda de VIP / Passe (moedas)</h1>
        <p>Define o preço <strong>em moedas</strong> de cada plano por duração. O jogador compra na aba <a href="/vip" target="_blank" style="color:var(--hazard);">/vip</a> e o agent aplica no Sparda — igual à concessão manual em <a href="/admin/entitlements" style="color:var(--hazard);">Conceder VIP/Passe</a>.</p>
    </div>
</div>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert-toast">Preços salvos.</div>
<?php endif; ?>

<form method="POST" action="/admin/vip">
    <?= \App\Csrf::field() ?>

    <div class="stat-card" style="padding:1.2rem 1.4rem;margin-bottom:1.5rem;">
        <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;font-size:1rem;color:var(--bone);">
            <input type="checkbox" name="enabled" value="1" <?= $vip['enabled'] ? 'checked' : '' ?> style="width:18px;height:18px;">
            <strong>Ligar a venda de VIP/Passe por moedas</strong>
        </label>
        <p style="color:var(--dim);font-size:0.8rem;margin:0.5rem 0 0 1.8rem;">Desligado, a aba <code>/vip</code> some do site. Cada plano abaixo também tem seu próprio liga/desliga e só aparece se tiver ao menos um preço.</p>
    </div>

    <p style="color:var(--dim);font-size:0.85rem;margin-bottom:0.8rem;">💡 Preço em <strong>moedas</strong>. Deixe <strong>0/vazio</strong> numa duração pra não vender aquela opção. As durações são 30/60/90 dias, em qualquer tier.</p>

    <?php
    $rows = [];
    foreach (\App\Vip::VIP_TIERS as $i => $key) {
        $rows[] = ['k' => $key, 'cfg' => $vip['tiers'][$key], 'enName' => "en_{$key}", 'lblName' => "label_{$key}", 'descName' => "desc_{$key}", 'priceName' => "price_{$key}_", 'badge' => $key];
    }
    $rows[] = ['k' => 'bp', 'cfg' => $vip['battlepass'], 'enName' => 'en_bp', 'lblName' => 'label_bp', 'descName' => 'desc_bp', 'priceName' => 'price_bp_', 'badge' => 'BattlePass'];
    ?>

    <?php foreach ($rows as $r): $c = $r['cfg']; ?>
        <div class="stat-card" style="padding:1.2rem 1.4rem;margin-bottom:1rem;">
            <div style="display:flex;flex-wrap:wrap;gap:1.2rem;align-items:flex-end;">
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;color:var(--bone);min-width:120px;">
                    <input type="checkbox" name="<?= $r['enName'] ?>" value="1" <?= $c['enabled'] ? 'checked' : '' ?> style="width:16px;height:16px;">
                    <strong><?= e($r['badge']) ?></strong>
                </label>
                <div style="flex:1;min-width:160px;">
                    <label style="display:block;font-size:.72rem;color:var(--dim);margin-bottom:.25rem;text-transform:uppercase;">Nome exibido</label>
                    <input type="text" name="<?= $r['lblName'] ?>" maxlength="60" value="<?= e($c['label']) ?>" style="<?= $txt ?>">
                </div>
                <div style="flex:2;min-width:200px;">
                    <label style="display:block;font-size:.72rem;color:var(--dim);margin-bottom:.25rem;text-transform:uppercase;">Descrição curta</label>
                    <input type="text" name="<?= $r['descName'] ?>" maxlength="200" value="<?= e($c['desc']) ?>" placeholder="ex: kit inicial + 2 slots de garagem" style="<?= $txt ?>">
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:1.2rem;margin-top:1rem;">
                <?php foreach ($durations as $d): ?>
                    <div>
                        <label style="display:block;font-size:.72rem;color:var(--dim);margin-bottom:.25rem;text-transform:uppercase;"><?= (int)$d ?> dias — 🪙</label>
                        <input type="number" min="0" name="<?= $r['priceName'] . $d ?>" value="<?= $c['prices'][(string)$d] ?? '' ?>" placeholder="0" style="<?= $inp ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn" style="margin-top:0.5rem;">Salvar preços</button>
</form>

<?php \App\View::endSection(); ?>
