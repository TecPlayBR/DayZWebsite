<?php
// ============================================================
// Vip - loja de VIP / BattlePass paga com MOEDAS.
// ============================================================
// O jogador compra um tier (PanelVip1..4) ou o BattlePass por X dias gastando
// moedas do saldo do site (players.coins). A compra:
//   1. debita as moedas de forma ATÔMICA (nunca deixa negativo);
//   2. grava um player_grant 'pending' (renovação SOMA dias ao que ainda falta);
//   3. registra no balance_log.
// O tecplay-agent puxa o grant pendente em /api/entitlements.php e aplica no
// mod Sparda - o MESMO fluxo da concessão manual em /admin/entitlements.
//
// Preço/labels: settings 'vip_store' (JSON). NÃO tem tabela nova (usa
// player_grants + settings). Durações fixas: 30/60/90 dias.
// ============================================================

namespace App;

class Vip {
    /** Durações vendáveis (dias). */
    public const DURATIONS = [30, 60, 90];

    /**
     * Tiers de VIP do mod Sparda (espelha /admin/entitlements). CUSTOM = painel
     * totalmente customizável (o "Loadout"). O agent escreve todos em
     * VipPanel/PlayersVIP.json (NamePanel = a chave), então CUSTOM funciona com o
     * agent atual sem mudanças.
     */
    public const VIP_TIERS = ['PanelVip1', 'PanelVip2', 'PanelVip3', 'PanelVip4', 'CUSTOM'];

    /**
     * Produtos EXTRA do mod Sparda vendáveis por moeda (fora dos tiers de VIP).
     * chave (= player_grants.type) => rótulo padrão. O agent escreve cada um num
     * JSON diferente do Sparda (skin -> SkinStore, killfeed -> KillFeed).
     */
    public const EXTRAS = ['skin' => 'Skin / Textura', 'killfeed' => 'KillFeed'];

    /** Config da loja de VIP (settings 'vip_store'), com defaults seguros. */
    public static function config(): array {
        $raw = Settings::get('vip_store', '');
        $c = $raw ? (json_decode($raw, true) ?: []) : [];
        $tiers = [];
        foreach (self::VIP_TIERS as $i => $key) {
            $t = $c['tiers'][$key] ?? [];
            $tiers[$key] = [
                'enabled' => !empty($t['enabled']),
                'label'   => trim((string)($t['label'] ?? '')) ?: ($key === 'CUSTOM' ? 'Loadout Customizável' : ('VIP ' . ($i + 1))),
                'desc'    => trim((string)($t['desc'] ?? '')),
                'image'   => trim((string)($t['image'] ?? '')),
                'perks'   => array_values(array_filter(array_map('trim', (array)($t['perks'] ?? [])))),
                'prices'  => self::cleanPrices($t['prices'] ?? []),
            ];
        }
        $bp = $c['battlepass'] ?? [];
        $extras = [];
        foreach (self::EXTRAS as $key => $defLabel) {
            $e = $c['extras'][$key] ?? [];
            $extras[$key] = [
                'enabled' => !empty($e['enabled']),
                'label'   => trim((string)($e['label'] ?? '')) ?: $defLabel,
                'desc'    => trim((string)($e['desc'] ?? '')),
                'image'   => trim((string)($e['image'] ?? '')),
                'perks'   => array_values(array_filter(array_map('trim', (array)($e['perks'] ?? [])))),
                'prices'  => self::cleanPrices($e['prices'] ?? []),
            ];
        }
        return [
            'enabled' => !empty($c['enabled']),
            'tiers'   => $tiers,
            'battlepass' => [
                'enabled' => !empty($bp['enabled']),
                'label'   => trim((string)($bp['label'] ?? '')) ?: 'Passe de Batalha',
                'desc'    => trim((string)($bp['desc'] ?? '')),
                'image'   => trim((string)($bp['image'] ?? '')),
                'perks'   => array_values(array_filter(array_map('trim', (array)($bp['perks'] ?? [])))),
                'prices'  => self::cleanPrices($bp['prices'] ?? []),
            ],
            'extras'  => $extras,
        ];
    }

    /** Normaliza o mapa de preços {dias: moedas} só com durações válidas e valor > 0. */
    private static function cleanPrices($prices): array {
        $out = [];
        if (is_array($prices)) {
            foreach (self::DURATIONS as $d) {
                $v = (int)($prices[(string)$d] ?? ($prices[$d] ?? 0));
                if ($v > 0) $out[(string)$d] = $v;
            }
        }
        return $out;
    }

    /** A loja de VIP está ligada (master + tem ao menos 1 item vendável)? */
    public static function enabled(): bool {
        $c = self::config();
        if (!$c['enabled']) return false;
        foreach ($c['tiers'] as $t) if ($t['enabled'] && $t['prices']) return true;
        if ($c['battlepass']['enabled'] && $c['battlepass']['prices']) return true;
        foreach ($c['extras'] as $e) if ($e['enabled'] && $e['prices']) return true;
        return false;
    }

    /** Preço em moedas de (type/tier, dias). null = não vendável. */
    public static function priceFor(string $type, ?string $tier, int $days): ?int {
        $c = self::config();
        if (!$c['enabled']) return null;
        if ($type === 'battlepass') {
            if (!$c['battlepass']['enabled']) return null;
            return $c['battlepass']['prices'][(string)$days] ?? null;
        }
        if ($type === 'vip' && $tier && isset($c['tiers'][$tier])) {
            $t = $c['tiers'][$tier];
            if (!$t['enabled']) return null;
            return $t['prices'][(string)$days] ?? null;
        }
        if (isset(self::EXTRAS[$type]) && isset($c['extras'][$type])) {
            $e = $c['extras'][$type];
            if (!$e['enabled']) return null;
            return $e['prices'][(string)$days] ?? null;
        }
        return null;
    }

