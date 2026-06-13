<?php
// ============================================================
// Settings — acesso às configurações editáveis (tabela `settings`).
// ============================================================
// Fonte ÚNICA da verdade do que é editável + tipo de cada chave (SCHEMA).
// - Cache em memória: lê o snapshot carregado no bootstrap → ZERO query extra
//   por leitura (antes, bonus_enabled era consultado 4-5x por page load).
// - set() valida contra o whitelist e normaliza por tipo (defesa: chave fora
//   do SCHEMA é rejeitada — mata o footgun "dev adiciona campo e esquece o whitelist").
// NÃO guarda segredos (db/tokens/MP ficam no config.php). Só config editável.
// ============================================================

namespace App;

class Settings {
    /** Whitelist + tipo de cada setting editável. Adicionar setting nova = só aqui. */
    public const SCHEMA = [
        'site_name'                => 'string',
        'site_tagline'             => 'string',
        'site_tagline_enus'        => 'string',
        'server_ip'                => 'string',
        'server_port'              => 'int',
        'battlemetrics_id'         => 'string',
        'next_wipe_at'             => 'datetime',
        'wipe_label'               => 'string',
        'discord_invite'           => 'url',
        'social_discord'           => 'url',
        'social_instagram'         => 'url',
        'social_whatsapp'          => 'url',
        'social_facebook'          => 'url',
        'social_youtube'           => 'url',
        'social_tiktok'            => 'url',
        'social_twitch'            => 'url',
        'social_kick'              => 'url',
        'social_x'                 => 'url',
        'maintenance_message'      => 'string',
        'maintenance_eta'          => 'string',
        'discord_sales_webhook'    => 'url',
        'promo_coupon_code'        => 'string',
        'promo_label'              => 'string',
        // toggles (bool)
        'maintenance_enabled'      => 'bool',
        'live_purchases_enabled'   => 'bool',
        'live_purchases_anonymize' => 'bool',
        'live_purchases_show_price'=> 'bool',
        'bonus_enabled'            => 'bool',
        // Config de recompensas do leaderboard (JSON gerenciado em /admin/rewards).
        'leaderboard_rewards'      => 'json',
        // Restart do servidor: horários (BR) pra mostrar o próximo + blindar o drop.
        'restart_enabled'          => 'bool',
        'restart_times'            => 'string',  // "00:00, 04:00, 08:00, ..."
        'restart_warn_minutes'     => 'int',     // janela de aviso vermelho (default 5)
    ];

    private static array $cache = [];

    /** Recebe o snapshot já carregado no bootstrap (SELECT key,value FROM settings). */
    public static function init(array $settings): void {
        self::$cache = $settings;
    }

    public static function get(string $key, $default = null) {
        return self::$cache[$key] ?? $default;
    }

    public static function getBool(string $key, bool $default = false): bool {
        if (!array_key_exists($key, self::$cache)) return $default;
        $v = self::$cache[$key];
        return $v === '1' || $v === 1 || $v === true || $v === 'true';
    }

    public static function getInt(string $key, int $default = 0): int {
        return array_key_exists($key, self::$cache) ? (int) self::$cache[$key] : $default;
    }

    public static function all(): array {
        return self::$cache;
    }

    /**
     * A entrega in-game está ativa? (Agent ou Bot funcionando).
     * Usado pra NÃO prometer "liberação automática" quando não há quem entregue.
     * - Agent: heartbeat `agent_last_sync` nos últimos 30 min (ele sincroniza a cada ~15s).
     * - Bot: token configurado E já integrou com sucesso (`discord_integration_last_ok`).
     */
    public static function deliveryActive(): bool {
        $agent = (int) self::get('agent_last_sync', 0);
        if ($agent > 0 && (time() - $agent) < 1800) return true;
        // Entrega nativa Sparda (mod lê/grava via getcoins/postcoins)
        $sparda = (int) self::get('sparda_last_sync', 0);
        if ($sparda > 0 && (time() - $sparda) < 1800) return true;
        $token = (string) self::get('discord_integration_token', '');
        $botOk = (string) self::get('discord_integration_last_ok', '0');
        if ($token !== '' && $botOk !== '' && $botOk !== '0') return true;
        return false;
    }

    public static function isAllowed(string $key): bool {
        return isset(self::SCHEMA[$key]);
    }

    /**
     * Grava uma setting. Rejeita (retorna false) qualquer chave fora do SCHEMA.
     * Normaliza por tipo e atualiza o cache em memória.
     */
    public static function set(string $key, string $raw): bool {
        if (!isset(self::SCHEMA[$key])) return false;
        $value = self::normalize(self::SCHEMA[$key], $raw);
        Database::query(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$key, $value]
        );
        self::$cache[$key] = $value;
        return true;
    }

    private static function normalize(string $type, string $raw): string {
        $v = trim($raw);
        switch ($type) {
            case 'int':  return (string) (int) $v;
            case 'bool': return ($v === '1' || $v === 'on' || $v === 'true') ? '1' : '0';
            case 'json': json_decode($v); return json_last_error() === JSON_ERROR_NONE ? $v : '{}';
            // url/datetime/string: trim apenas (o whitelist já é a barreira de segurança;
            // ser estrito demais aqui só quebraria o form do admin com falso-negativo).
            default:     return $v;
        }
    }
}
