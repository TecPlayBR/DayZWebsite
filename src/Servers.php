<?php
// ============================================================
// Servers - registry de servidores DayZ.
// Modo single-server: 1 linha (id=1). UI esconde seletor.
// Modo multi-server: 2+ linhas ativas. Checkout pede escolha.
// ============================================================

namespace App;

class Servers {

    public static function all(): array {
        return Database::fetchAll("SELECT * FROM servers ORDER BY sort_order ASC, id ASC");
    }

    public static function active(): array {
        return Database::fetchAll(
            "SELECT * FROM servers WHERE active = 1 ORDER BY sort_order ASC, id ASC"
        );
    }

    public static function find(int $id): ?array {
        $row = Database::fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public static function findByToken(string $token): ?array {
        $row = Database::fetchOne("SELECT * FROM servers WHERE agent_token = ? AND active = 1", [$token]);
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array {
        $row = Database::fetchOne("SELECT * FROM servers WHERE slug = ?", [$slug]);
        return $row ?: null;
    }

    /** Site opera em modo multi-server? */
    public static function isMulti(): bool {
        $n = (int)Database::fetchColumn("SELECT COUNT(*) FROM servers WHERE active = 1");
        return $n > 1;
    }

    public static function defaultId(): int {
        $id = Database::fetchColumn(
            "SELECT id FROM servers WHERE active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1"
        );
        return (int)($id ?: 1);
    }

    public static function generateToken(): string {
        return bin2hex(random_bytes(32));
    }
}
