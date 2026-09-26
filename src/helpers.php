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

if (!function_exists('status_servidor')) {
    /**
     * Status publico do servidor principal. Uma fonte so pra home e pro /status-servidor.json.
     * CFTools manda (o BattleMetrics passou a exigir assinatura paga); BattleMetrics e reserva.
     * Os dois tem cache (60s BattleMetrics, 45s CFTools), entao chamar isto e barato.
     */
    function status_servidor(array $config): array {
        $s = \App\ServerStatus::fetch($config['settings']['battlemetrics_id'] ?? null);
        if (\App\CFTools::isConfigured()) {
            $cf = \App\CFTools::onlinePlayers();
            if ($cf !== null) {   // CFTools respondeu (mesmo vazio) = servidor ONLINE
                $s['configured'] = true;
                $s['online']     = true;
                $s['players']    = count($cf);
                $s['source']     = 'cftools';
            }
        }
        return $s;
    }
}

if (!function_exists('csp_nonce')) {
    // Nonce da CSP desta resposta. TODA tag <script> das views leva
    // o atributo nonce com csp_nonce() (tests/csp-nonce.php reprova tag sem). Sem ele o navegador
    // bloqueia o script. Nunca carimbar o nonce no HTML pronto: um <script> injetado por
    // XSS ganharia o nonce junto e a CSP deixaria de proteger.
    function csp_nonce(): string {
        return \App\Csp::nonce();
    }
}

