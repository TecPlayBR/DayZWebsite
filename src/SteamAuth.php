<?php
// ============================================================
// SteamAuth - Login via Steam OpenID 2.0.
// ============================================================
// Fluxo:
//   1. Usuario clica em /auth/steam
//   2. Redirecionamos pro Steam (steamcommunity.com/openid/login)
//   3. Usuario loga no Steam (ou ja esta logado) e autoriza
//   4. Steam volta pro nosso /auth/steam/callback com params openid.*
//   5. Validamos os params batendo de volta no Steam (anti-spoof)
//   6. Extraimos SteamID64 e salvamos na sessao ($_SESSION['steam'])
//
// Em DEV (localhost), Steam OpenID bloqueia — usa-se modo manual
// (front-end pede SteamID via prompt). Isso eh implementado no JS,
// nao aqui.
// ============================================================

namespace App;

class SteamAuth {
    private const OPENID_URL = 'https://steamcommunity.com/openid/login';
    private const SESSION_KEY = 'steam';

    /** Monta URL de redirect pro Steam OpenID 2.0 */
    public static function loginUrl(string $siteUrl, string $returnPath = '/auth/steam/callback'): string {
        $siteUrl = rtrim($siteUrl, '/');
        $params = [
            'openid.ns'         => 'http://specs.openid.net/auth/2.0',
            'openid.mode'       => 'checkid_setup',
            'openid.return_to'  => $siteUrl . $returnPath,
            'openid.realm'      => $siteUrl,
            'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ];
        return self::OPENID_URL . '?' . http_build_query($params);
    }

    /**
     * Valida o callback do Steam OpenID. Retorna o SteamID64 se ok, null se falha.
     * SEGURANCA: NUNCA confie no claimed_id direto da URL — sempre revalida no Steam.
     */
    public static function verifyCallback(array $params): ?string {
        // Confere mode esperado
        if (($params['openid_mode'] ?? '') !== 'id_res') return null;

        // Extrai SteamID do claimed_id
        $claimedId = $params['openid_claimed_id'] ?? '';
        if (!preg_match('#^https?://steamcommunity\.com/openid/id/(7656119[0-9]{10})$#', $claimedId, $m)) {
            return null;
        }
        $steamId = $m[1];

        // Re-valida com o Steam (anti-spoof: alguem podia forjar a URL de retorno)
        $verify = $params;
        $verify['openid_mode'] = 'check_authentication';
        // Reconstroi as keys que o PHP transforma em underscore
        $body = [];
        foreach ($verify as $k => $v) {
            $body[str_replace('openid_', 'openid.', $k)] = $v;
        }

        $ch = curl_init(self::OPENID_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($body),
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || strpos($resp, 'is_valid:true') === false) {
            error_log("SteamAuth verify failed: " . ($err ?: substr($resp, 0, 200)));
            return null;
        }
        return $steamId;
    }

    /** Salva SteamID na sessao */
    public static function login(string $steamId, ?string $displayName = null, ?string $avatar = null): void {
        $_SESSION[self::SESSION_KEY] = [
            'steam_id'     => $steamId,
            'display_name' => $displayName,
            'avatar'       => $avatar,
            'logged_at'    => time(),
        ];
    }

    public static function logout(): void {
        unset($_SESSION[self::SESSION_KEY]);
    }

    public static function check(): bool {
        return !empty($_SESSION[self::SESSION_KEY]['steam_id']);
    }

    public static function user(): ?array {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function steamId(): ?string {
        return $_SESSION[self::SESSION_KEY]['steam_id'] ?? null;
    }

    /**
     * Busca perfil publico via Steam Web API (precisa API key).
     * Sem API key: retorna null (front pode mostrar placeholder).
     * Pega a API key em https://steamcommunity.com/dev/apikey
     */
    public static function fetchProfile(string $steamId, ?string $apiKey): ?array {
        if (!$apiKey) return null;
        $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key='
             . urlencode($apiKey) . '&steamids=' . urlencode($steamId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) return null;
        $data = json_decode($resp, true);
        $player = $data['response']['players'][0] ?? null;
        if (!$player) return null;

        return [
            'display_name' => $player['personaname']    ?? null,
            'avatar'       => $player['avatarfull']     ?? ($player['avatar'] ?? null),
            'profile_url'  => $player['profileurl']     ?? null,
            'country'      => $player['loccountrycode'] ?? null,
        ];
    }
}
