<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// ============================================================
// Front controller. Toda requisicao passa por aqui.
// ============================================================

declare(strict_types=1);

// Handler global de erros fatais — em produção mostra página customizada
// em vez do erro genérico do servidor. Erro detalhado vai pro error_log.
set_exception_handler(function (\Throwable $e) {
    error_log('[Tecplay] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    // Se o usuário é admin (sessão ativa), mostra detalhes (debug-friendly)
    $isAdmin = !empty($_SESSION['admin_user']['id']);
    // So a MENSAGEM pro admin (sem arquivo/linha/path — nao vaza estrutura do servidor).
    // O detalhe completo (file:line) ja foi pro error_log acima.
    $detail = $isAdmin ? $e->getMessage() : null;
    $errorPage = dirname(__DIR__) . '/views/pages/error_500.php';
    if (file_exists($errorPage)) {
        // Mini-bootstrap pra view renderizar standalone (não depende de Router)
        $config = $GLOBALS['__config_for_errors'] ?? [];
        include $errorPage;
    } else {
        echo '<!DOCTYPE html><html><body style="font-family:system-ui;padding:2rem;background:var(--bg-1);color:var(--bone);">';
        echo '<h1 style="color:var(--rust);">500 — Algo deu errado</h1>';
        echo '<p>Tente recarregar a página. Se persistir, contate o suporte.</p>';
        if ($detail) echo '<pre style="background:var(--bg-2);padding:1rem;color:var(--text-danger);">' . htmlspecialchars($detail) . '</pre>';
        echo '</body></html>';
    }
    exit;
});

// Em dev com `php -S` o built-in server roteia TUDO pelo router.
// Se o request bater num arquivo real (CSS, imagens, JS), retorna false
// e o servidor serve o arquivo estatico. Em producao o .htaccess do Apache
// faz a mesma coisa via RewriteCond %{REQUEST_FILENAME} -f.
if (PHP_SAPI === 'cli-server') {
    $requested = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($requested !== __DIR__ . '/' && is_file($requested)) {
        return false;
    }
}

// ============ BOOTSTRAP ============

$ROOT = dirname(__DIR__);
require $ROOT . '/src/Router.php';
require $ROOT . '/src/View.php';
require $ROOT . '/src/Lang.php';
require $ROOT . '/src/Database.php';
require $ROOT . '/src/Settings.php';
require $ROOT . '/src/CFTools.php';
require $ROOT . '/src/Auth.php';
require $ROOT . '/src/Csrf.php';
require $ROOT . '/src/RateLimit.php';
require $ROOT . '/src/MercadoPago.php';
require $ROOT . '/src/SteamAuth.php';
require $ROOT . '/src/ServerStatus.php';
require $ROOT . '/src/Restart.php';
require $ROOT . '/src/Mailer.php';
require $ROOT . '/src/Coupon.php';
require $ROOT . '/src/Affiliate.php';
require $ROOT . '/src/AuditLog.php';
require $ROOT . '/src/BalanceLog.php';
require $ROOT . '/src/Achievements.php';
require $ROOT . '/src/Servers.php';
require $ROOT . '/src/Boxes.php';
require $ROOT . '/src/Rewards.php';
require $ROOT . '/src/Vip.php';
require $ROOT . '/src/ClanEvent.php';
require $ROOT . '/src/Clan.php';
require $ROOT . '/src/Help.php';
require $ROOT . '/src/Events.php';
require $ROOT . '/src/Html.php';
require $ROOT . '/src/helpers.php';

// Carrega config se existir, senao redireciona pro instalador
$configFile = $ROOT . '/config/config.php';
if (!file_exists($configFile)) {
    if (basename($_SERVER['REQUEST_URI'] ?? '') !== 'install.php') {
        header('Location: /install.php');
        exit;
    }
    // Sera servido pelo install.php direto (Apache resolve antes do rewrite)
}

$config = file_exists($configFile) ? require $configFile : [];
$GLOBALS['__config_for_errors'] = &$config; // pro handler de erro acessar

// Fuso horário do site (datas exibidas no fuso certo, não UTC). Brasil por padrão.
date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');

// Garante charset UTF-8 em todas as respostas HTML (defesa em profundidade)
header('Content-Type: text/html; charset=utf-8');
// Headers de segurança (defesa em profundidade): anti-MIME-sniffing, anti-clickjacking,
// e Referrer-Policy pra não vazar URL completa pra terceiros.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Cookies de sessao com flags de seguranca (Secure, HttpOnly, SameSite=Lax)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// error_log dedicado do template em storage/logs (fora do public).
// Se o admin já tiver setado um path customizado no php.ini, respeitamos.
$tplLogDir = $ROOT . '/storage/logs';
if (!is_dir($tplLogDir)) @mkdir($tplLogDir, 0755, true);
$currentLog = ini_get('error_log');
if ($currentLog === '' || $currentLog === 'syslog' || stripos($currentLog, 'xampp') !== false) {
    @ini_set('error_log', $tplLogDir . '/php-errors.log');
}

// Rate limit storage em /storage (fora do public)
\App\RateLimit::init($ROOT . '/storage/ratelimit');

// Cache de server status (BattleMetrics)
\App\ServerStatus::init($ROOT . '/storage/cache');

// Mailer
\App\Mailer::init(array_merge($config['mail'] ?? [], [
    'dev_log_path' => $ROOT . '/storage/cache/mail-log.txt',
]));

// DB
if (!empty($config['db'])) {
    \App\Database::init($config['db']);

    // Alinha o fuso da SESSÃO MySQL com o do PHP (offset, ex: -03:00) pra NOW()
    // gravar e exibir no mesmo fuso. Offset (não nome) p/ não depender das tz tables.
    try { \App\Database::query("SET time_zone = ?", [(new \DateTime('now'))->format('P')]); } catch (\Throwable $e) {}

    // Carrega settings do DB e injeta no $config pra views usarem
    try {
        $dbSettings = \App\Database::fetchAll("SELECT `key`, `value` FROM settings");
        foreach ($dbSettings as $s) {
            $config['settings'][$s['key']] = $s['value'];
        }
    } catch (Throwable $e) {
        // DB ainda nao tem tabela settings (instalacao incompleta) — segue
    }
}

// Settings: snapshot em memória (cache) — evita re-consultar a mesma chave N vezes
// por request e centraliza o whitelist/validação de escrita.
\App\Settings::init($config['settings'] ?? []);

// Entrega in-game ativa? (Agent/Bot detectado). Usado pra não prometer entrega
// automática quando não há entregador, e pra avisar o admin no dashboard.
$config['delivery_active'] = \App\Settings::deliveryActive();

// Próximo restart do servidor (pro badge discreto + blindagem do drop da caixa).
$config['restart'] = \App\Restart::summary();

// Timeout de inatividade da sessão admin (segundos). Default 1h.
\App\Auth::setSessionTtl((int)($config['admin_session_ttl'] ?? 3600));

// CFTools Cloud (leaderboard/stats de gameplay + drop das caixas no jogo) — consulta
// direto, com cache. Credenciais: config.php (`$config['cftools']`) TEM PRIORIDADE; se
// não definidas lá, cai pro que o admin salvou em Configurações (settings). Assim o
// dev pode fixar no config.php OU o cliente preenche no painel, sem editar PHP.
$cftoolsCfg = $config['cftools'] ?? [];
if (empty($cftoolsCfg['app_id']) || empty($cftoolsCfg['secret']) || empty($cftoolsCfg['server_api_id'])) {
    $sAppId  = (string) \App\Settings::get('cftools_app_id', '');
    $sSecret = (string) \App\Settings::get('cftools_secret', '');
    $sApiId  = (string) \App\Settings::get('cftools_server_api_id', '');
    if ($sAppId !== '' && $sSecret !== '' && $sApiId !== '') {
        $cftoolsCfg = ['app_id' => $sAppId, 'secret' => $sSecret, 'server_api_id' => $sApiId];
    }
}
\App\CFTools::init($cftoolsCfg, $ROOT . '/storage/cache');

// Sessão Steam: completa nome/foto se faltar (sessões antigas / fetch que falhou no login).
\App\SteamAuth::enrich($config['steam_api_key'] ?? null);

// i18n
\App\Lang::init(
    $ROOT . '/lang',
    $config['default_locale'] ?? 'pt-br',
    ['pt-br', 'en-us']
);

// View engine
\App\View::setViewsPath($ROOT . '/views');

// ============ ROUTES ============

// ============ MURAL DE VENDAS AO VIVO ============
// Endpoint público que retorna últimas compras aprovadas (se o admin habilitou).
// Respeita LGPD: anonimiza nome por padrão; admin pode desligar via settings.

\App\Router::get('/api/recent-purchases.json', function() use ($config) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=15'); // micro-cache evita martelar DB

    $enabled = \App\Settings::getInt('live_purchases_enabled');
    if (!$enabled) {
        echo json_encode(['enabled' => false, 'items' => []]);
        return;
    }
    $anonymize = \App\Settings::getInt('live_purchases_anonymize');
    if ($anonymize === 0) $anonymize = 0; else $anonymize = 1; // default on

    // Mostra preço? Default: NÃO (admin pode revelar)
    $showPrice = \App\Settings::getInt('live_purchases_show_price');

    $rows = \App\Database::fetchAll(
        "SELECT p.coins_total, p.price_brl, p.created_at,
                pkg.name AS package_name, pkg.icon AS package_icon,
                pl.display_name
           FROM purchases p
           LEFT JOIN packages pkg ON pkg.id = p.package_id
           LEFT JOIN players  pl  ON pl.steam_id = p.steam_id
          WHERE p.mp_status = 'approved'
            AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          ORDER BY p.created_at DESC
          LIMIT 15"
    );

    $items = [];
    foreach ($rows as $r) {
        $name = trim($r['display_name'] ?? '') ?: 'Sobrevivente';
        if ($anonymize) {
            // "BryanPaim" → "B******m"
            $len = mb_strlen($name);
            if ($len <= 2) {
                $name = mb_substr($name, 0, 1) . str_repeat('*', max(2, $len));
            } else {
                $name = mb_substr($name, 0, 1) . str_repeat('*', max(3, $len - 2)) . mb_substr($name, -1);
            }
        }
        $items[] = [
            'name'      => $name,
            'package'   => $r['package_name'] ?? 'pacote',
            'icon'      => $r['package_icon'] ?? '🪙',
            'coins'     => (int)$r['coins_total'],
            'price'     => $showPrice ? (float)$r['price_brl'] : null,
            'when'      => $r['created_at'],
            'ago_secs'  => max(0, time() - strtotime($r['created_at'])),
        ];
    }
    echo json_encode(['enabled' => true, 'items' => $items]);
});

// ============ SEO: robots.txt + sitemap.xml ============

\App\Router::get('/robots.txt', function() use ($config) {
    $siteUrl = rtrim($config['site_url'] ?? '', '/') ?: (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\n";
    echo "Disallow: /admin\n";
    echo "Disallow: /api/\n";
    echo "Disallow: /auth/\n";
    echo "Disallow: /shop/checkout\n";
    echo "Disallow: /my-purchases\n";
    echo "Allow: /\n\n";
    echo "Sitemap: {$siteUrl}/sitemap.xml\n";
});

\App\Router::get('/sitemap.xml', function() use ($config) {
    $siteUrl = rtrim($config['site_url'] ?? '', '/') ?: (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    // lastmod real das páginas de conteúdo (sinal de re-crawl pro Google) — última
    // atualização de pacote serve de proxy pra home/loja. Falha → sem lastmod (ok).
    $pkgLastmod = null;
    try { $pkgLastmod = \App\Database::fetchColumn("SELECT MAX(updated_at) FROM packages") ?: null; } catch (\Throwable $e) {}
    $urls = [
        ['/',             '1.0', 'daily',  $pkgLastmod],
        ['/shop',         '0.9', 'daily',  $pkgLastmod],
        ['/caixas',       '0.7', 'weekly'],
        ['/galeria',      '0.7', 'weekly'],
        ['/depoimentos',  '0.6', 'weekly'],
        ['/ranking',      '0.6', 'daily'],
        ['/ajuda',        '0.7', 'weekly'],
        ['/clans',        '0.6', 'weekly'],
        ['/rules',        '0.5', 'monthly'],
    ];
    if (\App\Servers::isMulti()) {
        $urls[] = ['/servidores', '0.7', 'weekly'];
    }
    // Artigos da Central de Ajuda (conteúdo de alto valor SEO: guias/tutoriais).
    try {
        foreach (\App\Database::fetchAll("SELECT slug, updated_at FROM help_articles WHERE published = 1") as $a) {
            $urls[] = ['/ajuda/' . $a['slug'], '0.6', 'monthly', $a['updated_at']];
        }
    } catch (\Throwable $e) {}
    // Páginas de clã ativas (cada clã é uma página indexável).
    try {
        foreach (\App\Database::fetchAll("SELECT id, updated_at FROM clans WHERE status = 'active'") as $c) {
            $urls[] = ['/clan/' . $c['id'], '0.4', 'weekly', $c['updated_at']];
        }
    } catch (\Throwable $e) {}
    // Páginas dinâmicas publicadas. Pula 'rules' (já listado acima como /rules) pra
    // não duplicar conteúdo (/rules vs /page/rules) — o Google penaliza duplicata.
    foreach (\App\Database::fetchAll("SELECT slug, updated_at FROM pages WHERE published = 1") as $p) {
        if ($p['slug'] === 'rules') continue;
        // /page/connect é página de alto valor SEO (IP, guia, problemas) → prioridade maior.
        $prio = $p['slug'] === 'connect' ? '0.7' : '0.5';
        $urls[] = ['/page/' . $p['slug'], $prio, 'monthly', $p['updated_at']];
    }

    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($siteUrl . $u[0], ENT_XML1) . "</loc>\n";
        if (!empty($u[3])) echo "    <lastmod>" . date('Y-m-d', strtotime($u[3])) . "</lastmod>\n";
        echo "    <changefreq>{$u[2]}</changefreq>\n";
        echo "    <priority>{$u[1]}</priority>\n";
        echo "  </url>\n";
    }
    echo '</urlset>' . "\n";
});

\App\Router::get('/', function() use ($config, $ROOT) {
    // Toca o ciclo dos eventos de clã no tráfego da home (throttled, máx 1x/2min) — assim
    // baseline/congelamento acontecem na hora SEM depender de cron (zero-config no template).
    try { \App\ClanEvent::tickThrottled($ROOT . '/storage/cache'); } catch (\Throwable $e) {}
    // Mesma ordem da loja: featured primeiro, depois sort_order — assim o teaser bate.
    $packages = \App\Database::fetchAll(
        "SELECT * FROM packages WHERE enabled = 1 ORDER BY featured DESC, sort_order ASC"
    );
    $bonusEnabled = \App\Settings::getInt('bonus_enabled');
    $serverStatus = \App\ServerStatus::fetch($config['settings']['battlemetrics_id'] ?? null);

    // Promo sazonal: idem loja, pra preço riscado bater
    $promoCode = trim($config['settings']['promo_coupon_code'] ?? '');
    $promoCoupon = null;
    if ($promoCode !== '') {
        [$c, $err] = \App\Coupon::lookup($promoCode);
        if (!$err) $promoCoupon = $c;
    }

    // Anúncios ativos: published=1 e (starts_at null OR <=now) e (ends_at null OR >now)
    $announcements = \App\Database::fetchAll(
        "SELECT * FROM announcements
          WHERE published = 1
            AND (starts_at IS NULL OR starts_at <= NOW())
            AND (ends_at   IS NULL OR ends_at   >  NOW())
          ORDER BY created_at DESC LIMIT 3"
    );
    // Social proof: stats agregados pro bloco hero. Cacheável (queries baratas mas hot path).
    $homeStats = [
        'players_total' => (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM players"),
        'purchases_week' => (int)\App\Database::fetchColumn(
            "SELECT COUNT(*) FROM purchases WHERE mp_status = 'approved' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ),
    ];
    // Testimonials no home: pega ate 3 reviews aprovadas (rating >= 4) — fonte unica de verdade,
    // mesmas reviews que aparecem em /depoimentos. Substitui o setting testimonials_json antigo.
    $homeReviews = \App\Database::fetchAll(
        "SELECT display_name, avatar, rating, body, source, created_at
           FROM reviews
          WHERE approved = 1 AND rating >= 4 AND body IS NOT NULL AND body != ''
          ORDER BY created_at DESC LIMIT 3"
    );
    \App\View::display('pages.home', [
        'config' => $config, 'packages' => $packages, 'bonus_enabled' => $bonusEnabled,
        'server_status' => $serverStatus, 'announcements' => $announcements,
        'promo_coupon' => $promoCoupon, 'home_stats' => $homeStats,
        'home_reviews' => $homeReviews,
        'featured_event' => \App\Events::featured(),
    ]);
});

\App\Router::get('/ranking', function() use ($config) {
    // Abas de gameplay (CFTools) + a aba padrão de investimento (dados do site).
    $gameplayStats = [
        'kills'          => 'Kills',
        'kills_infected' => 'Zumbis',
        'kdratio'        => 'K/D',
        'playtime'       => 'Tempo online',
        'longest_kill'   => 'Kill mais longa',
    ];
    $rewardsRaw = \App\Settings::get('leaderboard_rewards', '');
    $rewards = $rewardsRaw ? (json_decode($rewardsRaw, true) ?: []) : [];
    $cfOn = \App\CFTools::isConfigured();
    $online = $cfOn ? (\App\CFTools::onlinePlayers() ?: []) : [];
    // Aba "Clãs" só aparece pra quem tem clã (a página em si também é gated).
    $rkSid    = \App\SteamAuth::check() ? \App\SteamAuth::steamId() : null;
    $rkInClan = $rkSid ? (bool) \App\Clan::forPlayer($rkSid) : false;

    // Visibilidade das abas (admin escolhe quais aparecem em /admin/rewards). Default: todas.
    $tabsCfg = is_array($rewards['tabs'] ?? null) ? $rewards['tabs'] : [];
    $tabVis  = static fn(string $k): bool => !array_key_exists($k, $tabsCfg) || !empty($tabsCfg[$k]);
    $visGameplay   = array_filter($gameplayStats, $tabVis, ARRAY_FILTER_USE_KEY);
    $investVisible = $tabVis('invest');

    $stat = (string)($_GET['stat'] ?? 'invest');
    // Nunca renderiza aba oculta: pedido escondido/indisponível cai numa visível.
    if ($stat !== 'invest') {
        if (!$cfOn || !isset($visGameplay[$stat])) {
            $stat = $investVisible ? 'invest' : ((string) array_key_first($visGameplay) ?: 'invest');
        }
    } elseif (!$investVisible) {
        $stat = ($cfOn && $visGameplay) ? (string) array_key_first($visGameplay) : 'invest';
    }

    if ($stat !== 'invest' && isset($visGameplay[$stat]) && $cfOn) {
        $lb = \App\CFTools::leaderboard($stat, 50) ?: [];
        \App\View::display('pages.ranking', [
            'config' => $config, 'mode' => 'gameplay', 'stat' => $stat,
            'gameplay_stats' => $visGameplay, 'cftools_on' => true, 'lb' => $lb,
            'rewards' => $rewards, 'online' => $online, 'invest_visible' => $investVisible,
            'in_clan' => $rkInClan,
        ]);
        return;
    }

    $top = \App\Database::fetchAll(
        "SELECT steam_id, display_name, total_spent_brl, coins, last_seen_at
           FROM players
          WHERE total_spent_brl > 0
          ORDER BY total_spent_brl DESC
          LIMIT 50"
    );
    \App\View::display('pages.ranking', [
        'config' => $config, 'mode' => 'invest', 'top' => $top,
        'gameplay_stats' => $visGameplay, 'cftools_on' => $cfOn, 'rewards' => $rewards,
        'online' => $online, 'invest_visible' => $investVisible, 'in_clan' => $rkInClan,
    ]);
});

// Perfil público de jogador. Usa dados REAIS que já temos (players/purchases) +
// avatar Steam (cache 6h) + stats de gameplay quando o player_stats tiver (via CFTools/Bot).
\App\Router::get('/player/{steamId}', function($steamId) use ($config, $ROOT) {
    if (!preg_match('/^7656119[0-9]{10}$/', $steamId)) {
        http_response_code(404);
        \App\View::display('pages.404', ['config' => $config]);
        return;
    }
    // Perfil UNIFICADO: público pra todos; o DONO logado vê tb o bloco privado
    // (saldo/compras/caixas/loja/streamer). Financeiro só SAI do banco quando é o
    // próprio dono (LGPD: visitante nunca recebe saldo nem no HTML nem na query).
    $viewerId = \App\SteamAuth::steamId();
    $isOwner  = $viewerId !== null && $viewerId === $steamId;
    // Recompensa por conquista: reconcilia/credita (idempotente) o que o dono ainda não
    // recebeu — antes do SELECT do player, pra o saldo já refletir o bônus desta visita.
    if ($isOwner) { \App\Achievements::grantRewards($steamId); }
    $playerCols = $isOwner
        ? "id, steam_id, display_name, coins, total_spent_brl, last_seen_at"
        : "steam_id, display_name, last_seen_at";
    $player = \App\Database::fetchOne(
        "SELECT {$playerCols} FROM players WHERE steam_id = ?",
        [$steamId]
    );
    if (!$player) {
        http_response_code(404);
        \App\View::display('pages.404', ['config' => $config]);
        return;
    }
    $stats = \App\Database::fetchOne("SELECT * FROM player_stats WHERE steam_id = ?", [$steamId]);
    // Se o CFTools está configurado e o cache local está velho (>15min) ou vazio,
    // busca direto na API (que tem seu próprio cache de 10min) e atualiza o player_stats.
    $stale = !$stats || (strtotime($stats['updated_at'] ?? '1970-01-01 00:00:00') < time() - 300);
    if (\App\CFTools::isConfigured() && $stale) {
        $fresh = \App\CFTools::syncSteam($steamId);
        if ($fresh) $stats = $fresh;
    }
    if ($stats && !empty($stats['extra_json'])) {
        $stats['extra'] = json_decode($stats['extra_json'], true) ?: [];
    }
    // Avatar/nome via XML público (cache 6h em storage/cache — evita curl por view + rate-limit).
    $avatar = null;
    $name   = $player['display_name'] ?? null;
    $cacheFile = $ROOT . '/storage/cache/steam-' . $steamId . '.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 21600)) {
        $c = json_decode((string)@file_get_contents($cacheFile), true) ?: [];
        $avatar = $c['avatar'] ?? null;
        $name   = $c['display_name'] ?? $name;
    } else {
        $prof = \App\SteamAuth::fetchProfilePublic($steamId);
        if ($prof) {
            $avatar = $prof['avatar'] ?? null;
            $name   = $prof['display_name'] ?? $name;
            @file_put_contents($cacheFile, json_encode($prof));
        }
    }

    // Conquistas: públicas (aparecem pra qualquer visitante do perfil).
    $achievementsAll = \App\Achievements::all();
    $unlockedAch     = \App\Achievements::unlocked($steamId);

    $viewData = [
        'config'       => $config,
        'player'       => $player,
        'stats'        => $stats ?: null,
        'avatar'       => $avatar,
        'display_name' => $name ?: 'Sobrevivente',
        'is_owner'     => $isOwner,
        'achievements' => $achievementsAll,
        'unlocked'     => $unlockedAch,
        'clan'         => \App\Clan::forPlayer($steamId),  // público: o clã do jogador
    ];

    // Bloco PRIVADO do dono (antigo /my-purchases): compras, caixas, loja, streamer.
    if ($isOwner) {
        $viewData['clan_invites'] = \App\Clan::invitesForPlayer($steamId); // convites pra ele aceitar
        $viewData['purchases'] = \App\Database::fetchAll(
            "SELECT * FROM purchases
              WHERE steam_id = ?
                AND (mp_status <> 'pending' OR created_at > (NOW() - INTERVAL 35 MINUTE))
              ORDER BY created_at DESC LIMIT 50",
            [$steamId]
        );
        $viewData['box_openings'] = \App\Database::fetchAll(
            "SELECT * FROM box_openings WHERE steam_id = ? ORDER BY id DESC LIMIT 50", [$steamId]
        );
        $shopSpends = [];
        try {
            $shopSpends = \App\Database::fetchAll(
                "SELECT s.spend_ref, s.sku, s.coins_spent, s.new_balance, s.created_at,
                        i.name AS item_name, i.icon AS item_icon
                   FROM shop_spends s
                   LEFT JOIN shop_items i ON i.sku = s.sku
                  WHERE s.steam_id = ?
                  ORDER BY s.id DESC LIMIT 50",
                [$steamId]
            );
        } catch (\Throwable $e) { /* tabela ausente em install antigo — degrada limpo */ }
        $viewData['shop_spends'] = $shopSpends;
        // Premiações do ranking que esse player ganhou (pra ele ver que recebeu).
        $viewData['reward_payouts'] = \App\Database::fetchAll(
            "SELECT * FROM reward_payouts WHERE steam_id = ? ORDER BY id DESC LIMIT 30", [$steamId]
        );
        // Bônus de conquista creditados (mesma transparência: o player vê o que recebeu).
        $achPayouts = \App\Database::fetchAll(
            "SELECT * FROM achievement_rewards_log WHERE steam_id = ? ORDER BY id DESC LIMIT 30", [$steamId]
        );
        if ($achPayouts) {
            $achNames = [];
            foreach (\App\Achievements::all() as $a) { $achNames[$a['slug']] = $a['name']; }
            foreach ($achPayouts as &$ap) { $ap['name'] = $achNames[$ap['slug']] ?? $ap['slug']; }
            unset($ap);
        }
        $viewData['achievement_payouts'] = $achPayouts;

        $affiliateOn = \App\Affiliate::enabled();
        $myStreamerCode = $affiliateOn ? \App\Affiliate::binding($steamId) : null;
        $myStreamerName = null;
        if ($myStreamerCode) {
            $myStreamerName = \App\Database::fetchColumn(
                "SELECT affiliate_name FROM coupons WHERE UPPER(code) = UPPER(?) LIMIT 1", [$myStreamerCode]
            ) ?: $myStreamerCode;
        }
        $viewData['affiliate_on']           = $affiliateOn;
        $viewData['affiliate_allow_switch'] = \App\Affiliate::allowSwitch();
        $viewData['my_streamer_code']       = $myStreamerCode;
        $viewData['my_streamer_name']       = $myStreamerName;
        $viewData['box_claim_enabled']      = \App\Settings::getBool('box_claim_enabled');
    }

    \App\View::display('pages.player_public', $viewData);
});

\App\Router::get('/server-status', function() use ($config) {
    $bmId = $config['settings']['battlemetrics_id'] ?? null;
    $status  = \App\ServerStatus::fetch($bmId);
    $players = \App\ServerStatus::fetchPlayers($bmId, 60);

    // CFTools dá os players online em TEMPO REAL (BattleMetrics atrasa minutos).
    // Quando configurado E há alguém online, prevalece pra lista e contagem.
    if (\App\CFTools::isConfigured()) {
        $cfOnline = \App\CFTools::onlinePlayers();
        if (!empty($cfOnline)) {
            $mapped = [];
            foreach ($cfOnline as $row) {
                $since = $row['since'] ?? null;
                $connectedAt = is_numeric($since) ? (int)$since : ($since ? strtotime((string)$since) : null);
                $mapped[] = ['name' => $row['name'] ?? 'Sobrevivente', 'connected_at' => $connectedAt ?: null];
            }
            $players = $mapped;
            $status['configured'] = true;
            $status['online']     = true;
            $status['players']    = count($mapped);
            $status['source']     = 'cftools';
        }
    }

    \App\View::display('pages.server_status', [
        'config' => $config, 'status' => $status, 'players' => $players,
    ]);
});

// ============ CAIXAS / LOOTBOXES (player) ============
\App\Router::get('/caixas', function() use ($config) {
    // Entrega oportunista: aproveita o page-load pra dropar pendências de quem está online.
    try { \App\Boxes::deliverPending(20); } catch (\Throwable $e) {}

    $boxes = \App\Boxes::all();
    $steamUser = \App\SteamAuth::user();
    $coins = 0;
    $dailyReady = [];
    if ($steamUser) {
        $coins = (int)(\App\Database::fetchColumn("SELECT coins FROM players WHERE steam_id = ?", [$steamUser['steam_id']]) ?: 0);
    }
    foreach ($boxes as &$b) {
        $b['items'] = \App\Boxes::items((int)$b['id']);
        if ((int)$b['is_daily'] === 1 && $steamUser) {
            $last = \App\Boxes::lastOpening((int)$b['id'], $steamUser['steam_id']);
            $cd = max(1, (int)$b['cooldown_hours']) * 3600;
            $b['daily_wait'] = ($last !== null && (time() - $last) < $cd) ? ($cd - (time() - $last)) : 0;
        }
    }
    unset($b);
    \App\View::display('pages.caixas', [
        'config' => $config, 'boxes' => $boxes,
        'steam_user' => $steamUser, 'coins' => $coins,
    ]);
});

\App\Router::post('/caixas/{slug}/open', function($slug) use ($config) {
    header('Content-Type: application/json; charset=utf-8');
    if (!\App\SteamAuth::check()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'login', 'login_url' => '/auth/steam']);
        return;
    }
    if (!\App\Csrf::check()) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'csrf']); return; }
    $steamId = \App\SteamAuth::steamId();
    // Rate limit: no máx 30 aberturas/min por player (anti-abuso)
    $rl = \App\RateLimit::check('box-open:' . $steamId, 30, 60);
    if (empty($rl['allowed'])) { http_response_code(429); echo json_encode(['ok' => false, 'error' => 'Muito rápido. Aguarde um pouco.']); return; }

    $box = \App\Boxes::find($slug);
    if (!$box) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Caixa não encontrada.']); return; }

    try {
        $res = \App\Boxes::open($box, $steamId);
    } catch (\Throwable $e) {
        error_log('[caixas] open: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Erro ao abrir a caixa. Tente de novo.']);
        return;
    }
    if (!$res['ok']) { echo json_encode(['ok' => false, 'error' => $res['error']]); return; }

    $won = $res['won'];
    // Saldo novo + pool (pro carrossel da animação)
    $coins = (int)(\App\Database::fetchColumn("SELECT coins FROM players WHERE steam_id = ?", [$steamId]) ?: 0);
    $pool = array_map(fn($i) => [
        'name' => $i['name'], 'image' => $i['image'], 'rarity' => $i['rarity'],
    ], $box['items']);
    echo json_encode([
        'ok' => true,
        'status' => $res['status'],
        'coins' => $coins,
        'won' => [
            'name' => $won['name'], 'image' => $won['image'], 'rarity' => $won['rarity'],
            'quantity' => (int)$won['quantity'], 'classname' => $won['classname'],
        ],
        'pool' => $pool,
    ]);
});

