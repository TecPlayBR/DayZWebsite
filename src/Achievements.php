<?php
// ============================================================
// Achievements - calculado on-the-fly a partir das compras do player.
// Sem tabela própria: derivado de purchases + reviews.
// name/description traduzidos via __() — keys em lang/{locale}.php.
// ============================================================

namespace App;

class Achievements {

    /** Lista canônica de achievements (10 total). slug = chave de lang.
     *  Cada um tem 'name' e 'description' lidos do arquivo de idioma atual. */
    public static function all(): array {
        $slugs = [
            'first_blood' => '🩸',
            'veteran'     => '⚔',
            'patron'      => '★',
            'legendary'   => '☠',
            'whale'       => '🐋',
            'night_owl'   => '☾',
            'insomniac'   => '🌙',
            'collector'   => '◇',
            'streak'      => '🔥',
            'generous'    => '💬',
            'rapid_fire'  => '⚡',
            'anniversary' => '🎂',
            // Conquistas de GAMEPLAY (derivadas do player_stats — CFTools/bot).
            'sharpshooter'=> '🎯',
            'exterminator'=> '☣',
            'survivor'    => '⏳',
        ];
        $out = [];
        foreach ($slugs as $slug => $icon) {
            $out[] = [
                'slug'        => $slug,
                'icon'        => $icon,
                'name'        => __("achievements.$slug.name"),
                'description' => __("achievements.$slug.description"),
            ];
        }
        return $out;
    }

    /**
     * Retorna achievements do player como [slug => true]
     */
    public static function unlocked(string $steamId): array {
        $stats = Database::fetchOne(
            "SELECT COUNT(*) AS purchase_count,
                    COALESCE(SUM(price_brl), 0) AS total_spent,
                    COALESCE(MAX(price_brl), 0) AS biggest_purchase,
                    COUNT(DISTINCT package_id) AS distinct_packages,
                    SUM(HOUR(created_at) BETWEEN 0 AND 5) AS night_purchases,
                    COUNT(DISTINCT DATE_FORMAT(created_at, '%Y-%m')) AS distinct_months,
                    MIN(created_at) AS first_purchase_at
               FROM purchases
              WHERE steam_id = ? AND mp_status = 'approved'",
            [$steamId]
        );

        // Rapid fire: tem algum DIA com 2+ compras aprovadas?
        $sameDayCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM (
                SELECT 1 FROM purchases
                 WHERE steam_id = ? AND mp_status = 'approved'
                 GROUP BY DATE(created_at)
                HAVING COUNT(*) >= 2
             ) AS x",
            [$steamId]
        );

        // Reviews aprovadas escritas por esse player
        $reviewCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM reviews WHERE steam_id = ? AND approved = 1",
            [$steamId]
        );

        $unlocked = [];
        if ((int)$stats['purchase_count']    >= 1)   $unlocked['first_blood'] = true;
        if ((int)$stats['purchase_count']    >= 5)   $unlocked['veteran']     = true;
        if ((float)$stats['total_spent']     >= 100) $unlocked['patron']      = true;
        if ((float)$stats['total_spent']     >= 500) $unlocked['legendary']   = true;
        if ((float)$stats['biggest_purchase']>= 200) $unlocked['whale']       = true;
        if ((int)$stats['night_purchases']   >= 1)   $unlocked['night_owl']   = true;
        if ((int)$stats['night_purchases']   >= 3)   $unlocked['insomniac']   = true;
        if ((int)$stats['distinct_packages'] >= 3)   $unlocked['collector']   = true;
        if ((int)$stats['distinct_months']   >= 3)   $unlocked['streak']      = true;
        if ($reviewCount                     >= 1)   $unlocked['generous']    = true;
        if ($sameDayCount                    >= 1)   $unlocked['rapid_fire']  = true;
        if (!empty($stats['first_purchase_at']) && strtotime($stats['first_purchase_at']) <= strtotime('-1 year')) {
            $unlocked['anniversary'] = true;
        }

        // Conquistas de GAMEPLAY (player_stats vem do CFTools/bot; degrada limpo se ausente).
        try {
            $gs = Database::fetchOne(
                "SELECT kills, kills_infected, playtime_seconds, longest_kill_m FROM player_stats WHERE steam_id = ? LIMIT 1",
                [$steamId]
            );
            if ($gs) {
                if ((float)($gs['longest_kill_m']  ?? 0) >= 500)    $unlocked['sharpshooter'] = true;
                if ((int)  ($gs['kills_infected']  ?? 0) >= 500)    $unlocked['exterminator'] = true;
                if ((int)  ($gs['playtime_seconds']?? 0) >= 360000) $unlocked['survivor']     = true; // 100h
            }
        } catch (\Throwable $e) { /* sem player_stats (install antigo) — ignora */ }

        return $unlocked;
    }

    /**
     * Recompensa configurável por conquista (admin define em settings:
     *   achievement_rewards_enabled = '1'  + achievement_rewards = {"slug": coins, ...}).
     * Idempotente: credita +X moedas UMA vez por conquista por jogador — o INSERT IGNORE
     * em achievement_rewards_log é o "claim atômico" (rowCount=1 = ganhei; 0 = já paguei).
     * NÃO lista publicamente: só credita e registra no log do painel. Chamada quando o
     * dono vê o próprio perfil (reconcilia o que faltou). Retorna [slug => coins] do que pagou.
     */
    public static function grantRewards(string $steamId): array {
        if (!Settings::getBool('achievement_rewards_enabled')) return [];
        $rewards = json_decode((string) Settings::get('achievement_rewards', '{}'), true);
        if (!is_array($rewards) || !$rewards) return [];

        $player = Database::fetchOne("SELECT id, coins FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
        if (!$player) return []; // sem player = sem onde creditar (conquistas derivam de compra)

        $paid = [];
        foreach (array_keys(self::unlocked($steamId)) as $slug) {
            $coins = (int) ($rewards[$slug] ?? 0);
            if ($coins <= 0) continue;
            if ($coins > 100000) $coins = 100000; // teto sano (anti-abuso)
            // Claim atômico: só credita quem GANHOU o INSERT. Replay/concorrência = 0 = pula.
            $claim = Database::execute(
                "INSERT IGNORE INTO achievement_rewards_log (steam_id, slug, coins) VALUES (?, ?, ?)",
                [$steamId, $slug, $coins]
            );
            if ($claim === 0) continue; // já recompensado
            $old = (int) $player['coins'];
            Database::query("UPDATE players SET coins = coins + ? WHERE id = ?", [$coins, (int) $player['id']]);
            $player['coins'] = $old + $coins; // consistência se pagar 2+ no mesmo loop
            BalanceLog::record(
                (int) $player['id'], $steamId, $old, $old + $coins,
                'achievement', 'achievement', null, "Conquista: {$slug} (+{$coins} por conta da casa)"
            );
            $paid[$slug] = $coins;
        }
        return $paid;
    }
}
