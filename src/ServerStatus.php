<?php
// ============================================================
// ServerStatus - consulta status do servidor DayZ via BattleMetrics.
// ============================================================
// BattleMetrics indexa servidores Steam (incluindo DayZ) e expoe via
// API REST publica gratuita: https://www.battlemetrics.com/developers
//
// Pra usar:
//   1. Cliente cadastra/encontra servidor dele em battlemetrics.com
//   2. Copia o ID numerico da URL (ex: 12345678)
//   3. Cola em Admin -> Settings -> battlemetrics_id
//
// Cache: 60s. BM tem rate limit de 60 req/min - esse cache nos da
// folga e melhora MUITO a latencia do hero.
// ============================================================

namespace App;

class ServerStatus {
    private const API_URL = 'https://api.battlemetrics.com/servers/';
    private const CACHE_TTL = 60; // segundos
    private static string $cacheDir = '';

    public static function init(string $storageDir): void {
        self::$cacheDir = rtrim($storageDir, '/\\') . '/server-status';
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
    }

    /**
     * Retorna info do servidor. Estrutura:
     *   ['configured' => bool, 'online' => bool, 'players' => int, 'max' => int, 'name' => str, 'rank' => int, 'map' => str, 'fetched_at' => ts]
     * Em caso de erro: ['configured' => true, 'online' => false, ...]
     * Quando nao configurado: ['configured' => false]
     */
    public static function fetch(?string $battlemetricsId): array {
        if (empty($battlemetricsId) || !preg_match('/^\d+$/', $battlemetricsId)) {
            return ['configured' => false];
        }

        // Tenta cache primeiro
        $cached = self::readCache($battlemetricsId);
        if ($cached !== null) return $cached;

        // Chama BM
        $url = self::API_URL . $battlemetricsId;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_USERAGENT      => 'Tecplay/1.0 (+https://tecplay.inf.br)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            // Falha - retorna estado vazio mas configurado (UI mostra "Verificando..." ou "Offline")
            $result = [
                'configured' => true, 'online' => false,
                'players' => 0, 'max' => 0,
                'name' => null, 'rank' => null, 'map' => null,
                'fetched_at' => time(),
                'error' => "HTTP $code",
            ];
            self::writeCache($battlemetricsId, $result, 30); // cache curto em erro
            return $result;
        }

        $data = json_decode($resp, true);
        $attr = $data['data']['attributes'] ?? [];
        $details = $attr['details'] ?? [];

        $result = [
            'configured' => true,
            'online'     => ($attr['status'] ?? '') === 'online',
            'players'    => (int)($attr['players']    ?? 0),
            'max'        => (int)($attr['maxPlayers'] ?? 0),
            'name'       => $attr['name']             ?? null,
            'rank'       => isset($attr['rank']) ? (int)$attr['rank'] : null,
            'map'        => $details['map']           ?? null,
            'ip'         => $attr['ip']               ?? null,
            'port'       => $attr['port']             ?? null,
            'fetched_at' => time(),
        ];
        self::writeCache($battlemetricsId, $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * Busca lista de players atualmente online no servidor.
     * Retorna: array de ['name' => str, 'connected_at' => ts, 'playtime_seconds' => int]
     * Cache: 60s (mesmo TTL do status principal).
     */
    public static function fetchPlayers(?string $battlemetricsId, int $limit = 50): array {
        if (empty($battlemetricsId) || !preg_match('/^\d+$/', $battlemetricsId)) return [];

        $cacheKey = $battlemetricsId . '_players';
        $cached = self::readCache($cacheKey);
        if ($cached !== null) return $cached['players'] ?? [];

        // BM endpoint: /servers/{id}/relationships/players?filter[online]=true
        $url = self::API_URL . $battlemetricsId
             . '/relationships/players?filter%5Bonline%5D=true&page%5Bsize%5D=' . $limit;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_USERAGENT      => 'Tecplay/1.0 (+https://tecplay.inf.br)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            self::writeCache($cacheKey, ['players' => []], 30);
            return [];
        }

        $data = json_decode($resp, true);
        $players = [];
        foreach (($data['data'] ?? []) as $row) {
            $attr = $row['attributes'] ?? [];
            $players[] = [
                'name'         => $attr['name'] ?? 'Anônimo',
                'connected_at' => isset($attr['createdAt']) ? strtotime($attr['createdAt']) : null,
            ];
        }
        self::writeCache($cacheKey, ['players' => $players], self::CACHE_TTL);
        return $players;
    }

    private static function readCache(string $id): ?array {
        if (!self::$cacheDir) return null;
        $file = self::$cacheDir . '/' . $id . '.json';
        if (!is_file($file)) return null;
        $raw = @file_get_contents($file);
        $data = @json_decode($raw, true);
        if (!is_array($data) || empty($data['_expires_at'])) return null;
        if (time() > $data['_expires_at']) return null;
        unset($data['_expires_at']);
        return $data;
    }

    private static function writeCache(string $id, array $data, int $ttl): void {
        if (!self::$cacheDir) return;
        $data['_expires_at'] = time() + $ttl;
        @file_put_contents(self::$cacheDir . '/' . $id . '.json', json_encode($data), LOCK_EX);
    }
}
