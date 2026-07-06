<?php
// ============================================================
// View engine PHP puro. Sem Blade/Twig.
// Suporta layouts e sections via View::section() / View::yield().
// ============================================================

namespace App;

class View {
    private static string $viewsPath = '';
    private static array  $sections  = [];
    private static array  $stack     = [];
    private static ?string $layout    = null;
    /** Variaveis injetadas pela view filha pra mesclar no $data do layout (View::with). */
    private static array  $extraData = [];

    public static function setViewsPath(string $path): void {
        self::$viewsPath = rtrim($path, '/\\');
    }

    /**
     * Renderiza uma view. Suporta dot notation: 'pages.home' -> views/pages/home.php
     */
    public static function render(string $view, array $data = []): string {
        self::$sections  = [];
        self::$stack     = [];
        self::$layout    = null;
        self::$extraData = [];

        $content = self::renderRaw($view, $data);

        // Se a view setou um layout via extend(), renderiza o layout.
        // Quando a view filha usa section('content')...endSection(), $sections['content']
        // ja esta populado e o $content retornado eh vazio (HTML estava dentro do buffer da section).
        // Quando NAO usa section, o $content tem o HTML cru e a gente promove pra section 'content'.
        if (self::$layout !== null) {
            if (!isset(self::$sections['content']) || self::$sections['content'] === '') {
                self::$sections['content'] = $content;
            }
            // View filha pode ter usado View::with() pra setar title/description/etc
            // que so existem no escopo dela - propaga pro layout via merge (extraData wins).
            $layoutData = array_merge($data, self::$extraData);
            return self::renderRaw(self::$layout, $layoutData);
        }
        return $content;
    }

    /**
     * Exporta uma variavel da view filha pro layout (ex: title, description, og_image).
     * Sobrescreve qualquer valor com a mesma chave que o controller passou pro render().
     */
    public static function with(string $key, $value): void {
        self::$extraData[$key] = $value;
    }

    public static function display(string $view, array $data = []): void {
        echo self::render($view, $data);
    }

    /**
     * Chamado pela view filha pra definir qual layout usar.
     */
    public static function extend(string $layout): void {
        self::$layout = $layout;
    }

    /**
     * Inicia uma section nomeada. Use junto com endSection().
     */
    public static function section(string $name): void {
        self::$stack[] = $name;
        ob_start();
    }

    public static function endSection(): void {
        if (empty(self::$stack)) return;
        $name = array_pop(self::$stack);
        self::$sections[$name] = ob_get_clean();
    }

    /**
     * No layout, imprime o conteudo de uma section (ou um default).
     */
    public static function yield(string $name, string $default = ''): string {
        return self::$sections[$name] ?? $default;
    }

    /**
     * Inclui uma partial (header, footer, etc). Variaveis do escopo atual sao passadas.
     */
    public static function partial(string $view, array $data = []): void {
        echo self::renderRaw($view, $data);
    }

    private static function renderRaw(string $view, array $data): string {
        $file = self::$viewsPath . '/' . str_replace('.', '/', $view) . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("View nao encontrada: $view (esperado em $file)");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return ob_get_clean();
    }
}
