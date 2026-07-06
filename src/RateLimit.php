<?php
// ============================================================
// Rate limit baseado em sessao + IP. Sem Redis, sem cache externo.
// File-based: simples e funciona em qualquer hospedagem compartilhada.
// ============================================================

namespace App;

class RateLimit {
    private static string $dir = '';

    public static function init(string $storageDir): void {
        self::$dir = rtrim($storageDir, '/\\');
        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0755, true);
        }
    }

    /**
     * Permite N hits dentro de uma janela de segundos pra um identificador.
     * Retorna [allowed: bool, remaining: int, reset_in: int]
     */
    public static function check(string $bucket, int $maxHits, int $windowSeconds): array {
        if (!self::$dir) {
            // Sem storage = sempre permite (fallback)
            return ['allowed' => true, 'remaining' => $maxHits, 'reset_in' => 0];
        }
        $file = self::$dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $bucket) . '.json';
        $now  = time();

        $data = ['hits' => [], 'first' => $now];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $parsed = @json_decode($raw, true);
            if (is_array($parsed)) $data = $parsed;
        }

        // Remove hits fora da janela
        $cutoff = $now - $windowSeconds;
        $data['hits'] = array_values(array_filter($data['hits'] ?? [], fn($t) => $t > $cutoff));

        $count = count($data['hits']);
        if ($count >= $maxHits) {
            $oldest = min($data['hits']);
            $resetIn = max(1, ($oldest + $windowSeconds) - $now);
            return ['allowed' => false, 'remaining' => 0, 'reset_in' => $resetIn];
        }

        $data['hits'][] = $now;
        @file_put_contents($file, json_encode($data), LOCK_EX);

        return [
            'allowed'   => true,
            'remaining' => $maxHits - $count - 1,
            'reset_in'  => $windowSeconds,
        ];
    }

    public static function clearBucket(string $bucket): void {
        if (!self::$dir) return;
        $file = self::$dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $bucket) . '.json';
        @unlink($file);
    }

    /**
     * IP do cliente - à prova de spoof. X-Forwarded-For SÓ é considerado quando a
     * conexão chega de um proxy LOCAL/privado (reverse proxy na mesma máquina/rede).
     * Em hospedagem direta (cPanel/VPS), REMOTE_ADDR já é o IP real do cliente e o
     * X-Forwarded-For seria forjado por um atacante pra furar o rate-limit (cada IP
     * falso = bucket novo). Atrás de CDN/Cloudflare, ajuste conforme sua infra.
     */
    public static function clientIp(): string {
        $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // filter_var com NO_PRIV_RANGE|NO_RES_RANGE retorna false se o IP é privado/reservado.
        $remoteIsPublic = filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        if (!$remoteIsPublic && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        return $remote;
    }
}
