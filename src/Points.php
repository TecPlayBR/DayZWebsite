<?php
namespace App;

// ============================================================
// Points — 2ª moeda do servidor ("pontos"), separada de coins.
// ============================================================
// Fase 1: ganha abrindo caixa (cada caixa define points_reward no admin).
// Fase 2: gastar na loja de pontos (spend()). Tudo logado em points_log.
// ============================================================

class Points
{
    public static function balance(string $steamId): int {
        return (int) (Database::fetchColumn("SELECT points FROM players WHERE steam_id = ? LIMIT 1", [$steamId]) ?? 0);
    }

    /** Credita pontos (cria o player se não existir). Best-effort + log. Retorna o saldo novo. */
    public static function award(string $steamId, int $amount, string $source, ?string $refType = null, $refId = null, ?string $note = null): int {
        if ($amount <= 0) return self::balance($steamId);
        $p = Database::fetchOne("SELECT id, points FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
        if ($p) {
            $old = (int) $p['points']; $pid = (int) $p['id'];
            Database::query("UPDATE players SET points = points + ? WHERE id = ?", [$amount, $pid]);
        } else {
            Database::query("INSERT INTO players (steam_id, points, origin, last_seen_at) VALUES (?, ?, 'box', NOW())", [$steamId, $amount]);
            $pid = (int) Database::pdo()->lastInsertId(); $old = 0;
        }
        $new = $old + $amount;
        self::log($pid, $steamId, $amount, $new, $source, $refType, $refId, $note);
        return $new;
    }

    /** Gasta pontos atomicamente (Fase 2 — loja). Retorna true se debitou. */
    public static function spend(string $steamId, int $amount, string $source, ?string $refType = null, $refId = null, ?string $note = null): bool {
        if ($amount <= 0) return true;
        $aff = Database::execute("UPDATE players SET points = points - ? WHERE steam_id = ? AND points >= ?", [$amount, $steamId, $amount]);
        if ($aff === 0) return false;
        $pid = (int) (Database::fetchColumn("SELECT id FROM players WHERE steam_id = ? LIMIT 1", [$steamId]) ?? 0);
        self::log($pid, $steamId, -$amount, self::balance($steamId), $source, $refType, $refId, $note);
        return true;
    }

    public static function history(string $steamId, int $limit = 20): array {
        return Database::fetchAll(
            "SELECT * FROM points_log WHERE steam_id = ? ORDER BY id DESC LIMIT " . max(1, (int) $limit),
            [$steamId]
        );
    }

    private static function log(int $pid, string $steamId, int $delta, int $balanceAfter, string $source, ?string $refType, $refId, ?string $note): void {
        try {
            Database::query(
                "INSERT INTO points_log (player_id, steam_id, delta, balance_after, source, ref_type, ref_id, note) VALUES (?,?,?,?,?,?,?,?)",
                [$pid ?: null, $steamId, $delta, $balanceAfter, $source, $refType, $refId !== null ? (string) $refId : null, $note]
            );
        } catch (\Throwable $e) { /* best-effort, não derruba a operação */ }
    }
}
