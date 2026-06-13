<?php
// ============================================================
// CFTools Cloud — Data API (cliente PHP). Mesmo padrão de ServerStatus/MercadoPago.
// ============================================================
// O SITE consulta o CFTools direto (não depende de bot externo). Cacheia tudo em
// storage/cache pra respeitar os rate limits apertados:
//   - token (POST /v1/auth/register): 2/min, vale 24h  -> cache 23h
//   - leaderboard: 7/min                                -> cache 10min
//   - users/lookup (steam64->cftools_id): 20/min, estável -> cache 7 dias
//   - /v2/player: 120/min                               -> cache 10min
//
// Config (config.php):
//   'cftools' => ['app_id'=>'...', 'secret'=>'...', 'server_api_id'=>'...']
// Precisa de GRANT ativo do dono do recurso no dashboard do CFTools.
// ============================================================

namespace App;

class CFTools {
    private const ROOT = 'https://data.cftools.cloud';
    private static string $appId = '';
    private static string $secret = '';
    private static string $serverApiId = '';
    private static string $cacheDir = '';

    public static function init(array $cfg, string $cacheDir): void {
        self::$appId       = (string)($cfg['app_id'] ?? '');
        self::$secret      = (string)($cfg['secret'] ?? '');
        self::$serverApiId = (string)($cfg['server_api_id'] ?? '');
        self::$cacheDir    = rtrim($cacheDir, '/\\');
        if (self::$cacheDir && !is_dir(self::$cacheDir)) @mkdir(self::$cacheDir, 0755, true);
    }

    public static function isConfigured(): bool {
        return self::$appId !== '' && self::$secret !== '' && self::$serverApiId !== '';
    }

    // ---------- cache helper ----------
    private static function cacheGet(string $key, int $ttl) {
        if (!self::$cacheDir) return null;
        $f = self::$cacheDir . '/cftools-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
        if (!is_file($f) || (time() - filemtime($f)) > $ttl) return null;
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }
    private static function cachePut(string $key, $data): void {
        if (!self::$cacheDir) return;
        $f = self::$cacheDir . '/cftools-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
        @file_put_contents($f, json_encode($data), LOCK_EX);
    }

