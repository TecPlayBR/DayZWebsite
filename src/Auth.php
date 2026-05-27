<?php
// ============================================================
// Auth do painel admin: login, logout, check de sessao.
// ============================================================

namespace App;

class Auth {
    private const SESSION_KEY = 'admin_user';

    public static function attempt(string $username, string $password): bool {
        $user = Database::fetchOne(
            "SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1",
            [$username]
        );
        if (!$user) return false;
        if (!password_verify($password, $user['password_hash'])) return false;

        $_SESSION[self::SESSION_KEY] = [
            'id'       => (int)$user['id'],
            'username' => $user['username'],
            'login_at' => time(),
        ];

        Database::query("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
        return true;
    }

    public static function logout(): void {
        unset($_SESSION[self::SESSION_KEY]);
        // Regenera ID da sessao pra evitar fixation
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    }

    public static function check(): bool {
        return isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY]);
    }

    public static function user(): ?array {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function requireAdmin(): void {
        if (!self::check()) {
            header('Location: /admin/login');
            exit;
        }
    }
}
