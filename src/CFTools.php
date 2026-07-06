<?php
// ============================================================
// CFTools Cloud - Data API (cliente PHP). Mesmo padrão de ServerStatus/MercadoPago.
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

    /**
     * Invalida TODO o cache do CFTools (token, lookups, leaderboard, player).
     * Chamar quando a config do CFTools muda no admin - senão o token/lookup
     * VELHO (cache 23h/7d) continua valendo contra o app NOVO e a integracao
     * "nao funciona" ate alguem apagar storage/cache na mao. Retorna nº de arquivos.
     */
    public static function clearCache(): int {
        if (!self::$cacheDir || !is_dir(self::$cacheDir)) return 0;
        $n = 0;
        foreach ((glob(self::$cacheDir . '/cftools-*.json') ?: []) as $f) {
            if (@unlink($f)) $n++;
        }
        return $n;
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

    /** POST autenticado (1 retry se token expirou). Retorna o array de resposta http() cru. */
    private static function authPost(string $path, array $body): ?array {
        $token = self::token();
        if (!$token) return null;
        $r = self::http('POST', $path, [], $body, $token);
        if ($r['code'] === 403 && (($r['data']['error'] ?? '') === 'expired-token')) {
            self::cachePut('token', []);
            $token = self::token();
            if (!$token) return null;
            $r = self::http('POST', $path, [], $body, $token);
        }
        return $r;
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

    /** Stats individuais (v2). Cache 5min (refresca o perfil mais rápido). */
    public static function player(string $cftoolsId): ?array {
        if (!self::isConfigured()) return null;
        $ck = 'player-' . $cftoolsId;
        $c = self::cacheGet($ck, 300);
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
        // Playtime/sessões NÃO ficam em game.dayz - ficam em omega (validado live).
        $omega = is_array($resp[$cid]['omega'] ?? null) ? $resp[$cid]['omega'] : [];

        $k     = is_array($dayz['kills'] ?? null) ? $dayz['kills'] : [];
        $sh    = is_array($dayz['shots'] ?? null) ? $dayz['shots'] : [];
        $hit   = (int)($sh['hit'] ?? 0);
        $fired = (int)($sh['fired'] ?? 0);
        return [
            'cftools_id'       => $cid,
            'kills'            => (int)($k['players'] ?? 0),
            'deaths'           => (int)($dayz['deaths'] ?? 0),
            'kdratio'          => (float)($dayz['kdratio'] ?? 0),
            'playtime_seconds' => (int)($omega['playtime'] ?? 0),
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
                'sessions'      => (int)($omega['sessions'] ?? 0),
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

    /**
     * Players online AGORA no servidor (GET /GSM/list). Cache 45s.
     * Normaliza pro essencial: steam_id, nome in-game, avatar, ping, desde quando.
     * Contrato validado live: { sessions: [ { gamedata:{player_name,steam64},
     *   persona:{profile:{avatar,name}}, live:{ping:{actual}}, created_at, id } ], status }
     */
    public static function onlinePlayers(): ?array {
        if (!self::isConfigured()) return null;
        $c = self::cacheGet('online', 45);
        if ($c !== null) return $c['rows'];
        $d = self::authGet('/v1/server/' . self::$serverApiId . '/GSM/list');
        $sessions = $d['sessions'] ?? null;
        if (!is_array($sessions)) return null;
        $rows = [];
        foreach ($sessions as $s) {
            $rows[] = [
                'steam_id'    => (string)($s['gamedata']['steam64'] ?? ''),
                'name'        => (string)($s['gamedata']['player_name'] ?? ($s['persona']['profile']['name'] ?? '?')),
                'avatar'      => $s['persona']['profile']['avatar'] ?? null,
                'ping'        => $s['live']['ping']['actual'] ?? null,
                'since'       => $s['created_at'] ?? null,
                'gamesession' => (string)($s['id'] ?? ''),
            ];
        }
        self::cachePut('online', ['rows' => $rows]);
        return $rows;
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

    // ---------- GameLabs (drop de item / ações no jogo) ----------

    /** Schema de uma action GameLabs (cache 24h - lista raramente muda + rate limit baixo). */
    private static function gameLabsAction(string $code): ?array {
        $c = self::cacheGet('gamelabs-actions', 86400);
        if (!$c || empty($c['actions'])) {
            $d = self::authGet('/v1/server/' . self::$serverApiId . '/GameLabs/actions');
            $list = $d['available_actions'] ?? null;
            if (!is_array($list)) return null;
            $c = ['actions' => $list];
            self::cachePut('gamelabs-actions', $c);
        }
        foreach ($c['actions'] as $a) {
            if (($a['actionCode'] ?? '') === $code) return $a;
        }
        return null;
    }

    /**
     * Dropa um item no personagem do jogador (precisa estar ONLINE).
     * Validado live: CFCloud_SpawnPlayerItem + quantity>=1 + referenceKey=SteamID64.
     * Retorna true se a API aceitou (204). NÃO garante que o player estava online -
     * cheque isOnline() antes pra decidir entre dropar agora ou enfileirar.
     */
    public static function spawnPlayerItem(string $steamId64, string $classname, int $qty = 1, bool $stacked = false): bool {
        if (!self::isConfigured() || $classname === '') return false;
        $action = self::gameLabsAction('CFCloud_SpawnPlayerItem');
        if (!$action || empty($action['parameters'])) return false;
        $p = $action['parameters'];
        if (isset($p['item']))     $p['item']['valueString']     = $classname;
        if (isset($p['quantity'])) $p['quantity']['valueInt']    = max(1, $qty);
        if (isset($p['stacked']))  $p['stacked']['valueBoolean'] = $stacked ? 1 : 0;
        if (isset($p['debug']))    $p['debug']['valueBoolean']   = 0;
        // CRÍTICO: o schema veio do cache (round-trip JSON), então 'options: {}' do
        // CFTools virou 'options: []' (array) no PHP. O mod GameLabs pode ignorar o
        // parâmetro quando 'options' não é objeto -> quantity = 0 -> 204 SEM drop.
        // Força objeto vazio pra bater com o payload que funcionou no teste manual.
        foreach ($p as $k => $param) {
            if (isset($param['options']) && is_array($param['options']) && empty($param['options'])) {
                $param['options'] = new \stdClass();
                $p[$k] = $param;
            }
        }
        $r = self::authPost('/v1/server/' . self::$serverApiId . '/GameLabs/action', [
            'actionCode'    => 'CFCloud_SpawnPlayerItem',
            'actionContext' => 'player',
            'referenceKey'  => $steamId64,
            'parameters'    => $p,
        ]);
        if ($r === null) return false;
        if (in_array((int)$r['code'], [200, 204], true)) return true;
        error_log('[CFTools] spawnPlayerItem -> HTTP ' . $r['code'] . ' ' . ($r['data']['error'] ?? $r['error']));
        return false;
    }

    /** O SteamID64 está online AGORA no servidor? (usa o cache de onlinePlayers, 45s). */
    public static function isOnline(string $steamId64): bool {
        $rows = self::onlinePlayers();
        if (!is_array($rows)) return false;
        foreach ($rows as $r) {
            if (($r['steam_id'] ?? '') === $steamId64) return true;
        }
        return false;
    }
}
