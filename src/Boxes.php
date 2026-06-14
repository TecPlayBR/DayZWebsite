<?php
// ============================================================
// Boxes - Caixas / Lootboxes. Sorteio por peso + entrega in-game.
// ============================================================
// Abrir caixa: valida (moedas OU cooldown da diária) -> debita -> sorteia item
// por peso -> registra a abertura -> tenta dropar in-game NA HORA (se online e
// longe do restart). Senão, fica 'pending' e o poller entrega depois.
// O drop usa CFTools::spawnPlayerItem (validado live).
// ============================================================

namespace App;

class Boxes {

    /** Caixas habilitadas pro grid público. */
    public static function all(): array {
        return Database::fetchAll(
            "SELECT * FROM boxes WHERE enabled = 1 ORDER BY sort_order ASC, id ASC"
        );
    }

    public static function find(string $slug): ?array {
        $b = Database::fetchOne("SELECT * FROM boxes WHERE slug = ? AND enabled = 1 LIMIT 1", [$slug]);
        if (!$b) return null;
        $b['items'] = self::items((int)$b['id']);
        return $b;
    }

    public static function findById(int $id): ?array {
        $b = Database::fetchOne("SELECT * FROM boxes WHERE id = ? LIMIT 1", [$id]);
        if (!$b) return null;
        $b['items'] = self::items($id);
        return $b;
    }

    /** Itens habilitados da caixa + % calculado (peso/soma). */
    public static function items(int $boxId): array {
        $items = Database::fetchAll(
            "SELECT * FROM box_items WHERE box_id = ? AND enabled = 1 ORDER BY sort_order ASC, id ASC",
            [$boxId]
        );
        $total = 0;
        foreach ($items as $it) $total += max(0, (int)$it['weight']);
        foreach ($items as &$it) {
            $it['chance_pct'] = $total > 0 ? round((int)$it['weight'] / $total * 100, 2) : 0;
        }
        return $items;
    }

    /** Sorteio ponderado pelo peso. Retorna o item ou null. */
    public static function draw(array $items): ?array {
        $pool = array_values(array_filter($items, fn($i) => (int)$i['weight'] > 0));
        if (!$pool) return null;
        $total = 0;
        foreach ($pool as $i) $total += (int)$i['weight'];
        $roll = random_int(1, $total);
        $acc = 0;
        foreach ($pool as $i) {
            $acc += (int)$i['weight'];
            if ($roll <= $acc) return $i;
        }
        return $pool[count($pool) - 1];
    }

    /** Última abertura desta caixa por este player (pro cooldown da diária). */
    public static function lastOpening(int $boxId, string $steamId): ?int {
        $ts = Database::fetchColumn(
            "SELECT UNIX_TIMESTAMP(created_at) FROM box_openings WHERE box_id = ? AND steam_id = ? ORDER BY id DESC LIMIT 1",
            [$boxId, $steamId]
        );
        return $ts ? (int)$ts : null;
    }