// Cron opcional: entrega pendências (chamar via cron a cada 1-2min). Token = agent_token.
\App\Router::get('/api/deliver-boxes.php', function() use ($config) {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_GET['token'] ?? '';
    if (!hash_equals((string)($config['agent_token'] ?? ''), (string)$token)) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); return; }
    $n = \App\Boxes::deliverPending(100);
    echo json_encode(['ok' => true, 'delivered' => $n]);
});

// Cron CONSOLIDADO (opcional) — UM cron só pro template: pendências de caixa + ciclo de eventos
// de clã + refresh de stats dos participantes + premiação automática. Token = agent_token.
// Aponte o cron do host aqui a cada ~2min. SEM cron o site funciona igual (dispara no tráfego);
// o cron só deixa o placar de clã mais ao vivo e o congelamento/premiação mais pontuais.
\App\Router::get('/api/cron.php', function() use ($config) {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals((string)($config['agent_token'] ?? ''), (string)($_GET['token'] ?? ''))) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); return; }
    $out = ['ok' => true];
    try { \App\ClanEvent::tick(); } catch (\Throwable $e) {}
    try { $out['clan_stats_synced'] = \App\ClanEvent::refreshAllActive(); } catch (\Throwable $e) { $out['clan_stats_synced'] = null; }
    try { $out['boxes_delivered']   = \App\Boxes::deliverPending(100); } catch (\Throwable $e) { $out['boxes_delivered'] = null; }
    try {
        if (\App\Rewards::shouldAutoAward()) { $r = \App\Rewards::award(); $out['rewards_paid'] = count($r['paid'] ?? []); }
        else { $out['rewards_paid'] = 0; }
    } catch (\Throwable $e) { $out['rewards_paid'] = null; }
    echo json_encode($out);
});

// ============ VIP / BATTLEPASS (loja paga com moedas) ============
\App\Router::get('/vip', function() use ($config) {
    if (!\App\Vip::enabled()) { header('Location: /'); exit; }
    $serverId  = \App\Servers::defaultId();
    $steamUser = \App\SteamAuth::user();
    $coins = 0; $active = [];
    if ($steamUser) {
        $coins  = (int)(\App\Database::fetchColumn("SELECT coins FROM players WHERE steam_id = ?", [$steamUser['steam_id']]) ?: 0);
        $active = \App\Vip::activeForPlayer($serverId, $steamUser['steam_id']);
    }
    \App\View::display('pages.vip', [
        'config' => $config, 'vip' => \App\Vip::config(), 'durations' => \App\Vip::DURATIONS,
        'steam_user' => $steamUser, 'coins' => $coins, 'active' => $active,
    ]);
});

\App\Router::post('/vip/buy', function() use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /vip?err=csrf'); exit; }
    if (!\App\Vip::enabled()) { header('Location: /'); exit; }
    $steamId = \App\SteamAuth::steamId();
    $rl = \App\RateLimit::check('vip-buy:' . $steamId, 10, 60);
    if (empty($rl['allowed'])) { header('Location: /vip?err=rate'); exit; }
    $type = in_array($_POST['type'] ?? '', ['vip', 'battlepass'], true) ? $_POST['type'] : 'vip';
    $tier = $type === 'vip' ? trim($_POST['tier'] ?? '') : null;
    $days = (int)($_POST['days'] ?? 0);
    $user = \App\SteamAuth::user();
    $nick = $user['display_name'] ?? null;
    $res  = \App\Vip::purchase(\App\Servers::defaultId(), $steamId, $nick, $type, $tier, $days);
    if (!$res['ok']) { header('Location: /vip?err=' . urlencode($res['error'])); exit; }
    header('Location: /vip?ok=' . ($res['extended'] ? 'renewed' : 'bought'));
    exit;
});

// ============ CLÃS (Fase 1) ============
\App\Router::get('/clans', function() use ($config) {
    $sid = \App\SteamAuth::check() ? \App\SteamAuth::steamId() : null;
    \App\View::display('pages.clans', [
        'config' => $config, 'clans' => \App\Clan::all(),
        'my_clan' => $sid ? \App\Clan::forPlayer($sid) : null,
        'steam_user' => \App\SteamAuth::user(),
    ]);
});

\App\Router::get('/clans/new', function() use ($config) {
    if (!\App\SteamAuth::check()) { $_SESSION['steam_login_return'] = '/clans/new'; header('Location: /auth/steam'); exit; }
    $mine = \App\Clan::forPlayer(\App\SteamAuth::steamId());
    if ($mine) { header('Location: /clan/' . $mine['id']); exit; } // 1 clã por jogador
    \App\View::display('pages.clan_new', ['config' => $config]);
});

\App\Router::post('/clans/create', function() use ($config, $ROOT) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clans/new?err=csrf'); exit; }
    $steamId = \App\SteamAuth::steamId();
    $rl = \App\RateLimit::check('clan-create:' . $steamId, 5, 3600);
    if (empty($rl['allowed'])) { header('Location: /clans/new?err=rate'); exit; }
    $logo = upload_image($_FILES['logo_file'] ?? [], $ROOT . '/public/assets/img/clans', 'clan', '/assets/img/clans');
    [$id, $err] = \App\Clan::create($steamId, $_POST['name'] ?? '', $_POST['tag'] ?? '', $_POST['description'] ?? null, $_POST['discord_url'] ?? null, $logo);
    if ($err) { header('Location: /clans/new?err=' . urlencode($err)); exit; }
    header('Location: /clan/' . $id); exit;
});

\App\Router::get('/clan/{id}', function($id) use ($config) {
    $clan = \App\Clan::get((int)$id);
    if (!$clan) { header('Location: /clans'); exit; }
    $sid = \App\SteamAuth::check() ? \App\SteamAuth::steamId() : null;
    $isOwner = $sid && \App\Clan::isOwner((int)$id, $sid);
    \App\View::display('pages.clan', [
        'config' => $config, 'clan' => $clan, 'members' => \App\Clan::members((int)$id),
        'is_owner' => $isOwner, 'my_clan' => $sid ? \App\Clan::forPlayer($sid) : null,
        'my_request' => $sid ? \App\Clan::outgoingRequest($sid) : null,
        'my_invite' => $sid ? \App\Clan::hasInvite((int)$id, $sid) : false,
        'pending' => $isOwner ? \App\Clan::pendingRequests((int)$id) : [],
        'sent_invites' => $isOwner ? \App\Clan::sentInvites((int)$id) : [],
        'steam_user' => \App\SteamAuth::user(),
    ]);
});

\App\Router::post('/clans/{id}/request', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id . '?err=csrf'); exit; }
    $err = \App\Clan::requestJoin((int)$id, \App\SteamAuth::steamId());
    header('Location: /clan/' . $id . ($err ? '?err=' . urlencode($err) : '?ok=requested')); exit;
});

\App\Router::post('/clans/{id}/invite', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id . '?err=csrf'); exit; }
    $err = \App\Clan::invite((int)$id, \App\SteamAuth::steamId(), preg_replace('/\s+/', '', $_POST['steam_id'] ?? ''));
    header('Location: /clan/' . $id . ($err ? '?err=' . urlencode($err) : '?ok=invited')); exit;
});

\App\Router::post('/clans/{id}/invite-cancel', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id); exit; }
    if (\App\Clan::isOwner((int)$id, \App\SteamAuth::steamId())) \App\Clan::dropRequest((int)$id, trim($_POST['steam_id'] ?? ''));
    header('Location: /clan/' . $id . '?ok=invite_cancelled'); exit;
});

\App\Router::post('/clans/{id}/requests/accept', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id); exit; }
    if (\App\Clan::isOwner((int)$id, \App\SteamAuth::steamId())) \App\Clan::accept((int)$id, trim($_POST['steam_id'] ?? ''));
    header('Location: /clan/' . $id . '?ok=accepted'); exit;
});

\App\Router::post('/clans/{id}/requests/reject', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id); exit; }
    if (\App\Clan::isOwner((int)$id, \App\SteamAuth::steamId())) \App\Clan::dropRequest((int)$id, trim($_POST['steam_id'] ?? ''));
    header('Location: /clan/' . $id . '?ok=rejected'); exit;
});

\App\Router::post('/clans/{id}/kick', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id); exit; }
    \App\Clan::removeMember((int)$id, \App\SteamAuth::steamId(), trim($_POST['steam_id'] ?? ''));
    header('Location: /clan/' . $id . '?ok=kicked'); exit;
});

\App\Router::post('/clans/{id}/leave', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id); exit; }
    $err = \App\Clan::leave(\App\SteamAuth::steamId());
    header('Location: ' . ($err ? '/clan/' . $id . '?err=' . urlencode($err) : '/clans?ok=left')); exit;
});

\App\Router::post('/clans/{id}/transfer', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id); exit; }
    $err = \App\Clan::transferOwnership((int)$id, \App\SteamAuth::steamId(), trim($_POST['steam_id'] ?? ''));
    header('Location: /clan/' . $id . ($err ? '?err=' . urlencode($err) : '?ok=transferred')); exit;
});

\App\Router::post('/clans/{id}/disband', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id); exit; }
    if (\App\Clan::isOwner((int)$id, \App\SteamAuth::steamId())) \App\Clan::disband((int)$id);
    header('Location: /clans?ok=disbanded'); exit;
});

\App\Router::post('/clans/{id}/edit', function($id) use ($config, $ROOT) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /clan/' . $id); exit; }
    if (\App\Clan::isOwner((int)$id, \App\SteamAuth::steamId())) {
        $logo = upload_image($_FILES['logo_file'] ?? [], $ROOT . '/public/assets/img/clans', 'clan', '/assets/img/clans');
        \App\Clan::updateInfo((int)$id, $_POST['description'] ?? null, $_POST['discord_url'] ?? null, $logo);
    }
    header('Location: /clan/' . $id . '?ok=saved'); exit;
});

// Convite: o JOGADOR aceita/recusa (consentimento do lado dele) — vindo do /player
\App\Router::post('/clan-invite/accept', function() use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /player/' . \App\SteamAuth::steamId()); exit; }
    $cid = (int)($_POST['clan_id'] ?? 0);
    $err = \App\Clan::accept($cid, \App\SteamAuth::steamId());
    header('Location: /clan/' . $cid . ($err ? '?err=' . urlencode($err) : '?ok=joined')); exit;
});
\App\Router::post('/clan-invite/reject', function() use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /player/' . \App\SteamAuth::steamId()); exit; }
    \App\Clan::dropRequest((int)($_POST['clan_id'] ?? 0), \App\SteamAuth::steamId());
    header('Location: /player/' . \App\SteamAuth::steamId()); exit;
});

// ============ EVENTOS DE CLÃ (aba "Clãs" do ranking) ============
// A aba é sempre visível (discoverability), mas os EVENTOS só pra quem tem clã
// (trava de privacidade do Bryan): sem clã, a página mostra "entre num clã" e
// NÃO carrega evento nenhum (zero vazamento).
\App\Router::get('/ranking/clans', function() use ($config) {
    $sid    = \App\SteamAuth::check() ? \App\SteamAuth::steamId() : null;
    $myClan = $sid ? \App\Clan::forPlayer($sid) : null;
    \App\ClanEvent::tick(); // congela/baseline eventos vencidos mesmo se o visitante não tem clã
    $events = [];
    if ($myClan) {
        foreach (\App\ClanEvent::publicEvents() as $ev) {
            $ph = \App\ClanEvent::phase($ev);
            // Evento ativo: refresca os stats dos membros no CFTools antes de montar o placar (ao vivo).
            if ($ph === 'active') { try { \App\ClanEvent::refreshActiveMembers((int)$ev['id'], $ev); } catch (\Throwable $e) {} }
            $events[] = [
                'ev'         => $ev,
                'phase'      => $ph,
                'scores'     => \App\ClanEvent::liveScores((int)$ev['id'], $ev),
                'registered' => \App\ClanEvent::isRegistered((int)$ev['id'], (int)$myClan['id']),
            ];
        }
    }
    \App\View::display('pages.clan_events', [
        'config'     => $config,
        'my_clan'    => $myClan,
        'is_leader'  => $myClan ? \App\Clan::isOwner((int)$myClan['id'], $sid) : false,
        'events'     => $events,
        'steam_user' => \App\SteamAuth::user(),
    ]);
});

\App\Router::post('/clan-events/{id}/register', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /ranking/clans?err=csrf'); exit; }
    $sid  = \App\SteamAuth::steamId();
    $clan = \App\Clan::forPlayer($sid);
    if (!$clan || !\App\Clan::isOwner((int)$clan['id'], $sid)) { header('Location: /ranking/clans?err=not_owner'); exit; }
    $err = \App\ClanEvent::register((int)$id, (int)$clan['id'], $sid);
    header('Location: /ranking/clans' . ($err ? '?err=' . urlencode($err) : '?ok=registered')); exit;
});

\App\Router::post('/clan-events/{id}/unregister', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /ranking/clans?err=csrf'); exit; }
    $sid  = \App\SteamAuth::steamId();
    $clan = \App\Clan::forPlayer($sid);
    if (!$clan || !\App\Clan::isOwner((int)$clan['id'], $sid)) { header('Location: /ranking/clans?err=not_owner'); exit; }
    $err = \App\ClanEvent::unregister((int)$id, (int)$clan['id']);
    header('Location: /ranking/clans' . ($err ? '?err=' . urlencode($err) : '?ok=unregistered')); exit;
});

// Admin: moderar clãs (remover abuso). Capability 'coupons' (mesma área de gestão).
\App\Router::get('/admin/clans', function() use ($config) {
    \App\Auth::requireCan('coupons');
    \App\View::display('admin.clans', ['config' => $config, 'clans' => \App\Clan::all()]);
});
\App\Router::post('/admin/clans/{id}/remove', function($id) use ($config) {
    \App\Auth::requireCan('coupons');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Clan::disband((int)$id);
    \App\AuditLog::record('clan.removed', 'clan', (string)$id);
    header('Location: /admin/clans?ok=1'); exit;
});

// ============ CENTRAL DE AJUDA (guia) ============
\App\Router::get('/ajuda', function() use ($config) {
    \App\View::display('pages.help_index', ['config' => $config, 'by_cat' => \App\Help::publishedByCategory()]);
});
\App\Router::get('/ajuda/{slug}', function($slug) use ($config) {
    $a = \App\Help::getBySlug($slug);
    if (!$a) { header('Location: /ajuda'); exit; }
    \App\View::display('pages.help_article', ['config' => $config, 'a' => $a, 'siblings' => \App\Help::siblings($a['category'], (int)$a['id'])]);
});

\App\Router::get('/rules', function() use ($config) {
    // Rota explícita pra rules: tenta página dinâmica primeiro, fallback pra view estática
    $page = \App\Database::fetchOne(
        "SELECT * FROM pages WHERE slug = 'rules' AND published = 1 LIMIT 1"
    );
    if ($page) {
        \App\View::display('pages.dynamic_page', ['config' => $config, 'page' => $page]);
    } else {
        \App\View::display('pages.rules', ['config' => $config]);
    }
});

