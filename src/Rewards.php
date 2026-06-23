<?php
// ============================================================
// Rewards - premiação recorrente do leaderboard (CFTools).
// ============================================================
// O admin define em /admin/rewards: quais categorias premiam, quantas moedas
// por colocação (1º/2º/3º), a cadência (manual/semanal/mensal) e se credita
// automático. A premiação pega o top N de cada categoria no leaderboard CFTools
// e credita moedas no saldo do site (players.coins) do vencedor por steam_id.
//
// Dedup: reward_payouts tem UNIQUE (period_label, category, place). O crédito só
// ocorre se o INSERT IGNORE inseriu linha nova -> nunca paga 2x o mesmo período.
//
// NOTA: o leaderboard CFTools é o ranking ATUAL (acumulado). "Mensal" credita o
// top atual a cada virada de mês; para competição com reset, precisa wipe de season.
// ============================================================

namespace App;

class Rewards {
    /** Categorias premiáveis = stats do leaderboard CFTools. */
    public const CATEGORIES = [
        'kills'          => 'Kills (jogadores)',
        'kills_infected' => 'Zumbis mortos',
        'kdratio'        => 'K/D',
        'playtime'       => 'Tempo online',
        'longest_kill'   => 'Kill mais longa',
    ];

    public static function config(): array {
        $raw = Settings::get('leaderboard_rewards', '');
        $c = $raw ? (json_decode($raw, true) ?: []) : [];
        return [
            'enabled'  => !empty($c['enabled']),
            'cadence'  => in_array($c['cadence'] ?? 'manual', ['manual', 'weekly', 'monthly'], true) ? $c['cadence'] : 'manual',
            'auto'     => !empty($c['auto']),
            'cats'     => $c['cats'] ?? [],
        ];
    }

    /** Rótulo do período atual conforme a cadência. */
    public static function periodLabel(?string $cadence = null): string {
        $cadence = $cadence ?? self::config()['cadence'];
        if ($cadence === 'monthly') return date('Y-m');
        if ($cadence === 'weekly')  return date('o-\WW');   // ISO: 2026-W24
        return date('Y-m-d');                                // manual: por dia
    }

