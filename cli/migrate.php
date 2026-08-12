<?php
// ============================================================
// cli/migrate.php - aplica as migrations pendentes do banco com SEGURANÇA.
// ============================================================
// Roda SÓ as migrations que ainda não foram aplicadas (rastreadas na tabela
// `schema_migrations`), na ordem certa de versão. As migrations do template são
// idempotentes (CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS /
// INSERT IGNORE), então rodar de novo é seguro: erros de "já existe" contam como OK.
//
// >> NUNCA apaga dados do cliente (moedas, jogadores, compras, páginas, pacotes).
//    Exceção declarada: v2.18.0_remove_points.sql dropa as tabelas do Sistema de
//    Pontos, que foi aposentado de propósito. É a ÚNICA migration destrutiva do
//    template e ela não encosta em mais nada.
//
// O motor de verdade está em cli/migrate-lib.php, compartilhado com a tela
// public/update.php (atualização pelo navegador, protegida por login de admin).
//
// Uso (SSH / terminal):
//     php cli/migrate.php
//
// Sem SSH (Hostinger/cPanel): use a tela /update.php pelo navegador, é mais simples.
// Se preferir cron, ATENÇÃO ao caminho: o cli/ NÃO fica dentro do public_html, e o
// layout varia por conta. Confirme no Gerenciador de Arquivos antes de colar:
//     com domínio próprio:  php /home/SEU_USER/domains/SEUDOMINIO.com/cli/migrate.php
//     sem domínio próprio:  php /home/SEU_USER/cli/migrate.php
// (rode 1x depois de subir os arquivos novos e remova o cron.)
//
// Só roda por LINHA DE COMANDO (o navegador NÃO executa -> não é backdoor exposta).
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script so roda pela linha de comando (CLI), nao pelo navegador.\n");
}

$ROOT = dirname(__DIR__);
$configFile = $ROOT . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "config/config.php nao encontrado. Rode o install.php primeiro.\n");
    exit(1);
}
$config = require $configFile;
if (empty($config['db'])) {
    fwrite(STDERR, "Bloco 'db' ausente no config/config.php.\n");
    exit(1);
}

require_once $ROOT . '/src/Database.php';
\App\Database::init($config['db']);

try {
    $pdo = \App\Database::pdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "Nao consegui conectar no banco: " . $e->getMessage() . "\n");
    exit(1);
}

require_once $ROOT . '/cli/migrate-lib.php';

$dir = $ROOT . '/migrations';
mig_garante_tabela($pdo);

$total     = count(mig_ordenar(glob($dir . '/*.sql') ?: []));
$pendentes = mig_pendentes($pdo, $dir);

if ($total === 0) {
    echo "Nenhuma migration encontrada em migrations/.\n";
    exit(0);
}
if (!$pendentes) {
    echo "Nada pendente. O banco ja esta atualizado ($total migration(s) registrada(s)).\n";
    exit(0);
}

echo "Pendentes: " . count($pendentes) . " de $total\n\n";

$r = mig_aplicar_pendentes($pdo, $dir);

foreach ($r['aplicadas']    as $n) { echo "OK aplicada:    $n\n"; }
foreach ($r['ja_presentes'] as $n) { echo "~  ja presente: $n (marcada como aplicada)\n"; }

if ($r['falhou']) {
    fwrite(STDERR, "\nX FALHOU:       " . $r['falhou']['arquivo'] . "\n");
    fwrite(STDERR, "  motivo: " . $r['falhou']['erro'] . "\n");
    fwrite(STDERR, "  Esta migration NAO foi registrada. Corrija e rode de novo:\n");
    fwrite(STDERR, "  as que passaram antes dela ja estao aplicadas e nao repetem.\n");
    exit(1);
}

echo "\nConcluido: " . count($r['aplicadas']) . " nova(s), "
   . count($r['ja_presentes']) . " ja estava(m) ok.\n";
exit(0);