// ============ LOJA ============
\App\Router::get('/shop', function() use ($config) {
    $packages = \App\Database::fetchAll(
        "SELECT * FROM packages WHERE enabled = 1 ORDER BY sort_order ASC"
    );
    $bonusEnabled = \App\Settings::getInt('bonus_enabled');

    // Promo sazonal: se setting promo_coupon_code está setado E o cupom existe E é válido,
    // pré-calcula desconto pra cada pacote (pra renderizar tag "X% OFF" + preço riscado).
    $promoCode = trim($config['settings']['promo_coupon_code'] ?? '');
    $promoLabel = trim($config['settings']['promo_label'] ?? '');
    $promoCoupon = null;
    if ($promoCode !== '') {
        [$c, $err] = \App\Coupon::lookup($promoCode);
        if (!$err) $promoCoupon = $c;
    }

    // Wishlist do player Steam logado (set de package_ids favoritos)
    $wishlist = [];
    if (\App\SteamAuth::check()) {
        foreach (\App\Database::fetchAll(
            "SELECT package_id FROM wishlist WHERE steam_id = ?",
            [\App\SteamAuth::steamId()]
        ) as $w) {
            $wishlist[$w['package_id']] = true;
        }
    }

    // Multi-server: lista de servidores ativos pra seletor no checkout.
    $activeServers = \App\Servers::active();
    $isMultiServer = count($activeServers) > 1;
    $selectedServerId = (int)($_GET['server'] ?? \App\Servers::defaultId());
    if (!array_filter($activeServers, fn($s) => (int)$s['id'] === $selectedServerId)) {
        $selectedServerId = \App\Servers::defaultId();
    }

    // JSON-LD: ItemList de Products — cada pacote vira uma Offer pra search engines.
    $siteUrl = rtrim($config['site_url'] ?? '', '/') ?: (($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $itemList = [];
    foreach ($packages as $i => $pkg) {
        $coinsTotal = (int)$pkg['coins'] + ($bonusEnabled ? (int)$pkg['bonus_coins'] : 0);
        $itemList[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'item'     => [
                '@type'       => 'Product',
                'name'        => $pkg['name'] . ' — ' . $coinsTotal . ' moedas',
                'description' => $pkg['name'] . ' do servidor. Entrega instantânea via Steam.',
                'sku'         => $pkg['id'],
                'offers'      => [
                    '@type'         => 'Offer',
                    'priceCurrency' => 'BRL',
                    'price'         => number_format((float)$pkg['price_brl'], 2, '.', ''),
                    'availability'  => 'https://schema.org/InStock',
                    'url'           => $siteUrl . '/shop',
                ],
            ],
        ];
    }
    $shopJsonLd = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'itemListElement' => $itemList,
    ];

    \App\View::display('pages.shop', [
        'config' => $config, 'packages' => $packages, 'bonus_enabled' => $bonusEnabled,
        'promo_coupon' => $promoCoupon, 'promo_label' => $promoLabel,
        'wishlist' => $wishlist, 'is_logged' => \App\SteamAuth::check(),
        'servers' => $activeServers, 'is_multi_server' => $isMultiServer,
        'selected_server_id' => $selectedServerId,
        'title' => 'Loja — Moedas DayZ',
        'description' => 'Compre moedas pro servidor DayZ. Entrega instantânea via Steam, pagamento seguro Mercado Pago (Pix, boleto, cartão).',
        'jsonld' => $shopJsonLd,
    ]);
});

\App\Router::post('/shop/checkout', function() use ($config) {
    // CSRF: token gerado quando o user carregou /shop e enviado no form
    if (!\App\Csrf::check()) {
        \App\View::display('pages.checkout_error', [
            'config' => $config,
            'msg' => 'Sua sessão expirou. Recarregue a página da loja e tente de novo.',
        ]);
        return;
    }

    // Rate limit: 10 checkouts por IP em 5 minutos (defesa contra DoS no MP API)
    $rl = \App\RateLimit::check('checkout_' . \App\RateLimit::clientIp(), 10, 300);
    if (!$rl['allowed']) {
        \App\View::display('pages.checkout_error', [
            'config' => $config,
            'msg' => 'Muitas tentativas em pouco tempo. Aguarde ' . $rl['reset_in'] . 's e tente novamente.',
        ]);
        return;
    }

    $packageId = trim($_POST['package_id'] ?? '');
    $steamId   = preg_replace('/\s+/', '', $_POST['steam_id'] ?? '');
    $termsAccepted = !empty($_POST['terms_accepted']);

    if (!$termsAccepted) {
        \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'Você precisa aceitar os Termos de Uso e a Política de Reembolso pra continuar.']);
        return;
    }

    if (!preg_match('/^7656119[0-9]{10}$/', $steamId)) {
        \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'SteamID inválido (formato esperado: 17 dígitos começando com 7656119)']);
        return;
    }

    $pkg = \App\Database::fetchOne(
        "SELECT * FROM packages WHERE id = ? AND enabled = 1 LIMIT 1", [$packageId]
    );
    if (!$pkg) {
        \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'Pacote não encontrado ou desativado.']);
        return;
    }

    // Resolve servidor de destino (multi-server). Single-server: usa o padrão (id=1).
    $serverId = (int)($_POST['server_id'] ?? 0);
    if ($serverId < 1) $serverId = \App\Servers::defaultId();
    $server = \App\Servers::find($serverId);
    if (!$server || !$server['active']) {
        \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'Servidor de destino inválido ou inativo.']);
        return;
    }

    $bonusEnabled = \App\Settings::getInt('bonus_enabled');
    $coinsBase  = (int)$pkg['coins'];
    $coinsBonus = $bonusEnabled ? (int)$pkg['bonus_coins'] : 0;
    $coinsTotal = $coinsBase + $coinsBonus;
    $priceOriginal = (float)$pkg['price_brl'];

    // Aplica cupom se enviado
    $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $discount   = 0.0;
    $appliedCouponCode = null;
    $priceFinal = $priceOriginal;
    $couponBonusCoins = 0;
    // Atribuição de afiliado/streamer: vínculo ATUAL do cliente (carimbado na compra,
    // mesmo sem cupom agora — assim a recorrência dele conta pro streamer).
    $affiliateStamp = \App\Affiliate::binding($steamId);
    if ($couponCode !== '') {
        [$coupon, $err] = \App\Coupon::lookup($couponCode, $packageId, $priceOriginal, $steamId);
        if ($err) {
            \App\View::display('pages.checkout_error', [
                'config' => $config,
                'msg' => 'Cupom inválido: ' . \App\Coupon::errorMessage($err),
            ]);
            return;
        }
        if (\App\Coupon::isAffiliate($coupon) && \App\Affiliate::enabled()) {
            // Cupom de afiliado + programa ligado: atrela o cliente ao streamer (1 por vez).
            \App\Affiliate::bind($steamId, $coupon['code']);
            $affiliateStamp = \App\Affiliate::binding($steamId);
            // Benefício pro cliente vale 1x: só na PRIMEIRA compra PAGA com este cupom.
            // Re-gerar o PIX da mesma 1ª compra mantém o benefício; depois de pagar 1 vez,
            // não repete (é o que protege a margem na recorrência — a atribuição segue pelo vínculo).
            $usedPaid = (int) \App\Database::fetchColumn(
                "SELECT COUNT(*) FROM purchases
                  WHERE steam_id = ? AND UPPER(coupon_code) = UPPER(?)
                    AND (mp_status = 'approved' OR delivered_at IS NOT NULL)",
                [$steamId, $coupon['code']]
            );
            if ($usedPaid === 0) {
                [$discount, $priceFinal] = \App\Coupon::applyDiscount($coupon, $priceOriginal);
                $couponBonusCoins = \App\Coupon::bonusCoins($coupon);
                $appliedCouponCode = $coupon['code'];
            }
        } else {
            // Cupom comum (ou afiliado com o programa desligado = trata como desconto normal).
            [$discount, $priceFinal] = \App\Coupon::applyDiscount($coupon, $priceOriginal);
            $couponBonusCoins = \App\Coupon::bonusCoins($coupon);
            $appliedCouponCode = $coupon['code'];
        }
    }
    $priceBrl = $priceFinal;
    // Moedas bônus do cupom (tipo 'coins') entram no total a entregar.
    if ($couponBonusCoins > 0) {
        $coinsBonus += $couponBonusCoins;
        $coinsTotal  = $coinsBase + $coinsBonus;
    }

    // Reaproveita pendência RECENTE igual (mesmo steam+pacote+servidor, não paga, <30min):
    // se o jogador clicou "Comprar" de novo / gerou outro PIX, NÃO cria linha duplicada.
    $reuse = \App\Database::fetchOne(
        "SELECT id FROM purchases
          WHERE steam_id = ? AND package_id = ? AND server_id = ?
            AND mp_status = 'pending' AND delivered_at IS NULL
            AND created_at > (NOW() - INTERVAL 30 MINUTE)
          ORDER BY id DESC LIMIT 1",
        [$steamId, $packageId, $serverId]
    );
    if ($reuse) {
        $purchaseId = (int)$reuse['id'];
        \App\Database::query(
            "UPDATE purchases
                SET coins_base = ?, coins_bonus = ?, coins_total = ?, price_brl = ?,
                    coupon_code = ?, discount_brl = ?, affiliate_coupon_code = ?,
                    terms_accepted_at = NOW(), terms_version = ?
              WHERE id = ?",
            [$coinsBase, $coinsBonus, $coinsTotal, $priceBrl, $appliedCouponCode, $discount, $affiliateStamp, '2026-05-27', $purchaseId]
        );
    } else {
        // Cria purchase pending com registro de aceite de termos + cupom (se aplicado)
        \App\Database::query(
            "INSERT INTO purchases
                (steam_id, package_id, server_id, coins_base, coins_bonus, coins_total,
                 price_brl, coupon_code, discount_brl, affiliate_coupon_code,
                 mp_status, terms_accepted_at, terms_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)",
            [$steamId, $packageId, $serverId, $coinsBase, $coinsBonus, $coinsTotal,
             $priceBrl, $appliedCouponCode, $discount, $affiliateStamp,
             '2026-05-27']
        );
        $purchaseId = (int)\App\Database::pdo()->lastInsertId();
    }
    // Marca a compra como "desta sessão" — só quem criou pode consultar status / pagar
    // com cartão depois (anti-IDOR: impede enumerar SteamID de compras alheias).
    if (!isset($_SESSION['checkout_pids']) || !is_array($_SESSION['checkout_pids'])) $_SESSION['checkout_pids'] = [];
    $_SESSION['checkout_pids'][$purchaseId] = true;

    // Cria preference no MP
    $mp = new \App\MercadoPago($config['mercado_pago']['access_token'] ?? '', $config['mercado_pago']['webhook_secret'] ?? null);
    if (!$mp->isConfigured()) {
        // Modo dev: simula sucesso direto sem MP
        \App\View::display('pages.checkout_dev', [
            'config' => $config, 'purchase_id' => $purchaseId, 'pkg' => $pkg,
            'steam_id' => $steamId, 'coins_total' => $coinsTotal, 'price_brl' => $priceBrl,
        ]);
        return;
    }

    $siteUrl = rtrim($config['site_url'] ?? ('https://' . $_SERVER['HTTP_HOST']), '/');

    // Pix não cobra R$0 (ex: cupom 100%). Bloqueia com mensagem amigável.
    if ($priceBrl < 0.01) {
        \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'O cupom cobre 100% do valor — fale com o admin pra liberar manualmente.']);
        return;
    }

    // ===== CHECKOUT TRANSPARENTE: Pix DIRETO no site (sem sair pro Mercado Pago) =====
    // QR copia-e-cola gerado aqui; o jogador paga sem deixar a página. O mp-webhook.php
    // credita os coins na aprovação. Cartão/boleto ficam no fallback /shop/card/{id}.
    $expires = gmdate("Y-m-d\\TH:i:s.000P", time() + 1800); // QR válido ~30 min
    $siteName = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'Loja');
    $pay = $mp->createPixPayment([
        'transaction_amount' => round($priceBrl, 2),
        'description'        => $siteName . ' — ' . $pkg['name'] . ' (' . $coinsTotal . ' moedas)',
        'external_reference' => (string)$purchaseId,
        'notification_url'   => $siteUrl . '/api/mp-webhook.php',
        'date_of_expiration' => $expires,
        'payer'              => ['email' => $steamId . '@pix.tecplay.inf.br'],
        'metadata'           => ['server_slug' => trim($config['matriz']['server_slug'] ?? ''), 'kind' => 'loja'],
    ]);
    $tx = $pay['point_of_interaction']['transaction_data'] ?? null;
    if (!$pay || !$tx || empty($tx['qr_code'])) {
        \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'Não foi possível gerar o Pix. Tente novamente em alguns minutos.']);
        return;
    }
    \App\Database::query(
        "UPDATE purchases SET mp_payment_id = ? WHERE id = ?",
        [(string)$pay['id'], $purchaseId]
    );

    \App\View::display('pages.checkout_pix', [
        'config'      => $config,
        'purchase_id' => $purchaseId,
        'steam_id'    => $steamId,
        'server_id'   => $serverId,
        'pkg'         => $pkg,
        'coins_total' => $coinsTotal,
        'price_brl'   => $priceBrl,
        'discount'    => $discount,
        'coupon_code' => $appliedCouponCode,
        'qr_code'     => $tx['qr_code'],
        'qr_base64'   => $tx['qr_code_base64'] ?? '',
        'ticket_url'  => $tx['ticket_url'] ?? '',
        'expires_at'  => $pay['date_of_expiration'] ?? $expires,
        'active_tab'  => (($_POST['pay_tab'] ?? '') === 'card') ? 'card' : 'pix',
    ]);
});

// Polling do checkout transparente: o JS pergunta aqui se o Pix já caiu. JSON.
\App\Router::get('/shop/status/{id}', function($id) use ($config) {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)$id;
    // Anti-IDOR: só libera status de compra criada NESTA sessão (senão dava pra
    // enumerar IDs e mapear SteamID de qualquer comprador). Rate-limit por IP também.
    $rl = \App\RateLimit::check('shop-status:' . \App\RateLimit::clientIp(), 120, 60);
    if (empty($rl['allowed'])) { http_response_code(429); echo json_encode(['error' => 'rate']); return; }
    if (empty($_SESSION['checkout_pids'][$pid])) { http_response_code(403); echo json_encode(['error' => 'forbidden']); return; }
    $p = \App\Database::fetchOne(
        "SELECT steam_id, mp_status, delivered_at FROM purchases WHERE id = ? LIMIT 1", [$pid]
    );
    if (!$p) { http_response_code(404); echo json_encode(['error' => 'not_found']); return; }
    $paid = !empty($p['delivered_at']) || $p['mp_status'] === 'approved';
    echo json_encode([
        'status'   => $p['mp_status'],
        'paid'     => $paid,
        'redirect' => $paid ? ('/player/' . $p['steam_id']) : null, // dono da sessão, ok
    ]);
});

// Fallback cartão/boleto: cria preference pra uma purchase pendente e manda pro MP.
\App\Router::get('/shop/card/{id}', function($id) use ($config) {
    $pid = (int)$id;
    // Anti-IDOR + anti-abuso: só o dono da sessão (quem criou no checkout) dispara
    // a criação de pagamento dessa compra; rate-limit por IP.
    $rl = \App\RateLimit::check('shop-card:' . \App\RateLimit::clientIp(), 15, 300);
    if (empty($rl['allowed'])) { http_response_code(429); \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'Muitas tentativas. Aguarde um pouco.']); return; }
    if (empty($_SESSION['checkout_pids'][$pid])) { http_response_code(403); \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'Compra não encontrada nesta sessão.']); return; }
    $p = \App\Database::fetchOne("SELECT * FROM purchases WHERE id = ? LIMIT 1", [$pid]);
    if (!$p) { http_response_code(404); \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'Compra não encontrada.']); return; }
    if (!empty($p['delivered_at'])) { header('Location: /player/' . $p['steam_id']); exit; }
    $mp = new \App\MercadoPago($config['mercado_pago']['access_token'] ?? '', $config['mercado_pago']['webhook_secret'] ?? null);
    if (!$mp->isConfigured()) { http_response_code(503); \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'Pagamento indisponível.']); return; }
    $siteUrl = rtrim($config['site_url'] ?? ('https://' . $_SERVER['HTTP_HOST']), '/');
    $pkgRow = \App\Database::fetchOne("SELECT name FROM packages WHERE id = ? LIMIT 1", [$p['package_id']]);
    $pref = $mp->createPreference([
        'items' => [[
            'id'          => $p['package_id'],
            'title'       => ($pkgRow['name'] ?? 'Moedas') . ' — ' . (int)$p['coins_total'] . ' moedas',
            'description' => 'Compra para SteamID ' . $p['steam_id'],
            'quantity'    => 1,
            'currency_id' => 'BRL',
            'unit_price'  => (float)$p['price_brl'],
        ]],
        'external_reference' => (string)$p['id'],
        'back_urls' => [
            'success' => $siteUrl . '/player/' . $p['steam_id'],
            'pending' => $siteUrl . '/shop/return?status=pending',
            'failure' => $siteUrl . '/shop/return?status=failure',
        ],
        'auto_return'    => 'approved',
        'notification_url' => $siteUrl . '/api/mp-webhook.php',
        'statement_descriptor' => $config['settings']['site_name'] ?? $config['site_name'] ?? 'DAYZ',
        'metadata' => ['server_slug' => trim($config['matriz']['server_slug'] ?? ''), 'kind' => 'loja'],
    ]);
    if (!$pref || empty($pref['init_point'])) {
        \App\View::display('pages.checkout_error', ['config' => $config, 'msg' => 'Não foi possível criar o pagamento. Tente novamente em alguns minutos.']);
        return;
    }
    header('Location: ' . $pref['init_point']);
    exit;
});

// ===== CARTÃO TRANSPARENTE (in-site): cria o pagamento SEM sair do site. =====
// O cartão é tokenizado NO NAVEGADOR pelo SDK do MP (public_key) -> aqui só chega o
// `token` de uso único + parcelas/CPF. O PAN nunca toca o servidor (PCI SAQ-A).
// A ENTREGA das moedas é feita pelo webhook (caminho único, claim atômico); aqui só
// criamos o pagamento e o front faz polling em /shop/status até cair.
\App\Router::post('/shop/card-pay/{id}', function($id) use ($config) {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)$id;
    $rl = \App\RateLimit::check('shop-cardpay:' . \App\RateLimit::clientIp(), 15, 300);
    if (empty($rl['allowed'])) { http_response_code(429); echo json_encode(['ok' => false, 'error' => 'Muitas tentativas. Aguarde um pouco e tente de novo.']); return; }
    if (!\App\Csrf::check()) { http_response_code(419); echo json_encode(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.']); return; }
    // Anti-IDOR: só o dono da sessão (quem criou no checkout) paga essa compra.
    if (empty($_SESSION['checkout_pids'][$pid])) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Compra não encontrada nesta sessão.']); return; }
    $p = \App\Database::fetchOne("SELECT * FROM purchases WHERE id = ? LIMIT 1", [$pid]);
    if (!$p) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Compra não encontrada.']); return; }
    if (!empty($p['delivered_at']) || $p['mp_status'] === 'approved') {
        echo json_encode(['ok' => true, 'status' => 'approved', 'redirect' => '/player/' . $p['steam_id']]); return;
    }

    $token        = trim((string)($_POST['token'] ?? ''));
    $pmId         = trim((string)($_POST['payment_method_id'] ?? ''));
    $installments = max(1, (int)($_POST['installments'] ?? 1));
    $issuerId     = trim((string)($_POST['issuer_id'] ?? ''));
    $docType      = trim((string)($_POST['doc_type'] ?? 'CPF')) ?: 'CPF';
    $docNumber    = preg_replace('/\D+/', '', (string)($_POST['doc_number'] ?? ''));
    $email        = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: ($p['steam_id'] . '@pix.tecplay.inf.br');
    if ($token === '' || $pmId === '') { http_response_code(422); echo json_encode(['ok' => false, 'error' => 'Dados do cartão incompletos. Revise e tente de novo.']); return; }

    $mp = new \App\MercadoPago($config['mercado_pago']['access_token'] ?? '', $config['mercado_pago']['webhook_secret'] ?? null);
    if (!$mp->isConfigured()) { http_response_code(503); echo json_encode(['ok' => false, 'error' => 'Pagamento indisponível no momento.']); return; }
    $siteUrl = rtrim($config['site_url'] ?? ('https://' . $_SERVER['HTTP_HOST']), '/');

    $cardPkg = \App\Database::fetchOne("SELECT name FROM packages WHERE id = ? LIMIT 1", [$p['package_id']]);
    $cardSite = $config['settings']['site_name'] ?? ($config['site_name'] ?? 'Loja');
    $payload = [
        'transaction_amount' => round((float)$p['price_brl'], 2),
        'token'              => $token,
        'description'        => $cardSite . ' — ' . ($cardPkg['name'] ?? 'Moedas') . ' (' . (int)$p['coins_total'] . ' moedas)',
        'installments'       => $installments,
        'payment_method_id'  => $pmId,
        'external_reference' => (string)$p['id'],
        'notification_url'   => $siteUrl . '/api/mp-webhook.php',
        'payer'              => ['email' => $email],
        'metadata'           => ['server_slug' => trim($config['matriz']['server_slug'] ?? ''), 'kind' => 'loja'],
    ];
    if ($issuerId !== '')  $payload['issuer_id'] = $issuerId;
    if ($docNumber !== '') $payload['payer']['identification'] = ['type' => $docType, 'number' => $docNumber];

    $pay = $mp->createCardPayment($payload);
    if (!$pay || empty($pay['status'])) { http_response_code(502); echo json_encode(['ok' => false, 'error' => 'Não foi possível processar o cartão. Tente outro cartão ou use o Pix.']); return; }

    // Guarda o payment_id pro webhook casar (sem rebaixar uma compra já entregue).
    \App\Database::query("UPDATE purchases SET mp_payment_id = ? WHERE id = ? AND delivered_at IS NULL", [(string)$pay['id'], $pid]);

    $status = $pay['status']; // approved | in_process | pending | rejected
    if ($status === 'approved') {
        echo json_encode(['ok' => true, 'status' => 'approved', 'redirect' => '/player/' . $p['steam_id']]); return;
    }
    if ($status === 'in_process' || $status === 'pending') {
        echo json_encode(['ok' => true, 'status' => $status, 'msg' => 'Pagamento em análise. Assim que aprovar, suas moedas entram automaticamente.']); return;
    }
    // rejected
    echo json_encode(['ok' => false, 'status' => 'rejected', 'error' => \App\MercadoPago::cardRejectMessage($pay['status_detail'] ?? '')]);
});

\App\Router::get('/shop/return', function() use ($config) {
    $status = $_GET['status'] ?? 'pending';
    \App\View::display('pages.checkout_return', ['config' => $config, 'status' => $status]);
});

// ============ STEAM AUTH ============
\App\Router::get('/auth/steam', function() use ($config) {
    $siteUrl = $config['site_url'] ?? ('http://' . $_SERVER['HTTP_HOST']);
    // Volta pra ONDE o usuário estava ao clicar em login (não joga ele na loja —
    // ficava evasivo, tipo "compra moeda agora"). Só seta se um fluxo específico
    // (ex: depoimentos, minhas-compras) ainda NÃO definiu o retorno, e só aceita
    // path interno same-site que não seja /auth ou /admin (evita loop/conflito).
    if (empty($_SESSION['steam_login_return'])) {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
            $path = parse_url($ref, PHP_URL_PATH) ?: '/';
            $query = parse_url($ref, PHP_URL_QUERY);
            if (!preg_match('#^/(auth|admin)(/|$)#', $path)) {
                $_SESSION['steam_login_return'] = $path . ($query ? '?' . $query : '');
            }
        }
    }
    header('Location: ' . \App\SteamAuth::loginUrl($siteUrl));
    exit;
});

