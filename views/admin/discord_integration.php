<?php
/** @var array $config, $vars */
/** @var string $token, $tokenMasked, $lastOk, $statusColor, $statusLabel, $publicUrl */
/** @var array  $log */
?>
<?php $title = 'Integração Discord'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🤖 Integração Discord</h1>
        <p>Conecta esse site ao <strong>Tecplay Bot Discord</strong> (Pro/Free). O bot consulta dados de players, vendas e stats via API autenticada por Bearer token.</p>
    </div>
</div>

<?php if (!empty($_GET['flash'])): ?>
    <div class="alert-toast"><?= e($_GET['flash']) ?></div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom: 1rem;">
    <div class="label">Status da integração</div>
    <div style="margin-top: 0.8rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <span style="padding:0.4rem 0.9rem;border-radius:6px;background:<?= e($statusColor) ?>;color:#fff;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.1em;">
            <?= e($statusLabel) ?>
        </span>
        <span style="color:var(--dim); font-size:0.85rem;">
            Última chamada bem-sucedida:
            <strong style="color:var(--bone);">
                <?= $lastOk > 0 ? e(date('d/m/Y H:i:s', $lastOk)) : '— nunca —' ?>
            </strong>
        </span>
    </div>
</div>

<div class="stat-card" style="margin-bottom: 1rem;">
    <div class="label">Token de acesso</div>
    <p style="color:var(--dim); font-size:0.85rem; margin: 0.7rem 0;">
        Esse token é o que o bot Discord usa pra se autenticar. Gera um novo e cola no painel
        do bot, aba "Integração Site". <strong>Gerar novo invalida o anterior na hora.</strong>
    </p>

    <?php if ($token === ''): ?>
        <div style="background:rgba(193,68,14,0.15); border-left:3px solid #c1440e; padding:0.8rem 1rem; border-radius:4px; margin-bottom:1rem; font-size:0.9rem;">
            ⚠ Nenhum token configurado. Endpoint <code>/api/bot-integration.php</code> vai
            retornar 401 até você gerar um.
        </div>
    <?php else: ?>
        <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:0.5rem;">
            <code id="tok-display" style="background:var(--bg-0); padding:0.6rem 0.9rem; border-radius:6px; flex:1; font-family:var(--font-mono); font-size:0.95rem; user-select:all;">
                <?= e($tokenMasked) ?>
            </code>
            <button type="button" onclick="navigator.clipboard.writeText(<?= json_encode($token) ?>).then(() => { this.textContent='✓ Copiado!'; setTimeout(()=>this.textContent='📋 Copiar', 1500); })" style="padding:0.6rem 1rem; background:var(--hazard); color:#000; border:none; border-radius:6px; cursor:pointer; font-weight:600;">📋 Copiar</button>
        </div>
        <div style="font-size:0.8rem; color:var(--dim);">
            Token completo (clica em "Copiar"): <strong style="color:var(--hazard);"><?= strlen($token) ?> caracteres</strong>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/discord-integration/regenerate" style="margin-top:1rem;" onsubmit="return confirm('Gerar um token NOVO vai invalidar o atual imediatamente. O bot vai perder acesso até você colar o novo token no painel dele.\n\nConfirma?');">
        <?= \App\Csrf::field() ?>
        <button type="submit" style="background:var(--rust); color:#fff; padding:0.6rem 1.2rem; border:none; border-radius:6px; cursor:pointer; font-weight:600;">
            🔄 Gerar novo token
        </button>
    </form>
</div>

<div class="stat-card" style="margin-bottom: 1rem;">
    <div class="label">Endpoint público</div>
    <p style="color:var(--dim); font-size:0.85rem; margin: 0.7rem 0;">
        URL que o bot Discord consome. Cola essa URL <strong>SEM o /api/bot-integration.php</strong> no painel do bot:
    </p>
    <code style="background:var(--bg-0); padding:0.6rem 0.9rem; border-radius:6px; display:block; font-family:var(--font-mono); font-size:0.95rem; user-select:all;">
        <?= e($publicUrl) ?>
    </code>
    <p style="color:var(--dim); font-size:0.8rem; margin-top:0.5rem;">
        Bot vai testar chamando <code><?= e($publicUrl) ?>/api/bot-integration.php</code> com o token acima como Bearer.
    </p>
</div>

<div class="stat-card">
    <div class="label">Últimas 10 chamadas</div>
    <?php if (empty($log)): ?>
        <p style="color:var(--dim); font-size:0.9rem; margin:1rem 0 0;">
            Nenhuma chamada registrada ainda. Quando o bot for testar, aparece aqui.
        </p>
    <?php else: ?>
        <table style="width:100%; margin-top:0.8rem; border-collapse:collapse; font-size:0.85rem;">
            <thead>
                <tr style="text-align:left; color:var(--dim); border-bottom:1px solid var(--border);">
                    <th style="padding:0.5rem 0.4rem;">Quando</th>
                    <th style="padding:0.5rem 0.4rem;">IP</th>
                    <th style="padding:0.5rem 0.4rem;">Action</th>
                    <th style="padding:0.5rem 0.4rem;">HTTP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log as $row): ?>
                    <?php
                    $code = (int)($row['status_code'] ?? 0);
                    $codeColor = $code === 200 ? 'var(--moss)' : ($code >= 400 && $code < 500 ? 'var(--hazard)' : '#e74c3c');
                    ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:0.5rem 0.4rem; font-family:var(--font-mono);">
                            <?= e(date('d/m H:i:s', strtotime((string)$row['called_at']))) ?>
                        </td>
                        <td style="padding:0.5rem 0.4rem; font-family:var(--font-mono); color:var(--dim);">
                            <?= e((string)$row['ip']) ?>
                        </td>
                        <td style="padding:0.5rem 0.4rem;">
                            <code><?= e((string)($row['action'] ?: '—')) ?></code>
                        </td>
                        <td style="padding:0.5rem 0.4rem;">
                            <span style="color:<?= e($codeColor) ?>; font-weight:600; font-family:var(--font-mono);">
                                <?= $code ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php \App\View::endSection(); ?>
