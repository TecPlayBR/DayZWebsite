<?php /** @var array $config, $logs, $actions; @var string $filter */ ?>
<?php $title = 'Audit Log'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Audit Log</h1>
        <p>Rastreio de ações dos administradores. Apenas leitura. Útil pra investigar quem fez o quê.</p>
    </div>
    <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
        <a href="/admin/audit" class="btn-mini outline">Todos</a>
        <?php foreach (array_slice($actions, 0, 6) as $a): ?>
            <a href="/admin/audit?action=<?= e($a['prefix']) ?>" class="btn-mini outline"><?= e($a['prefix']) ?> (<?= (int)$a['n'] ?>)</a>
        <?php endforeach; ?>
    </div>
</div>

<table class="admin-table">
    <thead>
        <tr>
            <th>Quando</th>
            <th>Admin</th>
            <th>Ação</th>
            <th>Alvo</th>
            <th>Detalhes</th>
            <th>IP</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr><td colspan="6" style="text-align:center; color:var(--dim); padding:2rem;">Nenhum registro <?= $filter ? 'pra esse filtro' : 'ainda' ?>.</td></tr>
        <?php else: foreach ($logs as $l): ?>
            <tr>
                <td class="dim mono" style="font-size:0.78rem; white-space: nowrap;"><?= e($l['created_at']) ?></td>
                <td><strong style="font-family: var(--font-mono); color: var(--bone);"><?= e($l['admin_username'] ?? '—') ?></strong></td>
                <td><span class="badge info" style="font-family: var(--font-mono);"><?= e($l['action']) ?></span></td>
                <td class="mono" style="font-size: 0.8rem;">
                    <?php if ($l['target_type']): ?>
                        <span class="dim"><?= e($l['target_type']) ?>:</span><?= e($l['target_id'] ?? '—') ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($l['payload'])): ?>
                        <details>
                            <summary style="cursor: pointer; color: var(--hazard); font-family: var(--font-mono); font-size: 0.8rem;">ver payload</summary>
                            <pre style="background: var(--bg-0); padding: 0.4rem 0.6rem; margin-top: 0.3rem; font-size: 0.75rem; color: var(--bone); border-left: 2px solid var(--rust); overflow-x: auto;"><?= e(json_encode(json_decode($l['payload']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        </details>
                    <?php else: ?>
                        <span class="dim">—</span>
                    <?php endif; ?>
                </td>
                <td class="dim mono" style="font-size: 0.75rem;"><?= e($l['ip'] ?? '—') ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<p style="margin-top: 1.5rem; color: var(--dim); font-size: 0.85rem;">
    Mostrando últimas 200 entradas. Pra histórico mais profundo, consulta direta no banco (<code>SELECT * FROM audit_log</code>).
</p>

<?php \App\View::endSection(); ?>