    /**
     * Grant ativo (pending/applied, ainda não expirado) desse jogador pra esse
     * type+tier. Usado pra mostrar status e pra RENOVAR somando dias.
     */
    public static function activeGrant(int $serverId, string $steamId, string $type, ?string $tier): ?array {
        return Database::fetchOne(
            "SELECT * FROM player_grants
              WHERE server_id = ? AND steam_id = ? AND type = ? AND (tier <=> ?)
                AND status IN ('pending','applied')
                AND (expiration_date IS NULL OR expiration_date >= CURDATE())
              ORDER BY expiration_date DESC, id DESC LIMIT 1",
            [$serverId, $steamId, $type, $tier]
        ) ?: null;
    }

    /** Todos os benefícios ativos do jogador (pro perfil/loja mostrar). */
    public static function activeForPlayer(int $serverId, string $steamId): array {
        return Database::fetchAll(
            "SELECT type, tier, MAX(expiration_date) AS expiration_date
               FROM player_grants
              WHERE server_id = ? AND steam_id = ?
                AND status IN ('pending','applied','external')
                AND (expiration_date IS NULL OR expiration_date >= CURDATE())
              GROUP BY type, tier",
            [$serverId, $steamId]
        );
    }

    /**
     * Compra/renova VIP/Passe gastando moedas. Atômico.
     * Retorna ['ok'=>bool, 'error'=>?str, 'new_balance'=>int, 'expiration'=>str, 'extended'=>bool, 'days_total'=>int].
     */
    public static function purchase(int $serverId, string $steamId, ?string $nick, string $type, ?string $tier, int $days): array {
        if (!in_array($days, self::DURATIONS, true)) return ['ok' => false, 'error' => 'invalid_duration'];
        if ($type === 'vip' && !in_array($tier, self::VIP_TIERS, true)) return ['ok' => false, 'error' => 'invalid_tier'];
        if ($type !== 'vip') $tier = null; // battlepass/skin/killfeed nao tem tier
        if (!in_array($type, array_merge(['vip', 'battlepass'], array_keys(self::EXTRAS)), true)) return ['ok' => false, 'error' => 'invalid_type'];

        $price = self::priceFor($type, $tier, $days);
        if ($price === null || $price <= 0) return ['ok' => false, 'error' => 'not_for_sale'];

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            // Saldo atual (lock) + débito atômico (nunca negativa).
            $player = Database::fetchOne("SELECT id, coins FROM players WHERE steam_id = ? LIMIT 1 FOR UPDATE", [$steamId]);
            if (!$player) { $pdo->rollBack(); return ['ok' => false, 'error' => 'no_player']; }
            $before = (int)$player['coins'];
            if ($before < $price) { $pdo->rollBack(); return ['ok' => false, 'error' => 'insufficient', 'new_balance' => $before, 'price' => $price]; }

            $upd = $pdo->prepare("UPDATE players SET coins = coins - ? WHERE id = ? AND coins >= ?");
            $upd->execute([$price, (int)$player['id'], $price]);
            if ($upd->rowCount() === 0) { $pdo->rollBack(); return ['ok' => false, 'error' => 'insufficient', 'new_balance' => $before, 'price' => $price]; }
            $after = $before - $price;

            // Renovação: soma dias ao que ainda falta (do grant ativo do MESMO type+tier).
            $active = self::activeGrant($serverId, $steamId, $type, $tier);
            $extended = false;
            if ($active && !empty($active['expiration_date'])) {
                $base = new \DateTime($active['expiration_date']);
                $today = new \DateTime('today');
                if ($base < $today) $base = $today;
                $extended = true;
            } else {
                $base = new \DateTime('today');
            }
            $base->modify("+{$days} days");
            $exp = $base->format('Y-m-d');

            $note = 'Compra na loja (moedas): ' . $price . ' moedas, ' . $days . ' dias'
                  . ($extended ? ' (renovação)' : '');
            $ins = $pdo->prepare(
                "INSERT INTO player_grants (server_id, steam_id, nickname, type, tier, days, expiration_date, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
            );
            $ins->execute([$serverId, $steamId, $nick, $type, $tier, $days, $exp, $note]);

            $pdo->commit();

            $label = $type === 'vip' ? (string)$tier
                   : ($type === 'battlepass' ? 'BattlePass' : (self::EXTRAS[$type] ?? $type));
            BalanceLog::record((int)$player['id'], $steamId, $before, $after, 'vip_purchase', $type, $tier ?? $type,
                ($extended ? 'Renovação ' : 'Compra ') . $label . " {$days}d");

            return ['ok' => true, 'new_balance' => $after, 'expiration' => $exp, 'extended' => $extended, 'days' => $days, 'price' => $price];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[Vip::purchase] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'failed'];
        }
    }

    public static function errorMessage(string $code): string {
        return match($code) {
            'insufficient'     => 'Moedas insuficientes pra essa compra.',
            'not_for_sale'     => 'Esse plano não está à venda no momento.',
            'no_player'        => 'Faça uma compra de moedas antes (sua conta ainda não tem saldo).',
            'invalid_duration' => 'Duração inválida.',
            'invalid_tier'     => 'Tier inválido.',
            default            => 'Não consegui concluir a compra. Tente de novo.',
        };
    }
}
