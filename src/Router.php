<?php
// ============================================================
// Router simples, sem dependencias.
// Suporta GET, POST. Path matching com placeholders {param}.
// ============================================================

namespace App;

class Router {
    private static array $routes = [];
    private static $notFoundHandler = null;

    public static function get(string $path, callable $handler): void {
        self::$routes['GET'][self::normalize($path)] = $handler;
    }

    public static function post(string $path, callable $handler): void {
        self::$routes['POST'][self::normalize($path)] = $handler;
    }

    public static function notFound(callable $handler): void {
        self::$notFoundHandler = $handler;
    }

    public static function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path   = self::normalize(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

        // Match exato primeiro (rapido)
        if (isset(self::$routes[$method][$path])) {
            call_user_func(self::$routes[$method][$path]);
            return;
        }

        // Match com placeholders {name}
        foreach (self::$routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';
            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                call_user_func_array($handler, array_values($params));
                return;
            }
        }

        // 404
        http_response_code(404);
        if (self::$notFoundHandler) {
            call_user_func(self::$notFoundHandler);
        } else {
            echo '404 - Not Found';
        }
    }

    private static function normalize(string $path): string {
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }
}
