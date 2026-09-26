<?php /** @var array $config */ ?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de acesso - <?= e($config['site_name'] ?? 'DayZ') ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/logo.png') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;600;700&family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <?= theme_override_tag() ?>
</head>
<body>
<div class="login-wrap">
    <form class="login-card" method="POST" action="/admin/login/2fa" autocomplete="off">
        <h1>SEGUNDA ETAPA</h1>
        <p class="sub">// CÓDIGO DO SEU APP AUTENTICADOR</p>

        <?= \App\Csrf::field() ?>

        <?php $err = $_GET['e'] ?? null; ?>
        <?php if ($err === 'codigo'): ?>
            <div class="login-error">Código inválido ou já usado. Confira o app e tente de novo.</div>
        <?php elseif ($err === 'csrf'): ?>
            <div class="login-error">Sessão expirada. Recarregue a página.</div>
        <?php endif; ?>

        <div class="field">
            <label for="f-codigo">Código de 6 dígitos</label>
            <input type="text" id="f-codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="11" placeholder="000 000" autofocus required class="mono" style="font-size:1.5rem; letter-spacing:.2em; text-align:center;">
        </div>
        <p style="font-size:.85rem; color:var(--dim); margin:-.2rem 0 1rem; line-height:1.5;">
            Perdeu o celular? Digite neste mesmo campo um dos códigos de recuperação que você guardou (formato <code>abcd-efgh</code>). Cada um vale uma vez.
        </p>

        <button type="submit" class="btn">Entrar</button>
        <p style="text-align:center;margin-top:14px;font-size:0.9rem;"><a href="/admin/login">Voltar pro login</a></p>
    </form>
</div>
</body>
</html>
