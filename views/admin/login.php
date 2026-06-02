<?php /** @var array $config */ ?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= e($config['site_name'] ?? 'DayZ') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;600;700&family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <?= theme_override_tag() ?>
</head>
<body>
<div class="login-wrap">
    <form class="login-card" method="POST" action="/admin/login" autocomplete="off">
        <h1>ACESSO RESTRITO</h1>
        <p class="sub">// PAINEL DE COMANDO</p>

        <?= \App\Csrf::field() ?>

        <?php $err = $_GET['e'] ?? null; ?>
        <?php if ($err === 'rate'): ?>
            <div class="login-error">
                Muitas tentativas. Tente novamente em <?= (int)($_GET['w'] ?? 60) ?> segundos.
            </div>
        <?php elseif ($err === 'csrf'): ?>
            <div class="login-error">Sessão expirada. Recarregue a página.</div>
        <?php elseif (!empty($error)): ?>
            <div class="login-error">Usuário ou senha inválidos.</div>
        <?php endif; ?>

        <div class="field">
            <label>Usuário</label>
            <input type="text" name="username" autofocus required>
        </div>
        <div class="field">
            <label>Senha</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit" class="btn">Entrar</button>
    </form>
</div>
</body>
</html>
