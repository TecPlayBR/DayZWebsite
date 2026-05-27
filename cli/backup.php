<?php
// ============================================================
// Tecplay - DayZ Website Template
// cli/backup.php — Backup do banco em SQL puro
// ============================================================
// USO:
//   php cli/backup.php          → cria backup novo em /storage/backups/
//   php cli/backup.php --prune  → também apaga backups > 30d
//
// CRON (hospedagem cPanel/Hostinger — uma vez ao dia 4h da manhã):
//   0 4 * * * /usr/bin/php /home/seu_usuario/dayzsite/cli/backup.php --prune
//
// SEM dependencia de mysqldump binary — usa PDO + SHOW CREATE TABLE.
// Funciona em qualquer hospedagem PHP/MySQL.
// ============================================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Este script só pode ser executado via CLI.\n");
}

$ROOT = dirname(__DIR__);
require $ROOT . '/src/Database.php';

$configFile = $ROOT . '/config/config.php';
if (!file_exists($configFile)) { die("[ERRO] config/config.php não existe.\n"); }
$config = require $configFile;

\App\Database::init($config['db']);
$pdo = \App\Database::pdo();

$backupDir = $ROOT . '/storage/backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
    die("[ERRO] Não consegui criar $backupDir\n");
}
// Protege a pasta de acesso web
$ht = $backupDir . '/.htaccess';
if (!file_exists($ht)) {
    @file_put_contents($ht, "Require all denied\nDeny from all\n");
}

$dbName = $config['db']['name'];
$ts = date('Ymd-His');
$file = $backupDir . "/backup-$ts.sql";
$fh = fopen($file, 'w');
if (!$fh) die("[ERRO] Não consegui abrir $file pra escrita\n");

echo "Iniciando backup de '$dbName' em $file ...\n";

fwrite($fh, "-- ============================================================\n");
fwrite($fh, "-- Backup automático Tecplay\n");
fwrite($fh, "-- Database: $dbName\n");
fwrite($fh, "-- Gerado em: " . date('Y-m-d H:i:s') . "\n");
fwrite($fh, "-- ============================================================\n\n");
fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

// Lista todas as tabelas
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$totalRows = 0;

foreach ($tables as $table) {
    echo "  → $table ";
    // DROP + CREATE
    fwrite($fh, "-- ========== TABLE: $table ==========\n");
    fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n");
    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    fwrite($fh, $create[1] . ";\n\n");

    // Dados
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    if ($count > 0) {
        $rows = $pdo->query("SELECT * FROM `$table`");
        $first = true;
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            if ($first) {
                $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($row)));
                fwrite($fh, "INSERT INTO `$table` ($cols) VALUES\n");
                $first = false;
            } else {
                fwrite($fh, ",\n");
            }
            $vals = array_map(function($v) use ($pdo) {
                if ($v === null) return 'NULL';
                if (is_int($v) || is_float($v)) return (string)$v;
                return $pdo->quote((string)$v);
            }, array_values($row));
            fwrite($fh, '(' . implode(', ', $vals) . ')');
        }
        fwrite($fh, ";\n\n");
        $totalRows += $count;
    }
    echo "($count linhas)\n";
}

fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fh);

$size = filesize($file);
$sizeKb = number_format($size / 1024, 1);
echo "\n[OK] Backup concluído: $file ($sizeKb KB, $totalRows linhas total)\n";

// Prune: apaga backups > 30 dias
if (in_array('--prune', $argv ?? [], true)) {
    $cutoff = time() - (30 * 86400);
    $removed = 0;
    foreach (glob($backupDir . '/backup-*.sql') as $old) {
        if (filemtime($old) < $cutoff) {
            @unlink($old);
            $removed++;
        }
    }
    if ($removed > 0) echo "[OK] Prune: $removed backups > 30 dias removidos.\n";
}
