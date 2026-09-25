<?php /** @var array $config, $ultimas; @var string $modo, $fornecedor; @var bool $tem_chave, $ok; @var int $n_verificados, $n_declarados, $n_menores, $n_falhas_30d; @var ?array $teste */ ?>
<?php $title = 'Conformidade ECA Digital'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<div class="admin-page-head">
    <div>
        <h1>🛡 Proteção de menores (ECA Digital)</h1>
        <p>Lei 15.211/2025 e Decreto 12.880/2026. Caixa de recompensa só para adulto verificado. Esta tela mostra só metadados: nunca CPF, nunca data de nascimento.</p>
    </div>
</div>

<?php if ($ok): ?><div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--moss);">✓ Feito.</div><?php endif; ?>
<?php if ($teste): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid <?= !empty($teste['ok']) ? 'var(--moss)' : 'var(--danger-border)' ?>;">
        Teste da chave: <?= e((string) ($teste['msg'] ?? '')) ?>
    </div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.2rem; border-left:3px solid <?= $modo === 'verificado' ? 'var(--moss)' : ($modo === 'declaracao' ? 'var(--hazard)' : 'var(--danger-border)') ?>;">
    <strong>Modo atual: <?= e($modo) ?></strong>
    <?php if ($modo === 'desligado'): ?> · <span style="color:var(--rust-2);">não conforme: caixas abertas sem verificação</span>
    <?php elseif ($modo === 'declaracao'): ?> · transição: caixas fechadas até colar a chave e mudar para Verificado
    <?php else: ?> · conforme<?php endif; ?>
    · fornecedor <strong><?= e($fornecedor) ?></strong> <?= $tem_chave ? '(chave salva)' : '<span style="color:var(--rust-2);">(sem chave)</span>' ?>
    <form method="POST" action="/admin/eca/testar-chave" style="display:inline; margin-left:.8rem;"><?= \App\Csrf::field() ?><button class="btn btn-sm" type="submit">Testar chave</button></form>
    <a class="btn btn-sm" href="/admin/settings#eca" style="margin-left:.4rem;">Configurar</a>
    <a class="btn btn-sm" href="/admin/eca/relatorio" target="_blank" style="margin-left:.4rem;">Relatório de conformidade</a>
    <a class="btn btn-sm" href="/admin/eca/export.csv" style="margin-left:.4rem;">Exportar CSV</a>
</div>

<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.2rem;">
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_verificados ?></div><div style="color:var(--dim);">adultos verificados</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_declarados ?></div><div style="color:var(--dim);">adultos só declarados</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_menores ?></div><div style="color:var(--dim);">menores (bloqueados)</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_falhas_30d ?></div><div style="color:var(--dim);">falhas do fornecedor (30 dias)</div></div>
</div>

<div class="stat-card">
    <h3 style="margin-top:0;">Últimas 200 verificações</h3>
    <table class="admin-table" style="width:100%;">
        <thead><tr><th>#</th><th>SteamID</th><th>Método</th><th>Resultado</th><th>Ref. do fornecedor</th><th>Situação</th><th>Quando</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($ultimas as $r): ?>
            <tr style="<?= $r['revoked_at'] ? 'opacity:.55;' : '' ?>">
                <td><?= (int) $r['id'] ?></td>
                <td><a href="/player/<?= e($r['steam_id']) ?>" style="color:var(--hazard);"><?= e($r['steam_id']) ?></a></td>
                <td><?= e($r['method']) ?></td>
                <td><?= e($r['result']) ?><?= $r['revoked_at'] ? ' (revogada: ' . e((string) $r['revoked_reason']) . ')' : '' ?></td>
                <td style="font-family:var(--font-mono); font-size:.8rem;"><?= e((string) ($r['provider_ref'] ?? '')) ?></td>
                <td><?= e((string) ($r['provider_status'] ?? '')) ?></td>
                <td><?= e($r['created_at']) ?></td>
                <td>
                    <?php if (!$r['revoked_at'] && $r['result'] === 'adulto' && $r['method'] !== 'declaracao'): ?>
                    <form method="POST" action="/admin/eca/revogar" onsubmit="return confirm('Revogar esta verificação?');" style="display:flex; gap:.3rem;">
                        <?= \App\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input name="motivo" placeholder="motivo" required maxlength="160" style="padding:.3rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                        <button class="btn btn-sm" type="submit">Revogar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php \App\View::endSection(); ?>