\App\Router::get('/auth/steam/callback', function() use ($config) {
    // PHP transforma "openid.X" em "openid_X" no $_GET
    $params = $_GET;
    $steamId = \App\SteamAuth::verifyCallback($params);
    if (!$steamId) {
        \App\View::display('pages.auth_error', ['config' => $config, 'msg' => 'Não foi possível verificar seu login Steam. Tente novamente.']);
        return;
    }

    // Tenta enriquecer com perfil via Steam Web API (se cliente configurou STEAM_API_KEY)
    $apiKey = $config['steam_api_key'] ?? null;
    $profile = \App\SteamAuth::fetchProfile($steamId, $apiKey);
    \App\SteamAuth::login(
        $steamId,
        $profile['display_name'] ?? null,
        $profile['avatar'] ?? null
    );

    // Se ja existe player com esse SteamID, atualiza display_name. Senao, cria stub.
    try {
        $existing = \App\Database::fetchOne("SELECT id FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
        if (!$existing) {
            \App\Database::query(
                "INSERT INTO players (steam_id, display_name, coins, origin, last_seen_at)
                 VALUES (?, ?, 0, 'panel', NOW())",
                [$steamId, $profile['display_name'] ?? null]
            );
        } elseif (!empty($profile['display_name'])) {
            \App\Database::query(
                "UPDATE players SET display_name = ?, last_seen_at = NOW() WHERE id = ?",
                [$profile['display_name'], $existing['id']]
            );
        }
    } catch (Throwable $e) { /* sem DB? ignora — login funciona via sessao */ }

    // Log de login (privacidade/auditoria: quem entrou no site via Steam). Best-effort —
    // SteamID é público; IP/UA pra auditoria. Decoupled do ranking (que segue público).
    try {
        \App\Database::query(
            "INSERT INTO login_log (steam_id, display_name, ip, user_agent) VALUES (?, ?, ?, ?)",
            [$steamId, $profile['display_name'] ?? null, \App\RateLimit::clientIp(),
             substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
        );
    } catch (\Throwable $e) { /* tabela ausente (migration pendente) — degrada limpo */ }

    // Redireciona pra de onde veio (setado em /auth/steam ou por um fluxo específico).
    // Default = home (NÃO a loja — evita parecer que tá empurrando moeda no login).
    // Guard anti open-redirect: só aceita path interno (começa com / e não //).
    $back = $_SESSION['steam_login_return'] ?? '/';
    unset($_SESSION['steam_login_return']);
    if (!is_string($back) || !preg_match('#^/[A-Za-z0-9/_?&=.\-]*$#', $back) || str_starts_with($back, '//')) {
        $back = '/';
    }
    header('Location: ' . $back);
    exit;
});

\App\Router::get('/auth/logout', function() {
    \App\SteamAuth::logout();
    header('Location: /');
    exit;
});

\App\Router::get('/depoimentos', function() use ($config) {
    $reviews = \App\Database::fetchAll(
        "SELECT * FROM reviews WHERE approved = 1 ORDER BY created_at DESC LIMIT 50"
    );
    $avgRow = \App\Database::fetchOne(
        "SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total FROM reviews WHERE approved = 1"
    );
    \App\View::display('pages.depoimentos', [
        'config' => $config, 'reviews' => $reviews,
        'avg_rating' => (float)($avgRow['avg_rating'] ?? 0),
        'total_reviews' => (int)($avgRow['total'] ?? 0),
        'steam_user' => \App\SteamAuth::user(),
    ]);
});

// ============ AVALIAÇÃO (exige login Steam) ============
// Só quem logou com Steam avalia: o nome vem do nick Steam (reaproveita o login).
// Vai como pending (approved=0) pro admin moderar antes de publicar em /depoimentos.
\App\Router::post('/reviews/public-submit', function() use ($config) {
    if (!\App\SteamAuth::check()) {
        $_SESSION['steam_login_return'] = '/depoimentos';
        header('Location: /auth/steam'); exit;
    }
    $steamId = \App\SteamAuth::steamId();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // 3 reviews por hora por jogador (anti-spam)
    $rl = \App\RateLimit::check('review-public:' . $steamId, 3, 3600);
    if (empty($rl['allowed'])) {
        header('Location: /depoimentos?err=rate_limited'); exit;
    }
    if (!\App\Csrf::check()) { header('Location: /depoimentos?err=csrf'); exit; }

    // Nome = nick do Steam (não confia em input livre). Fallback pro display_name do DB.
    $user = \App\SteamAuth::user();
    $name = trim((string)($user['display_name'] ?? ''));
    if ($name === '') {
        $name = (string) (\App\Database::fetchColumn("SELECT display_name FROM players WHERE steam_id = ?", [$steamId]) ?: 'Sobrevivente');
    }
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
    $body   = trim((string)($_POST['body'] ?? ''));
    if (mb_strlen($body) < 10 || mb_strlen($body) > 500) { header('Location: /depoimentos?err=invalid_body'); exit; }

    $avatar = trim((string)($user['avatar'] ?? '')) ?: null;
    if ($avatar !== null && !preg_match('#^https?://#i', $avatar)) $avatar = null; // só URL http(s)
    \App\Database::query(
        "INSERT INTO reviews (purchase_id, steam_id, display_name, avatar, rating, body, source, approved)
         VALUES (NULL, ?, ?, ?, ?, ?, 'public', 0)",
        [$steamId, mb_substr($name, 0, 60), $avatar, $rating, $body]
    );
    header('Location: /depoimentos?ok=submitted');
    exit;
});

// (Review por compra foi removida do perfil — avaliar é espontâneo via /depoimentos
//  → /reviews/public-submit. A antiga rota /reviews/submit saiu junto, sem caller.)

\App\Router::get('/admin/reviews', function() use ($config) {
    \App\Auth::requireCan('reviews');
    $filter = $_GET['filter'] ?? 'pending';
    // LEFT JOIN: depoimentos PÚBLICOS têm purchase_id NULL — com INNER JOIN eles
    // sumiam do painel (admin nunca via/aprovava). LEFT JOIN traz os dois tipos.
    $sql = "SELECT r.*, p.package_id, p.price_brl
              FROM reviews r LEFT JOIN purchases p ON p.id = r.purchase_id";
    if ($filter === 'pending')  $sql .= " WHERE r.approved = 0";
    if ($filter === 'approved') $sql .= " WHERE r.approved = 1";
    $sql .= " ORDER BY r.created_at DESC LIMIT 100";
    $reviews = \App\Database::fetchAll($sql);
    $counts = \App\Database::fetchOne(
        "SELECT SUM(approved=0) AS pending, SUM(approved=1) AS approved, COUNT(*) AS total FROM reviews"
    );
    \App\View::display('admin.reviews', [
        'config' => $config, 'reviews' => $reviews, 'filter' => $filter, 'counts' => $counts,
    ]);
});

\App\Router::post('/admin/reviews/{id}/toggle', function($id) use ($config) {
    \App\Auth::requireCan('reviews');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("UPDATE reviews SET approved = 1 - approved WHERE id = ?", [(int)$id]);
    header('Location: /admin/reviews?ok=1');
    exit;
});

\App\Router::post('/admin/reviews/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('reviews');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("DELETE FROM reviews WHERE id = ?", [(int)$id]);
    header('Location: /admin/reviews?ok=deleted');
    exit;
});

\App\Router::post('/wishlist/toggle', function() use ($config) {
    if (!\App\SteamAuth::check()) {
        http_response_code(401);
        header('Content-Type: application/json');
        die(json_encode(['ok' => false, 'login_url' => '/auth/steam']));
    }
    if (!\App\Csrf::check()) {
        http_response_code(419);
        header('Content-Type: application/json');
        die(json_encode(['ok' => false, 'error' => 'csrf']));
    }
    $steamId = \App\SteamAuth::steamId();
    $pkg = trim($_POST['package_id'] ?? '');
    if (!$pkg) { http_response_code(400); die(json_encode(['ok' => false])); }

    $exists = \App\Database::fetchColumn(
        "SELECT id FROM wishlist WHERE steam_id = ? AND package_id = ? LIMIT 1",
        [$steamId, $pkg]
    );
    if ($exists) {
        \App\Database::query("DELETE FROM wishlist WHERE id = ?", [(int)$exists]);
        $action = 'removed';
    } else {
        \App\Database::query(
            "INSERT INTO wishlist (steam_id, package_id) VALUES (?, ?)",
            [$steamId, $pkg]
        );
        $action = 'added';
    }
    header('Content-Type: application/json');
    die(json_encode(['ok' => true, 'action' => $action]));
});

\App\Router::get('/my-purchases', function() {
    // UNIFICADO no perfil: /my-purchases agora é o próprio /player/{seu-id}.
    // Mantido como redirect pra não quebrar links/bookmarks + os redirects dos POSTs
    // (claim-box, apoiar-streamer, reviews) que apontam pra cá com flash na query string.
    if (!\App\SteamAuth::check()) {
        $_SESSION['steam_login_return'] = '/my-purchases';
        header('Location: /auth/steam');
        exit;
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: /player/' . \App\SteamAuth::steamId() . ($qs !== '' ? '?' . $qs : ''));
    exit;
});

// "Apoie seu Streamer": cliente atrela o perfil a um streamer digitando o código dele.
// Não dá desconto aqui (o benefício é no checkout, 1x); isto é só a atribuição.
\App\Router::post('/apoiar-streamer', function() use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /my-purchases?err=csrf'); exit; }
    if (!\App\Affiliate::enabled()) { header('Location: /my-purchases'); exit; }
    $steamId = \App\SteamAuth::steamId();
    $code = strtoupper(trim($_POST['affiliate_code'] ?? ''));
    [$coupon, $err] = \App\Coupon::lookup($code);
    if ($err || !\App\Coupon::isAffiliate($coupon)) {
        header('Location: /my-purchases?aff=invalid'); exit;
    }
    $res = \App\Affiliate::bind($steamId, $coupon['code']);
    $map = ['bound' => 'ok', 'switched' => 'switched', 'already' => 'already', 'blocked' => 'blocked', 'disabled' => 'invalid'];
    header('Location: /my-purchases?aff=' . ($map[$res] ?? 'invalid'));
    exit;
});

// Resgatar (claim) uma caixa pendente: o player decide QUANDO o item cai no jogo.
// Server-authoritative: só resgata abertura que é DELE e está pendente (anti-burla).
\App\Router::post('/claim-box/{id}', function($id) use ($config) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /my-purchases?err=csrf'); exit; }
    $steamId = \App\SteamAuth::steamId();
    $id = (int) $id;
    $rl = \App\RateLimit::check('claimbox:' . $steamId, 20, 600);
    if (!$rl['allowed']) { header('Location: /my-purchases?box=ratelimited'); exit; }
    $op = \App\Database::fetchOne(
        "SELECT id, steam_id, classname, quantity, status FROM box_openings WHERE id = ? LIMIT 1", [$id]
    );
    if (!$op || $op['steam_id'] !== $steamId) { header('Location: /my-purchases?box=invalid'); exit; }
    if (($op['status'] ?? '') === 'delivered' || !empty($op['delivered_at'] ?? null)) {
        header('Location: /my-purchases?box=already'); exit;
    }
    // deliver() já blinda online + janela de restart + CFTools; devolve 'delivered'|'pending'.
    $res = \App\Boxes::deliver($id, $steamId, $op['classname'], (int)$op['quantity']);
    header('Location: /my-purchases?box=' . ($res === 'delivered' ? 'ok' : 'wait') . '#caixas');
    exit;
});

// ============ ADMIN ============
\App\Router::get('/admin/login', function() use ($config) {
    if (\App\Auth::check()) { header('Location: /admin'); exit; }
    \App\View::display('admin.login', ['config' => $config, 'error' => $_GET['e'] ?? null]);
});

\App\Router::post('/admin/login', function() use ($config) {
    // Rate limit dois eixos: por IP (defesa contra scanner) e por username+IP
    // (defesa contra brute-force de um user específico). Atrás de proxy/CDN
    // o IP único pode ser compartilhado, daí combinar com username evita
    // bloquear admins legítimos por "vizinhos" maliciosos.
    $ip = \App\RateLimit::clientIp();
    $username = trim($_POST['username'] ?? '');
    $rlIp   = \App\RateLimit::check('login_ip_' . $ip, 20, 15 * 60);
    $rlUser = \App\RateLimit::check('login_u_' . substr(md5($username . '|' . $ip), 0, 16), 5, 15 * 60);
    if (!$rlIp['allowed'] || !$rlUser['allowed']) {
        $w = max($rlIp['reset_in'], $rlUser['reset_in']);
        header('Location: /admin/login?e=rate&w=' . $w);
        exit;
    }
    // CSRF
    if (!\App\Csrf::check()) {
        header('Location: /admin/login?e=csrf');
        exit;
    }
    $password = $_POST['password'] ?? '';
    if (\App\Auth::attempt($username, $password)) {
        \App\RateLimit::clearBucket('login_ip_' . $ip);
        \App\RateLimit::clearBucket('login_u_' . substr(md5($username . '|' . $ip), 0, 16));
        // Regenera ID de sessao apos login (anti session fixation)
        session_regenerate_id(true);
        // Redirect pra home do role — support cai em /admin/players, editor em /admin/pages, etc.
        // Evita 403 logo após login pra quem não tem acesso a /admin (dashboard).
        header('Location: ' . \App\Auth::homePath());
    } else {
        header('Location: /admin/login?e=1');
    }
    exit;
});

// ── Esqueci minha senha (reset por email) ──────────────────────────────
\App\Router::get('/admin/forgot', function() use ($config) {
    if (\App\Auth::check()) { header('Location: /admin'); exit; }
    \App\View::display('admin.forgot', ['config' => $config, 'sent' => isset($_GET['sent'])]);
});

\App\Router::post('/admin/forgot', function() use ($config, $ROOT) {
    $ip = \App\RateLimit::clientIp();
    if (!\App\RateLimit::check('forgot_' . $ip, 5, 60 * 60)['allowed']) { header('Location: /admin/forgot?e=rate'); exit; }
    if (!\App\Csrf::check()) { header('Location: /admin/forgot?e=csrf'); exit; }

    $email = trim(strtolower($_POST['email'] ?? ''));
    // Resposta SEMPRE generica (anti-enumeracao de emails).
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $user = \App\Database::fetchOne("SELECT id, username, email FROM admin_users WHERE email = ? LIMIT 1", [$email]);
        if ($user && !empty($user['email'])) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1h
            \App\Database::query(
                "UPDATE admin_users SET reset_token_hash = ?, reset_expires = ? WHERE id = ?",
                [hash('sha256', $token), $expires, (int)$user['id']]
            );
            $siteUrl = rtrim($config['site_url'] ?? '', '/');
            $link    = $siteUrl . '/admin/reset?token=' . $token;
            $siteName = htmlspecialchars($config['site_name'] ?? 'seu site');
            require_once $ROOT . '/src/Mailer.php';
            \App\Mailer::init($config['mail'] ?? []);
            $html = '<div style="font-family:Arial,sans-serif;background:#0d0d10;padding:24px;color:#d4d4d8;">'
                . '<h2 style="color:#fff;">Redefinir senha do painel</h2>'
                . '<p>Recebemos um pedido pra redefinir a senha do admin de <strong>' . $siteName . '</strong>.</p>'
                . '<p><a href="' . htmlspecialchars($link) . '" style="display:inline-block;padding:12px 24px;background:#a855f7;color:#fff;text-decoration:none;border-radius:8px;">Redefinir minha senha</a></p>'
                . '<p style="font-size:13px;color:#a1a1aa;">O link vale por 1 hora e só pode ser usado uma vez. Se você não pediu isso, ignore — sua senha continua a mesma.</p>'
                . '<p style="font-size:12px;color:#71717a;word-break:break-all;">Ou cole no navegador: ' . htmlspecialchars($link) . '</p>'
                . '</div>';
            @\App\Mailer::send($email, 'Redefinir senha do painel', $html);
        }
    }
    header('Location: /admin/forgot?sent=1');
    exit;
});

\App\Router::get('/admin/reset', function() use ($config) {
    if (\App\Auth::check()) { header('Location: /admin'); exit; }
    $token = (string)($_GET['token'] ?? '');
    $valid = false;
    if ($token !== '') {
        $row = \App\Database::fetchOne(
            "SELECT id FROM admin_users WHERE reset_token_hash = ? AND reset_expires > NOW() LIMIT 1",
            [hash('sha256', $token)]
        );
        $valid = (bool)$row;
    }
    \App\View::display('admin.reset', ['config' => $config, 'token' => $token, 'valid' => $valid]);
});

\App\Router::post('/admin/reset', function() use ($config) {
    if (!\App\Csrf::check()) { header('Location: /admin/login?e=csrf'); exit; }
    $ip = \App\RateLimit::clientIp();
    if (!\App\RateLimit::check('reset_' . $ip, 10, 15 * 60)['allowed']) { header('Location: /admin/login?e=rate'); exit; }

    $token = (string)($_POST['token'] ?? '');
    $pass  = (string)($_POST['password'] ?? '');
    $row = $token !== '' ? \App\Database::fetchOne(
        "SELECT id FROM admin_users WHERE reset_token_hash = ? AND reset_expires > NOW() LIMIT 1",
        [hash('sha256', $token)]
    ) : null;
    if (!$row) { header('Location: /admin/reset?token=' . urlencode($token) . '&e=invalid'); exit; }
    if (strlen($pass) < 8) { header('Location: /admin/reset?token=' . urlencode($token) . '&e=short'); exit; }

    \App\Database::query(
        "UPDATE admin_users SET password_hash = ?, reset_token_hash = NULL, reset_expires = NULL WHERE id = ?",
        [password_hash($pass, PASSWORD_BCRYPT), (int)$row['id']]
    );
    header('Location: /admin/login?reset=ok');
    exit;
});

\App\Router::get('/admin/logout', function() {
    \App\Auth::logout();
    header('Location: /admin/login');
    exit;
});


// Helper compartilhado entre /admin (HTML) e /admin/dashboard.json (auto-refresh)
$collectDashboardData = function() {
    return [
        'stats' => [
            'players_count'   => (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM players"),
            'coins_total'     => (int)\App\Database::fetchColumn("SELECT COALESCE(SUM(coins),0) FROM players"),
            'revenue_total'   => (float)\App\Database::fetchColumn("SELECT COALESCE(SUM(total_spent_brl),0) FROM players"),
            'purchases_today' => (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM purchases WHERE created_at >= CURDATE()"),
            'revenue_today'   => (float)\App\Database::fetchColumn("SELECT COALESCE(SUM(price_brl),0) FROM purchases WHERE mp_status = 'approved' AND created_at >= CURDATE()"),
            'pending_count'   => (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM purchases WHERE mp_status = 'pending'"),
        ],
        'recent_purchases' => \App\Database::fetchAll(
            "SELECT id, steam_id, package_id, coins_total, price_brl, mp_status, created_at
               FROM purchases ORDER BY created_at DESC LIMIT 10"
        ),
    ];
};

\App\Router::get('/admin', function() use ($config, $collectDashboardData, $ROOT) {
    \App\Auth::requireCan('dashboard');
    $data = $collectDashboardData();
    \App\View::display('admin.dashboard', [
        'config' => $config, 'stats' => $data['stats'], 'recent_purchases' => $data['recent_purchases'],
        'pending_migrations' => pending_migrations($ROOT),
    ]);
});

\App\Router::get('/admin/sales-chart.json', function() use ($config) {
    \App\Auth::requireCan('dashboard');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    // Vendas dos últimos 30 dias agrupadas por dia
    $rows = \App\Database::fetchAll(
        "SELECT DATE(created_at) AS day,
                COUNT(*) AS count,
                COALESCE(SUM(price_brl), 0) AS revenue
           FROM purchases
          WHERE mp_status = 'approved'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          GROUP BY day
          ORDER BY day ASC"
    );
    // Preenche dias vazios (0 vendas) pra grafico ficar contínuo
    $byDay = [];
    foreach ($rows as $r) $byDay[$r['day']] = $r;

    $labels = []; $counts = []; $revenues = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $labels[]   = date('d/m', strtotime($d));
        $counts[]   = (int)($byDay[$d]['count']   ?? 0);
        $revenues[] = (float)($byDay[$d]['revenue'] ?? 0);
    }
    die(json_encode([
        'labels' => $labels, 'counts' => $counts, 'revenues' => $revenues,
    ]));
});

\App\Router::get('/admin/dashboard.json', function() use ($collectDashboardData) {
    \App\Auth::requireCan('dashboard');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $data = $collectDashboardData();
    // Formata pra JSON (badges, números formatados, etc) — pra o JS só substituir DOM
    foreach ($data['recent_purchases'] as &$p) {
        $p['coins_total']    = (int)$p['coins_total'];
        $p['price_brl_fmt']  = number_format((float)$p['price_brl'], 2, ',', '.');
        $p['status_class']   = match($p['mp_status']) {
            'approved' => 'success',
            'rejected','cancelled','refunded' => 'danger',
            'pending' => 'warning',
            default => 'info',
        };
    }
    die(json_encode($data, JSON_UNESCAPED_UNICODE));
});

\App\Router::get('/admin/players', function() use ($config) {
    \App\Auth::requireCan('players');
    $q       = trim($_GET['q'] ?? '');
    $origin  = $_GET['origin'] ?? '';
    $sort    = $_GET['sort'] ?? 'coins'; // coins, spent, recent, name
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 50;
    $offset  = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = "(steam_id LIKE ? OR display_name LIKE ?)";
        $params[] = "%$q%"; $params[] = "%$q%";
    }
    if (in_array($origin, ['agent','panel','payment','manual'], true)) {
        $where[] = "origin = ?";
        $params[] = $origin;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $orderBy = match($sort) {
        'spent'  => 'total_spent_brl DESC',
        'recent' => 'COALESCE(last_seen_at, \'1970-01-01\') DESC',
        'name'   => 'display_name ASC',
        default  => 'coins DESC',
    };

    $total = (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM players $whereSql", $params);
    $players = \App\Database::fetchAll(
        "SELECT id, steam_id, display_name, coins, total_spent_brl, last_seen_at, origin
           FROM players $whereSql
          ORDER BY $orderBy
          LIMIT $perPage OFFSET $offset",
        $params
    );

    \App\View::display('admin.players', [
        'config' => $config, 'players' => $players,
        'q' => $q, 'origin' => $origin, 'sort' => $sort,
        'page' => $page, 'per_page' => $perPage, 'total' => $total,
        'last_page' => max(1, (int)ceil($total / $perPage)),
    ]);
});

\App\Router::post('/admin/players/{id}/coins', function($id) use ($config) {
    \App\Auth::requireCan('players');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $newCoins = max(0, (int)($_POST['coins'] ?? 0));
    $player = \App\Database::fetchOne("SELECT coins, steam_id FROM players WHERE id = ?", [(int)$id]);
    $oldCoins = (int)($player['coins'] ?? 0);
    \App\Database::query("UPDATE players SET coins = ?, origin = 'panel' WHERE id = ?", [$newCoins, (int)$id]);
    \App\AuditLog::record('player.coins_changed', 'player', $id, [
        'before' => $oldCoins, 'after' => $newCoins, 'delta' => $newCoins - $oldCoins,
    ]);
    \App\BalanceLog::record(
        (int)$id, $player['steam_id'] ?? '',
        $oldCoins, $newCoins, 'admin',
        null, null,
        'Ajuste manual via painel admin'
    );
    // Open redirect guard: aceita só paths internos /admin/...
    $back = $_POST['_back'] ?? '/admin/players';
    if (!is_string($back) || !preg_match('#^/admin/[A-Za-z0-9/_?&=.-]*$#', $back)) {
        $back = '/admin/players';
    }
    header('Location: ' . $back . (strpos($back, '?') === false ? '?ok=1' : '&ok=1'));
    exit;
});

\App\Router::post('/admin/players/{id}/notes', function($id) use ($config) {
    \App\Auth::requireCan('players');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $notes = trim($_POST['notes'] ?? '');
    \App\Database::query("UPDATE players SET notes = ? WHERE id = ?", [$notes ?: null, (int)$id]);
    header('Location: /admin/players/' . (int)$id . '?ok=1');
    exit;
});

\App\Router::get('/admin/packages', function() use ($config) {
    \App\Auth::requireCan('packages');
    $packages = \App\Database::fetchAll(
        "SELECT * FROM packages ORDER BY sort_order ASC"
    );
    $bonusEnabled = \App\Settings::getInt('bonus_enabled');
    \App\View::display('admin.packages', [
        'config' => $config, 'packages' => $packages, 'bonus_enabled' => $bonusEnabled,
    ]);
});

\App\Router::post('/admin/packages/toggle-bonus', function() use ($config) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $new = \App\Settings::getInt('bonus_enabled') ? 0 : 1;
    \App\Settings::set('bonus_enabled', (string)$new);
    header('Location: /admin/packages?ok=1');
    exit;
});

\App\Router::post('/admin/packages/{id}/toggle', function($id) use ($config) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("UPDATE packages SET enabled = 1 - enabled WHERE id = ?", [$id]);
    header('Location: /admin/packages?ok=1');
    exit;
});

\App\Router::post('/admin/packages/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    // Pacote com compras NÃO é apagado (preserva histórico/recibo do jogador) — desative em vez disso.
    $used = (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM purchases WHERE package_id = ?", [$id]);
    if ($used > 0) { header('Location: /admin/packages?err=inuse'); exit; }
    \App\Database::query("DELETE FROM packages WHERE id = ?", [$id]);
    header('Location: /admin/packages?ok=deleted');
    exit;
});

\App\Router::get('/admin/packages/{id}/edit', function($id) use ($config) {
    \App\Auth::requireCan('packages');
    $pkg = \App\Database::fetchOne("SELECT * FROM packages WHERE id = ? LIMIT 1", [$id]);
    if (!$pkg) { http_response_code(404); echo 'Pacote não encontrado'; exit; }
    \App\View::display('admin.package_edit', ['config' => $config, 'pkg' => $pkg]);
});

\App\Router::post('/admin/packages/{id}/save', function($id) use ($config, $ROOT) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $existing = \App\Database::fetchOne("SELECT id, image FROM packages WHERE id = ? LIMIT 1", [$id]);
    if (!$existing) { http_response_code(404); exit; }

    $name        = trim($_POST['name'] ?? '');
    $icon        = trim($_POST['icon'] ?? '🪙');
    $coins       = max(0, (int)($_POST['coins'] ?? 0));
    $bonus       = max(0, (int)($_POST['bonus_coins'] ?? 0));
    $price       = max(0, (float)str_replace(',', '.', $_POST['price_brl'] ?? '0'));
    $bonusBadge  = trim($_POST['bonus_badge'] ?? '') ?: null;
    $ribbon      = trim($_POST['ribbon'] ?? '') ?: null;
    $featured    = isset($_POST['featured']) ? 1 : 0;
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);
    // Perks: 1 por linha no textarea
    $perks       = array_values(array_filter(array_map('trim', explode("\n", $_POST['perks'] ?? ''))));
    $bonusPerks  = array_values(array_filter(array_map('trim', explode("\n", $_POST['bonus_perks'] ?? ''))));

    if (!$name || $coins <= 0 || $price <= 0) {
        header('Location: /admin/packages/' . urlencode($id) . '/edit?err=invalid');
        exit;
    }

    // Imagem (capa): upload opcional pra assets/img/packages/. Mantém a atual se não subir nova.
    $image = $existing['image'] ?? null;
    $pkgDir = $ROOT . '/public/assets/img/packages';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['image'];
        $allowed = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'];
        $mime = detect_image_mime($f['tmp_name']);
        if ($f['size'] <= 5 * 1024 * 1024 && isset($allowed[$mime])) {
            ensure_writable_dir($pkgDir);
            $fname = 'p_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($f['tmp_name'], $pkgDir . '/' . $fname)) { ensure_writable_dir($pkgDir); move_uploaded_file($f['tmp_name'], $pkgDir . '/' . $fname); }
            if (is_file($pkgDir . '/' . $fname)) {
                if ($image && !preg_match('#^https?://#i', $image)) @unlink($pkgDir . '/' . basename($image));
                $image = $fname;
            }
        } else {
            header('Location: /admin/packages/' . urlencode($id) . '/edit?err=img'); exit;
        }
    }
    // Remover imagem (checkbox)
    if (!empty($_POST['remove_image']) && $image) {
        if (!preg_match('#^https?://#i', $image)) @unlink($pkgDir . '/' . basename($image));
        $image = null;
    }

    \App\Database::query(
        "UPDATE packages SET name = ?, icon = ?, image = ?, coins = ?, bonus_coins = ?, price_brl = ?,
                            bonus_badge = ?, ribbon = ?, featured = ?, sort_order = ?,
                            perks_json = ?, bonus_perks_json = ?
         WHERE id = ?",
        [
            $name, $icon, $image, $coins, $bonus, $price,
            $bonusBadge, $ribbon, $featured, $sortOrder,
            json_encode($perks, JSON_UNESCAPED_UNICODE),
            json_encode($bonusPerks, JSON_UNESCAPED_UNICODE),
            $id,
        ]
    );
    header('Location: /admin/packages?ok=1');
    exit;
});

