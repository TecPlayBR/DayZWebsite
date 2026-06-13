<?php
// ============================================================
// cli/migrate.php — aplica as migrations pendentes do banco com SEGURANÇA.
// ============================================================
// Roda SÓ as migrations que ainda não foram aplicadas (rastreadas na tabela
// `schema_migrations`), em ordem. As migrations do template são idempotentes
// (CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS / INSERT IGNORE), então
// rodar de novo é seguro — e este script trata erros de "já existe" como OK.
//
// >> NUNCA apaga nem altera dados existentes. Só adiciona o que falta.
//
// Uso (SSH / terminal):
//     php cli/migrate.php
//
// Sem SSH (Hostinger/cPanel): Painel -> Cron Jobs -> cron "uma vez" com:
//     php /home/SEU_USER/public_html/cli/migrate.php
//     (rode 1x depois de subir os arquivos novos e remova o cron).
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

// Tabela de controle: registra cada migration já aplicada.
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        filename   VARCHAR(150) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$applied = $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(\PDO::FETCH_COLUMN) ?: [];

$files = glob($ROOT . '/migrations/*.sql') ?: [];
sort($files); // ordem lexical = ordem de versão (v1.1.0 < v1.2.0 < v1.4.0 ...)

if (!$files) {
    echo "Nenhuma migration encontrada em migrations/.\n";
    exit(0);
}

// Erros que significam "a alteração já existe" — seguro tratar como aplicada.
$benign = ['already exists', 'duplicate column', 'duplicate key', 'duplicate entry', "doesn't exist for"];

$ran = 0; $skipped = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "· ja aplicada:  $name\n";
        $skipped++;
        continue;
    }
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        $record = true;
        echo "OK aplicada:    $name\n";
        $ran++;
    } catch (\Throwable $e) {
        $msg = strtolower($e->getMessage());
        $isBenign = false;
        foreach ($benign as $b) { if (strpos($msg, $b) !== false) { $isBenign = true; break; } }
        if ($isBenign) {
            // Efeito já presente (instalação que rodou a migration antes do tracking existir).
            $record = true;
            echo "~ ja presente:  $name (marcando como aplicada)\n";
            $skipped++;
        } else {
            fwrite(STDERR, "X FALHOU:       $name -> " . $e->getMessage() . "\n");
            fwrite(STDERR, "  Banco NAO foi alterado por esta migration. Corrija e rode de novo.\n");
            exit(1);
        }
    }
    if (!empty($record)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO schema_migrations (filename) VALUES (?)");
        $stmt->execute([$name]);
    }
}

echo "\n";
echo $ran > 0
    ? "Concluido: $ran migration(s) nova(s) aplicada(s), $skipped ja estava(m) ok.\n"
    : "Nada pendente — banco ja esta atualizado ($skipped migration(s) registrada(s)).\n";
exit(0);
