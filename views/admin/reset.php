<?php /** @var array $config; @var string $token; @var bool $valid */ ?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova senha — <?= e($config['site_name'] ?? 'DayZ') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;600;700&family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <?= theme_override_tag() ?>
</head>
<body>
<div class="login-wrap">
<?php if (empty($valid)): ?>
    <div class="login-card">
        <h1>LINK INVÁLIDO</h1>
        <p class="sub">// O link expirou ou já foi usado. Peça um novo.</p>
        <p style="text-align:center;margin-top:18px;"><a href="/admin/forgot">Pedir novo link</a></p>
    </div>
<?php else: ?>
    <form class="login-card" method="POST" action="/admin/reset" autocomplete="off">
        <h1>NOVA SENHA</h1>
        <p class="sub">// Defina sua nova senha (mínimo 8 caracteres)</p>

        <?= \App\Csrf::field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <?php $err = $_GET['e'] ?? null; ?>
        <?php if ($err === 'short'): ?>
            <div class="login-error">A senha precisa ter ao menos 8 caracteres.</div>
        <?php elseif ($err === 'invalid'): ?>
            <div class="login-error">Link inválido ou expirado. Peça um novo.</div>
        <?php endif; ?>

        <div class="field">
            <label>Nova senha</label>
            <input type="password" name="password" minlength="8" autofocus required>
        </div>

        <button type="submit" class="btn">Redefinir senha</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