// ── Criar pacote novo (id = slug; INSERT) ────────────────────────────
\App\Router::get('/admin/packages/new', function() use ($config) {
    \App\Auth::requireCan('packages');
    \App\View::display('admin.package_edit', ['config' => $config, 'pkg' => null]);
});
\App\Router::post('/admin/packages/create', function() use ($config, $ROOT) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id = strtolower(trim($_POST['id'] ?? ''));
    if (!preg_match('/^[a-z0-9][a-z0-9_\-]{1,39}$/', $id)) {
        header('Location: /admin/packages/new?err=id'); exit;
    }
    if (\App\Database::fetchOne("SELECT id FROM packages WHERE id = ? LIMIT 1", [$id])) {
        header('Location: /admin/packages/new?err=dup'); exit;
    }
    $name       = trim($_POST['name'] ?? '');
    $icon       = trim($_POST['icon'] ?? '🪙');
    $coins      = max(0, (int)($_POST['coins'] ?? 0));
    $bonus      = max(0, (int)($_POST['bonus_coins'] ?? 0));
    $price      = max(0, (float)str_replace(',', '.', $_POST['price_brl'] ?? '0'));
    $bonusBadge = trim($_POST['bonus_badge'] ?? '') ?: null;
    $ribbon     = trim($_POST['ribbon'] ?? '') ?: null;
    $featured   = isset($_POST['featured']) ? 1 : 0;
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);
    $perks      = array_values(array_filter(array_map('trim', explode("\n", $_POST['perks'] ?? ''))));
    $bonusPerks = array_values(array_filter(array_map('trim', explode("\n", $_POST['bonus_perks'] ?? ''))));
    if (!$name || $coins <= 0 || $price <= 0) {
        header('Location: /admin/packages/new?err=invalid'); exit;
    }
    // Imagem (capa) opcional — mesmo guard do save (MIME real + nome aleatório).
    $image = null;
    $pkgDir = $ROOT . '/public/assets/img/packages';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['image'];
        $allowed = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'];
        $mime = detect_image_mime($f['tmp_name']);
        if ($f['size'] <= 5 * 1024 * 1024 && isset($allowed[$mime])) {
            ensure_writable_dir($pkgDir);
            $fname = 'p_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            if (move_uploaded_file($f['tmp_name'], $pkgDir . '/' . $fname) && is_file($pkgDir . '/' . $fname)) {
                $image = $fname;
            }
        } else {
            header('Location: /admin/packages/new?err=img'); exit;
        }
    }
    \App\Database::query(
        "INSERT INTO packages
            (id, name, icon, image, coins, bonus_coins, price_brl, bonus_badge, ribbon, featured, sort_order, perks_json, bonus_perks_json, enabled)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
        [$id, $name, $icon, $image, $coins, $bonus, $price, $bonusBadge, $ribbon, $featured, $sortOrder,
         json_encode($perks, JSON_UNESCAPED_UNICODE), json_encode($bonusPerks, JSON_UNESCAPED_UNICODE)]
    );
    header('Location: /admin/packages?ok=1');
    exit;
});

// ============ LOJA IN-GAME (catálogo gastável — Fase 2) ============
// Gerido por quem tem a permissão 'packages' (mesma da loja de moedas).

\App\Router::get('/admin/shop', function() use ($config) {
    \App\Auth::requireCan('packages');
    $items = \App\Database::fetchAll("SELECT * FROM shop_items ORDER BY sort_order ASC, name ASC");
    \App\View::display('admin.shop_items', ['config' => $config, 'items' => $items]);
});

\App\Router::get('/admin/shop/new', function() use ($config) {
    \App\Auth::requireCan('packages');
    \App\View::display('admin.shop_item_edit', ['config' => $config, 'item' => null]);
});

\App\Router::get('/admin/shop/{id}/edit', function($id) use ($config) {
    \App\Auth::requireCan('packages');
    $item = \App\Database::fetchOne("SELECT * FROM shop_items WHERE id = ? LIMIT 1", [(int)$id]);
    if (!$item) { http_response_code(404); echo 'Item não encontrado'; exit; }
    \App\View::display('admin.shop_item_edit', ['config' => $config, 'item' => $item]);
});

\App\Router::post('/admin/shop/{id}/toggle', function($id) use ($config) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("UPDATE shop_items SET enabled = 1 - enabled WHERE id = ?", [(int)$id]);
    header('Location: /admin/shop?ok=1');
    exit;
});

\App\Router::post('/admin/shop/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("DELETE FROM shop_items WHERE id = ?", [(int)$id]);
    header('Location: /admin/shop?ok=1');
    exit;
});

\App\Router::post('/admin/shop/save', function() use ($config) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }

    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $icon      = trim($_POST['icon'] ?? '') ?: null;
    $coinsCost = max(0, (int)($_POST['coins_cost'] ?? 0));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $enabled   = isset($_POST['enabled']) ? 1 : 0;
    $sku       = trim($_POST['sku'] ?? '');
    $isNew     = $id === 0;

    $back = $isNew ? '/admin/shop/new' : '/admin/shop/' . $id . '/edit';

    if ($name === '' || $sku === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $sku)) {
        header('Location: ' . $back . '?err=invalid'); exit;
    }

    // deliver[]: valida JSON e normaliza cada entrada (classname obrigatório).
    $deliver = json_decode((string)($_POST['deliver'] ?? ''), true);
    if (!is_array($deliver)) {
        header('Location: ' . $back . '?err=bad_deliver'); exit;
    }
    $clean = [];
    foreach ($deliver as $d) {
        if (!is_array($d) || empty($d['classname']) || !is_string($d['classname'])) continue;
        $clean[] = [
            'classname'   => trim($d['classname']),
            'quantity'    => max(1, (int)($d['quantity'] ?? 1)),
            'attachments' => array_values(array_filter(array_map(
                static fn($x) => is_string($x) ? trim($x) : '', (array)($d['attachments'] ?? [])
            ))),
            'cargo'       => array_values(array_filter(array_map(
                static fn($x) => is_string($x) ? trim($x) : '', (array)($d['cargo'] ?? [])
            ))),
            'health'      => isset($d['health']) ? max(0.0, min(1.0, (float)$d['health'])) : 1.0,
        ];
    }
    if (empty($clean)) {
        header('Location: ' . $back . '?err=bad_deliver'); exit;
    }
    $deliverJson = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($isNew) {
        $taken = \App\Database::fetchColumn("SELECT 1 FROM shop_items WHERE sku = ? LIMIT 1", [$sku]);
        if ($taken) { header('Location: /admin/shop/new?err=sku_taken'); exit; }
        \App\Database::query(
            "INSERT INTO shop_items (sku, name, icon, coins_cost, enabled, sort_order, deliver_json)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$sku, $name, $icon, $coinsCost, $enabled, $sortOrder, $deliverJson]
        );
    } else {
        // SKU é imutável após criado (o bot referencia) — não atualiza o sku.
        \App\Database::query(
            "UPDATE shop_items SET name = ?, icon = ?, coins_cost = ?, enabled = ?, sort_order = ?, deliver_json = ?
             WHERE id = ?",
            [$name, $icon, $coinsCost, $enabled, $sortOrder, $deliverJson, $id]
        );
    }
    header('Location: /admin/shop?ok=1');
    exit;
});

\App\Router::get('/admin/purchases', function() use ($config) {
    \App\Auth::requireCan('purchases');
    $filter = $_GET['status'] ?? '';
    $sql = "SELECT * FROM purchases";
    $params = [];
    if (in_array($filter, ['approved','pending','rejected','cancelled','refunded'], true)) {
        $sql .= " WHERE mp_status = ?";
        $params[] = $filter;
    }
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    $purchases = \App\Database::fetchAll($sql, $params);
    $counts = \App\Database::fetchOne(
        "SELECT COUNT(*) AS total,
                SUM(mp_status = 'approved') AS approved,
                SUM(mp_status = 'pending')  AS pending,
                SUM(mp_status = 'rejected') AS rejected
           FROM purchases"
    );
    \App\View::display('admin.purchases', [
        'config' => $config, 'purchases' => $purchases, 'counts' => $counts, 'filter' => $filter,
    ]);
});

\App\Router::get('/admin/purchases/export', function() use ($config) {
    \App\Auth::requireCan('purchases');
    $filter = $_GET['status'] ?? '';
    $sql = "SELECT p.*, pl.display_name FROM purchases p
              LEFT JOIN players pl ON pl.steam_id = p.steam_id";
    $params = [];
    if (in_array($filter, ['approved','pending','rejected','cancelled','refunded'], true)) {
        $sql .= " WHERE p.mp_status = ?";
        $params[] = $filter;
    }
    $sql .= " ORDER BY p.created_at DESC";
    $rows = \App\Database::fetchAll($sql, $params);

    $filename = 'compras-' . date('Ymd-His') . ($filter ? "-$filter" : '') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 pro Excel BR abrir certinho
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'ID','Data','SteamID','Jogador','Pacote','Moedas Base','Bonus','Total','Valor (R$)',
        'Metodo Pagto','Status','MP Payment ID','Entregue em','Termos aceitos em'
    ], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['created_at'], $r['steam_id'], $r['display_name'] ?? '',
            $r['package_id'], $r['coins_base'], $r['coins_bonus'], $r['coins_total'],
            number_format((float)$r['price_brl'], 2, ',', ''),
            $r['payment_method'] ?? '',
            $r['mp_status'] ?? '',
            $r['mp_payment_id'] ?? '',
            $r['delivered_at'] ?? '',
            $r['terms_accepted_at'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
});

\App\Router::get('/admin/settings', function() use ($config) {
    \App\Auth::requireCan('settings');
    $settings = \App\Database::fetchAll("SELECT `key`, `value` FROM settings ORDER BY `key`");
    $map = [];
    foreach ($settings as $s) $map[$s['key']] = $s['value'];
    // CFTools: indica se já tem secret salvo (pra não re-pedir) e se o config.php
    // está sobrescrevendo os campos do painel (aí o que vale é o arquivo).
    $cf = $config['cftools'] ?? [];
    $cftoolsViaConfig = !empty($cf['app_id']) && !empty($cf['secret']) && !empty($cf['server_api_id']);
    \App\View::display('admin.settings', [
        'config' => $config, 'settings' => $map,
        'cftools_secret_set' => ($map['cftools_secret'] ?? '') !== '',
        'cftools_via_config' => $cftoolsViaConfig,
    ]);
});

\App\Router::post('/admin/settings', function() use ($config) {
    \App\Auth::requireCan('settings');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    // Campos de texto do form (subconjunto do SCHEMA que aparece nesta página).
    // bonus_enabled NÃO entra aqui (é togglado em /admin/packages) — senão salvar
    // configurações zeraria o bônus.
    $fields = ['site_name','site_tagline','site_tagline_enus','server_ip','server_port','discord_invite',
               'social_discord','social_instagram','social_whatsapp','social_facebook','social_youtube',
               'social_tiktok','social_twitch','social_kick','social_x',
               'battlemetrics_id','next_wipe_at','wipe_label',
               'maintenance_message','maintenance_eta',
               'discord_sales_webhook','promo_coupon_code','promo_label',
               'restart_times','restart_warn_minutes',
               'cftools_app_id','cftools_server_api_id'];
    // Toggles (checkbox): se não veio no POST, vira 0
    $toggles = ['maintenance_enabled', 'live_purchases_enabled', 'live_purchases_anonymize', 'live_purchases_show_price',
                'restart_enabled', 'affiliate_enabled', 'affiliate_allow_switch', 'box_claim_enabled', 'hide_online_players'];

    // Escrita via Settings::set(): valida contra o whitelist (SCHEMA), normaliza
    // por tipo e atualiza o cache em memória. Chave fora do SCHEMA é rejeitada.
    foreach ($fields as $k) {
        if (isset($_POST[$k])) \App\Settings::set($k, (string)$_POST[$k]);
    }
    foreach ($toggles as $k) {
        \App\Settings::set($k, !empty($_POST[$k]) ? '1' : '0');
    }
    // Secret do CFTools: só sobrescreve se o admin digitou algo (o campo vem vazio na
    // tela por segurança — não echoamos o secret salvo). Vazio = mantém o atual.
    if (isset($_POST['cftools_secret']) && trim((string)$_POST['cftools_secret']) !== '') {
        \App\Settings::set('cftools_secret', trim((string)$_POST['cftools_secret']));
    }
    // Mudou config do CFTools -> invalida o cache (token/lookup velhos batem no app
    // antigo). Sem isso, trocar o app CFTools "nao funciona" ate limpar storage/cache
    // na mao (bug reportado por usuario do template). Cache rebuilda no proximo acesso.
    \App\CFTools::clearCache();
    header('Location: /admin/settings?ok=1');
    exit;
});

// Limpar cache manualmente (escape hatch p/ config que "nao pega" por cache velho).
\App\Router::post('/admin/clear-cache', function() use ($ROOT) {
    \App\Auth::requireCan('settings');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $n = \App\CFTools::clearCache();
    // Tambem os caches seguros-de-reconstruir: status do servidor + perfis steam.
    $dir = $ROOT . '/storage/cache';
    $globs = array_merge(
        glob($dir . '/server-status/*.json') ?: [],
        glob($dir . '/steam-*.json') ?: []
    );
    foreach ($globs as $f) { if (@unlink($f)) $n++; }
    header('Location: /admin/settings?cache=' . $n);
    exit;
});

// ============ ADMIN: CAIXAS / LOOTBOXES ============
\App\Router::get('/admin/caixas', function() use ($config) {
    \App\Auth::requireCan('packages');
    $boxes = \App\Database::fetchAll("SELECT * FROM boxes ORDER BY sort_order ASC, id ASC");
    foreach ($boxes as &$b) {
        $b['item_count'] = (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM box_items WHERE box_id = ?", [(int)$b['id']]);
    }
    unset($b);
    $pending = (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM box_openings WHERE status = 'pending'");
    \App\View::display('admin.caixas', ['config' => $config, 'boxes' => $boxes, 'pending' => $pending]);
});

// Log de aberturas de caixa (anti-golpista): admin confere item + HORÁRIO do drop por SteamID.
// Registrado ANTES de /admin/caixas/{id} pra "logs" não cair na rota dinâmica.
\App\Router::get('/admin/caixas/logs', function() use ($config) {
    \App\Auth::requireCan('packages');
    $q = preg_replace('/[^0-9]/', '', (string)($_GET['steam'] ?? ''));
    $sql = "SELECT bo.*, b.name AS box_name FROM box_openings bo LEFT JOIN boxes b ON b.id = bo.box_id";
    $params = [];
    if ($q !== '') { $sql .= " WHERE bo.steam_id = ?"; $params[] = $q; }
    $sql .= " ORDER BY bo.id DESC LIMIT 200";
    $logs = \App\Database::fetchAll($sql, $params);
    \App\View::display('admin.caixa_logs', ['config' => $config, 'logs' => $logs, 'q' => $q]);
});

\App\Router::post('/admin/caixas/save', function() use ($config, $ROOT) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim((string)($_POST['name'] ?? ''));
    if ($name === '') { header('Location: /admin/caixas?err=name'); exit; }
    $slug  = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', ($_POST['slug'] ?? '') ?: $name)));
    if ($slug === '') $slug = 'caixa-' . substr(md5($name), 0, 6);
    $isDaily = !empty($_POST['is_daily']) ? 1 : 0;
    // Capa: upload novo vence; senão usa o campo URL (que vem pré-preenchido com a atual,
    // então salvar sem mexer mantém; limpar o campo remove). Vazio + sem upload = sem capa.
    $image = trim((string)($_POST['image'] ?? '')) ?: null;
    $up = upload_image($_FILES['image_file'] ?? [], $ROOT . '/public/assets/img/caixas', 'cx', '/assets/img/caixas');
    if ($up) $image = $up;
    // Ordem: na edição usa o campo; caixa NOVA sem ordem vai pro fim (max+1).
    if (isset($_POST['sort_order']) && $_POST['sort_order'] !== '') {
        $sortOrder = max(0, (int)$_POST['sort_order']);
    } else {
        $sortOrder = $id > 0 ? 0 : (1 + (int)(\App\Database::fetchOne("SELECT COALESCE(MAX(sort_order),0) m FROM boxes")['m'] ?? 0));
    }
    $fields = [
        $name, $slug,
        $image,
        trim((string)($_POST['description'] ?? '')) ?: null,
        $isDaily ? 0 : max(0, (int)($_POST['cost_coins'] ?? 0)),
        $isDaily,
        max(0, (int)($_POST['cooldown_hours'] ?? 24)),
        // Caixa NOVA nasce sempre ativa; na edição respeita o checkbox.
        $id > 0 ? (!empty($_POST['enabled']) ? 1 : 0) : 1,
        $sortOrder,
    ];
    if ($id > 0) {
        \App\Database::query(
            "UPDATE boxes SET name=?, slug=?, image=?, description=?, cost_coins=?, is_daily=?, cooldown_hours=?, enabled=?, sort_order=? WHERE id=?",
            array_merge($fields, [$id])
        );
    } else {
        \App\Database::query(
            "INSERT INTO boxes (name, slug, image, description, cost_coins, is_daily, cooldown_hours, enabled, sort_order) VALUES (?,?,?,?,?,?,?,?,?)",
            $fields
        );
        $id = (int)\App\Database::pdo()->lastInsertId();
    }
    \App\AuditLog::record('box.save', 'box', $id);
    header('Location: /admin/caixas/' . $id . '?ok=1');
    exit;
});

\App\Router::get('/admin/caixas/{id}', function($id) use ($config) {
    \App\Auth::requireCan('packages');
    $box = \App\Database::fetchOne("SELECT * FROM boxes WHERE id = ? LIMIT 1", [(int)$id]);
    if (!$box) { header('Location: /admin/caixas'); exit; }
    $items = \App\Database::fetchAll("SELECT * FROM box_items WHERE box_id = ? ORDER BY sort_order ASC, id ASC", [(int)$id]);
    $totalW = 0; foreach ($items as $it) $totalW += max(0, (int)$it['weight']);
    \App\View::display('admin.caixa_edit', ['config' => $config, 'box' => $box, 'items' => $items, 'total_weight' => $totalW]);
});

\App\Router::post('/admin/caixas/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("DELETE FROM box_items WHERE box_id = ?", [(int)$id]);
    \App\Database::query("DELETE FROM boxes WHERE id = ?", [(int)$id]);
    \App\AuditLog::record('box.delete', 'box', (int)$id);
    header('Location: /admin/caixas?ok=deleted');
    exit;
});

