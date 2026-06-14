<?php
/** @var array $config, $vars */
/** @var string $token, $tokenMasked, $apiGet, $apiPost, $jsonSnippet */
/** @var int    $lastSync */
/** @var string $statusColor, $statusLabel */
/** @var array  $log */
?>
<?php $title = 'Integração Sparda'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🎮 Integração Sparda (nativa, sem Agent)</h1>
        <p>Entrega de moedas <strong>in-game pelo próprio mod Sparda</strong> — sem precisar do Agent pago nem do Bot. O site vende a moeda (Mercado Pago) e credita o saldo; o mod lê/grava esse saldo direto aqui.</p>
    </div>
</div>

<?php if (!empty($_GET['flash'])): ?>
    <div class="alert-toast"><?= e($_GET['flash']) ?></div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom: 1rem;">
    <div class="label">Status da entrega</div>
    <div style="margin-top: 0.8rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <span style="padding:0.4rem 0.9rem;border-radius:6px;background:<?= e($statusColor) ?>;color:#fff;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.1em;">
            <?= e($statusLabel) ?>
        </span>
        <span style="color:var(--dim); font-size:0.85rem;">
            Última leitura/gravação do mod:
            <strong style="color:var(--bone);">
                <?= $lastSync > 0 ? e(date('d/m/Y H:i:s', $lastSync)) : '— nunca —' ?>
            </strong>
        </span>
    </div>
    <p style="color:var(--dim); font-size:0.8rem; margin-top:0.7rem;">
        Verde = o mod conversou com o site nos últimos 30 min. Enquanto estiver verde, o site promete <strong>liberação automática</strong> nas compras.
    </p>
</div>

<?php if ($token === '' || $token === 'ALTERE_AQUI_TOKEN_DO_AGENT'): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--rust);">
        <div class="label">⚠ Token não configurado</div>
        <p style="color:var(--dim); font-size:0.9rem; margin:0.7rem 0 0;">
            O <code>agent_token</code> no <code>config/config.php</code> ainda está no valor padrão.
            Gera um e cola lá:<br>
            <code style="display:block; background:var(--bg-0); padding:0.6rem 0.9rem; border-radius:6px; margin-top:0.5rem;">php -r "echo bin2hex(random_bytes(24));"</code>
            Os endpoints Sparda retornam <strong>401</strong> até isso ser feito.
        </p>
    </div>
<?php else: ?>

<div class="stat-card" style="margin-bottom: 1rem;">
    <div class="label">Token de acesso (mesmo do Agent)</div>
    <p style="color:var(--dim); font-size:0.85rem; margin: 0.7rem 0;">
        É o <code>agent_token</code> do seu <code>config.php</code>. Já vai embutido nas URLs abaixo — você não precisa copiá-lo separado.
    </p>
    <div style="display:flex; gap:0.5rem; align-items:center;">
        <code style="background:var(--bg-0); padding:0.6rem 0.9rem; border-radius:6px; flex:1; font-family:var(--font-mono); font-size:0.95rem; user-select:all;">
            <?= e($tokenMasked) ?>
        </code>
        <button type="button" onclick="navigator.clipboard.writeText(<?= json_encode($token) ?>).then(() => { this.textContent='✓ Copiado!'; setTimeout(()=>this.textContent='📋 Copiar', 1500); })" style="padding:0.6rem 1rem; background:var(--hazard); color:#000; border:none; border-radius:6px; cursor:pointer; font-weight:600;">📋 Copiar</button>
    </div>
</div>

