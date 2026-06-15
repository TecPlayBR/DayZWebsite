<?php /** @var ?string $detail; @var array $config */ ?>
<?php
$siteName = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'TECPLAY');
$discord = ($config['settings']['social_discord'] ?? '') ?: ($config['settings']['discord_invite'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erro 500 — <?= htmlspecialchars($siteName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;600;700&family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/theme.css">
    <?= function_exists('theme_override_tag') ? theme_override_tag() : '' ?>
</head>
<body>
<section class="hero" style="min-height: 100vh; align-items: center;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.95) 100%), url('/assets/img/background4.png');"></div>
    <div class="container hero-content" style="max-width: 100%; text-align: center;">
        <span class="hero-kicker" style="margin: 0 auto 1.5rem; border-left-color: var(--rust-2); color: var(--rust-2);">
            // Falha no sistema
        </span>

        <h1 class="hero-title" style="font-size: clamp(4rem, 12vw, 9rem); margin-bottom: 1rem;">
            <span class="accent">500</span>
        </h1>

        <p class="hero-subtitle" style="margin: 0 auto 2rem;">
            Algo deu errado por aqui. A equipe foi notificada e já está investigando.<br>
            <em style="color: var(--dim);">Recarregue a página em alguns instantes.</em>
        </p>

        <?php if (!empty($detail)): ?>
            <details style="max-width: 720px; margin: 1.5rem auto; text-align: left;">
                <summary style="color: var(--hazard); cursor: pointer; font-family: var(--font-mono); font-size: 0.85rem;">Detalhes técnicos (admin only)</summary>
                <pre style="background: var(--bg-1); border-left: 3px solid var(--rust-2); padding: 1rem; color: var(--text-danger); font-size: 0.8rem; overflow-x: auto;"><?= htmlspecialchars($detail) ?></pre>
            </details>
        <?php endif; ?>

        <div class="hero-actions" style="justify-content: center;">
            <a href="/" class="btn">← Página inicial</a>
            <?php if ($discord): ?>
                <a href="<?= htmlspecialchars($discord) ?>" target="_blank" rel="noopener" class="btn btn-outline">Reportar no Discord</a>
            <?php endif; ?>
        </div>
    </div>
</section>
</body>
</html>
