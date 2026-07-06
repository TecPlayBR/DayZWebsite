<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// Demo Seed CLI
// ============================================================
// Popula o banco com dados fictícios pra cliente ver o site "vivo"
// já no primeiro acesso. Idempotente: pode rodar várias vezes - só
// insere se ainda não houver dados de demo (steam_id começa com 7656119DEMO).
//
// Uso: php cli/seed-demo.php
//      php cli/seed-demo.php --clean  (remove tudo que foi semeado)
// ============================================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { die("Rode só via CLI.\n"); }

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/seed-demo-lib.php';

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die("Config não encontrada. Rode o /install.php primeiro.\n");
}
$config = require $configFile;
App\Database::init($config['db']);
$pdo = App\Database::pdo();

if (in_array('--clean', $argv ?? [], true)) {
    echo "Limpando dados demo...\n";
    seed_demo_clean($pdo);
    echo "OK - dados demo removidos.\n";
    exit;
}

$created = seed_demo_data($pdo);

echo "Demo seed concluído:\n";
echo "  - {$created['players']} jogadores\n";
echo "  - {$created['purchases']} compras\n";
echo "  - {$created['reviews']} reviews\n";
echo "  - {$created['announcements']} anúncios\n";
echo "\nDica: rode 'php cli/seed-demo.php --clean' pra remover tudo antes do go-live.\n";
