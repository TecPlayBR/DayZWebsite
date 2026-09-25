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
                <?= $lastOk > 0 ? e(date('d/m/Y H:i:s', $lastOk)) : '- nunca -' ?>
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
        <div style="background:var(--danger-overlay); border-left:3px solid var(--rust); padding:0.8rem 1rem; border-radius:4px; margin-bottom:1rem; font-size:0.9rem;">
            ⚠ Nenhum token configurado. Endpoint <code>/api/bot-integration.php</code> vai
            retornar 401 até você gerar um.
        </div>
    <?php else: ?>
        <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:0.5rem;">
            <code id="tok-display" style="background:var(--bg-0); padding:0.6rem 0.9rem; border-radius:6px; flex:1; font-family:var(--font-mono); font-size:0.95rem; user-select:all;">
                <?= e($tokenMasked) ?>
            </code>
            <button type="button" data-copiar="<?= e($token) ?>" style="padding:0.6rem 1rem; background:var(--hazard); color:#000; border:none; border-radius:6px; cursor:pointer; font-weight:600;">📋 Copiar</button>
        </div>
        <div style="font-size:0.8rem; color:var(--dim);">
            Token completo (clica em "Copiar"): <strong style="color:var(--hazard);"><?= strlen($token) ?> caracteres</strong>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/discord-integration/regenerate" style="margin-top:1rem;" data-confirm="Gerar um token NOVO vai invalidar o atual imediatamente. O bot vai perder acesso até você colar o novo token no painel dele.&#10;&#10;Confirma?">
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
        <table class="admin-table" data-nofilter style="margin-top:0.8rem;">
            <thead>
                <tr>
                    <th>Quando</th>
                    <th>IP</th>
                    <th>Action</th>
                    <th>HTTP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log as $row): ?>
                    <?php
                    $code = (int)($row['status_code'] ?? 0);
                    $codeColor = $code === 200 ? 'var(--moss)' : ($code >= 400 && $code < 500 ? 'var(--hazard)' : 'var(--rust-2)');
                    ?>
                    <tr>
                        <td class="mono">
                            <?= e(date('d/m H:i:s', strtotime((string)$row['called_at']))) ?>
                        </td>
                        <td class="mono dim">
                            <?= e((string)$row['ip']) ?>
                        </td>
                        <td>
                            <code><?= e((string)($row['action'] ?: '-')) ?></code>
                        </td>
                        <td>
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


<?php
// Resultado do teste e do salvamento. Cada caso com nome proprio: dizer so
// "falhou" faria o dono do site adivinhar entre token, endereco e bot fora do ar.
$botR = $_GET['bot'] ?? null;
$botMsg = match ($botR) {
    'salvo' => ['moss',   'Salvo. Clica em "Testar a conexao" pra confirmar que o bot responde.'],
    'ok'    => ['moss',   'O bot respondeu. O aviso do site pro bot esta funcionando.'],
    'token' => ['rust-2', 'O bot respondeu, mas RECUSOU o token (401). O endereco esta certo e o token nao.'],
    'rede'  => ['rust-2', 'Nao consegui alcancar o endereco: ' . (string) ($_GET['msg'] ?? '') . '. Confira o endereco, ou o bot esta fora do ar.'],
    'http'  => ['rust-2', 'O bot respondeu com HTTP ' . (string) ($_GET['msg'] ?? '?') . '. Endereco alcancado, resposta inesperada.'],
    'falta' => ['rust-2', 'Preencha o endereco E o token antes de testar.'],
    default => null,
};
$botEndpoint = $bot_endpoint ?? '';
$botTokenSet = !empty($bot_token_set);
$botPronto   = trim((string) $botEndpoint) !== '' && $botTokenSet;
?>

<!-- ============ O OUTRO SENTIDO: o site avisando o bot ============ -->
<div class="stat-card" style="padding:1.5rem; margin-top:2rem;">
    <h2 style="margin:0 0 0.3rem;">📤 O site avisando o bot</h2>
    <p style="color:var(--dim); margin:0 0 1rem; font-size:0.9rem;">
        O bloco acima e o bot <strong>lendo</strong> o site. Este e o caminho contrario: o site
        <strong>contando</strong> pro bot que uma venda foi aprovada, que um VIP mudou, e que
        voce ajustou moedas no painel.
    </p>

    <?php if (!$botPronto): ?>
        <div style="background:var(--danger-overlay); border-left:3px solid var(--rust-2); padding:0.8rem 1rem; margin-bottom:1rem;">
            <strong>Falta configurar.</strong> Sem isso, nada do site chega ao bot: a confirmacao de
            compra nao aparece no Discord e — se a moeda do seu servidor mora <em>dentro do jogo</em> —
            <strong>o ajuste de moedas no painel nao se aplica</strong> e o valor volta ao que estava,
            porque o site nao tem como escrever no servidor sozinho.
        </div>
    <?php else: ?>
        <div style="background:var(--bg-0); border-left:3px solid var(--moss); padding:0.8rem 1rem; margin-bottom:1rem;">
            Endereco e token preenchidos. <strong>Testar</strong> e o que prova que funciona.
        </div>
    <?php endif; ?>

    <?php if ($botMsg): ?>
        <div style="background:var(--bg-0); border-left:3px solid var(--<?= e($botMsg[0]) ?>); padding:0.8rem 1rem; margin-bottom:1rem;">
            <?= e($botMsg[1]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/discord-integration/bot-link">
        <?= \App\Csrf::field() ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem;">
            <div>
                <label style="display:block; font-size:0.82rem; color:var(--dim); margin-bottom:0.25rem;">Endereco do bot</label>
                <input type="text" name="bot_endpoint" value="<?= e($botEndpoint) ?>"
                       placeholder="https://bot.tecplay.inf.br"
                       style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
            <div>
                <label style="display:block; font-size:0.82rem; color:var(--dim); margin-bottom:0.25rem;">
                    Token do bot <?= $botTokenSet ? '<span style="color:var(--moss);">- ja salvo (deixe vazio pra manter)</span>' : '' ?>
                </label>
                <input type="password" name="bot_token" value="" autocomplete="new-password"
                       placeholder="<?= $botTokenSet ? '•••••••••• (salvo)' : 'cole o token aqui' ?>"
                       style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
        </div>
        <div style="margin-top:1rem; display:flex; gap:0.8rem; align-items:center;">
            <button type="submit" style="padding:0.6rem 1rem; background:var(--hazard); color:#000; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Salvar</button>
            <?php if ($botPronto): ?>
                <a href="/admin/discord-integration/testar-bot" style="color:var(--hazard);">Testar a conexao →</a>
                <span style="color:var(--dim); font-size:0.82rem;">(nao muda nada, so pergunta se ele responde)</span>
            <?php endif; ?>
        </div>
    </form>
    <p style="margin-top:0.8rem; font-size:0.8rem; color:var(--dim);">
        Os dois valores sao especificos do <strong>seu</strong> bot: peca ao suporte. O token
        nunca e exibido de volta nesta tela.
    </p>
</div>

<?php \App\View::endSection(); ?>
