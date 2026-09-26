<?php /** @var array $config; @var array $estado; @var ?string $segredo; @var ?string $uri; @var ?array $codigos; @var ?string $ok; @var ?string $erro */ ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🔐 Login em duas etapas</h1>
        <p>Além da senha, o login pede um código de 6 dígitos que muda a cada 30 segundos no seu celular. Se alguém descobrir sua senha, ainda não entra. É opcional e vale só pra sua conta.</p>
    </div>
</div>

<?php
$msgOk = ['ligado' => 'Duas etapas ligadas. A partir do próximo login o painel vai pedir o código.', 'desligado' => 'Duas etapas desligadas. Seu login volta a ser só com a senha.', 'codigos' => 'Códigos de recuperação novos gerados. Os antigos deixaram de valer.'];
$msgErro = ['codigo' => 'Código inválido. Confira se o relógio do celular está certo e digite o código que está na tela agora.', 'confirmacao' => 'Senha ou código não conferem.', 'rate' => 'Muitas tentativas. Espere alguns minutos.', 'csrf' => 'Sessão expirada. Recarregue a página.'];
?>
<?php if ($ok && isset($msgOk[$ok])): ?><div class="adm-aviso adm-aviso-ok"><?= e($msgOk[$ok]) ?></div><?php endif; ?>
<?php if ($erro && isset($msgErro[$erro])): ?><div class="adm-aviso adm-aviso-erro"><?= e($msgErro[$erro]) ?></div><?php endif; ?>

