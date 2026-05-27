<?php
// ============================================================
// i18n simples. Le lang/<locale>.php (array key=>value, suporta nested via dot).
// Locale determinado por:
//   1. ?lang=<locale> na query string (sticky via cookie)
//   2. Cookie 'lang' setado anteriormente
//   3. Accept-Language header (fallback de browser)
//   4. DEFAULT_LOCALE
// ============================================================

namespace App;

class Lang {
    private static string $langPath       = '';
    private static string $defaultLocale  = 'pt-br';
    private static array  $availableLocales = ['pt-br', 'en-us'];
    private static string $currentLocale  = '';
    private static array  $translations   = [];

    public static function init(string $langPath, string $defaultLocale = 'pt-br', array $available = ['pt-br', 'en-us']): void {
        self::$langPath        = rtrim($langPath, '/\\');
        self::$defaultLocale   = $defaultLocale;
        self::$availableLocales = $available;
        self::$currentLocale   = self::detectLocale();
        self::load();
    }

    public static function current(): string {
        return self::$currentLocale;
    }

    public static function available(): array {
        return self::$availableLocales;
    }

    /**
     * Traduz uma chave. Suporta nested: __('home.hero.title')
     * Suporta interpolacao: __('greeting', ['name' => 'Bryan'])  -> "Olá, :name" vira "Olá, Bryan"
     */
    public static function get(string $key, array $params = [], ?string $default = null): string {
        $value = self::$translations;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default ?? $key;
            }
        }
        if (!is_string($value)) return $default ?? $key;
        foreach ($params as $k => $v) {
            $value = str_replace(':' . $k, (string)$v, $value);
        }
        return $value;
    }

    private static function detectLocale(): string {
        // 1. Query string sticky
        if (isset($_GET['lang']) && in_array($_GET['lang'], self::$availableLocales, true)) {
            $picked = $_GET['lang'];
            // SameSite=Lax garante que o cookie sobrevive a redirects e a navegação top-level cross-site
            setcookie('lang', $picked, [
                'expires'  => time() + (365 * 86400),
                'path'     => '/',
                'httponly' => false,  // JS pode ler pra UI refletir o idioma
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            return $picked;
        }
        // 2. Cookie
        if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], self::$availableLocales, true)) {
            return $_COOKIE['lang'];
        }
        // 3. Accept-Language header (simplificado — pega 2 primeiras letras + tenta combinar)
        $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if (strpos($accept, 'pt') === 0) return 'pt-br';
        if (strpos($accept, 'en') === 0) return 'en-us';
        // 4. Default
        return self::$defaultLocale;
    }

    private static function load(): void {
        $file = self::$langPath . '/' . self::$currentLocale . '.php';
        if (!file_exists($file)) {
            $file = self::$langPath . '/' . self::$defaultLocale . '.php';
        }
        self::$translations = file_exists($file) ? (require $file) : [];
    }
}
