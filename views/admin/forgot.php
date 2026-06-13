<?php /** @var array $config; @var bool $sent */ ?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha — <?= e($config['site_name'] ?? 'DayZ') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;600;700&family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <?= theme_override_tag() ?>
</head>
<body>
<div class="login-wrap">
<?php if (!empty($sent)): ?>
    <div class="login-card">
        <h1>VERIFIQUE SEU EMAIL</h1>
        <p class="sub">// Se esse email tiver uma conta admin, enviamos um link pra redefinir a senha. Vale por 1 hora.</p>
        <p style="text-align:center;margin-top:18px;"><a href="/admin/login">Voltar ao login</a></p>
    </div>
<?php else: ?>
    <form class="login-card" method="POST" action="/admin/forgot" autocomplete="off">
        <h1>ESQUECEU A SENHA?</h1>
        <p class="sub">// Informe o email da conta admin</p>

        <?= \App\Csrf::field() ?>

        <?php $err = $_GET['e'] ?? null; ?>
        <?php if ($err === 'rate'): ?>
            <div class="login-error">Muitas tentativas. Tente novamente mais tarde.</div>
        <?php elseif ($err === 'csrf'): ?>
            <div class="login-error">Sessão expirada. Recarregue a página.</div>
        <?php endif; ?>

        <div class="field">
            <label>Email</label>
            <input type="email" name="email" autofocus required>
        </div>

        <button type="submit" class="btn">Enviar link de redefinição</button>
        <p style="text-align:center;margin-top:14px;font-size:0.9rem;"><a href="/admin/login">Voltar ao login</a></p>
    </form>
<?php endif; ?>
</div>
</body>
</html>