<?php if ($codigos): ?>
<div class="stat-card adm-bloco adm-codigos">
    <div class="label">Guarde estes códigos agora</div>
    <p class="adm-hint" style="margin:.6rem 0 .9rem;">Se perder o celular, cada código abaixo entra <strong>uma vez</strong> no lugar do código do app. Eles <strong>não aparecem de novo</strong>: anote num lugar seguro ou salve no gerenciador de senhas.</p>
    <ol class="adm-codigos-lista">
        <?php foreach ($codigos as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?>
    </ol>
    <div class="adm-acoes" style="margin-top:.9rem;"><button type="button" class="btn-mini outline" data-copiar="<?= e(implode("\n", $codigos)) ?>">📋 Copiar os 8 códigos</button></div>
</div>
<?php endif; ?>

<?php if ($estado['ativo']): ?>
    <div class="stat-card adm-bloco">
        <div class="label">Status</div>
        <p style="margin:.7rem 0 0;"><span class="adm-selo adm-selo-ok">LIGADO</span>
            <?php if (!empty($estado['desde'])): ?><span class="adm-hint">desde <?= e(date('d/m/Y H:i', strtotime((string) $estado['desde']))) ?></span><?php endif; ?></p>
        <p class="adm-hint" style="margin:.6rem 0 0;">Códigos de recuperação restantes: <strong style="color:<?= $estado['codigos_restantes'] <= 2 ? 'var(--text-danger)' : 'var(--bone)' ?>;"><?= (int) $estado['codigos_restantes'] ?> de 8</strong><?= $estado['codigos_restantes'] <= 2 ? '. Gere novos abaixo antes que acabem.' : '' ?></p>
    </div>

    <div class="adm-grid-2">
        <form method="POST" action="/admin/conta/2fa/codigos" class="stat-card" data-adm-form>
            <?= \App\Csrf::field() ?>
            <div class="label" style="margin-bottom:.8rem;">Gerar códigos de recuperação novos</div>
            <?= admin_campo(['label' => 'Código do app', 'name' => 'codigo', 'required' => true, 'placeholder' => '000 000', 'class' => 'mono', 'attrs' => 'inputmode="numeric" autocomplete="one-time-code" maxlength="7"', 'hint' => 'Os 8 códigos antigos deixam de valer.']) ?>
            <div class="adm-acoes" style="margin-top:.9rem;"><button type="submit" class="btn-mini">Gerar novos</button></div>
        </form>

        <form method="POST" action="/admin/conta/2fa/desligar" class="stat-card" data-adm-form data-confirm="Desligar as duas etapas? Seu login volta a ser só com a senha.">
            <?= \App\Csrf::field() ?>
            <div class="label" style="margin-bottom:.8rem;">Desligar</div>
            <?= admin_campo(['label' => 'Sua senha', 'name' => 'senha', 'type' => 'password', 'required' => true, 'attrs' => 'autocomplete="current-password"']) ?>
            <?= admin_campo(['label' => 'Código do app ou de recuperação', 'name' => 'codigo', 'required' => true, 'placeholder' => '000 000', 'class' => 'mono', 'attrs' => 'autocomplete="one-time-code" maxlength="11"', 'hint' => 'Pedimos os dois pra uma sessão esquecida aberta não bastar.']) ?>
            <div class="adm-acoes" style="margin-top:.9rem;"><button type="submit" class="btn-mini outline">Desligar duas etapas</button></div>
        </form>
    </div>

<?php elseif ($segredo): ?>
    <div class="stat-card adm-bloco">
        <div class="label">Ligar em 3 passos</div>
        <div class="adm-2fa-passos">
            <div>
                <p class="adm-label"><strong>1.</strong> Instale um app autenticador no celular</p>
                <p class="adm-hint">Google Authenticator, Microsoft Authenticator ou Authy. Todos gratuitos.</p>
                <p class="adm-label" style="margin-top:1rem;"><strong>2.</strong> No app, escolha adicionar conta e leia o QR code</p>
                <p class="adm-hint">Não consegue ler? Digite esta chave no app:</p>
                <p><code class="adm-2fa-chave"><?= e(trim(chunk_split($segredo, 4, ' '))) ?></code></p>
            </div>
            <div class="adm-2fa-qr" id="adm-2fa-qr" data-uri="<?= e((string) $uri) ?>" role="img" aria-label="QR code pra adicionar a conta no app autenticador">
                <span class="adm-hint">Carregando QR code...</span>
            </div>
        </div>
        <form method="POST" action="/admin/conta/2fa/confirmar" class="adm-2fa-confirmar" data-adm-form>
            <?= \App\Csrf::field() ?>
            <p class="adm-label"><strong>3.</strong> Digite o código que o app mostra agora</p>
            <?= admin_campo(['label' => 'Código de 6 dígitos', 'name' => 'codigo', 'required' => true, 'placeholder' => '000 000', 'class' => 'mono', 'attrs' => 'inputmode="numeric" autocomplete="one-time-code" maxlength="7"', 'hint' => 'Só liga depois que o código bater: se algo der errado aqui, sua conta não fica trancada.']) ?>
            <div class="adm-acoes" style="margin-top:.9rem;"><button type="submit" class="btn-mini">Confirmar e ligar</button></div>
        </form>
    </div>
    <script nonce="<?= csp_nonce() ?>" src="<?= asset('js/lib/qrcode.js') ?>"></script>
    <script nonce="<?= csp_nonce() ?>">
    (function () { // escopo proprio: o PJAX do admin re-executa este script a cada visita
        var el = document.getElementById('adm-2fa-qr');
        if (!el) return;
        var tentativas = 0;
        (function desenha() {
            // Via PJAX o qrcode.js carrega depois deste script: espera ele chegar.
            if (!window.qrcode) { if (++tentativas < 100) setTimeout(desenha, 50); return; }
            var q = window.qrcode(0, 'M');
            q.addData(el.getAttribute('data-uri'));
            q.make();
            el.innerHTML = q.createSvgTag(5, 2);
        })();
    })();
    </script>

<?php else: ?>
    <div class="stat-card adm-bloco">
        <div class="label">Status</div>
        <p style="margin:.7rem 0 0;"><span class="adm-selo">DESLIGADO</span> <span class="adm-hint">Seu login é só com a senha.</span></p>
        <form method="POST" action="/admin/conta/2fa/iniciar" style="margin-top:1rem;">
            <?= \App\Csrf::field() ?>
            <button type="submit" class="btn-mini">Ligar duas etapas</button>
        </form>
        <p class="adm-hint" style="margin-top:.8rem;">Você vai precisar do celular com um app autenticador. Leva um minuto.</p>
    </div>
<?php endif; ?>

<?php \App\View::endSection(); ?>
