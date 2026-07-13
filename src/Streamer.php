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

    /** Quantos players estao vinculados a esse streamer (codigo OU cupom vinculado). */
    public static function supporterCount(array $s): int
    {
        if (!self::available()) return 0;
        $codes = [strtoupper((string) $s['code'])];
        $cc = strtoupper(trim((string) ($s['coupon_code'] ?? '')));
        if ($cc !== '' && $cc !== $codes[0]) $codes[] = $cc;
        $ph = implode(',', array_fill(0, count($codes), '?'));
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM players WHERE affiliate_coupon_code IS NOT NULL AND UPPER(affiliate_coupon_code) IN ($ph)",
            $codes
        );
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

    /** Plataformas sociais suportadas (chave => label). Ordem = ordem de exibicao. */
    public const SOCIAL_PLATFORMS = [
        'youtube'   => 'YouTube',
        'twitch'    => 'Twitch',
        'kick'      => 'Kick',
        'discord'   => 'Discord',
        'instagram' => 'Instagram',
        'tiktok'    => 'TikTok',
        'twitter'   => 'X (Twitter)',
        'facebook'  => 'Facebook',
    ];

    /** Redes preenchidas do streamer: [key => ['label'=>, 'url'=>]] (so as que tem URL). */
    public static function socials(array $s): array
    {
        $out = [];
        $raw = $s['socials_json'] ?? '';
        $d = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
        if (is_array($d)) {
            foreach (self::SOCIAL_PLATFORMS as $key => $label) {
                $u = trim((string) ($d[$key] ?? ''));
                if ($u !== '') $out[$key] = ['label' => $label, 'url' => $u];
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