    /** Já houve premiação neste período? */
    public static function awardedThisPeriod(?string $label = null): bool {
        $label = $label ?? self::periodLabel();
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM reward_payouts WHERE period_label = ?", [$label]
        ) > 0;
    }

    /** O cron deve premiar agora? (auto ligado, cadência != manual, período não premiado). */
    public static function shouldAutoAward(): bool {
        $cfg = self::config();
        if (!$cfg['enabled'] || !$cfg['auto'] || $cfg['cadence'] === 'manual') return false;
        return !self::awardedThisPeriod();
    }

    /**
     * Roda a premiação do período atual. Credita o top N de cada categoria habilitada.
     * Retorna ['ok'=>bool, 'label'=>str, 'paid'=>[ [cat,place,steam_id,name,coins], ... ], 'error'=>?str].
     * Idempotente por período (UNIQUE em reward_payouts).
     */
    public static function award(): array {
        $cfg = self::config();
        if (!$cfg['enabled']) return ['ok' => false, 'error' => 'Premiação desativada.'];
        if (!CFTools::isConfigured()) return ['ok' => false, 'error' => 'CFTools não configurado (sem leaderboard).'];

        $label = self::periodLabel($cfg['cadence']);
        $paid = [];
        $skipped = [];   // vencedores que não deu pra creditar (sem steam_id mapeável)
        foreach (self::CATEGORIES as $key => $catLabel) {
            $cat = $cfg['cats'][$key] ?? null;
            if (!$cat || empty($cat['enabled'])) continue;
            $coinsByPlace = $cat['coins'] ?? [];
            $maxPlace = 0;
            foreach (['1', '2', '3'] as $pl) if ((int)($coinsByPlace[$pl] ?? 0) > 0) $maxPlace = max($maxPlace, (int)$pl);
            if ($maxPlace === 0) continue;

            $lb = CFTools::leaderboard($key, $maxPlace) ?: [];
            for ($place = 1; $place <= $maxPlace; $place++) {
                $coins = (int)($coinsByPlace[(string)$place] ?? 0);
                if ($coins <= 0) continue;
                $row = $lb[$place - 1] ?? null;
                if (!$row) continue;
                $name = (string)($row['latest_name'] ?? ($row['name'] ?? ''));
                // O leaderboard CFTools devolve cftools_id + latest_name, mas NÃO o steam_id.
                // Resolve o steam64: usa o do row se vier, senão mapeia o cftools_id pelo
                // player_stats (preenchido quando o player abre o perfil / via bot).
                $steamId = self::resolveSteamId($row);
                if (!preg_match('/^\d{17}$/', $steamId)) {
                    // Vencedor sem conta vinculada no site → não dá pra creditar. Reporta.
                    $skipped[] = ['category' => $key, 'cat_label' => $catLabel, 'place' => $place, 'name' => $name, 'coins' => $coins];
                    continue;
                }

                try {
                    if (self::creditOnce($label, $key, $place, $steamId, $name, $coins)) {
                        $paid[] = ['category' => $key, 'cat_label' => $catLabel, 'place' => $place, 'steam_id' => $steamId, 'name' => $name, 'coins' => $coins];
                    }
                } catch (\Throwable $e) {
                    error_log('[Rewards] creditOnce ' . $key . '#' . $place . ': ' . $e->getMessage());
                }
            }
        }
        // marca o último award (cosmético, pro admin ver)
        self::touchLastAwarded();
        return ['ok' => true, 'label' => $label, 'paid' => $paid, 'skipped' => $skipped];
    }

    /**
     * Resolve o steam64 de uma linha do leaderboard CFTools.
     * O leaderboard NÃO traz steam_id (só cftools_id + latest_name) → mapeia o
     * cftools_id pelo player_stats (preenchido quando o player abre o perfil/bot).
     */
    private static function resolveSteamId(array $row): string {
        $sid = (string)($row['steam_id'] ?? '');
        if (preg_match('/^\d{17}$/', $sid)) return $sid;
        $cid = (string)($row['cftools_id'] ?? ($row['id'] ?? ''));
        if ($cid === '') return '';
        return (string)(Database::fetchColumn(
            "SELECT steam_id FROM player_stats WHERE cftools_id = ? AND steam_id IS NOT NULL ORDER BY updated_at DESC LIMIT 1",
            [$cid]
        ) ?: '');
    }

    /**
     * Credita UMA colocação de forma idempotente: INSERT IGNORE no payout (UNIQUE);
     * só credita o saldo se a linha foi nova. Retorna true se creditou.
     */
    private static function creditOnce(string $label, string $cat, int $place, string $steamId, string $name, int $coins): bool {
        $aff = Database::execute(
            "INSERT IGNORE INTO reward_payouts (period_label, category, place, steam_id, player_name, coins)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$label, $cat, $place, $steamId, mb_substr($name, 0, 120), $coins]
        );
        if ($aff === 0) return false; // já pago neste período/categoria/posição

        $player = Database::fetchOne("SELECT id, coins FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
        if ($player) {
            $old = (int)$player['coins'];
            Database::query("UPDATE players SET coins = coins + ? WHERE id = ?", [$coins, (int)$player['id']]);
            $pid = (int)$player['id'];
        } else {
            Database::query(
                "INSERT INTO players (steam_id, display_name, coins, origin, last_seen_at) VALUES (?, ?, ?, 'reward', NOW())",
                [$steamId, $name ?: null, $coins]
            );
            $pid = (int)Database::pdo()->lastInsertId();
            $old = 0;
        }
        try {
            BalanceLog::record($pid, $steamId, $old, $old + $coins, 'reward', 'leaderboard', null,
                "Premiação {$cat} #{$place} ({$label})");
        } catch (\Throwable $e) { /* log opcional */ }
        return true;
    }

    private static function touchLastAwarded(): void {
        $raw = Settings::get('leaderboard_rewards', '');
        $c = $raw ? (json_decode($raw, true) ?: []) : [];
        $c['last_awarded'] = time();
        Settings::set('leaderboard_rewards', json_encode($c, JSON_UNESCAPED_UNICODE));
    }

    public static function lastAwarded(): int {
        $raw = Settings::get('leaderboard_rewards', '');
        $c = $raw ? (json_decode($raw, true) ?: []) : [];
        return (int)($c['last_awarded'] ?? 0);
    }

    public static function history(int $limit = 30): array {
        return Database::fetchAll(
            "SELECT * FROM reward_payouts ORDER BY id DESC LIMIT ?", [$limit]
        );
    }
}
