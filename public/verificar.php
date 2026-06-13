<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// verificar.php - Diagnostico de deploy (estrutura de arquivos)
// ============================================================
// Abra no navegador (https://seusite.com/verificar.php) pra conferir se o
// upload por FTP ficou completo. NAO expoe senhas, banco nem nenhum segredo —
// so checa se as pastas/arquivos estao no lugar e se o idioma carrega.
//
// >> APAGUE este arquivo depois de usar (nao e necessario no dia a dia).
// ============================================================

declare(strict_types=1);

$ROOT = dirname(__DIR__);

// Pastas/arquivos que precisam existir um nivel ACIMA de public/.
$checks = [
    'src/ (codigo do site)'             => $ROOT . '/src',
    'views/ (paginas/templates)'        => $ROOT . '/views',
    'lang/ (textos dos menus)'          => $ROOT . '/lang',
    'lang/pt-br.php'                    => $ROOT . '/lang/pt-br.php',
    'lang/en-us.php'                    => $ROOT . '/lang/en-us.php',
    'config/ (pasta de configuracao)'   => $ROOT . '/config',
    'migrations/ (atualizacoes do DB)'  => $ROOT . '/migrations',
    'schema.sql (banco inicial)'        => $ROOT . '/schema.sql',
    'storage/ (logs/cache)'             => $ROOT . '/storage',
];

$results = [];
$allOk = true;
foreach ($checks as $label => $path) {
    $ok = file_exists($path);
    if (!$ok) $allOk = false;
    $results[] = ['label' => $label, 'ok' => $ok];
}

// Esta instalado? (so presenca do config — sem ler conteudo)
$installed = file_exists($ROOT . '/config/config.php');

// Teste real de idioma: carrega o Lang e ve se uma chave do menu resolve.
$langStatus = null;
if (file_exists($ROOT . '/src/Lang.php')) {
    require $ROOT . '/src/Lang.php';
    \App\Lang::init($ROOT . '/lang', 'pt-br', ['pt-br', 'en-us']);
    $sample = \App\Lang::get('nav.rules');
    // Se carregou certo, vem "Regras". Se a chave volta crua, lang/ esta faltando.
    $langStatus = ($sample !== 'nav.rules');
    if (!$langStatus) $allOk = false;
}

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verificacao do deploy - DayZ Website</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #15110d; color: #d4c5a9; font-family: system-ui, sans-serif; line-height: 1.6; padding: 2rem 1rem; }
.wrap { max-width: 720px; margin: 0 auto; }
h1 { font-size: 1.5rem; color: #c0664a; margin-bottom: 1rem; }
.banner { padding: 1rem 1.2rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: .95rem; }
.banner.ok  { background: rgba(34,197,94,.12); border-left: 4px solid #5a8f4a; }
.banner.bad { background: rgba(192,102,74,.14); border-left: 4px solid #c0664a; }
ul { list-style: none; margin: 1rem 0; }
li { padding: .55rem .8rem; border-bottom: 1px solid rgba(212,197,169,.1); display: flex; gap: .6rem; align-items: baseline; }
.tag { font-weight: 700; min-width: 2.2rem; }
.tag.ok { color: #5fbf6f; } .tag.bad { color: #e07a5f; }
code { background: #1f1813; padding: .1rem .4rem; border-radius: 2px; color: #d9a441; }
.note { font-size: .85rem; color: #8a8170; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(212,197,169,.1); }
.fix { background: #1f1813; padding: 1rem 1.2rem; border-radius: 4px; margin-top: 1rem; font-size: .9rem; }
</style>
</head>
<body>
<div class="wrap">
<h1>Verificacao do deploy</h1>

<?php if ($allOk): ?>
    <div class="banner ok"><strong>✅ Tudo certo!</strong> Todos os arquivos essenciais estao no lugar
    <?= $langStatus ? 'e o idioma carrega normalmente' : '' ?>.
    <?= $installed ? 'O site ja esta instalado.' : 'Falta so rodar o <code>/install.php</code>.' ?></div>
<?php else: ?>
    <div class="banner bad"><strong>⚠ Faltam arquivos.</strong> O upload por FTP ficou incompleto.
    Veja abaixo o que esta faltando e reenvie essas pastas.</div>
<?php endif; ?>

<ul>
<?php foreach ($results as $r): ?>
    <li>
        <span class="tag <?= $r['ok'] ? 'ok' : 'bad' ?>"><?= $r['ok'] ? 'OK' : 'X' ?></span>
        <span><?= htmlspecialchars($r['label']) ?></span>
    </li>
<?php endforeach; ?>
<?php if ($langStatus !== null): ?>
    <li>
        <span class="tag <?= $langStatus ? 'ok' : 'bad' ?>"><?= $langStatus ? 'OK' : 'X' ?></span>
        <span>Idioma carregando (menu mostra "Regras", nao "NAV.RULES")</span>
    </li>
<?php endif; ?>
</ul>

<?php if (!$allOk): ?>
    <div class="fix">
        <strong>Como corrigir:</strong>
        <ol style="margin: .5rem 0 0 1.2rem;">
            <li>Baixe o template completo (ZIP) de novo.</li>
            <li>Pelo Gerenciador de Arquivos / FTP, suba as pastas que estao com <strong>X</strong> acima
            pra <strong>mesma pasta onde esta <code>src/</code></strong> (um nivel acima de <code>public/</code>).</li>
            <li>NAO suba/sobrescreva <code>config/config.php</code> nem <code>storage/</code>.</li>
            <li>Recarregue esta pagina (Ctrl+F5) — deve ficar tudo verde.</li>
        </ol>
    </div>
<?php endif; ?>

<p class="note">🔒 Esta pagina nao mostra senhas, banco nem nenhum dado sensivel — so confere arquivos.
Mesmo assim, <strong>apague o <code>verificar.php</code></strong> depois de usar (nao e necessario no dia a dia).</p>
</div>
</body>
</html>
