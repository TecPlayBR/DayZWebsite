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
    'updated'  => 'Cupom atualizado.',
    'toggled'  => 'Status atualizado.',
    'deleted'  => 'Cupom removido.',
    default => null,
};
$pkgMap = array_column($packages ?? [], 'name', 'id');
?>
<?php if ($errMsg): ?>
    <div style="background:var(--danger-overlay);border-left:3px solid var(--rust-2);padding:0.8rem 1rem;margin-bottom:1.5rem;color:var(--text-danger);"><?= e($errMsg) ?></div>
<?php elseif ($okMsg): ?>
    <div class="alert-toast"><?= e($okMsg) ?></div>
<?php endif; ?>

<form method="POST" action="/admin/coupons/create" class="stat-card" style="padding: 1.5rem; margin-bottom: 2rem;" data-adm-form>
    <?= \App\Csrf::field() ?>
    <div class="label" style="margin-bottom: 1rem;">+ Novo cupom</div>

    <div class="adm-grid-3 adm-bloco">
        <?= admin_campo(['label' => 'Código', 'name' => 'code', 'required' => true, 'placeholder' => 'BLACKFRIDAY20', 'class' => 'mono upper', 'attrs' => 'minlength="3" data-filtro="codigo"', 'hint' => 'O que o jogador digita no checkout. Letras, números, _ e -.']) ?>
        <?= admin_campo(['label' => 'Tipo', 'name' => 'discount_type', 'type' => 'select', 'value' => 'percent', 'options' => ['percent' => '% Percentual', 'fixed' => 'R$ Fixo', 'coins' => '🪙 Moedas bônus']]) ?>
        <?= admin_campo(['label' => 'Valor', 'name' => 'discount_value', 'type' => 'number', 'required' => true, 'placeholder' => '20', 'class' => 'mono', 'attrs' => 'min="0.01" step="0.01"', 'hint' => 'Porcentagem, reais de desconto ou, em Moedas bônus, quantas moedas o jogador ganha a mais (sem desconto no preço).']) ?>
        <?= admin_campo(['label' => 'Máx. usos no total', 'name' => 'max_uses', 'type' => 'number', 'placeholder' => '∞', 'attrs' => 'min="1"', 'hint' => 'Somando todos os jogadores. Vazio = ilimitado.']) ?>
        <?= admin_campo(['label' => 'Máx. por jogador', 'name' => 'per_user_limit', 'type' => 'number', 'placeholder' => '∞', 'attrs' => 'min="1"', 'hint' => '1 = cada pessoa usa uma vez (ex.: cupom de aniversário). Vazio = sem limite.']) ?>
    </div>

    <?php if (!empty($packages)): ?>
    <div class="adm-bloco">
        <p class="adm-label" style="margin:0 0 .4rem;">Vale só pra estes pacotes</p>
        <div class="adm-checks">
            <?php foreach ($packages as $pk): ?>
                <label class="adm-check"><input type="checkbox" name="package_ids[]" value="<?= e($pk['id']) ?>"> <?= e($pk['name']) ?></label>
            <?php endforeach; ?>
        </div>
        <small class="adm-hint">Nada marcado = vale pra todos os pacotes.</small>
    </div>
    <?php endif; ?>

    <details class="adm-secao">
        <summary>🎮 Programa de afiliado / streamer <span style="color:var(--dim);">(opcional, paga cachê por venda)</span></summary>
        <p class="adm-hint" style="margin:0 0 .8rem;">
            O cliente se atrela a este streamer ao usar o cupom (1 streamer por vez). O cachê é calculado
            sobre o <strong>valor cheio</strong>, só em compra <strong>paga</strong>, escalonado pela recorrência do cliente.
            Ative o programa e o relatório em <a href="/admin/settings" style="color:var(--hazard);">Configurações</a> e <a href="/admin/streamers" style="color:var(--hazard);">Streamers</a>.
        </p>
        <div class="adm-grid-4">
            <?= admin_campo(['label' => 'Streamer', 'name' => 'affiliate_name', 'placeholder' => 'ex: Streamer Exemplo', 'attrs' => 'maxlength="120"', 'hint' => 'Quem recebe o cachê das vendas com este cupom.']) ?>
            <?= admin_campo(['label' => '% 1ª compra', 'name' => 'commission_pct_1', 'type' => 'number', 'placeholder' => '5', 'class' => 'mono', 'attrs' => 'min="0" max="100" step="0.5"']) ?>
            <?= admin_campo(['label' => '% 2ª compra', 'name' => 'commission_pct_2', 'type' => 'number', 'placeholder' => '10', 'class' => 'mono', 'attrs' => 'min="0" max="100" step="0.5"']) ?>
            <?= admin_campo(['label' => '% 3ª+ compra', 'name' => 'commission_pct_3plus', 'type' => 'number', 'placeholder' => '0', 'class' => 'mono', 'attrs' => 'min="0" max="100" step="0.5"']) ?>
        </div>
    </details>

    <div class="adm-grid-3 adm-bloco">
        <?= admin_campo(['label' => 'Válido a partir de', 'name' => 'valid_from', 'type' => 'datetime-local', 'hint' => 'Vazio = já vale ao criar.']) ?>
        <?= admin_campo(['label' => 'Válido até', 'name' => 'valid_until', 'type' => 'datetime-local', 'hint' => 'Vazio = não expira.']) ?>
        <?= admin_campo(['label' => 'Notas internas', 'name' => 'notes', 'placeholder' => 'ex: Black Friday 2026', 'hint' => 'Só a equipe vê.']) ?>
    </div>

    <div class="adm-acoes"><button type="submit" class="btn-mini" style="padding: 0.6rem 1.4rem;">Criar cupom</button></div>
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
                <td>
                    <strong class="mono" style="color: var(--hazard); user-select: all;"><?= e($c['code']) ?></strong>
                    <?php $cpids = !empty($c['package_ids']) ? (json_decode($c['package_ids'], true) ?: []) : []; ?>
                    <?php if ($cpids): ?>
                        <div class="dim" style="font-size:0.72rem;">só: <?= e(implode(', ', array_map(fn($id) => $pkgMap[$id] ?? $id, $cpids))) ?></div>
                    <?php endif; ?>
                    <?php if (\App\Coupon::isAffiliate($c)):
                        $fp = fn($v) => rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ','); ?>
                        <div style="font-size:0.72rem; color:var(--moss);">🎮 <?= e($c['affiliate_name'] ?: 'afiliado') ?> · cachê <?= $fp($c['commission_pct_1']) ?>/<?= $fp($c['commission_pct_2']) ?>/<?= $fp($c['commission_pct_3plus']) ?>%</div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c['discount_type'] === 'percent'): ?>
                        <span style="color: var(--rust-2); font-family: var(--font-display); font-size: 1.1rem;"><?= rtrim(rtrim(number_format((float)$c['discount_value'], 2, ',', ''), '0'), ',') ?>%</span>
                    <?php elseif ($c['discount_type'] === 'coins'): ?>
                        <span style="color: var(--moss); font-family: var(--font-display); font-size: 1.1rem;">🪙 <?= number_format((int)$c['discount_value'], 0, ',', '.') ?></span>
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
                    <?php if (!empty($c['per_user_limit'])): ?>
                        <div class="dim" style="font-size:0.7rem;">máx <?= (int)$c['per_user_limit'] ?>x/jogador</div>
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
                <td class="dim" style="font-size: 0.8rem;"><?= e($c['notes'] ?? '-') ?></td>
                <td style="white-space: nowrap;">
                    <a href="/admin/coupons/<?= (int)$c['id'] ?>/edit" class="btn-mini outline">Editar</a>
                    <form method="POST" action="/admin/coupons/<?= (int)$c['id'] ?>/toggle" style="display: inline;">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline"><?= (int)$c['active'] ? 'Desativar' : 'Ativar' ?></button>
                    </form>
                    <form method="POST" action="/admin/coupons/<?= (int)$c['id'] ?>/delete" style="display: inline;"
                          data-confirm="Apagar cupom <?= e($c['code']) ?> permanentemente?">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline" style="border-color: var(--danger-border); color: var(--text-danger);">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php \App\View::endSection(); ?>
