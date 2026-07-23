<?php
// ============================================================
// Html - sanitizacao de HTML aceito do admin (paginas/novidades/ajuda).
// ============================================================
// Reconstroi o HTML a partir de um parser DOM: so tags da allowlist,
// so atributos da allowlist por tag, todo valor re-escapado, URLs de
// href/src validadas por esquema. Nao usa strip_tags/regex (fragil a
// bypass tipo <img/onerror=>, href sem aspas, java\tscript:). O corpo
// e renderizado em pagina PUBLICA, entao a defesa e obrigatoria mesmo
// vindo de admin (conta comprometida = XSS armazenado no publico).
// ============================================================

namespace App;

class Html {

    /** tag => lista de atributos permitidos naquela tag. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'a' => ['href', 'target', 'rel', 'title', 'class'],
        'img' => ['src', 'alt', 'width', 'height', 'class', 'loading'],
        'figure' => ['class'], 'figcaption' => ['class'],
        'code' => ['class'], 'pre' => ['class'], 'blockquote' => ['class'], 'hr' => [],
        'table' => ['class'], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    /** Tags cujo CONTEUDO tambem deve ser jogado fora (nao desembrulhado). */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'input', 'button', 'textarea', 'select', 'option', 'noscript', 'template', 'svg', 'math',
    ];

    /** Tags vazias (sem fechamento). */
    private const VOID = ['br', 'hr', 'img'];

    public static function sanitize(string $html): string {
        $html = trim($html);
        if ($html === '') return '';

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        // prolog xml forca parse em UTF-8 (senao DOMDocument assume latin-1 e mata acento).
        // wrapper com marcador pra localizar depois sem depender de html/body implicitos.
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div data-tecroot="1">' . $html . '</div>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = null;
        foreach ($dom->getElementsByTagName('div') as $d) {
            if ($d->getAttribute('data-tecroot') === '1') { $root = $d; break; }
        }
        if ($root === null) return '';

        return trim(self::renderChildren($root));
    }

    private static function renderChildren(\DOMNode $node): string {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::renderNode($child);
        }
        return $out;
    }

    private static function renderNode(\DOMNode $n): string {
        if ($n instanceof \DOMText) {
            // texto ja veio decodificado do DOM: re-escapa (saida segura).
            return htmlspecialchars($n->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!($n instanceof \DOMElement)) {
            return ''; // comentario, PI, etc: descarta
        }

        $tag = strtolower($n->nodeName);

        if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
            return ''; // dropa tag E conteudo
        }
        if (!isset(self::ALLOWED[$tag])) {
            // tag desconhecida (div, span, font...): desembrulha, mantem o conteudo limpo
            return self::renderChildren($n);
        }

        $attrs = '';
        foreach (self::ALLOWED[$tag] as $a) {
            if (!$n->hasAttribute($a)) continue;
            $v = $n->getAttribute($a);
            if ($a === 'href' || $a === 'src') {
                if (!self::safeUrl($v, $a === 'href')) continue;
            }
            $attrs .= ' ' . $a . '="' . htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        // rel de seguranca em <a target="_blank">
        if ($tag === 'a' && strtolower($n->getAttribute('target')) === '_blank' && stripos($attrs, ' rel=') === false) {
            $attrs .= ' rel="noopener noreferrer"';
        }

        if (in_array($tag, self::VOID, true)) {
            return '<' . $tag . $attrs . '>';
        }
        return '<' . $tag . $attrs . '>' . self::renderChildren($n) . '</' . $tag . '>';
    }

    /**
     * URL segura pra href/src. Aceita relativos (/, ./, ../, #, ?, sem esquema) e
     * http/https (mailto so em href). Normaliza espacos/controle antes de checar o
     * esquema pra pegar "java\tscript:" e afins.
     */
    private static function safeUrl(string $url, bool $isHref): bool {
        $u = trim($url);
        if ($u === '') return false;
        // remove espacos e caracteres de controle (browser ignora esses dentro da URL)
        $norm = preg_replace('/[\s\x00-\x1F\x7F]+/', '', $u);
        if ($norm === '' || $norm === null) return false;
        $first = $norm[0];
        if ($first === '/' || $first === '#' || $first === '?') return true;
        if (str_starts_with($norm, './') || str_starts_with($norm, '../')) return true;
        if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $norm, $m)) {
            $scheme = strtolower($m[1]);
            $ok = $isHref ? ['http', 'https', 'mailto'] : ['http', 'https'];
            return in_array($scheme, $ok, true);
        }
        // sem esquema e nao comeca com / => relativo (ex: "assets/img/x.png"): seguro
        return true;
    }
}
