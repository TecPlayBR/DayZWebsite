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
        $tokenB   = trim(($config['bot']['token']    ?? '') ?: ($config['settings']['bot_token']    ?? ''));
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
        $tokenB   = trim(($config['bot']['token']    ?? '') ?: ($config['settings']['bot_token']    ?? ''));
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

if (!function_exists('asset')) {
    function asset(string $path): string {
        $rel = ltrim($path, '/');
        $publicAssets = dirname(__DIR__) . '/public/assets/';

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
        $abs = dirname(__DIR__) . '/public/assets/css/theme.override.css';
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
