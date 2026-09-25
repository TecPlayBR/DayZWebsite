<?php
declare(strict_types=1);

namespace App;

/**
 * Content-Security-Policy do site, com nonce por resposta.
 *
 * POR QUE AQUI E NAO NO .htaccess
 * -------------------------------
 * Nonce e um valor aleatorio NOVO a cada resposta, carimbado no cabecalho E em cada
 * <script> da pagina. O .htaccess so sabe mandar texto fixo, entao a politica mora no PHP.
 * E o .htaccess nao pode setar CSP global: o "Header always set" do Apache/LiteSpeed
 * sobrescreve o cabecalho que o PHP mandou e o nonce sumiria.
 *
 * O QUE MUDOU EM RELACAO A POLITICA ANTIGA
 * ----------------------------------------
 * A antiga tinha 'unsafe-inline' e 'unsafe-eval' no script-src. Com isso, qualquer texto que
 * escapasse pro HTML e virasse <script> executava: a CSP nao segurava XSS nenhum. Agora:
 *   - so executa <script> com o nonce desta resposta (views usam csp_nonce());
 *   - script-src-attr 'none': onclick/onerror/onsubmit no HTML nao rodam. O comportamento
 *     que morava nesses atributos foi pro app.js (data-confirm, data-filtro, data-img-falha,
 *     data-recarregar, data-copiar);
 *   - sem 'unsafe-eval': nada nosso usa eval, e o SDK do Mercado Pago monta o cardForm sem
 *     ele (testado em navegador com a chave publica real);
 *   - Chart.js saiu do jsdelivr e e servido pelo proprio site (assets/js/lib/).
 *
 * O style-src continua com 'unsafe-inline' de proposito: o template tem centenas de
 * style="..." e CSS inline nao executa codigo. O ganho de seguranca esta no script.
 *
 * CLIENTE PRECISA DE UMA ORIGEM A MAIS (um CDN, um player)?
 * O caminho e ACRESCENTAR a origem na lista abaixo, nunca voltar 'unsafe-inline'.
 */
final class Csp
{
    private static ?string $nonce = null;

    /** Nonce desta resposta: 18 bytes aleatorios em base64url (24 caracteres). */
    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        }
        return self::$nonce;
    }

    public static function politica(): string
    {
        $n = self::nonce();
        $diretivas = [
            'default-src'     => "'self'",
            'script-src'      => "'self' 'nonce-$n' https://sdk.mercadopago.com https://*.mlstatic.com",
            'script-src-attr' => "'none'",
            'style-src'       => "'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            'font-src'        => "'self' https://fonts.gstatic.com data:",
            'img-src'         => "'self' data: https:",
            'frame-src'       => 'https://www.mercadopago.com https://www.mercadopago.com.br https://mercadopago.com https://*.mercadolibre.com https://www.youtube-nocookie.com https://www.youtube.com',
            'connect-src'     => "'self' https://api.mercadopago.com https://*.mercadolibre.com https://*.mlstatic.com",
            'base-uri'        => "'self'",
            'form-action'     => "'self' https://www.mercadopago.com https://www.mercadopago.com.br",
            'frame-ancestors' => "'self'",
            'report-uri'      => '/api/csp-report.php',
            // A Hostinger punha so isto no lugar da nossa CSP; mantemos o que ela queria.
            'upgrade-insecure-requests' => '',
        ];
        $partes = [];
        foreach ($diretivas as $k => $v) $partes[] = $v === '' ? $k : "$k $v";
        return implode('; ', $partes);
    }

    /**
     * Manda a politica. Chamar antes de qualquer saida.
     *
     * Vai em DOIS cabecalhos porque a Hostinger TROCA a Content-Security-Policy que o PHP manda
     * por "upgrade-insecure-requests" (provado no staging em 26/09/2026: header() direto, com
     * replace=false, tudo trocado) e respeita a que o .htaccess seta. Entao:
     *   - X-Tecplay-Csp leva a politica e o public/.htaccess COPIA ele pra CSP e depois apaga;
     *   - Content-Security-Policy direto fica pra servidor sem .htaccess (nginx), onde vale ele.
     * Onde as duas chegam (Apache com mod_php), sao identicas e o navegador aplica a mesma coisa.
     */
    public static function enviar(): void
    {
        if (headers_sent()) return;
        $p = self::politica();
        header('Content-Security-Policy: ' . $p);
        header('X-Tecplay-Csp: ' . $p);
    }
}