<div class="stat-card" style="margin-bottom: 1rem;">
    <div class="label">URLs pro mod (Api_Get / Api_Post)</div>
    <p style="color:var(--dim); font-size:0.85rem; margin: 0.7rem 0;">
        Cola cada uma no campo correspondente da config do mod Sparda. <strong>Já vêm com o token e terminam em <code>&amp;steamid=</code></strong> — o mod cola o SteamID do jogador no final automaticamente. Não remova o final.
    </p>

    <div style="margin-bottom:0.9rem;">
        <div style="font-size:0.8rem; color:var(--hazard); font-weight:700; margin-bottom:0.3rem;">Api_Get (lê o saldo)</div>
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <code style="background:var(--bg-0); padding:0.6rem 0.9rem; border-radius:6px; flex:1; font-family:var(--font-mono); font-size:0.85rem; user-select:all; word-break:break-all;"><?= e($apiGet) ?></code>
            <button type="button" onclick="navigator.clipboard.writeText(<?= json_encode($apiGet) ?>).then(() => { this.textContent='✓'; setTimeout(()=>this.textContent='📋', 1500); })" style="padding:0.6rem 0.9rem; background:var(--hazard); color:#000; border:none; border-radius:6px; cursor:pointer; font-weight:600;">📋</button>
        </div>
    </div>

    <div>
        <div style="font-size:0.8rem; color:var(--hazard); font-weight:700; margin-bottom:0.3rem;">Api_Post (grava o saldo depois que gasta)</div>
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <code style="background:var(--bg-0); padding:0.6rem 0.9rem; border-radius:6px; flex:1; font-family:var(--font-mono); font-size:0.85rem; user-select:all; word-break:break-all;"><?= e($apiPost) ?></code>
            <button type="button" onclick="navigator.clipboard.writeText(<?= json_encode($apiPost) ?>).then(() => { this.textContent='✓'; setTimeout(()=>this.textContent='📋', 1500); })" style="padding:0.6rem 0.9rem; background:var(--hazard); color:#000; border:none; border-radius:6px; cursor:pointer; font-weight:600;">📋</button>
        </div>
    </div>
</div>

<div class="stat-card" style="margin-bottom: 1rem;">
    <div class="label">Bloco pra colar na config do mod Sparda</div>
    <p style="color:var(--dim); font-size:0.85rem; margin: 0.7rem 0;">
        No JSON de config do mod, garanta <code>EnableWebsiteAPI = 1</code> e as duas URLs. Exemplo dos campos:
    </p>
    <div style="display:flex; gap:0.5rem; align-items:flex-start;">
        <pre style="background:var(--bg-0); padding:0.8rem 0.9rem; border-radius:6px; flex:1; font-family:var(--font-mono); font-size:0.8rem; overflow-x:auto; margin:0; user-select:all;"><?= e($jsonSnippet) ?></pre>
        <button type="button" onclick="navigator.clipboard.writeText(<?= json_encode($jsonSnippet) ?>).then(() => { this.textContent='✓'; setTimeout(()=>this.textContent='📋', 1500); })" style="padding:0.6rem 0.9rem; background:var(--hazard); color:#000; border:none; border-radius:6px; cursor:pointer; font-weight:600;">📋</button>
    </div>
    <p style="color:var(--dim); font-size:0.78rem; margin-top:0.6rem;">
        Os nomes exatos dos campos dependem da versão do mod. O que importa: ligar a integração web e apontar GET/POST pras URLs acima.
    </p>
</div>

<?php endif; ?>

<div class="stat-card">
    <div class="label">Últimas movimentações via Sparda</div>
    <?php if (empty($log)): ?>
        <p style="color:var(--dim); font-size:0.9rem; margin:1rem 0 0;">
            Nenhuma gravação do mod ainda. Quando um jogador gastar moeda in-game, aparece aqui.
        </p>
    <?php else: ?>
        <table style="width:100%; margin-top:0.8rem; border-collapse:collapse; font-size:0.85rem;">
            <thead>
                <tr style="text-align:left; color:var(--dim); border-bottom:1px solid var(--border);">
                    <th style="padding:0.5rem 0.4rem;">Quando</th>
                    <th style="padding:0.5rem 0.4rem;">SteamID</th>
                    <th style="padding:0.5rem 0.4rem;">Antes</th>
                    <th style="padding:0.5rem 0.4rem;">Depois</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log as $row): ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:0.5rem 0.4rem; font-family:var(--font-mono);">
                            <?= e(date('d/m H:i:s', strtotime((string)$row['created_at']))) ?>
                        </td>
                        <td style="padding:0.5rem 0.4rem; font-family:var(--font-mono); color:var(--dim);">
                            <?= e((string)$row['steam_id']) ?>
                        </td>
                        <td style="padding:0.5rem 0.4rem; font-family:var(--font-mono);">
                            <?= (int)$row['balance_before'] ?>
                        </td>
                        <td style="padding:0.5rem 0.4rem; font-family:var(--font-mono); color:var(--hazard); font-weight:600;">
                            <?= (int)$row['balance_after'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php \App\View::endSection(); ?>