if (!function_exists('e')) {
    // HTML escape (atalho)
    function e(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('bot_notify_token')) {
    /**
     * Token que ESTE site usa pra se identificar no bot (header X-Tecplay-Token).
     *
     * Prefere o `discord_integration_token`, que é o segredo DESTE site e de mais
     * ninguém. O `bot_token` é o segredo GLOBAL do bot, o mesmo colado no site de
     * todo cliente: quem tem o seu tem o de todos. Ele fica só como degrau de
     * convivência, pra instalação antiga não parar de notificar da noite pro dia.
     *
     * Do lado do bot, o token daqui resolve a guild sozinho, então a guild deixa de
     * vir do corpo do POST - que era o furo: a credencial não amarrava o destino.
     */
    function bot_notify_token(array $config): string {
        $proprio = trim((string) ($config['settings']['discord_integration_token'] ?? ''));
        if ($proprio !== '') return $proprio;
        return trim((string) (($config['bot']['token'] ?? '') ?: ($config['settings']['bot_token'] ?? '')));
    }
}

if (!function_exists('notify_bot_vip')) {
    /**
     * Avisa o BOT que um VIP foi concedido/revogado (sync OPCIONAL site->bot).
     * Best-effort (não bloqueia/quebra se o bot estiver fora). Mesmo padrão do mp-webhook.
     * Só dispara se: sync ligado (setting vip_sync_bot != '0', default ON) E bot configurado.
     * O bot resolve steam_id->discord_id no próprio discord_links e dá/tira o cargo VIP.
     * @param string $action 'grant' | 'revoke'
     * @param array  $info   ['tier'=>?, 'expiration_date'=>?, 'nickname'=>?, 'site_grant_id'=>?]
     */
    function notify_bot_vip(array $config, string $steamId, string $action, array $info = []): void {
        try {
            if (\App\Settings::get('vip_sync_bot', '1') === '0') return; // desligado pelo dono
        } catch (\Throwable $e) { /* setting ausente = default on */ }
        $endpoint = trim(($config['bot']['endpoint'] ?? '') ?: ($config['settings']['bot_endpoint'] ?? ''));
        $tokenB   = bot_notify_token($config); // segredo DESTE site (ver bot_notify_token)
        if ($endpoint === '' || $tokenB === '') return; // bot não integrado -> nada a sincronizar
        $payload = json_encode([
            'steam_id'        => $steamId,
            'action'          => $action,
            'tier'            => $info['tier']            ?? null,
            'expiration_date' => $info['expiration_date'] ?? null,
            'nickname'        => $info['nickname']        ?? null,
            'site_grant_id'   => $info['site_grant_id']   ?? null,
        ]);
        $ch = curl_init(rtrim($endpoint, '/') . '/notify/grant-vip');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Tecplay-Token: ' . $tokenB],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        @curl_exec($ch); // best-effort
        curl_close($ch);
    }
}

if (!function_exists('notify_bot_release')) {
    /**
     * Avisa o bot que uma NOVIDADE (release/patch note) foi publicada -> o bot posta
     * no canal channel.novidades. Gated por Settings novidades_bot (default on) +
     * bot_endpoint/token. Retorna TRUE só se o bot confirmou que POSTOU (pra o site
     * marcar announced_at e não re-postar). Best-effort: qualquer falha -> false.
     */
    function notify_bot_release(array $config, array $info = []): bool {
        try {
            if (\App\Settings::get('novidades_bot', '1') === '0') return false; // desligado pelo dono
        } catch (\Throwable $e) { /* setting ausente = default on */ }
        $endpoint = trim(($config['bot']['endpoint'] ?? '') ?: ($config['settings']['bot_endpoint'] ?? ''));
        $tokenB   = bot_notify_token($config); // segredo DESTE site (ver bot_notify_token)
        if ($endpoint === '' || $tokenB === '') return false; // bot não integrado
        $payload = json_encode([
            'site_token'   => trim((string)($config['settings']['discord_integration_token'] ?? '')), // roteia pra guild certa
            'release_id'   => $info['release_id']   ?? null,
            'title'        => $info['title']        ?? '',
            'category'     => $info['category']     ?? '',
            'cat_label'    => $info['cat_label']    ?? '',
            'cat_emoji'    => $info['cat_emoji']    ?? '',
            'version'      => $info['version']      ?? '',
            'body_excerpt' => $info['body_excerpt'] ?? '',
            'image'        => $info['hero_image']   ?? '',
            'url'          => $info['url']          ?? '',
        ]);
        $ch = curl_init(rtrim($endpoint, '/') . '/notify/release');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Tecplay-Token: ' . $tokenB],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $resp = @curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) return false;
        $j = json_decode((string) $resp, true);
        return is_array($j) && !empty($j['posted']); // só marca announced se REALMENTE postou
    }
}

if (!function_exists('release_teaser')) {
    /**
     * Resumo curto e legível de uma novidade pro Discord (embed não comporta o
     * post inteiro). Pega o texto dos primeiros parágrafos, corta no limite de
     * caracteres respeitando a palavra. Nunca joga tag/HTML cru.
     */
    function release_teaser(string $html, int $max = 300): string {
        $text = '';
        if (preg_match_all('#<p[^>]*>(.*?)</p>#is', $html, $m)) {
            foreach ($m[1] as $p) {
                $t = trim(html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $t = preg_replace('/\s+/', ' ', $t);
                if ($t === '') continue;
                $text = $text === '' ? $t : $text . ' ' . $t;
                if (mb_strlen($text) >= $max) break;
            }
        }
        if ($text === '') {
            $text = preg_replace('/\s+/', ' ', trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
        if (mb_strlen($text) > $max) {
            $cut = mb_substr($text, 0, $max);
            $sp  = mb_strrpos($cut, ' ');
            if ($sp !== false && $sp > (int)($max * 0.6)) $cut = mb_substr($cut, 0, $sp);
            $text = rtrim($cut, " ,.;:-") . '…';
        }
        return $text;
    }
}

if (!function_exists('release_hero')) {
    /** Primeira imagem do corpo, em URL absoluta (pra imagem de destaque do embed). */
    function release_hero(string $html, string $siteUrl): ?string {
        if (!preg_match('#<img[^>]+src\s*=\s*["\']([^"\']+)["\']#i', $html, $m)) return null;
        $src = trim($m[1]);
        if ($src === '') return null;
        if (preg_match('#^https?://#i', $src)) return $src;
        $base = rtrim($siteUrl, '/');
        return $src[0] === '/' ? $base . $src : $base . '/' . ltrim($src, '/');
    }
}

if (!function_exists('pending_migrations')) {
    /**
     * Migrations em /migrations que ainda NÃO foram aplicadas (não estão na tabela
     * schema_migrations). Usado pra avisar o admin que o banco está atrasado - o
     * cliente subiu os arquivos novos mas esqueceu de rodar `php cli/migrate.php`.
     *
     * Só acusa quando schema_migrations EXISTE e tem lacuna. Instalação nova (via
     * schema.sql, que já traz tudo) não cria essa tabela → retorna [] (sem alarme falso).
     * Qualquer erro também retorna [] - nunca trava o painel.
     */
    function pending_migrations(string $root): array {
        try {
            $files = glob(rtrim($root, '/\\') . '/migrations/*.sql') ?: [];
            if (!$files) return [];
            $applied = \App\Database::fetchAll("SELECT filename FROM schema_migrations");
            $done = [];
            foreach ($applied as $a) { $done[$a['filename']] = true; }
            $pending = [];
            foreach ($files as $f) {
                $name = basename($f);
                if (empty($done[$name])) $pending[] = $name;
            }
            sort($pending);
            return $pending;
        } catch (\Throwable $e) {
            return []; // schema_migrations ausente (install novo) ou erro → sem aviso
        }
    }
}

if (!function_exists('detect_image_mime')) {
    /**
     * Detecta o MIME de um arquivo de imagem de forma resiliente. Alguns hosts NÃO
     * têm a extensão PHP `fileinfo` (aí `finfo_open()` é undefined e dava fatal
     * "Call to undefined function finfo_open()" no upload). Ordem:
     *   1) finfo (ideal, quando a extensão existe);
     *   2) getimagesize (parte do GD, quase sempre presente - e ainda confirma que é
     *      uma IMAGEM de verdade, então é até mais seguro pra upload de imagem);
     *   3) mime_content_type (último recurso).
     * Retorna o MIME (ex.: 'image/png') ou null. A validação por allowlist continua
     * sendo feita por quem chama - isto só descobre o tipo.
     */
    function detect_image_mime(string $path): ?string {
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) { $m = @finfo_file($fi, $path); @finfo_close($fi); if (!empty($m)) return $m; }
        }
        $info = @getimagesize($path);
        if (!empty($info['mime'])) return $info['mime'];
        if (function_exists('mime_content_type')) { $m = @mime_content_type($path); if (!empty($m)) return $m; }
        return null;
    }
}

if (!function_exists('ensure_writable_dir')) {
    /**
     * Garante que um diretório existe E é gravável pelo PHP - inclusive em hosts que
     * rodam o PHP num usuário diferente do dono dos arquivos (enviados por FTP), onde
     * a pasta nasce sem permissão de escrita pro processo web. Cria recursivo e ESCALA
     * a permissão (0775 -> 0777) até conseguir. Retorna true se ficou gravável.
     * Centraliza o "fazer funcionar sozinho" - o cliente não precisa dar chmod na mão.
     */
    function ensure_writable_dir(string $dir): bool {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (is_dir($dir) && !is_writable($dir)) @chmod($dir, 0775);
        if (is_dir($dir) && !is_writable($dir)) @chmod($dir, 0777); // último recurso (host trava 775)
        return is_dir($dir) && is_writable($dir);
    }
}

if (!function_exists('public_dir')) {
    /**
     * Caminho absoluto da pasta PUBLICA (docroot) desta instalacao.
     *
     * Por que existe: o template era escrito assumindo que a pasta publica se
     * chama "public". Isso e verdade em parte das hospedagens, mas em conta com
     * dominio proprio (o caso mais comum em cliente) o docroot e "public_html" e
     * o conteudo de public/ mora direto dentro dele. Com o nome hardcoded, todo
     * upload do painel era gravado em <raiz>/public/..., fora do docroot: salvava
     * de verdade, o painel dizia "atualizado", e a imagem vinha quebrada porque a
     * URL /assets/... apontava pro docroot certo, onde o arquivo nao estava.
     *
     * Ordem de resolucao:
     *   1. PUBLIC_DIR, definida pelo front controller (ele sabe onde mora).
     *   2. procura, ao lado da raiz, a pasta que REALMENTE tem assets/ dentro.
     *   3. ultimo recurso: <raiz>/public.
     */
    function public_dir(): string {
        if (defined('PUBLIC_DIR') && is_dir(PUBLIC_DIR)) return PUBLIC_DIR;
        $root = dirname(__DIR__);
        foreach (['public', 'public_html', 'htdocs', 'www'] as $nome) {
            if (is_dir($root . '/' . $nome . '/assets')) return $root . '/' . $nome;
        }
        return $root . '/public';
    }
}

if (!function_exists('site_name')) {
    /**
     * Nome do site, garantido NAO vazio.
     *
     * Existe porque o padrao espalhado pelo template era
     * `$config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'`,
     * e `??` NAO cai no fallback quando o valor e string vazia — so quando e null
     * ou ausente. Um "Nome do site" apagado no painel virava texto vazio em 46
     * lugares, incluindo o descritor que aparece na fatura do cartao do jogador.
     *
     * `Settings::NUNCA_VAZIO` ja impede gravar vazio; este helper e a segunda
     * barreira, pra instalacao que ja gravou vazio ANTES do conserto (foi o caso
     * do renascerz) continuar renderizando algo em vez de um buraco.
     */
    function site_name(string $fallback = 'Servidor', ?array $cfg = null): string {
        // Aceita o array explicitamente: dentro de uma view existe um `$config`
        // LOCAL (o View faz extract), e depender do global aqui criaria a chance
        // silenciosa de ler um valor diferente do que a pagina esta usando. Hoje
        // as rotas passam o mesmo array, mas isso e coincidencia, nao contrato.
        if ($cfg === null) {
            global $config;
            $cfg = is_array($config ?? null) ? $config : [];
        }
        foreach ([$cfg['settings']['site_name'] ?? null, $cfg['site_name'] ?? null] as $v) {
            $v = trim((string) $v);
            if ($v !== '') return $v;
        }
        return $fallback;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string {
        $rel = ltrim($path, '/');
        $publicAssets = public_dir() . '/assets/';

        // Override de marca: se o cliente subiu pelo painel uma versão custom de
        // uma imagem (logo/favicon/background), ela fica em assets/img/custom/ -
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
        // Cache-busting via filemtime: ?v=<unix-ts> só pra CSS/JS - quando o
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
     * theme.override.css fica gitignored - cada instalação tem o seu.
     */
    function theme_override_tag(): string {
        $abs = public_dir() . '/assets/css/theme.override.css';
        if (!is_file($abs)) {
            return '';
        }
        $v = filemtime($abs);
        return '<link rel="stylesheet" href="/assets/css/theme.override.css?v=' . $v . '">';
    }
}

if (!function_exists('fmt_dt')) {
    /** Formata data ('Y-m-d H:i:s' ou timestamp) pra algo legível: "13 jun 2026, 04:10". */
    function fmt_dt($value, string $fallback = '-'): string {
        if (empty($value)) return $fallback;
        $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
        if (!$ts) return $fallback;
        static $m = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
        return date('j', $ts) . ' ' . $m[(int)date('n', $ts)] . ' ' . date('Y, H:i', $ts);
    }
}

if (!function_exists('time_ago')) {
    /** Tempo relativo amigável: "agora mesmo", "há 2h", "ontem", "há 3 dias"; data se antigo. */
    function time_ago($value, string $fallback = '-'): string {
        if (empty($value)) return $fallback;
        $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
        if (!$ts) return $fallback;
        $d = max(0, time() - $ts);
        if ($d < 60)      return 'agora mesmo';
        if ($d < 3600)    return 'há ' . (int)floor($d / 60) . ' min';
        if ($d < 86400)   return 'há ' . (int)floor($d / 3600) . 'h';
        if ($d < 172800)  return 'ontem';
        if ($d < 2592000) return 'há ' . (int)floor($d / 86400) . ' dias';
        return fmt_dt($value, $fallback);
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

if (!function_exists('partial')) {
    function partial(string $view, array $data = []): void {
        View::partial($view, $data);
    }
}

if (!function_exists('upload_image')) {
    /**
     * Salva uma imagem enviada (PNG/WEBP/JPG/GIF, máx 5MB) em $destDir e retorna o
     * caminho web completo (ex: /assets/img/caixas/cx_abc.png), ou null se inválida.
     * Centraliza o upload de imagem do admin (caixas + itens de caixa).
     */
    function upload_image(array $file, string $destDir, string $prefix, string $webPrefix): ?string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) return null;
        $allowed = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'];
        $mime = detect_image_mime($file['tmp_name']);
        if (!isset($allowed[$mime])) return null;
        ensure_writable_dir($destDir);
        $fname = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $dest = $destDir . '/' . $fname;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            ensure_writable_dir($destDir); // força permissão e tenta de novo
            if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
        }
        return rtrim($webPrefix, '/') . '/' . $fname;
    }
}

if (!function_exists('home_features')) {
    /**
     * Config da seção "O Que Você Vai Encontrar" da home.
     * Se o admin editou (setting `home_features` com cards), usa isso. Senão, cai
     * pros 4 cards genéricos do idioma (template padrão - instalação nova não muda).
     * Retorna ['enabled'=>bool, 'title'=>str, 'subtitle'=>str, 'cards'=>[['icon','title','text'],...]].
     */
    function home_features(): array {
        $raw = \App\Settings::get('home_features', '');
        $c = $raw ? json_decode($raw, true) : null;
        if (is_array($c) && !empty($c['cards']) && is_array($c['cards'])) {
            $cards = [];
            foreach ($c['cards'] as $card) {
                $t = trim((string)($card['title'] ?? ''));
                if ($t === '') continue;
                $cards[] = [
                    'icon'  => trim((string)($card['icon'] ?? '◆')) ?: '◆',
                    'title' => $t,
                    'text'  => trim((string)($card['text'] ?? '')),
                ];
            }
            if ($cards) {
                return [
                    'enabled'  => !isset($c['enabled']) || !empty($c['enabled']),
                    'title'    => trim((string)($c['title'] ?? '')) ?: __('features.title'),
                    'subtitle' => trim((string)($c['subtitle'] ?? '')) ?: __('features.subtitle'),
                    'cards'    => $cards,
                ];
            }
        }
        // Fallback: 4 cards genéricos do idioma (comportamento original do template).
        $icons = ['survival' => '☣', 'economy' => '⛁', 'pvp' => '⚔', 'community' => '✦'];
        $cards = [];
        foreach ($icons as $key => $icon) {
            $cards[] = [
                'icon'  => $icon,
                'title' => __('features.items.' . $key . '.title'),
                'text'  => __('features.items.' . $key . '.text'),
            ];
        }
        return ['enabled' => true, 'title' => __('features.title'), 'subtitle' => __('features.subtitle'), 'cards' => $cards];
    }
}

if (!function_exists('youtube_embed_url')) {
    /** Converte um link do YouTube (watch/youtu.be/shorts/embed) em URL de embed. null se inválido. */
    function youtube_embed_url(?string $url): ?string {
        $url = trim((string) $url);
        if ($url === '') return null;
        if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|v/))([A-Za-z0-9_-]{11})#', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        return null;
    }
}

if (!function_exists('clan_tag')) {
    /**
     * HTML do badge [TAG] clicável (leva pra página do clã) pra prefixar o nick
     * do jogador pelo site. Retorna '' se o jogador não está num clã. Cacheado.
     * Uso: <?= clan_tag($steamId) ?><?= e($nick) ?>  →  [RVH] Bryan
     */
    function clan_tag(string $steamId): string {
        if ($steamId === '') return '';
        $b = \App\Clan::badgeForPlayer($steamId);
        if (!$b) return '';
        return '<a href="/clan/' . (int)$b['id'] . '" class="clan-tag" title="Ver clã">[' . e($b['tag']) . ']</a> ';
    }
}

if (!function_exists('clan_tag_plain')) {
    /**
     * Igual clan_tag(), mas SEM <a> (só texto estilizado). Usar quando o tag fica
     * DENTRO de outro <a> (ex: lista de online no ranking) - <a> aninhado é HTML
     * invalido e o navegador quebra a estrutura (nome some). '' se sem clã.
     */
    function clan_tag_plain(string $steamId): string {
        if ($steamId === '') return '';
        $b = \App\Clan::badgeForPlayer($steamId);
        if (!$b) return '';
        return '<span class="clan-tag">[' . e($b['tag']) . ']</span> ';
    }
}

if (!function_exists('admin_campo')) {
    /**
     * Campo de formulario do ADMIN, do jeito que o Bryan pediu na 3.3.1: rotulo com
     * asterisco quando obrigatorio, dica em TEXTO visivel (nao em placeholder, que some ao
     * digitar), input com a classe .field. Um jeito so de desenhar campo, em todo formulario.
     *
     * $o: label, name, type (text|textarea|select|number|url|date|datetime-local|password|email),
     *     value, required, hint, placeholder, options ([valor => rotulo], so select), attrs (string), rows,
     *     class (classes extras do controle, ex. 'mono upper'; so letras, numeros, - e _)
     */
    function admin_campo(array $o): string {
        $name  = (string) ($o['name'] ?? '');
        $type  = (string) ($o['type'] ?? 'text');
        $id    = 'f-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
        $req   = !empty($o['required']);
        $value = (string) ($o['value'] ?? '');
        $attrs = ' ' . trim((string) ($o['attrs'] ?? '')) . ($req ? ' required data-adm-required' : '');
        $ph    = isset($o['placeholder']) ? ' placeholder="' . e((string) $o['placeholder']) . '"' : '';
        $extra = trim(preg_replace('/[^a-z0-9 _-]/i', '', (string) ($o['class'] ?? '')));
        $cls   = 'field' . ($extra !== '' ? ' ' . $extra : '');
        $rot   = '<label class="adm-label" for="' . e($id) . '">' . e((string) ($o['label'] ?? '')) . ($req ? ' <span class="adm-req" aria-hidden="true">*</span>' : '') . '</label>';
        if ($type === 'textarea') {
            $rows = (int) ($o['rows'] ?? 3);
            $ctl = '<textarea class="' . $cls . '" id="' . e($id) . '" name="' . e($name) . '" rows="' . $rows . '"' . $ph . $attrs . '>' . e($value) . '</textarea>';
        } elseif ($type === 'select') {
            $ctl = '<select class="' . $cls . '" id="' . e($id) . '" name="' . e($name) . '"' . $attrs . '>';
            foreach ((array) ($o['options'] ?? []) as $v => $l) {
                $ctl .= '<option value="' . e((string) $v) . '"' . ((string) $v === $value ? ' selected' : '') . '>' . e((string) $l) . '</option>';
            }
            $ctl .= '</select>';
        } else {
            $ctl = '<input class="' . $cls . '" type="' . e($type) . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"' . $ph . $attrs . '>';
        }
        $hint = isset($o['hint']) && (string) $o['hint'] !== '' ? '<small class="adm-hint">' . e((string) $o['hint']) . '</small>' : '';
        return '<div class="adm-campo">' . $rot . $ctl . $hint . '</div>';
    }
}

if (!function_exists('admin_campo_imagem')) {
    /**
     * Campo de IMAGEM do admin: upload primeiro (o que funciona sempre), link como alternativa,
     * previa da atual, e a dica fixa que evita o erro classico: link do Discord expira, Google
     * Drive e GitHub entregam pagina e nao imagem. Gera <input type=file name="<name>_file"> +
     * <input name="<name>"> (URL). O handler decide: arquivo enviado vence a URL.
     */
    function admin_campo_imagem(array $o): string {
        $name  = (string) ($o['name'] ?? 'image');
        $value = (string) ($o['value'] ?? '');
        $id    = 'f-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
        $req   = !empty($o['required']);
        $h  = '<div class="adm-campo adm-imagem">';
        $h .= '<label class="adm-label" for="' . e($id) . '-file">' . e((string) ($o['label'] ?? 'Imagem')) . ($req ? ' <span class="adm-req" aria-hidden="true">*</span>' : '') . '</label>';
        $h .= '<div class="adm-imagem-linha">';
        if ($value !== '') $h .= '<img class="adm-imagem-previa" src="' . e($value) . '" alt="" loading="lazy">';
        $h .= '<div class="adm-imagem-campos">';
        $h .= '<input type="file" id="' . e($id) . '-file" name="' . e($name) . '_file" accept="image/png,image/jpeg,image/webp,image/gif">';
        $h .= '<details class="adm-imagem-url"><summary>ou colar um link</summary>';
        $h .= '<input class="field mono" type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '" placeholder="/assets/img/... ou https://...">';
        $h .= '</details></div></div>';
        $h .= '<small class="adm-hint">Envie do seu computador (PNG, JPG, WEBP ou GIF, até 5 MB). Se usar link, tem que ser link direto e permanente: Discord expira, Google Drive e GitHub não servem.</small>';
        $h .= '</div>';
        return $h;
    }
}

if (!function_exists('csv_seguro')) {
    /**
     * Neutraliza injecao de formula em CSV: valor que comeca com =, +, - ou @ vira formula
     * no Excel/LibreOffice ao abrir. Um apostrofo na frente faz ele ser lido como texto.
     */
    function csv_seguro($v): string {
        $s = (string) $v;
        return ($s !== '' && strpbrk($s[0], '=+-@') !== false) ? "'" . $s : $s;
    }
}

if (!function_exists('clan_tag_cf')) {
    /** Igual clan_tag(), mas pelo cftools_id (ranking de gameplay). '' se sem clã. */
    function clan_tag_cf(?string $cftoolsId): string {
        $cftoolsId = trim((string) $cftoolsId);
        if ($cftoolsId === '') return '';
        $b = \App\Clan::badgeByCftools($cftoolsId);
        if (!$b) return '';
        return '<a href="/clan/' . (int)$b['id'] . '" class="clan-tag" title="Ver clã">[' . e($b['tag']) . ']</a> ';
    }
}
