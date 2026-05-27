<?php /** @var array $config, $coupons */ ?>
<?php $title = 'Cupons'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Cupons de desconto</h1>
        <p>Crie códigos que o cliente aplica no checkout (ex: <code>BLACKFRIDAY20</code> pra 20% off).</p>
    </div>
</div>

<?php
$err = $_GET['err'] ?? null; $ok = $_GET['ok'] ?? null;
$errMsg = match($err) {
    'code'      => 'Código inválido (mín. 3 caracteres, só A-Z 0-9 _ -)',
    'duplicate' => 'Já existe um cupom com esse código.',
    default => null,
};
$okMsg = match($ok) {
    'created'  => 'Cupom criado.',
    'toggled'  => 'Status atualizado.',
    'deleted'  => 'Cupom removido.',
    default => null,
};
?>
<?php if ($errMsg): ?>
    <div style="background:rgba(231,76,60,0.12);border-left:3px solid var(--rust-2);padding:0.8rem 1rem;margin-bottom:1.5rem;color:#fca5a5;"><?= e($errMsg) ?></div>
<?php elseif ($okMsg): ?>
    <div class="alert-toast"><?= e($okMsg) ?></div>
<?php endif; ?>

<form method="POST" action="/admin/coupons/create" class="stat-card" style="padding: 1.5rem; margin-bottom: 2rem;">
    <?= \App\Csrf::field() ?>
    <div class="label" style="margin-bottom: 1rem;">+ Novo cupom</div>
    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Código</label>
            <input type="text" name="code" required minlength="3" placeholder="BLACKFRIDAY20"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono); text-transform: uppercase;"
                   oninput="this.value = this.value.replace(/[^A-Za-z0-9_-]/g, '').toUpperCase()">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Tipo</label>
            <select name="discount_type" style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                <option value="percent">% Percentual</option>
                <option value="fixed">R$ Fixo</option>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Valor</label>
            <input type="number" name="discount_value" required min="0.01" step="0.01" placeholder="20"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Máx. usos <small>(opcional)</small></label>
            <input type="number" name="max_uses" min="1" placeholder="∞"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
        </div>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr 2fr auto; gap: 0.8rem; align-items: end;">
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Válido a partir <small>(opcional)</small></label>
            <input type="datetime-local" name="valid_from"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Válido até <small>(opcional)</small></label>
            <input type="datetime-local" name="valid_until"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Notas internas</label>
            <input type="text" name="notes" placeholder="ex: Black Friday 2026"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
        </div>
        <button type="submit" class="btn-mini" style="padding: 0.6rem 1.2rem;">Criar</button>
    </div>
</form>

<table class="admin-table">
    <thead>
        <tr>
            <th>Código</th>
            <th>Desconto</th>
            <th>Usos</th>
            <th>Janela</th>
            <th>Status</th>
            <th>Notas</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($coupons)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--dim);padding:2rem;">Nenhum cupom ainda.</td></tr>
        <?php else: foreach ($coupons as $c): ?>
            <tr>
                <td><strong class="mono" style="color: var(--hazard); user-select: all;"><?= e($c['code']) ?></strong></td>
                <td>
                    <?php if ($c['discount_type'] === 'percent'): ?>
                        <span style="color: var(--rust-2); font-family: var(--font-display); font-size: 1.1rem;"><?= rtrim(rtrim(number_format((float)$c['discount_value'], 2, ',', ''), '0'), ',') ?>%</span>
                    <?php else: ?>
                        <span style="color: var(--rust-2); font-family: var(--font-display); font-size: 1.1rem;">R$ <?= number_format((float)$c['discount_value'], 2, ',', '.') ?></span>
                    <?php endif; ?>
                </td>
                <td class="mono">
                    <?= (int)$c['used_count'] ?>
                    <?php if ($c['max_uses']): ?>
                        <span class="dim">/ <?= (int)$c['max_uses'] ?></span>
                    <?php else: ?>
                        <span class="dim">/ ∞</span>
                    <?php endif; ?>
                </td>
                <td class="dim" style="font-size:0.8rem;">
                    <?= $c['valid_from']  ? 'De ' . e($c['valid_from']) : 'Sempre' ?><br>
                    <?= $c['valid_until'] ? 'Até ' . e($c['valid_until']) : '' ?>
                </td>
                <td>
                    <?php if ((int)$c['active']): ?>
                        <span class="badge success">ativo</span>
                    <?php else: ?>
                        <span class="badge info">desativado</span>
                    <?php endif; ?>
                </td>
                <td class="dim" style="font-size: 0.8rem;"><?= e($c['notes'] ?? '—') ?></td>
                <td style="white-space: nowrap;">
                    <form method="POST" action="/admin/coupons/<?= (int)$c['id'] ?>/toggle" style="display: inline;">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline"><?= (int)$c['active'] ? 'Desativar' : 'Ativar' ?></button>
                    </form>
                    <form method="POST" action="/admin/coupons/<?= (int)$c['id'] ?>/delete" style="display: inline;"
                          onsubmit="return confirm('Apagar cupom <?= e($c['code']) ?> permanentemente?');">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline" style="border-color: rgba(231,76,60,0.4); color: #fca5a5;">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php \App\View::endSection(); ?>
