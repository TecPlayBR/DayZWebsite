<?php
// ============================================================
// BalanceLog - registra cada mudança de saldo de player.
// ============================================================
// Útil pra suporte ("ei admin, sumiram 100 moedas, o que aconteceu?")
// e pra auditoria/contabilidade.
// ============================================================

namespace App;

class BalanceLog {

    /**
     * Registra uma mudança de saldo.
     * Calcula delta automaticamente baseado em before/after.
     */
    public static function record(
        int $playerId,
        string $steamId,
        int $balanceBefore,
        int $balanceAfter,
        string $source,
        ?string $refType = null,
        $refId = null,
        ?string $notes = null
    ): void {
        try {
            $delta = $balanceAfter - $balanceBefore;
            if ($delta === 0) return; // sem mudança, não loga

            Database::query(
                "INSERT INTO balance_log (player_id, steam_id, delta, balance_before, balance_after,
                                          source, ref_type, ref_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $playerId, $steamId, $delta, $balanceBefore, $balanceAfter,
                    $source, $refType, $refId === null ? null : (string)$refId, $notes,
                ]
            );
        } catch (\Throwable $e) {
            error_log("[BalanceLog] " . $e->getMessage());
        }
    }
}