\App\Router::post('/admin/caixas/{id}/items/save', function($id) use ($config, $ROOT) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $boxId = (int)$id;
    $itemId = (int)($_POST['item_id'] ?? 0);
    $type = in_array($_POST['type'] ?? 'item', ['item','coins'], true) ? $_POST['type'] : 'item';
    $classname = trim((string)($_POST['classname'] ?? ''));
    $qty  = max(1, (int)($_POST['quantity'] ?? 1));
    $name = trim((string)($_POST['name'] ?? ''));
    if ($type === 'coins') {
        // Recompensa em moedas: classname é irrelevante; quantity = qtd de moedas.
        if ($classname === '') $classname = 'coins';
        if ($name === '') $name = $qty . ' moedas';
    } else {
        if ($classname === '') { header('Location: /admin/caixas/' . $boxId . '?err=classname'); exit; }
        if ($name === '') $name = $classname;
    }
    // Imagem do item: upload novo > URL digitada > imagem atual (na edição).
    $image = trim((string)($_POST['image'] ?? '')) ?: null;
    if ($itemId > 0 && $image === null) {
        $image = \App\Database::fetchColumn("SELECT image FROM box_items WHERE id = ? AND box_id = ?", [$itemId, $boxId]) ?: null;
    }
    $up = upload_image($_FILES['image_file'] ?? [], $ROOT . '/public/assets/img/caixas', 'ci', '/assets/img/caixas');
    if ($up) $image = $up;
    $rarity = in_array($_POST['rarity'] ?? 'common', ['common','uncommon','rare','epic','legendary'], true) ? $_POST['rarity'] : 'common';
    $f = [
        $type, $classname, $name, $image, $qty,
        \App\Boxes::rarityWeight($rarity), // peso DERIVADO da raridade (campo "peso" removido do admin)
        $rarity,
        !empty($_POST['enabled']) ? 1 : 0,
        (int)($_POST['sort_order'] ?? 0),
    ];
    if ($itemId > 0) {
        \App\Database::query(
            "UPDATE box_items SET type=?, classname=?, name=?, image=?, quantity=?, weight=?, rarity=?, enabled=?, sort_order=? WHERE id=? AND box_id=?",
            array_merge($f, [$itemId, $boxId])
        );
    } else {
        \App\Database::query(
            "INSERT INTO box_items (type, classname, name, image, quantity, weight, rarity, enabled, sort_order, box_id) VALUES (?,?,?,?,?,?,?,?,?,?)",
            array_merge($f, [$boxId])
        );
    }
    header('Location: /admin/caixas/' . $boxId . '?ok=item');
    exit;
});

\App\Router::post('/admin/caixas/{id}/items/{itemId}/delete', function($id, $itemId) use ($config) {
    \App\Auth::requireCan('packages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("DELETE FROM box_items WHERE id = ? AND box_id = ?", [(int)$itemId, (int)$id]);
    header('Location: /admin/caixas/' . (int)$id . '?ok=item_del');
    exit;
});

// ============ RECOMPENSAS DO LEADERBOARD ============
// Categorias ranqueáveis no CFTools que aceitam premiação. Animais NÃO entra
// (o CFTools não expõe leaderboard de animais — só stat individual no perfil).
$REWARD_CATEGORIES = [
    'kills'          => 'Kills (jogadores)',
    'kills_infected' => 'Zumbis mortos',
    'kdratio'        => 'K/D',
    'playtime'       => 'Tempo online',
    'longest_kill'   => 'Kill mais longa',
];

\App\Router::get('/admin/rewards', function() use ($config, $REWARD_CATEGORIES) {
    \App\Auth::requireCan('settings');
    $raw = \App\Settings::get('leaderboard_rewards', '');
    $rewards = $raw ? (json_decode($raw, true) ?: []) : [];
    \App\View::display('admin.rewards', [
        'config' => $config, 'categories' => $REWARD_CATEGORIES, 'rewards' => $rewards,
        'cftools_on' => \App\CFTools::isConfigured(),
        'last_awarded' => \App\Rewards::lastAwarded(),
        'period_label' => \App\Rewards::periodLabel(),
        'awarded_period' => \App\Rewards::awardedThisPeriod(),
        'history' => \App\Rewards::history(20),
    ]);
});

\App\Router::post('/admin/rewards', function() use ($REWARD_CATEGORIES) {
    \App\Auth::requireCan('settings');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    // Preserva last_awarded já gravado.
    $prevRaw = \App\Settings::get('leaderboard_rewards', '');
    $prev = $prevRaw ? (json_decode($prevRaw, true) ?: []) : [];
    $out = [
        'enabled' => !empty($_POST['master_enabled']) ? 1 : 0,
        'cadence' => in_array($_POST['cadence'] ?? 'manual', ['manual','weekly','monthly'], true) ? $_POST['cadence'] : 'manual',
        'auto'    => !empty($_POST['auto']) ? 1 : 0,
        'last_awarded' => (int)($prev['last_awarded'] ?? 0),
        'cats' => [],
    ];
    foreach (array_keys($REWARD_CATEGORIES) as $key) {
        $out['cats'][$key] = [
            'enabled' => !empty($_POST['cat_' . $key . '_enabled']) ? 1 : 0,
            'coins'   => [
                '1' => max(0, (int)($_POST['cat_' . $key . '_1'] ?? 0)),
                '2' => max(0, (int)($_POST['cat_' . $key . '_2'] ?? 0)),
                '3' => max(0, (int)($_POST['cat_' . $key . '_3'] ?? 0)),
            ],
        ];
    }
    // Visibilidade das abas do /ranking (esconder aba inteira; independe de premiação).
    $out['tabs'] = ['invest' => !empty($_POST['tab_invest']) ? 1 : 0];
    foreach (array_keys($REWARD_CATEGORIES) as $key) {
        $out['tabs'][$key] = !empty($_POST['tab_' . $key]) ? 1 : 0;
    }
    \App\Settings::set('leaderboard_rewards', json_encode($out, JSON_UNESCAPED_UNICODE));
    \App\AuditLog::record('rewards.save', 'rewards', null);
    header('Location: /admin/rewards?ok=1');
    exit;
});

// Premiar agora (manual) — credita o top do período atual. Idempotente por período.
\App\Router::post('/admin/rewards/award-now', function() use ($config) {
    \App\Auth::requireCan('settings');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $res = \App\Rewards::award();
    \App\AuditLog::record('rewards.award_now', 'rewards', null, ['paid' => count($res['paid'] ?? [])]);
    if (!$res['ok']) { header('Location: /admin/rewards?err=' . urlencode($res['error'] ?? 'erro')); exit; }
    $n = count($res['paid']);
    $sk = count($res['skipped'] ?? []);
    header('Location: /admin/rewards?ok=award&n=' . $n . '&sk=' . $sk);
    exit;
});

// Cron de premiação automática. Token = agent_token. Chamar a cada hora no host.
\App\Router::get('/api/award-rewards.php', function() use ($config) {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals((string)($config['agent_token'] ?? ''), (string)($_GET['token'] ?? ''))) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); return; }
    if (!\App\Rewards::shouldAutoAward()) { echo json_encode(['ok' => true, 'skipped' => true, 'reason' => 'fora de cadencia ou ja premiado']); return; }
    $res = \App\Rewards::award();
    echo json_encode(['ok' => (bool)($res['ok'] ?? false), 'label' => $res['label'] ?? null, 'paid' => count($res['paid'] ?? [])]);
});

// ============ ADMIN: EVENTOS & SORTEIOS ============
\App\Router::get('/admin/eventos', function() use ($config) {
    \App\Auth::requireCan('pages');
    $events = \App\Database::fetchAll("SELECT * FROM events ORDER BY sort_order ASC, id DESC");
    \App\View::display('admin.eventos', ['config' => $config, 'events' => $events, 'edit' => null]);
});

\App\Router::get('/admin/eventos/{id}', function($id) use ($config) {
    \App\Auth::requireCan('pages');
    $edit = \App\Database::fetchOne("SELECT * FROM events WHERE id = ? LIMIT 1", [(int)$id]);
    $events = \App\Database::fetchAll("SELECT * FROM events ORDER BY sort_order ASC, id DESC");
    \App\View::display('admin.eventos', ['config' => $config, 'events' => $events, 'edit' => $edit ?: null]);
});

\App\Router::post('/admin/eventos/save', function() use ($config) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id    = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') { header('Location: /admin/eventos?err=title'); exit; }
    $slug  = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $_POST['slug'] ?: $title)));
    if ($slug === '') $slug = 'evento-' . substr(md5($title), 0, 6);
    $norm = function($v) { $v = trim((string)$v); return $v === '' ? null : date('Y-m-d H:i:s', strtotime($v)); };
    $wsid = trim((string)($_POST['winner_steam_id'] ?? ''));
    $f = [
        $title, $slug,
        in_array($_POST['type'] ?? 'event', ['event','raffle'], true) ? $_POST['type'] : 'event',
        trim((string)($_POST['image'] ?? '')) ?: null,
        trim((string)($_POST['description'] ?? '')) ?: null,
        trim((string)($_POST['prize'] ?? '')) ?: null,
        $norm($_POST['starts_at'] ?? ''),
        $norm($_POST['ends_at'] ?? ''),
        preg_match('/^\d{17}$/', $wsid) ? $wsid : null,
        trim((string)($_POST['winner_name'] ?? '')) ?: null,
        !empty($_POST['enabled']) ? 1 : 0,
        (int)($_POST['sort_order'] ?? 0),
    ];
    if ($id > 0) {
        \App\Database::query(
            "UPDATE events SET title=?, slug=?, type=?, image=?, description=?, prize=?, starts_at=?, ends_at=?, winner_steam_id=?, winner_name=?, enabled=?, sort_order=? WHERE id=?",
            array_merge($f, [$id])
        );
    } else {
        \App\Database::query(
            "INSERT INTO events (title, slug, type, image, description, prize, starts_at, ends_at, winner_steam_id, winner_name, enabled, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            $f
        );
        $id = (int)\App\Database::pdo()->lastInsertId();
    }
    \App\AuditLog::record('event.save', 'event', $id);
    header('Location: /admin/eventos/' . $id . '?ok=1');
    exit;
});

\App\Router::post('/admin/eventos/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("DELETE FROM events WHERE id = ?", [(int)$id]);
    \App\AuditLog::record('event.delete', 'event', (int)$id);
    header('Location: /admin/eventos?ok=deleted');
    exit;
});

// ============ ADMIN: EVENTOS DE CLÃ ============
\App\Router::get('/admin/clan-events', function() use ($config) {
    \App\Auth::requireCan('pages');
    \App\ClanEvent::tick();
    \App\View::display('admin.clan_events', ['config' => $config, 'events' => \App\ClanEvent::all(), 'edit' => null, 'scores' => []]);
});
\App\Router::get('/admin/clan-events/{id}', function($id) use ($config) {
    \App\Auth::requireCan('pages');
    \App\ClanEvent::tick();
    $edit = \App\ClanEvent::get((int)$id);
    $scores = $edit ? \App\ClanEvent::liveScores((int)$id, $edit) : [];
    \App\View::display('admin.clan_events', ['config' => $config, 'events' => \App\ClanEvent::all(), 'edit' => $edit, 'scores' => $scores]);
});
\App\Router::post('/admin/clan-events/save', function() use ($config) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin/clan-events?err=csrf'); exit; }
    $id     = (int)($_POST['id'] ?? 0);
    $title  = trim($_POST['title'] ?? '');
    if ($title === '') { header('Location: /admin/clan-events' . ($id ? '/' . $id : '') . '?err=title'); exit; }
    $metric = in_array($_POST['metric'] ?? '', array_keys(\App\ClanEvent::METRICS), true) ? $_POST['metric'] : 'kills_infected';
    $slug   = \App\ClanEvent::slugify($_POST['slug'] ?? $title);
    $desc   = trim($_POST['description'] ?? '') ?: null;
    $prize  = trim($_POST['prize'] ?? '') ?: null;
    $prizeCoins = max(0, (int)($_POST['prize_coins'] ?? 0));
    $fixDt  = static fn($v) => $v ? (date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $v))) ?: null) : null;
    $starts = $fixDt($_POST['starts_at'] ?? '');
    $ends   = $fixDt($_POST['ends_at'] ?? '');
    if (!$starts || !$ends || strtotime($ends) <= strtotime($starts)) { header('Location: /admin/clan-events' . ($id ? '/' . $id : '') . '?err=dates'); exit; }
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $sort    = (int)($_POST['sort_order'] ?? 0);
    try {
        if ($id) {
            \App\Database::query(
                "UPDATE clan_events SET title=?, slug=?, description=?, metric=?, prize=?, prize_coins=?, starts_at=?, ends_at=?, enabled=?, sort_order=? WHERE id=?",
                [$title, $slug, $desc, $metric, $prize, $prizeCoins, $starts, $ends, $enabled, $sort, $id]
            );
        } else {
            \App\Database::query(
                "INSERT INTO clan_events (title, slug, description, metric, prize, prize_coins, starts_at, ends_at, enabled, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$title, $slug, $desc, $metric, $prize, $prizeCoins, $starts, $ends, $enabled, $sort]
            );
            $id = (int)\App\Database::pdo()->lastInsertId();
        }
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') { header('Location: /admin/clan-events' . ($id ? '/' . $id : '') . '?err=slug'); exit; }
        throw $e;
    }
    \App\AuditLog::record('clan_event.save', 'clan_event', $id);
    header('Location: /admin/clan-events/' . $id . '?ok=1'); exit;
});
\App\Router::post('/admin/clan-events/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin/clan-events?err=csrf'); exit; }
    \App\Database::query("DELETE FROM clan_event_members WHERE event_id = ?", [(int)$id]);
    \App\Database::query("DELETE FROM clan_event_entries WHERE event_id = ?", [(int)$id]);
    \App\Database::query("DELETE FROM clan_events WHERE id = ?", [(int)$id]);
    \App\AuditLog::record('clan_event.delete', 'clan_event', (int)$id);
    header('Location: /admin/clan-events?ok=deleted'); exit;
});
\App\Router::post('/admin/clan-events/{id}/reward', function($id) use ($config) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin/clan-events?err=csrf'); exit; }
    $err = \App\ClanEvent::reward((int)$id);
    \App\AuditLog::record('clan_event.reward', 'clan_event', (int)$id);
    header('Location: /admin/clan-events/' . (int)$id . ($err ? '?err=' . urlencode($err) : '?ok=rewarded')); exit;
});

// ============ EVENTOS & SORTEIOS (público) ============
\App\Router::get('/eventos', function() use ($config) {
    \App\View::display('pages.eventos', ['config' => $config, 'groups' => \App\Events::grouped()]);
});

\App\Router::get('/admin/pages', function() use ($config) {
    \App\Auth::requireCan('pages');
    $pages = \App\Database::fetchAll(
        "SELECT id, slug, title_ptbr, title_enus, published, sort_order, updated_at
           FROM pages ORDER BY sort_order ASC, slug ASC"
    );
    \App\View::display('admin.pages_list', ['config' => $config, 'pages' => $pages]);
});

\App\Router::get('/admin/pages/new', function() use ($config) {
    \App\Auth::requireCan('pages');
    \App\View::display('admin.pages_edit', ['config' => $config, 'page' => null]);
});

\App\Router::get('/admin/pages/{id}/edit', function($id) use ($config) {
    \App\Auth::requireCan('pages');
    $page = \App\Database::fetchOne("SELECT * FROM pages WHERE id = ? LIMIT 1", [(int)$id]);
    if (!$page) { http_response_code(404); echo 'Página não encontrada'; exit; }
    \App\View::display('admin.pages_edit', ['config' => $config, 'page' => $page]);
});

\App\Router::post('/admin/pages/save', function() use ($config) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id         = (int)($_POST['id'] ?? 0);
    $slug       = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $title_pt   = trim($_POST['title_ptbr'] ?? '');
    $title_en   = trim($_POST['title_enus'] ?? '') ?: null;
    // Sanitiza HTML: admin pode injetar <script> mesmo sem querer; defesa em profundidade
    $body_pt    = \App\Html::sanitize((string)($_POST['body_ptbr'] ?? ''));
    $body_en_raw = (string)($_POST['body_enus'] ?? '');
    $body_en    = $body_en_raw === '' ? null : \App\Html::sanitize($body_en_raw);
    $published  = isset($_POST['published']) ? 1 : 0;
    $sort       = (int)($_POST['sort_order'] ?? 0);

    if (!$slug || !$title_pt) {
        header('Location: /admin/pages?err=missing');
        exit;
    }
    if ($id > 0) {
        \App\Database::query(
            "UPDATE pages SET slug=?, title_ptbr=?, title_enus=?, body_ptbr=?, body_enus=?, published=?, sort_order=? WHERE id = ?",
            [$slug, $title_pt, $title_en, $body_pt, $body_en, $published, $sort, $id]
        );
    } else {
        \App\Database::query(
            "INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$slug, $title_pt, $title_en, $body_pt, $body_en, $published, $sort]
        );
    }
    header('Location: /admin/pages?ok=1');
    exit;
});

\App\Router::post('/admin/pages/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("DELETE FROM pages WHERE id = ?", [(int)$id]);
    header('Location: /admin/pages?ok=1');
    exit;
});

// Seção "O Que Você Vai Encontrar" da home (cards de venda). Editável; default = genérico.
\App\Router::get('/admin/home-features', function() use ($config) {
    \App\Auth::requireCan('pages');
    \App\View::display('admin.home_features', ['config' => $config, 'hf' => home_features()]);
});

\App\Router::post('/admin/home-features', function() use ($config) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $icons = $_POST['card_icon']  ?? [];
    $titles = $_POST['card_title'] ?? [];
    $texts = $_POST['card_text']  ?? [];
    $cards = [];
    for ($i = 0; $i < count($titles); $i++) {
        $t = trim((string)($titles[$i] ?? ''));
        if ($t === '') continue; // card sem título = descartado
        $cards[] = [
            'icon'  => mb_substr(trim((string)($icons[$i] ?? '◆')) ?: '◆', 0, 8),
            'title' => mb_substr($t, 0, 60),
            'text'  => mb_substr(trim((string)($texts[$i] ?? '')), 0, 240),
        ];
        if (count($cards) >= 12) break; // teto sano
    }
    $cfg = [
        'enabled'  => !empty($_POST['enabled']),
        'title'    => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 80),
        'subtitle' => mb_substr(trim((string)($_POST['subtitle'] ?? '')), 0, 240),
        'cards'    => $cards,
    ];
    \App\Settings::set('home_features', json_encode($cfg, JSON_UNESCAPED_UNICODE));
    \App\AuditLog::record('home_features.saved', 'settings', null, ['cards' => count($cards)]);
    header('Location: /admin/home-features?ok=1');
    exit;
});

// ============ ADMIN: CENTRAL DE AJUDA ============
\App\Router::get('/admin/help', function() use ($config) {
    \App\Auth::requireCan('pages');
    \App\View::display('admin.help', ['config' => $config, 'articles' => \App\Help::all()]);
});
\App\Router::get('/admin/help/new', function() use ($config) {
    \App\Auth::requireCan('pages');
    \App\View::display('admin.help_edit', ['config' => $config, 'a' => null]);
});
\App\Router::get('/admin/help/{id}/edit', function($id) use ($config) {
    \App\Auth::requireCan('pages');
    $a = \App\Help::get((int)$id);
    if (!$a) { http_response_code(404); echo 'Artigo não encontrado'; exit; }
    \App\View::display('admin.help_edit', ['config' => $config, 'a' => $a]);
});
\App\Router::post('/admin/help/save', function() use ($config, $ROOT) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id    = (int)($_POST['id'] ?? 0);
    $cat   = array_key_exists($_POST['category'] ?? '', \App\Help::CATEGORIES) ? $_POST['category'] : 'comecando';
    $title = trim($_POST['title'] ?? '');
    if ($title === '') { header('Location: /admin/help' . ($id ? '/' . $id . '/edit' : '/new') . '?err=title'); exit; }
    $summary   = mb_substr(trim($_POST['summary'] ?? ''), 0, 300) ?: null;
    $body      = \App\Html::sanitize((string)($_POST['body'] ?? ''));
    $video     = trim($_POST['video_url'] ?? '') ?: null;
    $published = isset($_POST['published']) ? 1 : 0;
    $sort      = (int)($_POST['sort_order'] ?? 0);
    $img       = upload_image($_FILES['image_file'] ?? [], $ROOT . '/public/assets/img/help', 'help', '/assets/img/help');
    if ($id > 0) {
        $slug = \App\Help::makeSlug($title, $id);
        if ($img !== null) {
            \App\Database::query("UPDATE help_articles SET category=?,slug=?,title=?,summary=?,body=?,video_url=?,image=?,published=?,sort_order=? WHERE id=?",
                [$cat, $slug, $title, $summary, $body, $video, $img, $published, $sort, $id]);
        } else {
            \App\Database::query("UPDATE help_articles SET category=?,slug=?,title=?,summary=?,body=?,video_url=?,published=?,sort_order=? WHERE id=?",
                [$cat, $slug, $title, $summary, $body, $video, $published, $sort, $id]);
        }
    } else {
        $slug = \App\Help::makeSlug($title);
        \App\Database::query("INSERT INTO help_articles (category,slug,title,summary,body,video_url,image,published,sort_order) VALUES (?,?,?,?,?,?,?,?,?)",
            [$cat, $slug, $title, $summary, $body, $video, $img, $published, $sort]);
    }
    \App\AuditLog::record('help.saved', 'help', $slug ?? null);
    header('Location: /admin/help?ok=1');
    exit;
});
\App\Router::post('/admin/help/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('pages');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("DELETE FROM help_articles WHERE id = ?", [(int)$id]);
    header('Location: /admin/help?ok=1');
    exit;
});

// Rota pública dinâmica: /{slug} (após todas as rotas específicas, captura qualquer slug válido)
\App\Router::get('/page/{slug}', function($slug) use ($config) {
    $page = \App\Database::fetchOne(
        "SELECT * FROM pages WHERE slug = ? AND published = 1 LIMIT 1", [$slug]
    );
    if (!$page) { http_response_code(404); \App\View::display('pages.404', ['config' => $config]); return; }
    \App\View::display('pages.dynamic_page', ['config' => $config, 'page' => $page]);
});

\App\Router::get('/admin/logs', function() use ($config) {
    \App\Auth::requireCan('logs');
    $logPath = ini_get('error_log') ?: '';
    $lines = [];
    $size = 0;
    $error = null;
    if ($logPath && is_readable($logPath)) {
        $size = filesize($logPath);
        // Lê últimas ~500 linhas (tail)
        $fp = fopen($logPath, 'r');
        if ($fp) {
            $buffer = '';
            $chunk = 32 * 1024; // 32KB
            $pos = max(0, $size - $chunk);
            fseek($fp, $pos);
            $buffer = fread($fp, $chunk);
            fclose($fp);
            $allLines = explode("\n", $buffer);
            if ($pos > 0) array_shift($allLines); // descarta linha quebrada
            $lines = array_slice($allLines, -500);
        }
    } else {
        $error = $logPath ? "Log não acessível: $logPath" : 'error_log do PHP não configurado.';
    }
    \App\View::display('admin.logs', [
        'config' => $config, 'lines' => $lines, 'log_path' => $logPath,
        'log_size' => $size, 'error' => $error,
    ]);
});

// ============ SERVIDORES (admin CRUD + página pública) ============

\App\Router::get('/admin/servers', function() use ($config) {
    \App\Auth::requireCan('servers');
    $servers = \App\Servers::all();
    \App\View::display('admin.servers', ['config' => $config, 'servers' => $servers]);
});

