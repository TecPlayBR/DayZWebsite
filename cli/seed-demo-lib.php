<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// Demo Seed - biblioteca compartilhada (PDO puro)
// ============================================================
// Lógica de povoamento de dados-demo reutilizável tanto pelo CLI
// (cli/seed-demo.php) quanto pelo instalador web (public/install.php).
// Recebe um PDO já conectado — não toca em config nem em App\Database.
//
// Idempotente: só insere o que ainda não existe (steam_id 76561197000* e
// anúncios com título "[demo] ..."). Pode rodar várias vezes sem duplicar.
// ============================================================

if (!defined('DEMO_STEAM_PREFIX')) {
    // Range 76561197000* = contas inválidas/teste (nunca colide com SteamID64 real).
    define('DEMO_STEAM_PREFIX', '76561197000');
}

if (!function_exists('seed_demo_clean')) {
    function seed_demo_clean(PDO $pdo): void {
        $like = DEMO_STEAM_PREFIX . '%';
        foreach (['balance_log', 'reviews', 'purchases', 'wishlist', 'players'] as $t) {
            try {
                $st = $pdo->prepare("DELETE FROM `$t` WHERE steam_id LIKE ?");
                $st->execute([$like]);
            } catch (\Throwable $e) { /* tabela ausente em install antigo — ignora */ }
        }
        try { $pdo->exec("DELETE FROM announcements WHERE title LIKE '[demo]%'"); }
        catch (\Throwable $e) { /* ignora */ }
    }
}

if (!function_exists('seed_demo_data')) {
    /**
     * Popula dados-demo. Retorna contagem por tipo.
     * @return array{players:int,purchases:int,reviews:int,announcements:int}
     */
    function seed_demo_data(PDO $pdo): array {
        $prefix  = DEMO_STEAM_PREFIX;
        $created = ['players' => 0, 'purchases' => 0, 'reviews' => 0, 'announcements' => 0];

        // Precisa de pacotes ativos pra gerar compras coerentes.
        $packages = $pdo->query("SELECT id, price_brl FROM packages WHERE enabled = 1 ORDER BY sort_order ASC")
                        ->fetchAll(PDO::FETCH_ASSOC);
        if (!$packages) {
            // Sem pacotes não dá pra semear compras; cria só players/anúncios.
            $packages = [];
        }

        // ============ PLAYERS ============
        $names = [
            'SobreviventeBR','NoSleepZ','ChernoKing','BrabusPVP','SilentRaider',
            'MossberGirl','LobaSolitaria','RioCoutoZ','TecPlayerX','VeteranoZ',
        ];
        $insPlayer = $pdo->prepare(
            "INSERT INTO players (steam_id, display_name, coins, total_spent_brl, origin, last_seen_at, created_at)
             VALUES (?, ?, ?, ?, 'agent', DATE_SUB(NOW(), INTERVAL ? HOUR), DATE_SUB(NOW(), INTERVAL ? DAY))"
        );
        $existsPlayer = $pdo->prepare("SELECT id FROM players WHERE steam_id = ?");
        foreach ($names as $i => $name) {
            $steam = $prefix . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
            $existsPlayer->execute([$steam]);
            if ($existsPlayer->fetchColumn()) continue;
            $insPlayer->execute([
                $steam, $name, random_int(20, 350), (float)random_int(15, 480),
                random_int(1, 48), random_int(0, 25),
            ]);
            $created['players']++;
        }

        // ============ PURCHASES ============
        if ($packages) {
            $statusPool = ['approved','approved','approved','approved','approved','pending','rejected'];
            for ($n = 0; $n < 25; $n++) {
                $i      = random_int(0, count($names) - 1);
                $steam  = $prefix . str_pad((string)($i + 1), 6, '0', STR_PAD_LEFT);
                $pkg    = $packages[array_rand($packages)];
                $status = $statusPool[array_rand($statusPool)];
                $coins  = random_int(10, 200);
                $hours  = random_int(0, 720); // até 30 dias
                $delivered = $status === 'approved'
                    ? 'DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR)' : 'NULL';
                $pdo->prepare(
                    "INSERT INTO purchases
                        (steam_id, package_id, coins_base, coins_bonus, coins_total, price_brl,
                         mp_status, mp_payment_id, delivered_at, terms_accepted_at, terms_version, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, $delivered, NOW(), '2026-05-27', DATE_SUB(NOW(), INTERVAL ? HOUR))"
                )->execute([
                    $steam, $pkg['id'], $coins, 0, $coins, (float)$pkg['price_brl'],
                    $status, 'DEMO-' . bin2hex(random_bytes(6)), $hours,
                ]);
                $created['purchases']++;
            }

            // ============ REVIEWS (até 5) ============
            $reviewTexts = [
                'Mais um servidor bom de verdade, sem bug, sem laggar. Recomendo.',
                'Entrega instantânea, comprei e em 30s ja tava com as moedas. TOP.',
                'Comunidade gente fina, staff acordada. Joguei 60h e curti cada minuto.',
                'PvP justo, regras claras. Voltei depois de meses e o servidor ta firme.',
                'A loja é prática, paguei no Pix e em segundos veio confirmação. 5 estrelas.',
            ];
            $st = $pdo->prepare(
                "SELECT id, steam_id FROM purchases WHERE mp_status = 'approved' AND steam_id LIKE ? LIMIT 5"
            );
            $st->execute([$prefix . '%']);
            $approved = $st->fetchAll(PDO::FETCH_ASSOC);
            $existsReview = $pdo->prepare("SELECT id FROM reviews WHERE purchase_id = ?");
            $getName = $pdo->prepare("SELECT display_name FROM players WHERE steam_id = ?");
            $insReview = $pdo->prepare(
                "INSERT INTO reviews (purchase_id, steam_id, display_name, rating, body, approved, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, DATE_SUB(NOW(), INTERVAL ? DAY))"
            );
            foreach ($approved as $idx => $p) {
                $existsReview->execute([$p['id']]);
                if ($existsReview->fetchColumn()) continue;
                $getName->execute([$p['steam_id']]);
                $playerName = $getName->fetchColumn() ?: 'Sobrevivente';
                $insReview->execute([
                    $p['id'], $p['steam_id'], $playerName,
                    random_int(4, 5), $reviewTexts[$idx], random_int(1, 20),
                ]);
                $created['reviews']++;
            }
        }

        // ============ ANNOUNCEMENTS (3) ============
        $announcements = [
            ['[demo] Bem-vindo ao servidor!',    'Servidor novo, comunidade ativa, regras claras. Entra no Discord pra mais.', 'info'],
            ['[demo] Wipe programado',           'Próximo wipe daqui 7 dias. Aproveite pra terminar suas bases.',                'warning'],
            ['[demo] Promoção de inverno ativa', 'Use o cupom INVERNO20 e ganhe 20% em qualquer pacote. Só até domingo.',        'success'],
        ];
        $existsAnn = $pdo->prepare("SELECT id FROM announcements WHERE title = ?");
        $insAnn = $pdo->prepare(
            "INSERT INTO announcements (title, body, kind, published, created_at) VALUES (?, ?, ?, 1, NOW())"
        );
        foreach ($announcements as $a) {
            $existsAnn->execute([$a[0]]);
            if ($existsAnn->fetchColumn()) continue;
            $insAnn->execute([$a[0], $a[1], $a[2]]);
            $created['announcements']++;
        }

        return $created;
    }
}
