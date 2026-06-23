<?php /** @var array $config, $coupon, $packages */ ?>
<?php $title = 'Editar cupom'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<?php
$cpids = !empty($coupon['package_ids']) ? (json_decode($coupon['package_ids'], true) ?: []) : [];
// datetime do banco ("YYYY-MM-DD HH:MM:SS") -> formato do <input datetime-local> ("YYYY-MM-DDTHH:MM")
$dtLocal = function ($v): string {
    $v = trim((string) $v);
    if ($v === '') return '';
    return substr(str_replace(' ', 'T', $v), 0, 16);
};
$fieldStyle = 'width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);';
?>

<div class="admin-page-head">
    <div>
        <h1>Editar cupom</h1>
        <p>Ajuste o desconto, os limites e a janela de validade. O <strong>código</strong> não muda (compras e vínculos apontam pra ele).</p>
    </div>
    <a href="/admin/coupons" class="btn-mini outline">← Voltar</a>
</div>

<form method="POST" action="/admin/coupons/<?= (int)$coupon['id'] ?>/save" class="stat-card" style="padding: 1.5rem; max-width: 900px;">
    <?= \App\Csrf::field() ?>
    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Código <small>(fixo)</small></label>
            <input type="text" value="<?= e($coupon['code']) ?>" disabled
                   style="<?= $fieldStyle ?> font-family:var(--font-mono); opacity:0.6; cursor:not-allowed;">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Tipo</label>
            <select name="discount_type" style="<?= $fieldStyle ?>">
                <option value="percent" <?= $coupon['discount_type']==='percent'?'selected':'' ?>>% Percentual</option>
                <option value="fixed"   <?= $coupon['discount_type']==='fixed'?'selected':'' ?>>R$ Fixo</option>
                <option value="coins"   <?= $coupon['discount_type']==='coins'?'selected':'' ?>>🪙 Moedas bônus</option>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Valor</label>
            <input type="number" name="discount_value" required min="0.01" step="0.01" value="<?= e(rtrim(rtrim(number_format((float)$coupon['discount_value'], 2, '.', ''), '0'), '.')) ?>"
                   style="<?= $fieldStyle ?> font-family:var(--font-mono);">
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Máx. usos no TOTAL <small>(opcional)</small></label>
            <input type="number" name="max_uses" min="1" placeholder="∞" value="<?= $coupon['max_uses'] ? (int)$coupon['max_uses'] : '' ?>" style="<?= $fieldStyle ?>">
            <small style="color:var(--dim); font-size:0.7rem;">Somando TODOS os jogadores. Já usado: <?= (int)$coupon['used_count'] ?>x. Vazio = ilimitado.</small>
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Máx. por jogador <small>(opcional)</small></label>
            <input type="number" name="per_user_limit" min="1" placeholder="∞" value="<?= !empty($coupon['per_user_limit']) ? (int)$coupon['per_user_limit'] : '' ?>" style="<?= $fieldStyle ?>">
            <small style="color:var(--dim); font-size:0.7rem;">Ex: <strong>1</strong> = cada jogador usa uma vez só (cupom de aniversário). Vazio = sem limite por jogador.</small>
        </div>
    </div>

    <?php if (!empty($packages)): ?>
    <div style="margin-bottom: 0.8rem;">
        <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.4rem; text-transform: uppercase;">Vale só pra estes pacotes <small>(nada marcado = todos)</small></label>
        <div style="display:flex; flex-wrap:wrap; gap:0.9rem;">
            <?php foreach ($packages as $pk): ?>
                <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.85rem; color:var(--bone); cursor:pointer;">
                    <input type="checkbox" name="package_ids[]" value="<?= e($pk['id']) ?>" <?= in_array($pk['id'], $cpids, true)?'checked':'' ?>> <?= e($pk['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <details style="margin-bottom: 0.8rem; border:1px solid var(--border); border-radius:4px; padding:0.6rem 0.9rem;" <?= \App\Coupon::isAffiliate($coupon)?'open':'' ?>>
        <summary style="cursor:pointer; font-size:0.85rem; color:var(--bone);">🎮 Programa de afiliado / streamer <span style="color:var(--dim);">(opcional)</span></summary>
        <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:0.8rem; margin-top:0.6rem;">
            <div>
                <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform:uppercase;">Streamer</label>
                <input type="text" name="affiliate_name" maxlength="120" value="<?= e($coupon['affiliate_name'] ?? '') ?>" style="<?= $fieldStyle ?>">
            </div>
            <?php foreach (['commission_pct_1'=>'% 1ª compra','commission_pct_2'=>'% 2ª compra','commission_pct_3plus'=>'% 3ª+ compra'] as $k=>$lbl): ?>
            <div>
                <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform:uppercase;"><?= $lbl ?></label>
                <input type="number" name="<?= $k ?>" min="0" max="100" step="0.5" value="<?= e(rtrim(rtrim(number_format((float)($coupon[$k] ?? 0), 2, '.', ''), '0'), '.')) ?>" style="<?= $fieldStyle ?> font-family:var(--font-mono);">
            </div>
            <?php endforeach; ?>
        </div>
    </details>

    <div style="display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 0.8rem; margin-bottom: 1rem;">
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Válido a partir <small>(opcional)</small></label>
            <input type="datetime-local" name="valid_from" value="<?= e($dtLocal($coupon['valid_from'] ?? '')) ?>" style="<?= $fieldStyle ?>">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Válido até <small>(opcional)</small></label>
            <input type="datetime-local" name="valid_until" value="<?= e($dtLocal($coupon['valid_until'] ?? '')) ?>" style="<?= $fieldStyle ?>">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Notas internas</label>
            <input type="text" name="notes" value="<?= e($coupon['notes'] ?? '') ?>" style="<?= $fieldStyle ?>">
        </div>
    </div>

    <div style="display:flex; gap:0.8rem; align-items:center;">
        <button type="submit" class="btn-mini" style="padding: 0.6rem 1.5rem;">Salvar alterações</button>
        <a href="/admin/coupons" class="btn-mini outline">Cancelar</a>
    </div>
</form>

<?php \App\View::endSection(); ?>
