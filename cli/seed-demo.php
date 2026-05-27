<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// Demo Seed CLI
// ============================================================
// Popula o banco com dados fictícios pra cliente ver o site "vivo"
// já no primeiro acesso. Idempotente: pode rodar várias vezes — só
// insere se ainda não houver dados de demo (steam_id começa com 7656119DEMO).
//
// Uso: php cli/seed-demo.php
//      php cli/seed-demo.php --clean  (remove tudo que foi semeado)
// ============================================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { die("Rode só via CLI.\n"); }

require __DIR__ . '/../src/Database.php';

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die("Config não encontrada. Rode o /install.php primeiro.\n");
}
$config = require $configFile;
App\Database::init($config['db']);

$clean = in_array('--clean', $argv ?? []);

// Os steam_ids "demo" começam com 76561197000000 (range reservado pra contas inválidas/teste)
$demoPrefix = '76561197000';

if ($clean) {
    echo "Limpando dados demo...\n";
    App\Database::execute("DELETE FROM balance_log  WHERE steam_id LIKE ?", [$demoPrefix . '%']);
    App\Database::execute("DELETE FROM reviews      WHERE steam_id LIKE ?", [$demoPrefix . '%']);
    App\Database::execute("DELETE FROM purchases    WHERE steam_id LIKE ?", [$demoPrefix . '%']);
    App\Database::execute("DELETE FROM wishlist     WHERE steam_id LIKE ?", [$demoPrefix . '%']);
    App\Database::execute("DELETE FROM players      WHERE steam_id LIKE ?", [$demoPrefix . '%']);
    App\Database::execute("DELETE FROM announcements WHERE title LIKE '[demo]%'");
    echo "OK — dados demo removidos.\n";
    exit;
}

// ============ PLAYERS ============
$names = [
    'SobreviventeBR','NoSleepZ','ChernoKing','BrabusPVP','SilentRaider',
    'MossberGirl','LobaSolitaria','RioCoutoZ','TecPlayerX','VeteranoZ',
];
$packages = App\Database::fetchAll("SELECT id, price_brl FROM packages WHERE enabled = 1 ORDER BY sort_order ASC");
if (!$packages) {
    die("Nenhum package cadastrado. Faça o seed do schema primeiro.\n");
}

$created = ['players' => 0, 'purchases' => 0, 'reviews' => 0, 'announcements' => 0];

foreach ($names as $i => $name) {
    $steam = $demoPrefix . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
    $exists = App\Database::fetchColumn("SELECT id FROM players WHERE steam_id = ?", [$steam]);
    if ($exists) continue;

    $coins = random_int(20, 350);
    $spent = (float)random_int(15, 480);
    $daysAgo = random_int(0, 25);
    App\Database::execute(
        "INSERT INTO players (steam_id, display_name, coins, total_spent_brl, origin, last_seen_at, created_at)
         VALUES (?, ?, ?, ?, 'agent', DATE_SUB(NOW(), INTERVAL ? HOUR), DATE_SUB(NOW(), INTERVAL ? DAY))",
        [$steam, $name, $coins, $spent, random_int(1, 48), $daysAgo]
    );
    $created['players']++;
}

// ============ PURCHASES ============
$statusPool = ['approved','approved','approved','approved','approved','pending','rejected'];
for ($n = 0; $n < 25; $n++) {
    $i = random_int(0, count($names) - 1);
    $steam = $demoPrefix . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
    $pkg = $packages[array_rand($packages)];
    $status = $statusPool[array_rand($statusPool)];
    $coins = random_int(10, 200);
    $hoursAgo = random_int(0, 720); // até 30 dias
    $delivered = $status === 'approved' ? 'DATE_SUB(NOW(), INTERVAL ' . $hoursAgo . ' HOUR)' : 'NULL';

    App\Database::execute(
        "INSERT INTO purchases
            (steam_id, package_id, coins_base, coins_bonus, coins_total, price_brl,
             mp_status, mp_payment_id, delivered_at, terms_accepted_at, terms_version, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, $delivered, NOW(), '2026-05-27', DATE_SUB(NOW(), INTERVAL ? HOUR))",
        [
            $steam, $pkg['id'], $coins, 0, $coins, (float)$pkg['price_brl'],
            $status, 'DEMO-' . bin2hex(random_bytes(6)),
            $hoursAgo,
        ]
    );
    $created['purchases']++;
}

// ============ REVIEWS (5) ============
$reviewTexts = [
    'Mais um servidor bom de verdade, sem bug, sem laggar. Recomendo.',
    'Entrega instantânea, comprei e em 30s ja tava com as moedas. TOP.',
    'Comunidade gente fina, staff acordada. Joguei 60h e curti cada minuto.',
    'PvP justo, regras claras. Voltei depois de meses e o servidor ta firme.',
    'A loja é prática, paguei no Pix e em segundos veio confirmação. 5 estrelas.',
];
$approvedPurchases = App\Database::fetchAll(
    "SELECT id, steam_id FROM purchases WHERE mp_status = 'approved' AND steam_id LIKE ? LIMIT 5",
    [$demoPrefix . '%']
);
foreach ($approvedPurchases as $idx => $p) {
    $existing = App\Database::fetchColumn("SELECT id FROM reviews WHERE purchase_id = ?", [$p['id']]);
    if ($existing) continue;
    $playerName = App\Database::fetchColumn("SELECT display_name FROM players WHERE steam_id = ?", [$p['steam_id']]);
    App\Database::execute(
        "INSERT INTO reviews (purchase_id, steam_id, display_name, rating, body, approved, created_at)
         VALUES (?, ?, ?, ?, ?, 1, DATE_SUB(NOW(), INTERVAL ? DAY))",
        [$p['id'], $p['steam_id'], $playerName, random_int(4, 5), $reviewTexts[$idx], random_int(1, 20)]
    );
    $created['reviews']++;
}

// ============ ANNOUNCEMENTS (3) ============
$announcements = [
    ['[demo] Bem-vindo ao servidor!',     'Servidor novo, comunidade ativa, regras claras. Entra no Discord pra mais.', 'info'],
    ['[demo] Wipe programado',            'Próximo wipe daqui 7 dias. Aproveite pra terminar suas bases.',                'warning'],
    ['[demo] Promoção de inverno ativa',  'Use o cupom INVERNO20 e ganhe 20% em qualquer pacote. Só até domingo.',        'success'],
];
foreach ($announcements as $a) {
    $exists = App\Database::fetchColumn("SELECT id FROM announcements WHERE title = ?", [$a[0]]);
    if ($exists) continue;
    App\Database::execute(
        "INSERT INTO announcements (title, body, kind, published, created_at)
         VALUES (?, ?, ?, 1, NOW())",
        [$a[0], $a[1], $a[2]]
    );
    $created['announcements']++;
}

echo "Demo seed concluído:\n";
echo "  - {$created['players']} jogadores\n";
echo "  - {$created['purchases']} compras\n";
echo "  - {$created['reviews']} reviews\n";
echo "  - {$created['announcements']} anúncios\n";
echo "\nDica: rode 'php cli/seed-demo.php --clean' pra remover tudo antes do go-live.\n";
