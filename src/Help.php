<?php
// ============================================================
// Help - Central de Ajuda (guia/tutoriais por categoria).
// ============================================================
// Conteúdo migrado do "Começo" do Discord. Artigos com categoria, corpo HTML
// (sanitizado), vídeo do YouTube (embutido) e imagem. Editável no painel.
// ============================================================

namespace App;

class Help {
    /** Categorias fixas (ordem = ordem de exibição). slug => rótulo. */
    public const CATEGORIES = [
        'comecando' => 'Começando',
        'mecanicas' => 'Mecânicas',
        'eventos'   => 'Eventos & Áreas',
        'economia'  => 'Economia & Comércio',
        'suporte'   => 'Suporte & Políticas',
    ];

    public static function catLabel(string $key): string {
        return self::CATEGORIES[$key] ?? $key;
    }

    /** Artigos publicados agrupados por categoria (pra página /ajuda). */
    public static function publishedByCategory(): array {
        $rows = Database::fetchAll(
            "SELECT id, category, slug, title, summary, image, video_url
               FROM help_articles WHERE published = 1
              ORDER BY sort_order ASC, title ASC"
        );
        $out = [];
        foreach (array_keys(self::CATEGORIES) as $cat) $out[$cat] = [];
        foreach ($rows as $r) {
            $c = $r['category'];
            if (!isset($out[$c])) $out[$c] = [];
            $out[$c][] = $r;
        }
        return $out;
    }

    /** Artigo publicado por slug. null se não existe/oculto. */
    public static function getBySlug(string $slug): ?array {
        return Database::fetchOne(
            "SELECT * FROM help_articles WHERE slug = ? AND published = 1 LIMIT 1", [$slug]
        ) ?: null;
    }

    /** Outros artigos da mesma categoria (pra navegação no rodapé do artigo). */
    public static function siblings(string $category, int $excludeId, int $limit = 6): array {
        return Database::fetchAll(
            "SELECT slug, title FROM help_articles
              WHERE category = ? AND published = 1 AND id <> ?
              ORDER BY sort_order ASC, title ASC LIMIT ?", [$category, $excludeId, $limit]
        );
    }

    // ---------- Admin ----------
    public static function all(): array {
        return Database::fetchAll("SELECT * FROM help_articles ORDER BY category ASC, sort_order ASC, title ASC");
    }
    public static function get(int $id): ?array {
        return Database::fetchOne("SELECT * FROM help_articles WHERE id = ? LIMIT 1", [$id]) ?: null;
    }

    /** Gera um slug único a partir do título (ignora $ignoreId na checagem). */
    public static function makeSlug(string $title, int $ignoreId = 0): string {
        $base = self::slugify($title);
        if ($base === '') $base = 'artigo';
        $slug = $base; $i = 2;
        while (true) {
            $row = Database::fetchOne("SELECT id FROM help_articles WHERE slug = ? LIMIT 1", [$slug]);
            if (!$row || (int)$row['id'] === $ignoreId) return $slug;
            $slug = $base . '-' . $i++;
        }
    }

    private static function slugify(string $s): string {
        $s = mb_strtolower(trim($s));
        $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ç'=>'c'];
        $s = strtr($s, $map);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim((string)$s, '-');
    }
}