    /**
     * Abre a caixa. Retorna ['ok'=>bool, 'error'=>?str, 'won'=>?item, 'status'=>str, 'opening_id'=>int].
     * status: 'delivered' (caiu no jogo) | 'pending' (vai cair quando der).
     */
    public static function open(array $box, string $steamId): array {
        $boxId = (int)$box['id'];
        $items = self::items($boxId);
        if (!$items) return ['ok' => false, 'error' => 'Esta caixa não tem itens configurados.'];

        // Diária: grátis, com cooldown OPCIONAL. Paga: sem cooldown, checa saldo.
        if ((int)$box['is_daily'] === 1) {
            // cooldown_hours = 0 => sem espera (pode abrir em sequência).
            $cd = (int)$box['cooldown_hours'] * 3600;
            if ($cd > 0) {
                $last = self::lastOpening($boxId, $steamId);
                if ($last !== null && (time() - $last) < $cd) {
                    $wait = $cd - (time() - $last);
                    return ['ok' => false, 'error' => 'Caixa diária no cooldown. Volte em ' . self::humanWait($wait) . '.'];
                }
            }
            $cost = 0;
        } else {
            $cost = max(0, (int)$box['cost_coins']);
            $player = Database::fetchOne("SELECT id, coins FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
            $coins = (int)($player['coins'] ?? 0);
            if ($coins < $cost) {
                return ['ok' => false, 'error' => 'Saldo insuficiente. Você tem ' . $coins . ' e a caixa custa ' . $cost . ' moedas.'];
            }
        }

        // Sorteia ANTES de debitar (se não houver item válido, não cobra).
        $won = self::draw($items);
        if (!$won) return ['ok' => false, 'error' => 'Não foi possível sortear um item.'];

        // Debita moedas (caixa paga) — com log.
        if ($cost > 0) {
            $player = $player ?? Database::fetchOne("SELECT id, coins FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
            $pid = (int)($player['id'] ?? 0);
            $old = (int)($player['coins'] ?? 0);
            // Debito atômico: só desconta se ainda tem saldo (anti race / duplo-clique).
            $aff = Database::execute(
                "UPDATE players SET coins = coins - ? WHERE steam_id = ? AND coins >= ?",
                [$cost, $steamId, $cost]
            );
            if ($aff === 0) return ['ok' => false, 'error' => 'Saldo insuficiente.'];
            try {
                BalanceLog::record($pid, $steamId, $old, $old - $cost, 'box', 'box', $boxId, 'Abertura: ' . $box['name']);
            } catch (\Throwable $e) { /* log opcional */ }
        }

        // Registra a abertura (pending).
        Database::query(
            "INSERT INTO box_openings (box_id, steam_id, item_id, classname, item_name, quantity, rarity, cost_paid, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
            [$boxId, $steamId, (int)$won['id'], $won['classname'], $won['name'], (int)$won['quantity'], $won['rarity'], $cost]
        );
        $openingId = (int)Database::pdo()->lastInsertId();

        // Entrega.
        if (($won['type'] ?? 'item') === 'coins') {
            // Recompensa em MOEDAS: credita no saldo na hora (quantity = qtd de moedas).
            $amount = max(1, (int)$won['quantity']);
            $p = Database::fetchOne("SELECT id, coins FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
            if ($p) {
                $old = (int)$p['coins'];
                Database::query("UPDATE players SET coins = coins + ? WHERE id = ?", [$amount, (int)$p['id']]);
                $pid2 = (int)$p['id'];
            } else {
                Database::query("INSERT INTO players (steam_id, coins, origin, last_seen_at) VALUES (?, ?, 'box', NOW())", [$steamId, $amount]);
                $pid2 = (int)Database::pdo()->lastInsertId(); $old = 0;
            }
            try { BalanceLog::record($pid2, $steamId, $old, $old + $amount, 'box', 'box', $boxId, 'Caixa: ' . $box['name'] . ' (moedas)'); } catch (\Throwable $e) {}
            Database::query("UPDATE box_openings SET status = 'delivered', delivered_at = NOW() WHERE id = ?", [$openingId]);
            $status = 'delivered';
        } else {
            // Item in-game: dropa agora se online E longe do restart; senão fica pendente.
            $status = self::deliver($openingId, $steamId, $won['classname'], (int)$won['quantity']);
        }

        return ['ok' => true, 'won' => $won, 'status' => $status, 'opening_id' => $openingId];
    }

    /**
     * Tenta dropar uma abertura. Retorna 'delivered' ou 'pending'.
     * Blindagem: não dropa offline nem perto do restart (item iria pro limbo).
     */
    public static function deliver(int $openingId, string $steamId, string $classname, int $qty): string {
        if (Restart::inDangerWindow()) return 'pending';
        if (!CFTools::isConfigured() || !CFTools::isOnline($steamId)) return 'pending';
        $ok = CFTools::spawnPlayerItem($steamId, $classname, max(1, $qty));
        if ($ok) {
            Database::query("UPDATE box_openings SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND status = 'pending'", [$openingId]);
            return 'delivered';
        }
        return 'pending';
    }

    /**
     * Poller: entrega aberturas pendentes pra quem está online agora (e longe do restart).
     * Chamar periodicamente (cron) OU oportunisticamente quando o site detecta online.
     * Retorna quantas entregou.
     */
    public static function deliverPending(int $limit = 50): int {
        if (Restart::inDangerWindow() || !CFTools::isConfigured()) return 0;
        $online = CFTools::onlinePlayers();
        if (empty($online)) return 0;
        $ids = array_filter(array_map(fn($r) => $r['steam_id'] ?? '', $online));
        if (!$ids) return 0;
        $delivered = 0;
        $pending = Database::fetchAll(
            "SELECT id, steam_id, classname, quantity FROM box_openings WHERE status = 'pending' ORDER BY id ASC LIMIT ?",
            [$limit]
        );
        foreach ($pending as $op) {
            if (!in_array($op['steam_id'], $ids, true)) continue;
            if (CFTools::spawnPlayerItem($op['steam_id'], $op['classname'], max(1, (int)$op['quantity']))) {
                Database::query("UPDATE box_openings SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND status = 'pending'", [(int)$op['id']]);
                $delivered++;
            }
        }
        return $delivered;
    }

    /** Aberturas recentes de um player (pro histórico/inventário no perfil). */
    public static function history(string $steamId, int $limit = 20): array {
        return Database::fetchAll(
            "SELECT bo.*, b.name AS box_name FROM box_openings bo
               LEFT JOIN boxes b ON b.id = bo.box_id
              WHERE bo.steam_id = ? ORDER BY bo.id DESC LIMIT ?",
            [$steamId, $limit]
        );
    }

    private static function humanWait(int $sec): string {
        $h = intdiv($sec, 3600); $m = intdiv($sec % 3600, 60);
        if ($h > 0) return $h . 'h ' . $m . 'min';
        return max(1, $m) . 'min';
    }
}
