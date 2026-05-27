<?php
// ============================================================
// CSRF protection. Gera token por sessao, valida em POSTs sensiveis.
// ============================================================

namespace App;

class Csrf {
    private const KEY = 'csrf_token';

    public static function token(): string {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function check(): bool {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || empty($_SESSION[self::KEY])) return false;
        return hash_equals($_SESSION[self::KEY], $sent);
    }

    public static function require(): void {
        if (!self::check()) {
            http_response_code(419);
            die('CSRF token inválido. Recarregue a página.');
        }
    }
}
