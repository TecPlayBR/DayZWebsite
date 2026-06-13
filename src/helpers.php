<?php
// ============================================================
// Funcoes globais utilitarias. Carregadas no bootstrap.
// ============================================================

use App\Lang;
use App\View;

if (!function_exists('__')) {
    function __(string $key, array $params = [], ?string $default = null): string {
        return Lang::get($key, $params, $default);
    }
}

if (!function_exists('e')) {
    // HTML escape (atalho)
    function e(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string {
        $rel = ltrim($path, '/');
        $publicAssets = dirname(__DIR__) . '/public/assets/';

        // Override de marca: se o cliente subiu pelo painel uma versão custom de
        // uma imagem (logo/favicon/background), ela fica em assets/img/custom/ —
        // pasta GITIGNORED, que NÃO é sobrescrita quando ele atualiza o template.
        // Casa por nome-base SEM extensão (logo.png -> custom/logo.{png,jpg,webp,...})
        // pra o upload poder manter a extensão real e o content-type correto.
        // Usa a custom no lugar da padrão, com cache-bust (muda quando re-upa).
        if (strncmp($rel, 'img/', 4) === 0 && strpos($rel, 'custom/') === false) {
            $stem = pathinfo($rel, PATHINFO_FILENAME);
            $customDir = $publicAssets . 'img/custom/';
            foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $e) {
                $cand = $customDir . $stem . '.' . $e;
                if (is_file($cand)) {
                    return '/assets/img/custom/' . rawurlencode($stem . '.' . $e) . '?v=' . filemtime($cand);
                }
            }
        }

        $url = '/assets/' . $rel;
        // Cache-busting via filemtime: ?v=<unix-ts> só pra CSS/JS — quando o
        // cliente edita o tema, o browser pega a versão nova em vez do cache
        // de 1 mês do .htaccess. Imagens padrão ficam sem versionar (raramente mudam).
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['css', 'js'], true)) {
            $abs = $publicAssets . $rel;
            if (is_file($abs)) {
                $url .= '?v=' . filemtime($abs);
            }
        }
        return $url;
    }
}

if (!function_exists('theme_override_tag')) {
    /**
     * Retorna <link> pro theme.override.css se ele existir; string vazia caso contrário.
     * Mecanismo de skin customizada pelo cliente sem tocar no template.
     * theme.override.css fica gitignored — cada instalação tem o seu.
     */
    function theme_override_tag(): string {
        $abs = dirname(__DIR__) . '/public/assets/css/theme.override.css';
        if (!is_file($abs)) {
            return '';
        }
        $v = filemtime($abs);
        return '<link rel="stylesheet" href="/assets/css/theme.override.css?v=' . $v . '">';
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . '/' . ltrim($path, '/');
    }
}

if (!function_exists('lang_url')) {
    // Gera URL pra trocar de idioma mantendo a rota atual
    function lang_url(string $locale): string {
        $path  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $query = $_GET;
        $query['lang'] = $locale;
        return $path . '?' . http_build_query($query);
    }
}

if (!function_exists('locale')) {
    function locale(): string {
        return Lang::current();
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): void {
        View::display($view, $data);
    }
}

if (!function_exists('partial')) {
    function partial(string $view, array $data = []): void {
        View::partial($view, $data);
    }
}
