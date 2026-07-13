<?php

namespace App;

/**
 * Streamer: entidade propria (codigo de apoio separado do cupom de desconto).
 * A pagina publica /streamer/<code> mostra fotos, videos e canal; o botao Apoiar
 * atrela o jogador (players.affiliate_coupon_code) ao codigo do streamer.
 */
class Streamer
{
    /** A tabela existe? (evita erro se a migration ainda nao rodou.) */
    public static function available(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                Database::fetchColumn("SELECT 1 FROM streamers LIMIT 1");
                $ok = true;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    public static function get(string $code): ?array
    {
        if (!self::available()) return null;
        $row = Database::fetchOne(
            "SELECT * FROM streamers WHERE UPPER(code) = UPPER(?) AND active = 1 LIMIT 1",
            [trim($code)]
        );
        return $row ?: null;
    }

    /** Codigo de streamer valido (pro Apoiar). */
    public static function validCode(string $code): bool
    {
        return self::get($code) !== null;
    }

    public static function allActive(): array
    {
        if (!self::available()) return [];
        return Database::fetchAll("SELECT * FROM streamers WHERE active = 1 ORDER BY sort_order, name");
    }

    /** Streamers em destaque (pra home). */
    public static function featured(): array
    {
        if (!self::available()) return [];
        return Database::fetchAll(
            "SELECT * FROM streamers WHERE active = 1 AND featured = 1 ORDER BY sort_order, name"
        );
    }

    /** Lista de URLs de foto do streamer (decodifica o JSON, sempre array). */
    public static function photos(array $s): array
    {
        $out = [];
        $raw = $s['photos_json'] ?? '';
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d)) {
                foreach ($d as $u) {
                    $u = trim((string) $u);
                    if ($u !== '') $out[] = $u;
                }
            }
        }
        return $out;
    }

    /** Lista de URLs de video. */
    public static function videos(array $s): array
    {
        $out = [];
        $raw = $s['video_urls_json'] ?? '';
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d)) {
                foreach ($d as $u) {
                    $u = trim((string) $u);
                    if ($u !== '') $out[] = $u;
                }
            }
        }
        return $out;
    }
}
