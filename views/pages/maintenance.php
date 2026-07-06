<?php /** @var array $config */ ?>
<?php
$msg = $config['settings']['maintenance_message'] ?? 'Estamos fazendo um update no servidor. Voltamos já.';
$eta = $config['settings']['maintenance_eta'] ?? '';
$etaTs = $eta ? strtotime($eta) : 0;
$siteName = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'TECPLAY');
$discord = ($config['settings']['social_discord'] ?? '') ?: ($config['settings']['discord_invite'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Em manutenção - <?= e($siteName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;600;700&family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <?= theme_override_tag() ?>
    <link rel="icon" type="image/png" href="<?= asset('img/logo.png') ?>">
</head>
<body>
<section class="hero" style="min-height: 100vh; align-items: center;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background4.png') ?>');"></div>
    <div class="container hero-content" style="max-width: 100%; text-align: center;">
        <span class="hero-kicker" style="margin: 0 auto 1.5rem; border-left-color: var(--hazard); color: var(--hazard); background: var(--hazard-overlay);">
            // Servidor em manutenção
        </span>

        <h1 class="hero-title" style="margin-bottom: 2rem;">
            Voltamos<br><span class="accent">Em Breve.</span>
        </h1>

        <p class="hero-subtitle" style="margin: 0 auto 2rem;"><?= e($msg) ?></p>

        <?php if ($etaTs && $etaTs > time()): ?>
            <div class="hero-wipe" data-wipe-target="<?= $etaTs ?>" style="position: static; display: inline-flex; flex-direction: column; margin: 0 auto 2rem; align-items: center;">
                <span class="wipe-label">Previsão de retorno em</span>
                <span class="wipe-time" data-wipe-countdown>-</span>
            </div>
        <?php endif; ?>

        <?php if ($discord): ?>
            <div class="hero-actions" style="justify-content: center;">
                <a href="<?= e($discord) ?>" target="_blank" rel="noopener" class="btn">Acompanhe no Discord</a>
            </div>
        <?php endif; ?>
    </div>
</section>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
