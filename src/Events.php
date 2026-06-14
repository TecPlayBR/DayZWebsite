<?php
// ============================================================
// Events - Eventos & Sorteios (página /eventos + teaser na home).
// ============================================================
// Status calculado pelas datas: 'upcoming' (antes de starts_at),
// 'active' (entre starts_at e ends_at, ou sem ends_at), 'ended' (passou ends_at).
// ============================================================

namespace App;

class Events {

    /** Todos habilitados, com status calculado. */
    public static function all(): array {
        $rows = Database::fetchAll("SELECT * FROM events WHERE enabled = 1 ORDER BY sort_order ASC, COALESCE(starts_at, created_at) DESC");
        foreach ($rows as &$e) $e['status'] = self::status($e);
        return $rows;
    }

    public static function find(string $slug): ?array {
        $e = Database::fetchOne("SELECT * FROM events WHERE slug = ? AND enabled = 1 LIMIT 1", [$slug]);
        if ($e) $e['status'] = self::status($e);
        return $e ?: null;
    }

    /** Agrupa pro display: ['active'=>[], 'upcoming'=>[], 'ended'=>[]]. */
    public static function grouped(): array {
        $g = ['active' => [], 'upcoming' => [], 'ended' => []];
        foreach (self::all() as $e) $g[$e['status']][] = $e;
        return $g;
    }

    /** Destaque pra home: 1º ativo, senão 1º próximo. null se nada. */
    public static function featured(): ?array {
        $g = self::grouped();
        return $g['active'][0] ?? ($g['upcoming'][0] ?? null);
    }

    public static function status(array $e): string {
        $now = time();
        $start = !empty($e['starts_at']) ? strtotime((string)$e['starts_at']) : null;
        $end   = !empty($e['ends_at'])   ? strtotime((string)$e['ends_at'])   : null;
        if ($start !== null && $now < $start) return 'upcoming';
        if ($end !== null && $now > $end)     return 'ended';
        return 'active';
    }

    public static function hasAny(): bool {
        return (int) Database::fetchColumn("SELECT COUNT(*) FROM events WHERE enabled = 1") > 0;
    }
}
