<?php
// ============================================================
// Releases — Novidades / Notas de Atualização (patch notes pro player).
// ============================================================
// O admin publica o que mudou (update de mod, correção, novidade). Player lê em
// /novidades — lista simples, mais recente primeiro (sem página por-item).
// Tabela: site_releases.
// ============================================================

namespace App;

class Releases {
    /** Categorias: slug => [rótulo, emoji]. */
    public const CATEGORIES = [
        'novo'        => ['Novidade',    '🆕'],
        'atualizacao' => ['Atualização', '⚙️'],
        'correcao'    => ['Correção',    '🔧'],
        'hotfix'      => ['Hotfix',      '🚑'],
    ];

    public static function catLabel(string $k): string { return self::CATEGORIES[$k][0] ?? $k; }
    public static function catEmoji(string $k): string { return self::CATEGORIES[$k][1] ?? '📢'; }
    public static function validCat(string $k): string { return isset(self::CATEGORIES[$k]) ? $k : 'atualizacao'; }

    /** Publicadas, mais recente primeiro (pra /novidades). */
    public static function published(int $limit = 100): array {
        return Database::fetchAll(
            "SELECT * FROM site_releases WHERE published = 1
              ORDER BY COALESCE(released_at, DATE(created_at)) DESC, sort_order ASC, id DESC
              LIMIT " . max(1, (int)$limit)
        );
    }

    /** Tem ao menos 1 publicada? (pra mostrar/ocultar o link na nav — template nasce vazio). */
    public static function hasAny(): bool {
        try { return (bool) Database::fetchColumn("SELECT 1 FROM site_releases WHERE published = 1 LIMIT 1"); }
        catch (\Throwable $e) { return false; }
    }

    // ---------- Admin ----------
    public static function all(): array {
        return Database::fetchAll("SELECT * FROM site_releases ORDER BY COALESCE(released_at, DATE(created_at)) DESC, id DESC");
    }
    public static function get(int $id): ?array {
        return Database::fetchOne("SELECT * FROM site_releases WHERE id = ? LIMIT 1", [$id]) ?: null;
    }
}
