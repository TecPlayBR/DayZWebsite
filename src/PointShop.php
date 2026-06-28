<?php
namespace App;

// ============================================================
// PointShop — Loja de Pontos (Fase 2): gastar pontos em itens in-game.
// ============================================================
// Compra: debita pontos (Points::spend, atômico) -> cria point_purchase ->
// entrega via CFTools (item principal + anexos/kit). Offline/restart => fila
// (deliverPending). Espelha o mecanismo das Caixas.
// ============================================================

class PointShop
{
    /** Jogador tem VIP ativo? (grant type=vip aplicado/pendente e não expirado). */
    public static function isVip(string $steamId): bool {
        try {
            return (bool) Database::fetchColumn(
                "SELECT 1 FROM player_grants WHERE steam_id = ? AND type = 'vip'
                   AND status IN ('applied','pending')
                   AND (expiration_date IS NULL OR expiration_date >= CURDATE()) LIMIT 1",
                [$steamId]
            );
        } catch (\Throwable $e) { return false; }
    }

    /** Tem ao menos 1 item habilitado? (pra mostrar/ocultar o link na nav). */
    public static function hasItems(): bool {
        try { return (bool) Database::fetchColumn("SELECT 1 FROM point_shop_items WHERE enabled = 1 LIMIT 1"); }
        catch (\Throwable $e) { return false; }
    }

    public static function categories(): array {
        return array_map(fn($r) => $r['category'], Database::fetchAll(
            "SELECT DISTINCT category FROM point_shop_items WHERE enabled = 1 ORDER BY category ASC"
        ));
    }

    public static function items(): array {
        return Database::fetchAll(
            "SELECT * FROM point_shop_items WHERE enabled = 1 ORDER BY category ASC, sort_order ASC, point_cost ASC"
        );
    }

    public static function allItems(): array {
        return Database::fetchAll("SELECT * FROM point_shop_items ORDER BY sort_order ASC, id DESC");
    }

    public static function get(int $id): ?array {
        return Database::fetchOne("SELECT * FROM point_shop_items WHERE id = ? LIMIT 1", [$id]) ?: null;
    }

    public static function attachments(int $itemId): array {
        return Database::fetchAll(
            "SELECT classname, quantity FROM point_shop_item_attachments WHERE item_id = ? ORDER BY sort_order ASC, id ASC",
            [$itemId]
        );
    }

    /**
     * Compra um item com pontos. $isVip vem do route (status VIP do jogador).
     * Erros: not_found, disabled, vip_only, no_points. Sucesso: ['ok'=>true,'status'=>...,'item'=>...].
     */
    public static function buy(int $itemId, string $steamId, bool $isVip): array {
        $item = self::get($itemId);
        if (!$item || !$item['enabled']) return ['ok' => false, 'error' => 'not_found'];
        if ((int)$item['vip_only'] === 1 && !$isVip) return ['ok' => false, 'error' => 'vip_only'];
        $cost = max(0, (int)$item['point_cost']);

        // Débito atômico de pontos (não compra se não tem saldo).
        if (!Points::spend($steamId, $cost, 'shop', 'point_shop', $itemId, 'Loja de pontos: ' . $item['name'])) {
            return ['ok' => false, 'error' => 'no_points', 'balance' => Points::balance($steamId)];
        }

        $atts = self::attachments($itemId);
        $attJson = $atts ? json_encode($atts, JSON_UNESCAPED_UNICODE) : null;
        Database::query(
            "INSERT INTO point_purchases (steam_id, item_id, item_name, classname, quantity, attachments_json, point_cost, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
            [$steamId, $itemId, $item['name'], $item['classname'], (int)$item['quantity'], $attJson, $cost]
        );
        $purchaseId = (int)Database::pdo()->lastInsertId();

        $status = self::deliver($purchaseId, $steamId, $item['classname'], (int)$item['quantity'], $atts);
        return ['ok' => true, 'status' => $status, 'item' => $item, 'purchase_id' => $purchaseId,
                'points' => Points::balance($steamId)];
    }

    /** Entrega: spawna item principal + anexos. Offline/restart => 'pending'. */
    public static function deliver(int $purchaseId, string $steamId, string $classname, int $qty, array $attachments): string {
        if (Restart::inDangerWindow()) return 'pending';
        if (!CFTools::isConfigured() || !CFTools::isOnline($steamId)) return 'pending';
        $ok = CFTools::spawnPlayerItem($steamId, $classname, max(1, $qty));
        if (!$ok) return 'pending';
        foreach ($attachments as $a) {
            CFTools::spawnPlayerItem($steamId, (string)$a['classname'], max(1, (int)$a['quantity'])); // best-effort
        }
        Database::query("UPDATE point_purchases SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND status = 'pending'", [$purchaseId]);
        return 'delivered';
    }

    /** Poller das compras pendentes (pra quem está online). Retorna quantas entregou. */
    public static function deliverPending(int $limit = 50): int {
        if (Restart::inDangerWindow() || !CFTools::isConfigured()) return 0;
        $online = CFTools::onlinePlayers();
        if (empty($online)) return 0;
        $ids = array_filter(array_map(fn($r) => $r['steam_id'] ?? '', $online));
        if (!$ids) return 0;
        $pending = Database::fetchAll(
            "SELECT id, steam_id, classname, quantity, attachments_json FROM point_purchases WHERE status = 'pending' ORDER BY id ASC LIMIT " . max(1, (int)$limit)
        );
        $delivered = 0;
        foreach ($pending as $p) {
            if (!in_array($p['steam_id'], $ids, true)) continue;
            if (!CFTools::spawnPlayerItem($p['steam_id'], $p['classname'], max(1, (int)$p['quantity']))) continue;
            $atts = $p['attachments_json'] ? (json_decode($p['attachments_json'], true) ?: []) : [];
            foreach ($atts as $a) {
                CFTools::spawnPlayerItem($p['steam_id'], (string)$a['classname'], max(1, (int)$a['quantity']));
            }
            Database::query("UPDATE point_purchases SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND status = 'pending'", [(int)$p['id']]);
            $delivered++;
        }
        return $delivered;
    }

    public static function history(string $steamId, int $limit = 20): array {
        return Database::fetchAll(
            "SELECT * FROM point_purchases WHERE steam_id = ? ORDER BY id DESC LIMIT " . max(1, (int)$limit),
            [$steamId]
        );
    }

    public static function slugifyCat(string $s): string {
        return trim($s) !== '' ? trim($s) : 'Geral';
    }
}
