<?php
// ============================================================
// Auth do painel admin: login, logout, check de sessao, RBAC.
// ============================================================
// Roles:
//   super_admin → tudo (gerencia equipe + settings críticos)
//   finance     → dashboard, packages, combos, purchases, coupons
//   support     → dashboard, players, reviews, announcements, pages, logs
//   editor      → pages, gallery, announcements, customize
// ============================================================

namespace App;

class Auth {
    private const SESSION_KEY = 'admin_user';

    /** TTL de inatividade da sessão admin (segundos). 0 = desativado.
     *  Setado no bootstrap a partir de config['admin_session_ttl']. */
    private static int $sessionTtl = 0;

    public static function setSessionTtl(int $seconds): void {
        self::$sessionTtl = max(0, $seconds);
    }

    /** Matriz role → áreas permitidas. super_admin tem '*' (todas).
     *  IMPORTANTE: support NÃO vê dashboard nem nada com valor financeiro -
     *  só relação direta com jogadores (atendimento, moedas, reviews). */
    private const PERMISSIONS = [
        'super_admin' => ['*'],
        'finance'     => ['dashboard', 'packages', 'combos', 'purchases', 'coupons'],
        'support'     => ['players', 'reviews', 'support'],
        'editor'      => ['pages', 'gallery', 'announcements', 'customize'],
    ];

    /** Roles disponíveis pro <select> em /admin/team */
    public static function availableRoles(): array {
        return [
            'super_admin' => 'Super Admin (acesso total)',
            'finance'     => 'Financeiro (compras, pacotes, cupons, dashboard)',
            'support'     => 'Suporte (só jogadores e avaliações)',
            'editor'      => 'Editor (páginas, galeria, anúncios, visual)',
        ];
    }

    /** Página inicial pro role - usada como fallback de redirect após login
     *  pra usuários que não têm acesso ao dashboard (/admin). */
    public static function homePath(): string {
        $role = self::role();
        $homes = [
            'super_admin' => '/admin',
            'finance'     => '/admin',
            'support'     => '/admin/players',
            'editor'      => '/admin/pages',
        ];
        return $homes[$role] ?? '/admin';
    }

    public static function attempt(string $username, string $password): bool {
        $user = Database::fetchOne(
            "SELECT id, username, password_hash, role FROM admin_users WHERE username = ? LIMIT 1",
            [$username]
        );
        if (!$user) return false;
        if (!password_verify($password, $user['password_hash'])) return false;

        $_SESSION[self::SESSION_KEY] = [
            'id'            => (int)$user['id'],
            'username'      => $user['username'],
            'role'          => $user['role'] ?? 'super_admin',
            'login_at'      => time(),
            'last_activity' => time(),
        ];

        Database::query("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
        return true;
    }

    public static function logout(): void {
        unset($_SESSION[self::SESSION_KEY]);
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    }

    public static function check(): bool {
        return isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY]);
    }

    public static function user(): ?array {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function role(): string {
        return self::user()['role'] ?? '';
    }

    /** True se o role atual tem permissão pra área dada (ex: 'packages', 'players'). */
    public static function can(string $area): bool {
        $role = self::role();
        $perms = self::PERMISSIONS[$role] ?? [];
        return in_array('*', $perms, true) || in_array($area, $perms, true);
    }

    public static function requireAdmin(): void {
        if (!self::check()) {
            header('Location: /admin/login');
            exit;
        }
        // Timeout por inatividade: se passou do TTL desde a última ação, expira a sessão.
        if (self::$sessionTtl > 0) {
            $last = $_SESSION[self::SESSION_KEY]['last_activity']
                 ?? $_SESSION[self::SESSION_KEY]['login_at'] ?? time();
            if (time() - $last > self::$sessionTtl) {
                self::logout();
                header('Location: /admin/login?expired=1');
                exit;
            }
            $_SESSION[self::SESSION_KEY]['last_activity'] = time();
        }
    }

    /** Bloqueia se user não pode acessar a área. Renderiza 403 e encerra. */
    public static function requireCan(string $area): void {
        self::requireAdmin();
        if (!self::can($area)) {
            http_response_code(403);
            require dirname(__DIR__) . '/views/pages/admin_forbidden.php';
            exit;
        }
    }
}