\App\Router::post('/admin/servers/create', function() use ($config) {
    \App\Auth::requireCan('servers');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $name = trim($_POST['name'] ?? '');
    $slug = strtolower(preg_replace('/[^a-z0-9-]+/i', '-', trim($_POST['slug'] ?? '')));
    if (strlen($name) < 2 || strlen($slug) < 2) {
        header('Location: /admin/servers?err=invalid'); exit;
    }
    if (\App\Servers::findBySlug($slug)) {
        header('Location: /admin/servers?err=slug'); exit;
    }
    \App\Database::execute(
        "INSERT INTO servers (name, slug, description, ip, port, battlemetrics_id, agent_token, map, max_players, active, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
        [
            $name, $slug,
            trim($_POST['description'] ?? '') ?: null,
            trim($_POST['ip'] ?? '') ?: null,
            (int)($_POST['port'] ?? 2302) ?: null,
            trim($_POST['battlemetrics_id'] ?? '') ?: null,
            \App\Servers::generateToken(),
            trim($_POST['map'] ?? '') ?: 'Chernarus',
            (int)($_POST['max_players'] ?? 60),
            (int)($_POST['sort_order'] ?? 0),
        ]
    );
    \App\AuditLog::record('server.create', 'server', null, ['name' => $name, 'slug' => $slug]);
    header('Location: /admin/servers?ok=1'); exit;
});

\App\Router::post('/admin/servers/update', function() use ($config) {
    \App\Auth::requireCan('servers');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) { header('Location: /admin/servers'); exit; }
    \App\Database::execute(
        "UPDATE servers SET name = ?, description = ?, ip = ?, port = ?, battlemetrics_id = ?,
                            map = ?, max_players = ?, active = ?, sort_order = ?
         WHERE id = ?",
        [
            trim($_POST['name'] ?? ''),
            trim($_POST['description'] ?? '') ?: null,
            trim($_POST['ip'] ?? '') ?: null,
            (int)($_POST['port'] ?? 0) ?: null,
            trim($_POST['battlemetrics_id'] ?? '') ?: null,
            trim($_POST['map'] ?? '') ?: 'Chernarus',
            (int)($_POST['max_players'] ?? 60),
            isset($_POST['active']) ? 1 : 0,
            (int)($_POST['sort_order'] ?? 0),
            $id,
        ]
    );
    \App\AuditLog::record('server.update', 'server', $id);
    header('Location: /admin/servers?ok=1'); exit;
});

\App\Router::post('/admin/servers/regen-token', function() use ($config) {
    \App\Auth::requireCan('servers');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) { header('Location: /admin/servers'); exit; }
    \App\Database::execute("UPDATE servers SET agent_token = ? WHERE id = ?", [\App\Servers::generateToken(), $id]);
    \App\AuditLog::record('server.regen_token', 'server', $id);
    header('Location: /admin/servers?ok=1&regen=' . $id); exit;
});

\App\Router::post('/admin/servers/delete', function() use ($config) {
    \App\Auth::requireCan('servers');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) { header('Location: /admin/servers'); exit; }
    // Não deleta se ainda tem compras vinculadas — protege histórico
    $hasPurchases = (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM purchases WHERE server_id = ?", [$id]);
    if ($hasPurchases > 0) {
        header('Location: /admin/servers?err=has_purchases&id=' . $id); exit;
    }
    \App\Database::execute("DELETE FROM servers WHERE id = ?", [$id]);
    \App\AuditLog::record('server.delete', 'server', $id);
    header('Location: /admin/servers?ok=1'); exit;
});

\App\Router::get('/servidores', function() use ($config) {
    $servers = \App\Servers::active();
    // Para cada servidor com battlemetrics_id, busca status (cache 60s)
    foreach ($servers as &$s) {
        $s['live'] = $s['battlemetrics_id']
            ? \App\ServerStatus::fetch($s['battlemetrics_id'])
            : null;
    }
    unset($s);
    \App\View::display('pages.servers', ['config' => $config, 'servers' => $servers]);
});

// ============ GALERIA (admin CRUD) ============

\App\Router::get('/admin/gallery', function() use ($config) {
    \App\Auth::requireCan('gallery');
    $items = \App\Database::fetchAll("SELECT * FROM gallery ORDER BY sort_order ASC, id DESC");
    \App\View::display('admin.gallery', ['config' => $config, 'items' => $items]);
});

\App\Router::post('/admin/gallery/upload', function() use ($config, $ROOT) {
    \App\Auth::requireCan('gallery');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $caption = trim($_POST['caption'] ?? '');
    $sort    = (int)($_POST['sort_order'] ?? 0);
    // Ordem auto-incremento sequencial: se não informada (ou 0), vai pro fim (maior + 1).
    if ($sort <= 0) {
        $sort = ((int)\App\Database::fetchColumn("SELECT COALESCE(MAX(sort_order), 0) FROM gallery")) + 1;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        header('Location: /admin/gallery?err=upload'); exit;
    }
    $file = $_FILES['file'];
    if ($file['size'] > 5 * 1024 * 1024) {
        header('Location: /admin/gallery?err=size'); exit;
    }
    $mime = detect_image_mime($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        header('Location: /admin/gallery?err=type'); exit;
    }
    $ext = $allowed[$mime];
    $fname = 'g_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $galleryDir = $ROOT . '/public/assets/img/gallery';
    ensure_writable_dir($galleryDir);
    $dest = $galleryDir . '/' . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        ensure_writable_dir($galleryDir); // força permissão e tenta de novo
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            header('Location: /admin/gallery?err=move'); exit;
        }
    }
    \App\Database::execute(
        "INSERT INTO gallery (filename, caption, sort_order, published) VALUES (?, ?, ?, 1)",
        [$fname, $caption ?: null, $sort]
    );
    \App\AuditLog::record('gallery.upload', 'gallery', null, ['filename' => $fname]);
    header('Location: /admin/gallery?ok=1'); exit;
});

\App\Router::post('/admin/gallery/update', function() use ($config) {
    \App\Auth::requireCan('gallery');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id      = (int)($_POST['id'] ?? 0);
    $caption = trim($_POST['caption'] ?? '');
    $sort    = (int)($_POST['sort_order'] ?? 0);
    $pub     = isset($_POST['published']) ? 1 : 0;
    if ($id < 1) { header('Location: /admin/gallery'); exit; }
    \App\Database::execute(
        "UPDATE gallery SET caption = ?, sort_order = ?, published = ? WHERE id = ?",
        [$caption ?: null, $sort, $pub, $id]
    );
    \App\AuditLog::record('gallery.update', 'gallery', $id);
    header('Location: /admin/gallery?ok=1'); exit;
});

\App\Router::post('/admin/gallery/delete', function() use ($config, $ROOT) {
    \App\Auth::requireCan('gallery');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) { header('Location: /admin/gallery'); exit; }
    $row = \App\Database::fetchOne("SELECT filename FROM gallery WHERE id = ?", [$id]);
    if ($row) {
        $path = $ROOT . '/public/assets/img/gallery/' . $row['filename'];
        if (is_file($path)) @unlink($path);
        \App\Database::execute("DELETE FROM gallery WHERE id = ?", [$id]);
        \App\AuditLog::record('gallery.delete', 'gallery', $id, ['filename' => $row['filename']]);
    }
    header('Location: /admin/gallery?ok=1'); exit;
});

\App\Router::get('/galeria', function() use ($config) {
    $items = \App\Database::fetchAll(
        "SELECT * FROM gallery WHERE published = 1 ORDER BY sort_order ASC, id DESC"
    );
    \App\View::display('pages.gallery', [
        'config' => $config, 'items' => $items,
        'skip_hero_preload' => true, // galeria não tem hero — evita warning de preload sem uso
    ]);
});

\App\Router::get('/admin/audit', function() use ($config) {
    \App\Auth::requireCan('audit');
    $filter = trim($_GET['action'] ?? '');
    $sql = "SELECT * FROM audit_log";
    $params = [];
    if ($filter) { $sql .= " WHERE action LIKE ?"; $params[] = "$filter%"; }
    $sql .= " ORDER BY created_at DESC LIMIT 200";
    $logs = \App\Database::fetchAll($sql, $params);

    $actions = \App\Database::fetchAll(
        "SELECT DISTINCT SUBSTRING_INDEX(action, '.', 1) AS prefix, COUNT(*) AS n
           FROM audit_log GROUP BY prefix ORDER BY n DESC"
    );
    \App\View::display('admin.audit', [
        'config' => $config, 'logs' => $logs, 'filter' => $filter, 'actions' => $actions,
    ]);
});

\App\Router::get('/admin/customize', function() use ($config, $ROOT) {
    \App\Auth::requireCan('customize');
    // Detecta quais imagens de marca já têm versão custom ativa (pra UI mostrar
    // status + botão de reset). Casa por nome-base sem extensão, como o asset().
    $customDir = $ROOT . '/public/assets/img/custom';
    $isCustom = function(string $slot) use ($customDir): bool {
        $stem = pathinfo($slot, PATHINFO_FILENAME);
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $e) {
            if (is_file($customDir . '/' . $stem . '.' . $e)) return true;
        }
        return false;
    };

    // Cores do tema: defaults do template (theme.css :root), sobrescritos pelos
    // valores do theme.override.css se ele existir (pra prefill do color picker).
    $themeDefaults = [
        '--bg-0' => '#0a0612', '--bg-1' => '#120a1f', '--bg-2' => '#1c1230', '--bg-3' => '#271942',
        '--rust' => '#a855f7', '--rust-2' => '#c084fc', '--bone' => '#ede9fe',
        '--moss' => '#16a34a', '--hazard' => '#facc15', '--dim' => '#a1a1aa',
    ];
    $themeColors  = $themeDefaults;
    $overrideFile = $ROOT . '/public/assets/css/theme.override.css';
    $themeActive  = is_file($overrideFile);
    if ($themeActive) {
        $css = (string)file_get_contents($overrideFile);
        foreach ($themeDefaults as $var => $_) {
            if (preg_match('/' . preg_quote($var, '/') . '\s*:\s*(#[0-9a-fA-F]{6})\b/', $css, $m)) {
                $themeColors[$var] = strtolower($m[1]);
            }
        }
    }

    \App\View::display('admin.customize', [
        'config' => $config, 'isCustom' => $isCustom,
        'themeColors' => $themeColors, 'themeActive' => $themeActive,
    ]);
});

\App\Router::post('/admin/customize/theme', function() use ($ROOT) {
    \App\Auth::requireCan('customize');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }

    $overrideFile = $ROOT . '/public/assets/css/theme.override.css';
    $vars  = ['--bg-0','--bg-1','--bg-2','--bg-3','--rust','--rust-2','--bone','--moss','--hazard','--dim'];

    // Lê as cores ATUAIS do override (pra registrar o "antes" no audit — recuperável).
    $readColors = function($file) use ($vars) {
        $out = [];
        if (is_file($file)) {
            $css = (string)file_get_contents($file);
            foreach ($vars as $v) {
                if (preg_match('/' . preg_quote($v, '/') . '\s*:\s*(#[0-9a-fA-F]{3,8})\b/', $css, $m)) {
                    $out[$v] = strtolower($m[1]);
                }
            }
        }
        return $out;
    };
    $before = $readColors($overrideFile);

    if (isset($_POST['reset_theme'])) {
        // Backup antes de apagar: a paleta atual fica em theme.override.css.bak +
        // vai pro audit log. Reset NUNCA mais é perda irreversível.
        if (is_file($overrideFile)) { @copy($overrideFile, $overrideFile . '.bak'); @unlink($overrideFile); }
        \App\AuditLog::record('customize.theme_reset', 'theme', null,
            $before ? ['paleta_removida' => $before, 'backup' => 'theme.override.css.bak'] : []);
        header('Location: /admin/customize?ok=theme_reset'); exit;
    }

    $lines = [];
    $after = [];
    foreach ($vars as $v) {
        $val = $_POST['c_' . ltrim($v, '-')] ?? '';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $val)) continue; // ignora inválido (defesa)
        $val = strtolower($val);
        $lines[] = '    ' . $v . ': ' . $val . ';';
        $after[$v] = $val;
    }
    if (!$lines) { header('Location: /admin/customize?err=theme'); exit; }

    $css = "/* Tema customizado — gerado pelo painel (/admin/customize).\n"
         . "   Gitignored: sobrevive a updates do template. Pra voltar ao padrão, use o botão no painel. */\n"
         . ":root {\n" . implode("\n", $lines) . "\n}\n";

    ensure_writable_dir(dirname($overrideFile));
    if (file_put_contents($overrideFile, $css) === false) {
        ensure_writable_dir(dirname($overrideFile)); // força permissão e tenta de novo
        if (file_put_contents($overrideFile, $css) === false) {
            header('Location: /admin/customize?err=theme_write'); exit;
        }
    }
    // Audit com ANTES -> DEPOIS por cor (diff só do que mudou) — rastreável/recuperável.
    $diff = [];
    foreach ($after as $v => $nv) {
        $ov = $before[$v] ?? '(padrão)';
        if ($ov !== $nv) $diff[$v] = $ov . ' -> ' . $nv;
    }
    \App\AuditLog::record('customize.theme', 'theme', null, [
        'antes'  => $before ?: '(sem override — usava o padrão do template)',
        'depois' => $after,
        'mudou'  => $diff ?: '(sem mudança de valor)',
    ]);
    header('Location: /admin/customize?ok=theme'); exit;
});

// Slots de marca que o cliente pode trocar pelo painel. A imagem custom é gravada
// em assets/img/custom/<stem>.<ext> (gitignored) e o asset() a usa no lugar da
// padrão — então o update do template NUNCA sobrescreve a marca do cliente.
// chave = nome canônico do arquivo padrão (precisa bater com o que o asset() pede).
$BRAND_SLOTS = [
    'logo_semfundo.png'       => 5,  // logo principal (header/footer/emails) — MB
    'logo_semfundo_small.png' => 2,
    'logo.png'                => 2,  // favicon
    'background.png'          => 6,  // hero home
    'background2.png'         => 6,
    'background3.png'         => 6,
    'background4.png'         => 6,
    'background5.png'         => 6,
];

\App\Router::post('/admin/customize/upload', function() use ($BRAND_SLOTS, $ROOT) {
    \App\Auth::requireCan('customize');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }

    $slot = $_POST['slot'] ?? '';
    if (!isset($BRAND_SLOTS[$slot])) { header('Location: /admin/customize?err=slot'); exit; }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        header('Location: /admin/customize?err=upload'); exit;
    }
    $file = $_FILES['file'];
    if ($file['size'] > $BRAND_SLOTS[$slot] * 1024 * 1024) {
        header('Location: /admin/customize?err=size'); exit;
    }
    $mime = detect_image_mime($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) { header('Location: /admin/customize?err=type'); exit; }
    $ext = $allowed[$mime];

    $customDir = $ROOT . '/public/assets/img/custom';
    ensure_writable_dir($customDir);

    $stem = pathinfo($slot, PATHINFO_FILENAME);
    // Remove qualquer versão anterior desse slot (qualquer extensão) antes de gravar.
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $e) {
        $old = $customDir . '/' . $stem . '.' . $e;
        if (is_file($old)) @unlink($old);
    }
    $dest = $customDir . '/' . $stem . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        ensure_writable_dir($customDir); // força permissão e tenta de novo
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            header('Location: /admin/customize?err=move'); exit;
        }
    }
    \App\AuditLog::record('customize.upload', 'brand', null, ['slot' => $slot, 'ext' => $ext]);
    header('Location: /admin/customize?ok=upload'); exit;
});

\App\Router::post('/admin/customize/reset', function() use ($BRAND_SLOTS, $ROOT) {
    \App\Auth::requireCan('customize');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }

    $slot = $_POST['slot'] ?? '';
    if (!isset($BRAND_SLOTS[$slot])) { header('Location: /admin/customize?err=slot'); exit; }

    $customDir = $ROOT . '/public/assets/img/custom';
    $stem = pathinfo($slot, PATHINFO_FILENAME);
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $e) {
        $old = $customDir . '/' . $stem . '.' . $e;
        if (is_file($old)) @unlink($old);
    }
    \App\AuditLog::record('customize.reset', 'brand', null, ['slot' => $slot]);
    header('Location: /admin/customize?ok=reset'); exit;
});

\App\Router::get('/admin/team', function() use ($config) {
    \App\Auth::requireCan('team');
    $admins = \App\Database::fetchAll(
        "SELECT id, username, email, role, created_at, last_login_at FROM admin_users ORDER BY created_at ASC"
    );
    \App\View::display('admin.team', ['config' => $config, 'admins' => $admins]);
});

\App\Router::post('/admin/team/create', function() use ($config) {
    \App\Auth::requireCan('team');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '') ?: null;
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'support';

    if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        header('Location: /admin/team?err=username'); exit;
    }
    if (strlen($password) < 8) {
        header('Location: /admin/team?err=password'); exit;
    }
    if (!array_key_exists($role, \App\Auth::availableRoles())) {
        $role = 'support';
    }
    $exists = \App\Database::fetchColumn("SELECT id FROM admin_users WHERE username = ? LIMIT 1", [$username]);
    if ($exists) {
        header('Location: /admin/team?err=duplicate'); exit;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    \App\Database::query(
        "INSERT INTO admin_users (username, password_hash, email, role) VALUES (?, ?, ?, ?)",
        [$username, $hash, $email, $role]
    );
    \App\AuditLog::record('admin.created', 'admin', $username, ['email' => $email, 'role' => $role]);
    header('Location: /admin/team?ok=created');
    exit;
});

\App\Router::post('/admin/team/{id}/role', function($id) use ($config) {
    \App\Auth::requireCan('team');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $role = $_POST['role'] ?? '';
    if (!array_key_exists($role, \App\Auth::availableRoles())) {
        header('Location: /admin/team?err=invalid_role'); exit;
    }
    // Não permite que admin tire seu próprio super_admin (evita auto-trancar)
    $me = \App\Auth::user();
    if ((int)$id === (int)($me['id'] ?? 0) && $role !== 'super_admin') {
        header('Location: /admin/team?err=self_demote'); exit;
    }
    // Garante que sempre existe pelo menos 1 super_admin
    if ($role !== 'super_admin') {
        $superCount = (int)\App\Database::fetchColumn(
            "SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND id != ?", [(int)$id]
        );
        if ($superCount === 0) {
            header('Location: /admin/team?err=last_super'); exit;
        }
    }
    $username = \App\Database::fetchColumn("SELECT username FROM admin_users WHERE id = ?", [(int)$id]);
    \App\Database::query("UPDATE admin_users SET role = ? WHERE id = ?", [$role, (int)$id]);
    \App\AuditLog::record('admin.role_changed', 'admin', $username, ['new_role' => $role]);
    header('Location: /admin/team?ok=role');
    exit;
});

\App\Router::post('/admin/team/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('team');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $me = \App\Auth::user();
    if ((int)$id === (int)($me['id'] ?? 0)) {
        header('Location: /admin/team?err=self'); exit;
    }
    // Garante que não está deletando o último admin
    $count = (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM admin_users");
    if ($count <= 1) {
        header('Location: /admin/team?err=last'); exit;
    }
    $removedUser = \App\Database::fetchColumn("SELECT username FROM admin_users WHERE id = ?", [(int)$id]);
    \App\Database::query("DELETE FROM admin_users WHERE id = ?", [(int)$id]);
    \App\AuditLog::record('admin.deleted', 'admin', $removedUser);
    header('Location: /admin/team?ok=deleted');
    exit;
});

\App\Router::post('/admin/team/{id}/password', function($id) use ($config) {
    \App\Auth::requireCan('team');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $newPass = $_POST['password'] ?? '';
    if (strlen($newPass) < 8) {
        header('Location: /admin/team?err=password'); exit;
    }
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    \App\Database::query("UPDATE admin_users SET password_hash = ? WHERE id = ?", [$hash, (int)$id]);
    header('Location: /admin/team?ok=password');
    exit;
});

\App\Router::get('/admin/combos', function() use ($config) {
    \App\Auth::requireCan('combos');
    $combos = \App\Database::fetchAll(
        "SELECT * FROM combos ORDER BY sort_order ASC, id DESC"
    );
    $packages = \App\Database::fetchAll(
        "SELECT id, name, coins, bonus_coins, price_brl FROM packages WHERE enabled = 1 ORDER BY sort_order ASC"
    );
    \App\View::display('admin.combos', ['config' => $config, 'combos' => $combos, 'packages' => $packages]);
});

\App\Router::post('/admin/combos/create', function() use ($config) {
    \App\Auth::requireCan('combos');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $slug = strtolower(preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['slug'] ?? ''))));
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $packageIds = $_POST['package_ids'] ?? [];
    if (!is_array($packageIds)) $packageIds = [];
    $packageIds = array_values(array_filter($packageIds, 'is_string'));
    $price = max(0.01, (float)str_replace(',', '.', $_POST['custom_price'] ?? '0'));

    if (!$slug || !$name || count($packageIds) < 2) {
        header('Location: /admin/combos?err=invalid'); exit;
    }
    try {
        \App\Database::query(
            "INSERT INTO combos (slug, name, description, package_ids, custom_price, enabled)
             VALUES (?, ?, ?, ?, ?, 1)",
            [$slug, $name, $description, json_encode($packageIds), $price]
        );
        \App\AuditLog::record('combo.created', 'combo', $slug);
        header('Location: /admin/combos?ok=1');
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') { header('Location: /admin/combos?err=duplicate'); exit; }
        throw $e;
    }
    exit;
});

\App\Router::post('/admin/combos/{id}/toggle', function($id) use ($config) {
    \App\Auth::requireCan('combos');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("UPDATE combos SET enabled = 1 - enabled WHERE id = ?", [(int)$id]);
    header('Location: /admin/combos?ok=1');
    exit;
});

\App\Router::post('/admin/combos/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('combos');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("DELETE FROM combos WHERE id = ?", [(int)$id]);
    header('Location: /admin/combos?ok=1');
    exit;
});

\App\Router::get('/admin/coupons', function() use ($config) {
    \App\Auth::requireCan('coupons');
    $coupons = \App\Database::fetchAll(
        "SELECT * FROM coupons ORDER BY active DESC, created_at DESC"
    );
    $packages = \App\Database::fetchAll("SELECT id, name FROM packages ORDER BY sort_order ASC");
    \App\View::display('admin.coupons', ['config' => $config, 'coupons' => $coupons, 'packages' => $packages]);
});