    // ---------- HTTP ----------
    private static function http(string $method, string $path, array $query = [], ?array $body = null, ?string $token = null): array {
        $url = self::ROOT . $path . ($query ? ('?' . http_build_query($query)) : '');
        $headers = ['User-Agent: ' . self::$appId . ' (DayZWebsite)']; // UA com app id é obrigatório
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
        if ($body !== null) $headers[] = 'Content-Type: application/json';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT        => 12,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err) return ['code' => 0, 'data' => null, 'error' => $err];
        $data = json_decode((string)$resp, true);
        return ['code' => $code, 'data' => is_array($data) ? $data : null, 'error' => ''];
    }

    // ---------- token (24h) ----------
    private static function token(): ?string {
        $c = self::cacheGet('token', 23 * 3600);
        if ($c && !empty($c['token'])) return $c['token'];
        $r = self::http('POST', '/v1/auth/register', [], [
            'application_id' => self::$appId,
            'secret'         => self::$secret,
        ]);
        $token = $r['data']['token'] ?? null;
        if ($token) { self::cachePut('token', ['token' => $token]); return $token; }
        error_log('[CFTools] auth falhou: HTTP ' . $r['code'] . ' ' . ($r['error'] ?: json_encode($r['data'])));
        return null;
    }

    /** GET autenticado com 1 retry se o token expirou (403 expired-token). */
    private static function authGet(string $path, array $query = []): ?array {
        $token = self::token();
        if (!$token) return null;
        $r = self::http('GET', $path, $query, null, $token);
        if ($r['code'] === 403 && (($r['data']['error'] ?? '') === 'expired-token')) {
            self::cachePut('token', []); // invalida
            $token = self::token();
            if (!$token) return null;
            $r = self::http('GET', $path, $query, null, $token);
        }
        if ($r['code'] !== 200) {
            error_log('[CFTools] GET ' . $path . ' -> HTTP ' . $r['code'] . ' ' . ($r['data']['error'] ?? $r['error']));
            return null;
        }
        return $r['data'];
    }

    // ---------- API pública ----------

    /** Steam64 -> cftools_id (cache 7 dias; mapeamento é estável). null se não achar. */
    public static function cftoolsIdForSteam(string $steamId64): ?string {
        if (!self::isConfigured()) return null;
        $ck = 'lookup-' . $steamId64;
        $c = self::cacheGet($ck, 7 * 86400);
        if ($c) return $c['cftools_id'] ?? null;
        $d = self::authGet('/v1/users/lookup', ['identifier' => $steamId64]);
        $id = $d['cftools_id'] ?? null;
        self::cachePut($ck, ['cftools_id' => $id]); // cacheia inclusive o null (evita re-bater)
        return $id;
    }

    /** Stats individuais (v2). Cache 10min. */
    public static function player(string $cftoolsId): ?array {
        if (!self::isConfigured()) return null;
        $ck = 'player-' . $cftoolsId;
        $c = self::cacheGet($ck, 600);
        if ($c) return $c;
        $d = self::authGet('/v2/server/' . self::$serverApiId . '/player', ['cftools_id' => $cftoolsId]);
        if ($d) self::cachePut($ck, $d);
        return $d;
    }

    /**
     * Stats DayZ normalizados pro nosso schema. Resolve steam64->cftools_id->/v2/player.
     * Resposta CFTools: { <cftools_id>: { game: { dayz: {...} } } } (contrato validado live).
     */
    public static function dayzStatsForSteam(string $steamId64): ?array {
        $cid = self::cftoolsIdForSteam($steamId64);
        if (!$cid) return null;
        $resp = self::player($cid);
        $dayz = $resp[$cid]['game']['dayz'] ?? null;
        if (!is_array($dayz)) return null;

        $k     = is_array($dayz['kills'] ?? null) ? $dayz['kills'] : [];
        $sh    = is_array($dayz['shots'] ?? null) ? $dayz['shots'] : [];
        $hit   = (int)($sh['hit'] ?? 0);
        $fired = (int)($sh['fired'] ?? 0);
        return [
            'cftools_id'       => $cid,
            'kills'            => (int)($k['players'] ?? 0),
            'deaths'           => (int)($dayz['deaths'] ?? 0),
            'kdratio'          => (float)($dayz['kdratio'] ?? 0),
            'playtime_seconds' => (int)($dayz['playtime'] ?? 0),
            'longest_kill_m'   => (int)($dayz['longest_kill'] ?? 0),
            'longest_shot_m'   => (int)($dayz['longest_shot'] ?? 0),
            'suicides'         => (int)($dayz['suicides'] ?? 0),
            'kills_infected'   => (int)($k['infected'] ?? 0),
            'extra'            => [
                'kills_animals' => (int)($k['animals'] ?? 0),
                'shots'         => $fired,
                'hits'          => $hit,
                'accuracy_pct'  => $fired > 0 ? round($hit / $fired * 100, 1) : 0,
                'distance_km'   => round((float)($dayz['distance_traveled'] ?? 0) / 1000, 1),
            ],
        ];
    }

    /** Busca no CFTools e faz upsert em player_stats. Retorna a linha gravada (ou null). */
    public static function syncSteam(string $steamId64): ?array {
        $s = self::dayzStatsForSteam($steamId64);
        if (!$s) return null;
        Database::query(
            "INSERT INTO player_stats
              (steam_id, cftools_id, kills, deaths, kdratio, playtime_seconds,
               longest_kill_m, longest_shot_m, suicides, kills_infected, extra_json)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               cftools_id=VALUES(cftools_id), kills=VALUES(kills), deaths=VALUES(deaths),
               kdratio=VALUES(kdratio), playtime_seconds=VALUES(playtime_seconds),
               longest_kill_m=VALUES(longest_kill_m), longest_shot_m=VALUES(longest_shot_m),
               suicides=VALUES(suicides), kills_infected=VALUES(kills_infected),
               extra_json=VALUES(extra_json)",
            [
                $steamId64, $s['cftools_id'], $s['kills'], $s['deaths'], $s['kdratio'],
                $s['playtime_seconds'], $s['longest_kill_m'], $s['longest_shot_m'],
                $s['suicides'], $s['kills_infected'], json_encode($s['extra'], JSON_UNESCAPED_UNICODE),
            ]
        );
        return Database::fetchOne("SELECT * FROM player_stats WHERE steam_id = ?", [$steamId64]);
    }

    /** Leaderboard pronto. stat: kills|deaths|suicides|playtime|longest_kill|longest_shot|kdratio. Cache 10min. */
    public static function leaderboard(string $stat, int $limit = 50): ?array {
        if (!self::isConfigured()) return null;
        $ck = 'lb-' . $stat . '-' . $limit;
        $c = self::cacheGet($ck, 600);
        if ($c) return $c['rows'] ?? null;
        $d = self::authGet('/v1/server/' . self::$serverApiId . '/leaderboard', [
            'stat' => $stat, 'order' => -1, 'limit' => max(1, min(100, $limit)),
        ]);
        $rows = $d['leaderboard'] ?? ($d['players'] ?? ($d['data'] ?? null));
        if (is_array($rows)) { self::cachePut($ck, ['rows' => $rows]); return $rows; }
        return null;
    }
}
