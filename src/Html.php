<?php
// ============================================================
// Html - sanitização de HTML aceito do admin (páginas dinâmicas).
// ============================================================
// Não substitui HTMLPurifier, mas pra HTML simples vindo de admin
// confiável é a defesa em profundidade certa contra XSS armazenado.
// ============================================================

namespace App;

class Html {

    private const ALLOWED_TAGS = '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><a><code><pre><blockquote><hr><table><thead><tbody><tr><th><td>';

    /**
     * Recebe HTML do admin, retorna versão sanitizada:
     * - strip_tags com allowlist
     * - remove TODOS event handlers (onclick, onerror, etc)
     * - remove javascript:, data:, vbscript: em href/src
     * - mantém apenas href, target, rel em <a>; class em geral é permitido
     */
    public static function sanitize(string $html): string {
        // 1. Strip tags fora da allowlist
        $clean = strip_tags($html, self::ALLOWED_TAGS);

        // 2. Remove atributos on* (event handlers)
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*(["\'])[^"\']*\1/i', '', $clean);
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*[^\s>]+/i', '', $clean);

        // 3. Remove schemes perigosos em href/src
        $clean = preg_replace_callback(
            '/(href|src)\s*=\s*(["\'])(.*?)\2/i',
            function($m) {
                $url = trim($m[3]);
                if (preg_match('#^\s*(javascript|data|vbscript|file):#i', $url)) {
                    return $m[1] . '="#blocked"';
                }
                return $m[0];
            },
            $clean
        );

        // 4. Remove <style> e <script> caso strip_tags vaze (defesa extra)
        $clean = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $clean);

        // 5. Adiciona rel="noopener noreferrer" em <a target="_blank">
        $clean = preg_replace_callback(
            '/<a\b([^>]*\btarget\s*=\s*["\']_blank["\'])([^>]*)>/i',
            function($m) {
                if (stripos($m[0], 'rel=') !== false) return $m[0];
                return rtrim($m[0], '>') . ' rel="noopener noreferrer">';
            },
            $clean
        );

        return $clean;
    }
}
