<?php
// ============================================================
// AuditLog - registra ações admin pra rastreabilidade.
// ============================================================

namespace App;

class AuditLog {

    /**
     * Registra uma ação. Falha silenciosa pra não afetar fluxo normal.
     *
     * @param string $action     ex: 'player.coins_changed', 'coupon.created', 'review.approved'
     * @param string $targetType ex: 'player', 'coupon', 'review', 'package'
     * @param mixed  $targetId   ex: ID numérico ou string
     * @param array  $payload    dados adicionais (before/after, etc)
     */
    public static function record(string $action, ?string $targetType = null, $targetId = null, array $payload = []): void {
        try {
            $user = Auth::user();
            $ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

            Database::query(
                "INSERT INTO audit_log (admin_user_id, admin_username, action, target_type, target_id, payload, ip, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $user['id'] ?? null,
                    $user['username'] ?? null,
                    $action,
                    $targetType,
                    $targetId === null ? null : (string)$targetId,
                    empty($payload) ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
                    $ip,
                    $ua,
                ]
            );
        } catch (\Throwable $e) {
            error_log("[AuditLog] " . $e->getMessage());
        }
    }
}