\App\Router::post('/admin/coupons/create', function() use ($config) {
    \App\Auth::requireCan('coupons');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $code  = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', strtoupper(trim($_POST['code'] ?? ''))));
    $type  = in_array($_POST['discount_type'] ?? '', ['percent','fixed','coins'], true) ? $_POST['discount_type'] : 'percent';
    $value = max(0.01, (float)str_replace(',', '.', $_POST['discount_value'] ?? '0'));
    $maxUses = (int)($_POST['max_uses'] ?? 0) ?: null;
    $perUserLimit = (int)($_POST['per_user_limit'] ?? 0) ?: null;
    $validFrom  = trim($_POST['valid_from']  ?? '') ?: null;
    $validUntil = trim($_POST['valid_until'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '') ?: null;
    // Programa de afiliado/streamer (opcional): nome do streamer + comissão escalonada.
    $affiliateName = trim($_POST['affiliate_name'] ?? '');
    $affiliateName = $affiliateName !== '' ? mb_substr($affiliateName, 0, 120) : null;
    $clampPct = fn($k) => max(0.0, min(100.0, (float) str_replace(',', '.', $_POST[$k] ?? '0')));
    $pct1 = $clampPct('commission_pct_1');
    $pct2 = $clampPct('commission_pct_2');
    $pct3 = $clampPct('commission_pct_3plus');
    // Restrição por pacote (opcional): nenhum marcado = vale pra TODOS (null).
    // Só aceita ids que existem de verdade (defesa).
    $packageIds = null;
    $sel = $_POST['package_ids'] ?? [];
    if (is_array($sel) && $sel) {
        $validIds = array_column(\App\Database::fetchAll("SELECT id FROM packages"), 'id');
        $keep = array_values(array_intersect($validIds, $sel));
        if ($keep) $packageIds = json_encode($keep);
    }

    if (!$code || strlen($code) < 3) {
        header('Location: /admin/coupons?err=code'); exit;
    }
    if ($type === 'percent' && $value > 100) { $value = 100; }

    try {
        \App\Database::query(
            "INSERT INTO coupons (code, discount_type, discount_value, max_uses, per_user_limit, valid_from, valid_until, notes, package_ids, active, affiliate_name, commission_pct_1, commission_pct_2, commission_pct_3plus)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)",
            [$code, $type, $value, $maxUses, $perUserLimit, $validFrom, $validUntil, $notes, $packageIds, $affiliateName, $pct1, $pct2, $pct3]
        );
        \App\AuditLog::record('coupon.created', 'coupon', $code, [
            'type' => $type, 'value' => $value, 'max_uses' => $maxUses,
        ]);
        header('Location: /admin/coupons?ok=created');
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            header('Location: /admin/coupons?err=duplicate');
        } else { throw $e; }
    }
    exit;
});

\App\Router::get('/admin/coupons/{id}/edit', function($id) use ($config) {
    \App\Auth::requireCan('coupons');
    $coupon = \App\Database::fetchOne("SELECT * FROM coupons WHERE id = ? LIMIT 1", [(int)$id]);
    if (!$coupon) { http_response_code(404); echo 'Cupom não encontrado'; exit; }
    $packages = \App\Database::fetchAll("SELECT id, name FROM packages ORDER BY sort_order ASC");
    \App\View::display('admin.coupon_edit', ['config' => $config, 'coupon' => $coupon, 'packages' => $packages]);
});

\App\Router::post('/admin/coupons/{id}/save', function($id) use ($config) {
    \App\Auth::requireCan('coupons');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $existing = \App\Database::fetchOne("SELECT id, code FROM coupons WHERE id = ? LIMIT 1", [(int)$id]);
    if (!$existing) { http_response_code(404); exit; }
    // O código é a identidade do cupom (compras/vínculos apontam pra ele) → NÃO se edita aqui.
    $type  = in_array($_POST['discount_type'] ?? '', ['percent','fixed','coins'], true) ? $_POST['discount_type'] : 'percent';
    $value = max(0.01, (float)str_replace(',', '.', $_POST['discount_value'] ?? '0'));
    if ($type === 'percent' && $value > 100) { $value = 100; }
    $maxUses      = (int)($_POST['max_uses'] ?? 0) ?: null;
    $perUserLimit = (int)($_POST['per_user_limit'] ?? 0) ?: null;
    $validFrom  = trim($_POST['valid_from']  ?? '') ?: null;
    $validUntil = trim($_POST['valid_until'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '') ?: null;
    $affiliateName = trim($_POST['affiliate_name'] ?? '');
    $affiliateName = $affiliateName !== '' ? mb_substr($affiliateName, 0, 120) : null;
    $clampPct = fn($k) => max(0.0, min(100.0, (float) str_replace(',', '.', $_POST[$k] ?? '0')));
    $pct1 = $clampPct('commission_pct_1');
    $pct2 = $clampPct('commission_pct_2');
    $pct3 = $clampPct('commission_pct_3plus');
    $packageIds = null;
    $sel = $_POST['package_ids'] ?? [];
    if (is_array($sel) && $sel) {
        $validIds = array_column(\App\Database::fetchAll("SELECT id FROM packages"), 'id');
        $keep = array_values(array_intersect($validIds, $sel));
        if ($keep) $packageIds = json_encode($keep);
    }
    \App\Database::query(
        "UPDATE coupons SET discount_type = ?, discount_value = ?, max_uses = ?, per_user_limit = ?,
                valid_from = ?, valid_until = ?, notes = ?, package_ids = ?,
                affiliate_name = ?, commission_pct_1 = ?, commission_pct_2 = ?, commission_pct_3plus = ?
         WHERE id = ?",
        [$type, $value, $maxUses, $perUserLimit, $validFrom, $validUntil, $notes, $packageIds,
         $affiliateName, $pct1, $pct2, $pct3, (int)$id]
    );
    \App\AuditLog::record('coupon.updated', 'coupon', $existing['code'], ['type' => $type, 'value' => $value]);
    header('Location: /admin/coupons?ok=updated');
    exit;
});

\App\Router::post('/admin/coupons/{id}/toggle', function($id) use ($config) {
    \App\Auth::requireCan('coupons');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("UPDATE coupons SET active = 1 - active WHERE id = ?", [(int)$id]);
    $code = \App\Database::fetchColumn("SELECT code FROM coupons WHERE id = ?", [(int)$id]);
    \App\AuditLog::record('coupon.toggled', 'coupon', $code);
    header('Location: /admin/coupons?ok=toggled');
    exit;
});

\App\Router::post('/admin/coupons/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('coupons');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $code = \App\Database::fetchColumn("SELECT code FROM coupons WHERE id = ?", [(int)$id]);
    \App\Database::query("DELETE FROM coupons WHERE id = ?", [(int)$id]);
    \App\AuditLog::record('coupon.deleted', 'coupon', $code);
    header('Location: /admin/coupons?ok=deleted');
    exit;
});

// Relatório de cachê por streamer/afiliado (programa "Apoie seu Streamer").
\App\Router::get('/admin/streamers', function() use ($config) {
    \App\Auth::requireCan('coupons');
    \App\View::display('admin.streamers', [
        'config'        => $config,
        'streamers'     => \App\Affiliate::report(),
        'affiliate_on'  => \App\Affiliate::enabled(),
        'allow_switch'  => \App\Affiliate::allowSwitch(),
    ]);
});

// ============ ADMIN: ENTITLEMENTS (VIP / BattlePass) ============
// Loja de VIP/Passe por moedas: liga/desliga + tabela de preços (tier x duração).
\App\Router::get('/admin/vip', function() use ($config) {
    \App\Auth::requireCan('coupons');
    \App\View::display('admin.vip', ['config' => $config, 'vip' => \App\Vip::config(), 'durations' => \App\Vip::DURATIONS]);
});

\App\Router::post('/admin/vip', function() use ($config) {
    \App\Auth::requireCan('coupons');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $cfg = ['enabled' => !empty($_POST['enabled']), 'tiers' => [], 'battlepass' => []];
    foreach (\App\Vip::VIP_TIERS as $key) {
        $prices = [];
        foreach (\App\Vip::DURATIONS as $d) {
            $v = (int)($_POST["price_{$key}_{$d}"] ?? 0);
            if ($v > 0) $prices[(string)$d] = $v;
        }
        $cfg['tiers'][$key] = [
            'enabled' => !empty($_POST["en_{$key}"]),
            'label'   => mb_substr(trim($_POST["label_{$key}"] ?? ''), 0, 60),
            'desc'    => mb_substr(trim($_POST["desc_{$key}"] ?? ''), 0, 200),
            'prices'  => $prices,
        ];
    }
    $bpp = [];
    foreach (\App\Vip::DURATIONS as $d) {
        $v = (int)($_POST["price_bp_{$d}"] ?? 0);
        if ($v > 0) $bpp[(string)$d] = $v;
    }
    $cfg['battlepass'] = [
        'enabled' => !empty($_POST['en_bp']),
        'label'   => mb_substr(trim($_POST['label_bp'] ?? ''), 0, 60),
        'desc'    => mb_substr(trim($_POST['desc_bp'] ?? ''), 0, 200),
        'prices'  => $bpp,
    ];
    \App\Settings::set('vip_store', json_encode($cfg, JSON_UNESCAPED_UNICODE));
    \App\AuditLog::record('vip_store.saved', 'settings', null);
    header('Location: /admin/vip?ok=1');
    exit;
});

\App\Router::get('/admin/entitlements', function() use ($config) {
    \App\Auth::requireCan('coupons');
    $sid = \App\Servers::defaultId();
    $grants = \App\Database::fetchAll(
        "SELECT * FROM player_grants WHERE server_id = ? ORDER BY id DESC LIMIT 100",
        [$sid]
    );
    \App\View::display('admin.entitlements', ['config' => $config, 'grants' => $grants]);
});

\App\Router::post('/admin/entitlements/grant', function() use ($config) {
    \App\Auth::requireCan('coupons');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $sid   = \App\Servers::defaultId();
    $steam = trim($_POST['steam_id'] ?? '');
    $nick  = trim($_POST['nickname'] ?? '') ?: null;
    $type  = in_array($_POST['type'] ?? '', ['vip', 'battlepass'], true) ? $_POST['type'] : 'vip';
    $tier  = null;
    if ($type === 'vip') {
        $tier = in_array($_POST['tier'] ?? '', ['PanelVip1','PanelVip2','PanelVip3','PanelVip4','CUSTOM'], true) ? $_POST['tier'] : 'PanelVip1';
    }
    $days  = max(1, min(3650, (int)($_POST['days'] ?? 30)));
    if (!preg_match('/^7656119[0-9]{10}$/', $steam)) { header('Location: /admin/entitlements?err=steam'); exit; }
    $exp = (new \DateTime("+{$days} days"))->format('Y-m-d');
    \App\Database::query(
        "INSERT INTO player_grants (server_id, steam_id, nickname, type, tier, days, expiration_date, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
        [$sid, $steam, $nick, $type, $tier, $days, $exp]
    );
    header('Location: /admin/entitlements?ok=1');
    exit;
});

\App\Router::post('/admin/entitlements/revoke', function() use ($config) {
    \App\Auth::requireCan('coupons');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $sid = \App\Servers::defaultId();
    $id  = (int)($_POST['id'] ?? 0);
    // applied/pending -> revoked (o agent remove do jogo + ack -> removed)
    \App\Database::query(
        "UPDATE player_grants SET status = 'revoked' WHERE id = ? AND server_id = ? AND status IN ('applied','pending')",
        [$id, $sid]
    );
    header('Location: /admin/entitlements?ok=2');
    exit;
});

\App\Router::get('/admin/announcements', function() use ($config) {
    \App\Auth::requireCan('announcements');
    $list = \App\Database::fetchAll(
        "SELECT * FROM announcements ORDER BY published DESC, created_at DESC"
    );
    // ?edit=ID carrega o anúncio no form (o handler /save já faz UPDATE quando id>0).
    $editId = (int)($_GET['edit'] ?? 0);
    $editing = $editId > 0 ? \App\Database::fetchOne("SELECT * FROM announcements WHERE id = ? LIMIT 1", [$editId]) : null;
    \App\View::display('admin.announcements', ['config' => $config, 'announcements' => $list, 'editing' => $editing]);
});

\App\Router::post('/admin/announcements/save', function() use ($config) {
    \App\Auth::requireCan('announcements');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $id        = (int)($_POST['id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $body      = trim($_POST['body'] ?? '');
    $kind      = in_array($_POST['kind'] ?? '', ['info','warning','danger','success'], true) ? $_POST['kind'] : 'info';
    $cta_label = trim($_POST['cta_label'] ?? '') ?: null;
    $cta_url   = trim($_POST['cta_url'] ?? '') ?: null;
    $starts    = trim($_POST['starts_at'] ?? '') ?: null;
    $ends      = trim($_POST['ends_at']   ?? '') ?: null;
    $published = isset($_POST['published']) ? 1 : 0;
    if (!$title) { header('Location: /admin/announcements?err=missing'); exit; }
    if ($id > 0) {
        \App\Database::query(
            "UPDATE announcements SET title=?, body=?, kind=?, cta_label=?, cta_url=?, starts_at=?, ends_at=?, published=? WHERE id=?",
            [$title, $body, $kind, $cta_label, $cta_url, $starts, $ends, $published, $id]
        );
    } else {
        \App\Database::query(
            "INSERT INTO announcements (title, body, kind, cta_label, cta_url, starts_at, ends_at, published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$title, $body, $kind, $cta_label, $cta_url, $starts, $ends, $published]
        );
    }
    header('Location: /admin/announcements?ok=1');
    exit;
});

\App\Router::post('/admin/announcements/{id}/delete', function($id) use ($config) {
    \App\Auth::requireCan('announcements');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    \App\Database::query("DELETE FROM announcements WHERE id = ?", [(int)$id]);
    header('Location: /admin/announcements?ok=1');
    exit;
});

// ── Recompensa por conquista (configurável) ──────────────────────────
\App\Router::get('/admin/achievements', function() use ($config) {
    \App\Auth::requireCan('settings');
    $rewards = json_decode((string)\App\Settings::get('achievement_rewards', '{}'), true);
    \App\View::display('admin.achievements', [
        'config'     => $config,
        'list'       => \App\Achievements::all(),
        'rewards'    => is_array($rewards) ? $rewards : [],
        'enabled'    => \App\Settings::getBool('achievement_rewards_enabled'),
        'paid_count' => (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM achievement_rewards_log"),
        'paid_coins' => (int)\App\Database::fetchColumn("SELECT COALESCE(SUM(coins),0) FROM achievement_rewards_log"),
        'recent'     => \App\Database::fetchAll("SELECT * FROM achievement_rewards_log ORDER BY id DESC LIMIT 30"),
    ]);
});
\App\Router::post('/admin/achievements', function() use ($config) {
    \App\Auth::requireCan('settings');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    $enabled = !empty($_POST['enabled']) ? '1' : '0';
    $rewards = [];
    foreach (\App\Achievements::all() as $a) {
        $v = (int)($_POST['reward'][$a['slug']] ?? 0);
        if ($v < 0) $v = 0;
        if ($v > 100000) $v = 100000;
        if ($v > 0) $rewards[$a['slug']] = $v;
    }
    // Grava só as 2 chaves dele (Settings::set já é INSERT...ON DUPLICATE + atualiza o cache).
    \App\Settings::set('achievement_rewards', json_encode($rewards, JSON_UNESCAPED_UNICODE));
    \App\Settings::set('achievement_rewards_enabled', $enabled);
    header('Location: /admin/achievements?ok=1');
    exit;
});

// ── Log de logins no site (auditoria) ────────────────────────────────
\App\Router::get('/admin/logins', function() use ($config) {
    \App\Auth::requireCan('logs');
    $q = preg_replace('/[^0-9]/', '', (string)($_GET['steam'] ?? ''));
    $rows = $q !== ''
        ? \App\Database::fetchAll("SELECT * FROM login_log WHERE steam_id = ? ORDER BY id DESC LIMIT 200", [$q])
        : \App\Database::fetchAll("SELECT * FROM login_log ORDER BY id DESC LIMIT 200");
    \App\View::display('admin.logins', [
        'config' => $config,
        'rows'   => $rows,
        'q'      => $q,
        'total'  => (int)\App\Database::fetchColumn("SELECT COUNT(*) FROM login_log"),
        'uniq'   => (int)\App\Database::fetchColumn("SELECT COUNT(DISTINCT steam_id) FROM login_log"),
    ]);
});

\App\Router::get('/admin/support', function() use ($config) {
    \App\Auth::requireCan('support');
    \App\View::display('admin.support', ['config' => $config]);
});

// ============ INTEGRAÇÃO DISCORD (v1.1.0) ============

\App\Router::get('/admin/discord-integration', function() use ($config) {
    \App\Auth::requireCan('discord_integration');
    $token = (string) (\App\Database::fetchColumn(
        "SELECT `value` FROM settings WHERE `key` = 'discord_integration_token'"
    ) ?: '');
    $lastOk = (int) (\App\Database::fetchColumn(
        "SELECT `value` FROM settings WHERE `key` = 'discord_integration_last_ok'"
    ) ?: 0);

    $tokenMasked = '';
    if ($token !== '') {
        $tokenMasked = strlen($token) > 8
            ? substr($token, 0, 4) . str_repeat('•', max(0, strlen($token) - 8)) . substr($token, -4)
            : str_repeat('•', strlen($token));
    }

    // Status: verde<5min · amarelo<1h · vermelho >1h ou nunca
    $age = $lastOk > 0 ? (time() - $lastOk) : PHP_INT_MAX;
    if ($age < 300) {
        $statusColor = 'var(--moss)'; $statusLabel = '🟢 Conectado';
    } elseif ($age < 3600) {
        $statusColor = '#f59e0b'; $statusLabel = '🟡 Inativo recente';
    } else {
        $statusColor = '#dc2626'; $statusLabel = $lastOk > 0 ? '🔴 Desconectado' : '⚫ Nunca testado';
    }

    $log = [];
    try {
        $log = \App\Database::fetchAll(
            "SELECT called_at, ip, action, status_code FROM discord_integration_log
             ORDER BY id DESC LIMIT 10"
        );
    } catch (\Throwable $e) {
        // Tabela pode não existir se migration não rodou ainda
        $log = [];
    }

    $publicUrl = rtrim(($config['app_url'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))), '/');

    \App\View::display('admin.discord_integration', [
        'config' => $config,
        'token' => $token,
        'tokenMasked' => $tokenMasked,
        'lastOk' => $lastOk,
        'statusColor' => $statusColor,
        'statusLabel' => $statusLabel,
        'log' => $log,
        'publicUrl' => $publicUrl,
    ]);
});

\App\Router::post('/admin/discord-integration/regenerate', function() use ($config) {
    \App\Auth::requireCan('discord_integration');
    if (!\App\Csrf::check()) { header('Location: /admin?err=csrf'); exit; }
    // tcp_ + 48 hex chars (24 bytes = 192 bits de entropia) = ~52 chars
    $new = 'tcp_' . bin2hex(random_bytes(24));
    \App\Database::query(
        "INSERT INTO settings (`key`, `value`) VALUES ('discord_integration_token', ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
        [$new]
    );
    // Resetar "last_ok" pra status voltar pra vermelho até bot testar com token novo
    \App\Database::query(
        "INSERT INTO settings (`key`, `value`) VALUES ('discord_integration_last_ok', '0')
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    \App\AuditLog::record('discord_integration.regenerate', 'discord_token');
    header('Location: /admin/discord-integration?flash=' . urlencode('Token novo gerado. Cola no painel do bot.'));
    exit;
});

\App\Router::get('/admin/sparda', function() use ($config) {
    \App\Auth::requireCan('servers');

    $token = (string) ($config['agent_token'] ?? '');
    $tokenMasked = '';
    if ($token !== '') {
        $tokenMasked = strlen($token) > 8
            ? substr($token, 0, 4) . str_repeat('•', max(0, strlen($token) - 8)) . substr($token, -4)
            : str_repeat('•', strlen($token));
    }

    $lastSync = (int) \App\Settings::get('sparda_last_sync', 0);
    $age = $lastSync > 0 ? (time() - $lastSync) : PHP_INT_MAX;
    if ($age < 1800) {
        $statusColor = 'var(--moss)'; $statusLabel = '🟢 Ativa';
    } elseif ($age < 86400) {
        $statusColor = '#f59e0b'; $statusLabel = '🟡 Inativa recente';
    } else {
        $statusColor = '#dc2626'; $statusLabel = $lastSync > 0 ? '🔴 Sem sincronizar' : '⚫ Nunca usada';
    }

    $publicUrl = rtrim(($config['app_url'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))), '/');
    $tok = rawurlencode($token);
    // O mod Sparda cola o SteamID no FINAL da URL (GetRestContext(url)+GET(steamid)).
    // Por isso as URLs terminam em "&steamid=" — o mod completa com o ID do jogador.
    $apiGet  = $publicUrl . '/api/getcoins.php?token=' . $tok . '&steamid=';
    $apiPost = $publicUrl . '/api/postcoins.php?token=' . $tok . '&steamid=';
    $jsonSnippet = "\"EnableWebsiteAPI\": 1,\n\"Api_Get\": \"" . $apiGet . "\",\n\"Api_Post\": \"" . $apiPost . "\"";

    $log = [];
    try {
        $log = \App\Database::fetchAll(
            "SELECT created_at, steam_id, balance_before, balance_after
             FROM balance_log WHERE source = 'sparda' ORDER BY id DESC LIMIT 10"
        );
    } catch (\Throwable $e) {
        $log = [];
    }

    \App\View::display('admin.sparda', [
        'config'      => $config,
        'token'       => $token,
        'tokenMasked' => $tokenMasked,
        'apiGet'      => $apiGet,
        'apiPost'     => $apiPost,
        'jsonSnippet' => $jsonSnippet,
        'lastSync'    => $lastSync,
        'statusColor' => $statusColor,
        'statusLabel' => $statusLabel,
        'log'         => $log,
    ]);
});

\App\Router::get('/admin/players/{id}', function($id) use ($config) {
    \App\Auth::requireCan('players');
    $player = \App\Database::fetchOne("SELECT * FROM players WHERE id = ? LIMIT 1", [(int)$id]);
    if (!$player) { http_response_code(404); echo 'Jogador não encontrado'; exit; }
    $history = \App\Database::fetchAll(
        "SELECT * FROM purchases WHERE steam_id = ? ORDER BY created_at DESC LIMIT 30",
        [$player['steam_id']]
    );
    $balanceLog = \App\Database::fetchAll(
        "SELECT * FROM balance_log WHERE player_id = ? ORDER BY created_at DESC LIMIT 50",
        [(int)$id]
    );
    \App\View::display('admin.player_detail', [
        'config' => $config, 'player' => $player, 'history' => $history, 'balance_log' => $balanceLog,
    ]);
});

// ============ 404 ============
\App\Router::notFound(function() use ($config) {
    http_response_code(404);
    \App\View::display('pages.404', ['config' => $config]);
});

// ============ CSRF GUARD pra POSTs do admin ============
// Rotas de admin que são usadas por quem está DESLOGADO (login + recuperação de senha)
// NÃO podem exigir sessão admin — senão o fluxo de "esqueci a senha" fica inacessível
// (lockout sem saída). Elas já fazem CSRF + rate-limit próprios nos handlers.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $publicAdminPost = in_array($path, ['/admin/login', '/admin/forgot', '/admin/reset'], true);
    if (strpos($path, '/admin/') === 0 && !$publicAdminPost) {
        \App\Auth::requireAdmin();
        if (!\App\Csrf::check()) {
            http_response_code(419);
            die('CSRF token inválido. Volte e tente novamente.');
        }
    }
}

// ============ MAINTENANCE MODE ============
// Bloqueia acesso publico se setting maintenance_enabled = 1.
// Admin continua acessivel (rotas /admin/*), instalador idem.
$maintEnabled = (int)($config['settings']['maintenance_enabled'] ?? 0);
if ($maintEnabled) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $isAdmin   = strpos($path, '/admin') === 0;
    $isApi     = strpos($path, '/api/') === 0;
    $isAsset   = strpos($path, '/assets/') === 0;
    $isInstall = $path === '/install.php';
    if (!$isAdmin && !$isApi && !$isAsset && !$isInstall) {
        http_response_code(503);
        header('Retry-After: 3600');
        \App\View::display('pages.maintenance', ['config' => $config]);
        exit;
    }
}

// ============ DISPATCH ============
\App\Router::dispatch();
