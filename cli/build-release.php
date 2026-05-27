<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// Build Release ZIP
// ============================================================
// Gera o ZIP final do template, pronto pra distribuir ao cliente.
// Exclui automaticamente: config.php real, storage local, backups,
// .git, IDEs, dumps SQL e qualquer arquivo de dev.
//
// Uso: php cli/build-release.php [versao]
//      php cli/build-release.php 1.0
// ============================================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { die("Rode só via CLI.\n"); }

if (!class_exists('ZipArchive')) {
    die("ERRO: extensão PHP 'zip' não está habilitada. Habilite no php.ini.\n");
}

$ROOT    = dirname(__DIR__);
$version = $argv[1] ?? '1.0';
$date    = date('Y-m-d');
$zipName = "DayZWebsite-v{$version}-{$date}.zip";
$zipPath = $ROOT . '/' . $zipName;

// ============ EXCLUSÕES ============
// Pastas inteiras a IGNORAR (relativo à raiz)
$excludeDirs = [
    '.git',
    '.vscode',
    '.idea',
    'node_modules',
    'vendor',
    // storage/* fica fora — só os .gitkeep entram pra criar a estrutura de pastas
    'storage/backups',
    'storage/cache',
    'storage/logs',
    'storage/ratelimit',
    'storage/server-status',
    // Imagens de seed da galeria (cliente pode subir as dele depois)
];

// Arquivos específicos a IGNORAR (relativo à raiz)
$excludeFiles = [
    'config/config.php',                    // SEU config local com credenciais reais
    '.env',
    '.env.local',
    '.gitignore',
    '.gitattributes',
    'composer.lock',
    'package-lock.json',
];

// Padrões (regex) a IGNORAR
$excludePatterns = [
    '#\.zip$#i',                     // ZIPs anteriores
    '#\.sql$#i',                     // Dumps SQL (exceto schema.sql)
    '#\.log$#i',                     // Logs
    '#\.swp$#',                      // Vim swap
    '#~$#',                          // Backup do editor
    '#\.DS_Store$#',                 // Mac
    '#Thumbs\.db$#',                 // Windows
    '#/cli/(seed_|fix_|enable_).*\.php$#', // Scripts one-shot de dev (mantém só seed-demo, backup, build-release)
    '#/public/assets/img/gallery/seed_g\d+\.png$#', // Imagens semeadas do meu DB local
];

// Arquivos que SEMPRE entram, mesmo se baterem em pattern (whitelist)
$forceInclude = [
    'schema.sql',                    // schema é fundamental
];

// ============ COLETA OS ARQUIVOS ============
$files = [];
$rootReal = realpath($ROOT);

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootReal, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$skipped = 0;
foreach ($it as $f) {
    $abs = $f->getPathname();
    $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($rootReal))), '/');

    if ($f->isDir()) continue; // ZipArchive cria diretórios sob demanda

    // Skip se está dentro de uma pasta excluída
    $skip = false;
    foreach ($excludeDirs as $dir) {
        if (str_starts_with($rel, $dir . '/') || $rel === $dir) {
            $skip = true; break;
        }
    }
    if ($skip) { $skipped++; continue; }

    // Skip se é arquivo explicitamente excluído
    if (in_array($rel, $excludeFiles, true)) { $skipped++; continue; }

    // Skip por pattern (a menos que esteja no force-include)
    if (!in_array($rel, $forceInclude, true)) {
        foreach ($excludePatterns as $pat) {
            if (preg_match($pat, '/' . $rel)) { $skip = true; break; }
        }
    }
    if ($skip) { $skipped++; continue; }

    $files[$rel] = $abs;
}

// Garante que .gitkeep estejam nas pastas storage que o app precisa em runtime
$needsKeep = ['storage/backups', 'storage/cache', 'storage/logs', 'storage/ratelimit', 'storage/server-status'];

// ============ MONTA O ZIP ============
if (file_exists($zipPath)) unlink($zipPath);
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    die("ERRO: não consegui criar $zipPath\n");
}

// Prefixa tudo com DayZWebsite/ pra cliente extrair direto em uma pasta com nome bonito
$prefix = "DayZWebsite/";

foreach ($files as $rel => $abs) {
    $zip->addFile($abs, $prefix . $rel);
}

// Cria as pastas vazias necessárias com .gitkeep
foreach ($needsKeep as $dir) {
    $zip->addFromString($prefix . $dir . '/.gitkeep', '');
}

// Adiciona um arquivo INSTRUCOES_RAPIDAS.txt na raiz pra cliente apressado
$quick = <<<TXT
================================================================
  TECPLAY DAYZ WEBSITE - INSTALACAO RAPIDA
================================================================

1. SUBA TUDO pro seu host (FTP/cPanel)
2. APONTE o public_html pra pasta "public/"
3. CRIE uma database MySQL no cPanel
4. ACESSE https://seudominio.com/install.php
5. PREENCHA o wizard

Pronto. Para detalhes completos abra INSTALACAO.md.

IMPORTANTE - LEIA ANTES DE USAR:
- LICENSE.txt   (licenca, proibicoes, sancoes)
- INSTALACAO.md (passo-a-passo completo)
- README.md     (visao geral do template)

SUPORTE OFICIAL (versao original, plano contratado):
  https://tecplay.inf.br/suporte/

MODIFICACOES SOB DEMANDA (servico pago):
  https://tecplay.inf.br/servicos/#web

E-mail:  suporte@tecplay.inf.br
Discord: https://discord.gg/uwSE3WSjNH

================================================================
  Versao: {$version}
  Build:  {$date}
================================================================
TXT;
$zip->addFromString($prefix . 'INSTRUCOES_RAPIDAS.txt', $quick);

$totalFiles = $zip->numFiles;
$zip->close();

$sizeKb = round(filesize($zipPath) / 1024, 1);
$sizeMb = round($sizeKb / 1024, 2);

echo "\n";
echo "============================================================\n";
echo "  ZIP gerado com sucesso!\n";
echo "============================================================\n";
echo "  Nome:    $zipName\n";
echo "  Local:   $zipPath\n";
echo "  Arquivos: $totalFiles (ignorados: $skipped)\n";
echo "  Tamanho: {$sizeKb} KB ({$sizeMb} MB)\n";
echo "============================================================\n";
echo "\n";
echo "Checklist pre-distribuicao:\n";
echo "  [ ] Abra o ZIP e confirme que NAO tem config/config.php\n";
echo "  [ ] Confirme que storage/cache, logs, etc. estao VAZIOS (so .gitkeep)\n";
echo "  [ ] Teste extracao em pasta limpa\n";
echo "  [ ] LICENSE.txt, INSTALACAO.md e INSTRUCOES_RAPIDAS.txt estao na raiz\n";
echo "\n";
