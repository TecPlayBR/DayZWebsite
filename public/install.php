<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// install.php - Wizard de instalacao
// ============================================================
// Form de 1 pagina (dividido em etapas no navegador). Quando o cliente envia,
// valida tudo, conecta no DB, importa schema.sql, gera config/config.php e
// oferece o acesso ao site. Apos sucesso, tenta se auto-renomear.
//
// IMPORTANTE (2026-08-12): esta tela NAO carrega o theme.css do site. Ate esta
// versao o CSS daqui usava var(--bg-1)/var(--rust)/etc, que so existem no
// theme.css -> TODAS as cores caiam pra vazio e a pagina renderizava crua.
// Por isso as cores abaixo sao literais e o instalador e auto-contido.
// ============================================================

declare(strict_types=1);

$ROOT       = dirname(__DIR__);
$configFile = $ROOT . '/config/config.php';
$schemaFile = $ROOT . '/schema.sql';
$exampleFile = $ROOT . '/config/config.example.php';

// =========== PRE-FLIGHT: estrutura do deploy ===========
// As pastas src/, views/, lang/, config/ e o schema.sql ficam um nivel ACIMA de
// public/. Se o upload (FTP) for parcial e faltar alguma, o site quebra de forma
// silenciosa (ex: sem lang/ o menu vira "NAV.RULES"). Conferimos ANTES de instalar.
$structureRequired = [
    'src'            => $ROOT . '/src',
    'views'          => $ROOT . '/views',
    'lang'           => $ROOT . '/lang',
    'lang/pt-br.php' => $ROOT . '/lang/pt-br.php',
    'config'         => $ROOT . '/config',
    'schema.sql'     => $ROOT . '/schema.sql',
];
$structureMissing = [];
foreach ($structureRequired as $label => $path) {
    if (!file_exists($path)) $structureMissing[] = $label;
}

// cli/ e migrations/ NAO bloqueiam a instalacao (o site roda sem elas), mas sem cli/
// nao existe `php cli/migrate.php` pra atualizar depois, e o seed de exemplo nao roda.
// Avisamos aqui em vez de deixar o cliente descobrir isso meses depois.
$structureAviso = [];
foreach (['cli' => $ROOT . '/cli', 'migrations' => $ROOT . '/migrations'] as $label => $path) {
    if (!is_dir($path)) $structureAviso[] = $label;
}

// =========== DIAGNOSTICO: descobre POR QUE faltou ===========
// Ate 2026-08-11 esta tela so dizia "reenvie o template completo por FTP". Conselho
// errado no caso mais comum: os arquivos JA ESTAO no servidor, so que um nivel fundo,
// porque o cliente arrastou a PASTA em vez do CONTEUDO dela. Reenviar nao conserta.
// Um cliente real perdeu tempo com isso. Agora a tela diz o que MOVER, com o nome da
// pasta que existe de verdade no servidor dele.
$deployHint = null;   // ['titulo' => ..., 'passos' => [...]]

if ($structureMissing) {
    // Caso 1: subiram a PASTA public/ dentro do public_html
    //   .../public_html/public/install.php  ->  o instalador esta um nivel fundo
    $aquiNome = basename(__DIR__);
    $paiNome  = basename($ROOT);
    if ($aquiNome === 'public' && !file_exists($ROOT . '/src')) {
        $deployHint = [
            'titulo' => 'você subiu a PASTA public/ em vez do conteúdo dela.',
            'passos' => [
                'Entre em <code>' . htmlspecialchars($paiNome) . '/public/</code> pelo FTP',
                'Mova <strong>tudo o que está dentro</strong> (index.php, install.php, .htaccess, assets/, api/) para <code>' . htmlspecialchars($paiNome) . '/</code>',
                'Apague a pasta <code>public/</code> vazia',
            ],
        ];
    }

    // Caso 2: subiram a pasta do TEMPLATE inteira ao lado do public_html
    //   .../DayZWebsite/src  em vez de  .../src
    $candidatos = [];
    foreach ((glob($ROOT . '/*', GLOB_ONLYDIR) ?: []) as $dir) {
        if (is_dir($dir . '/src') && is_dir($dir . '/views') && file_exists($dir . '/schema.sql')) {
            $candidatos[] = basename($dir);
        }
    }
    if ($candidatos) {
        $pasta = $candidatos[0];
        $deployHint = [
            'titulo' => 'você subiu a PASTA do template em vez do conteúdo dela.',
            'passos' => [
                'Achei o template em <code>' . htmlspecialchars($pasta) . '/</code>, um nível fundo demais',
                'Mova o <strong>conteúdo</strong> de <code>' . htmlspecialchars($pasta) . '/</code> (src/, views/, lang/, config/, migrations/, schema.sql) para a pasta onde ele está',
                'Não mova a pasta <code>public/</code> de dentro dele: o conteúdo dela já deve estar na pasta pública',
            ],
        ];
    }
}

if (file_exists($configFile)) {
    // Ja instalado: 404 puro, sem vazar estrutura/instrucao (defesa contra recon -
    // nao confirma que existe config.php nem ensina como forcar reinstalacao).
    // Pra reinstalar de proposito: apague config/config.php e este 404 some sozinho.
    http_response_code(404);
    die();
}

// =========== PROBE: "Testar conexao" do wizard (AJAX) ===========
// Motivo: erro de banco e de longe a falha nº 1 da instalacao. Antes o cliente so
// descobria depois de preencher a tela inteira e apertar Instalar. Aqui ele testa
// na hora, com a mesma salvaguarda anti-destruicao do install de verdade.
if (($_GET['probe'] ?? '') === 'db' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $h = trim($_POST['db_host'] ?? 'localhost');
    $n = trim($_POST['db_name'] ?? '');
    $u = trim($_POST['db_user'] ?? '');
    $p = $_POST['db_pass'] ?? '';
    if ($n === '' || $u === '') {
        echo json_encode(['ok' => false, 'msg' => 'Preencha database e usuario antes de testar.']);
        exit;
    }
    try {
        $pdo = new PDO("mysql:host=$h;dbname=$n;charset=utf8mb4", $u, $p, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 6,
        ]);
        $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $ocupado = null; $quantos = 0;
        foreach (['admin_users', 'players', 'purchases', 'pages', 'packages', 'settings'] as $t) {
            try {
                if (!$pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t))->fetch()) continue;
                $c = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
                if ($c > 0) { $ocupado = $t; $quantos = $c; break; }
            } catch (\Throwable $e) { /* tabela inacessivel - segue */ }
        }
        if ($ocupado) {
            echo json_encode(['ok' => false, 'msg' =>
                'Conectou, mas esse banco JA TEM dados (tabela ' . $ocupado . ' com ' . $quantos .
                ' registro). O instalador nao vai apagar nada: use um banco vazio. ' .
                'Se a intencao e atualizar o site, o certo e rodar php cli/migrate.php.']);
        } else {
            echo json_encode(['ok' => true, 'msg' => 'Conectado. MySQL ' . $ver . ', banco vazio e pronto pra instalar.']);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Nao conectou: ' . $e->getMessage()]);
    }
    exit;
}

$errors = [];
$success = false;

// =========== PROCESSA POST ===========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $structureMissing) {
    // Bloqueia a instalacao: faltam pastas/arquivos essenciais. Instalar assim
    // geraria um site quebrado (config gravado mas sem lang/views/etc.).
    $errors[] = 'Instalação bloqueada: faltam arquivos essenciais no servidor (veja o aviso acima). '
              . 'Reenvie o template completo por FTP antes de continuar.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$structureMissing) {
    $site_name      = trim($_POST['site_name'] ?? '');
    $site_tagline   = trim($_POST['site_tagline'] ?? '');
    $site_url       = rtrim(trim($_POST['site_url'] ?? ''), '/');
    $db_host        = trim($_POST['db_host'] ?? 'localhost');
    $db_name        = trim($_POST['db_name'] ?? '');
    $db_user        = trim($_POST['db_user'] ?? '');
    $db_pass        = $_POST['db_pass'] ?? '';
    $admin_user     = trim($_POST['admin_user'] ?? '');
    $admin_pass     = $_POST['admin_pass'] ?? '';
    $admin_pass2    = $_POST['admin_pass2'] ?? '';
    $admin_email    = trim($_POST['admin_email'] ?? '');
    $agent_token    = trim($_POST['agent_token'] ?? '');
    $mp_token       = trim($_POST['mp_token'] ?? '');
    $mp_public      = trim($_POST['mp_public'] ?? '');
    $mp_webhook_sec = trim($_POST['mp_webhook_sec'] ?? '');
    $seed_demo      = !empty($_POST['seed_demo']);
    $seedResult     = null;
    $cf_app_id      = trim($_POST['cftools_app_id'] ?? '');
    $cf_secret      = trim($_POST['cftools_secret'] ?? '');
    $cf_server_api  = trim($_POST['cftools_server_api_id'] ?? '');

    // Validacoes basicas
    if ($site_name === '')   $errors[] = 'Nome do site obrigatorio.';
    if ($db_name === '')     $errors[] = 'Nome do banco obrigatorio.';
    if ($db_user === '')     $errors[] = 'Usuario do banco obrigatorio.';
    if (strlen($admin_user) < 3)  $errors[] = 'Usuario admin precisa de pelo menos 3 caracteres.';
    if (strlen($admin_pass) < 8)  $errors[] = 'Senha admin precisa de pelo menos 8 caracteres.';
    if ($admin_pass !== $admin_pass2) $errors[] = 'Senhas admin nao batem.';
    if (strlen($agent_token) < 16) $errors[] = 'AGENT_TOKEN precisa de pelo menos 16 caracteres (sugestao automatica abaixo).';

    // Conecta no banco e importa schema
    if (!$errors) {
        try {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // SALVAGUARDA ANTI-DESTRUIÇÃO: o schema.sql dropa tabelas. Se o banco já
            // tem QUALQUER dado de cliente (não só admin_users), recusamos reinstalar -
            // senão um "reinstalar pra atualizar" apagaria páginas/pacotes/jogadores/compras.
            // Pra ATUALIZAR é subir arquivos + `php cli/migrate.php` (nunca o install.php).
            foreach (['admin_users', 'players', 'purchases', 'pages', 'packages', 'settings'] as $t) {
                try {
                    if (!$pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetch()) continue;
                    $n = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
                    if ($n > 0) {
                        $errors[] = "Esse banco JÁ TEM dados (tabela <code>$t</code> com $n registro(s)). "
                                  . "O instalador NÃO vai rodar pra não apagar o que existe. "
                                  . "Se a intenção é <strong>atualizar</strong>, NÃO use o install.php: "
                                  . "suba os arquivos novos e rode <code>php cli/migrate.php</code>. "
                                  . "Pra instalar do zero de verdade, use um banco vazio.";
                        break;
                    }
                } catch (\Throwable $e) { /* tabela não existe ou inacessível - segue */ }
            }
        } catch (PDOException $e) {
            $errors[] = 'Erro ao conectar no banco: ' . htmlspecialchars($e->getMessage());
        }
    }

    if (!$errors) {
        try {
            // Importa schema.sql
            $sql = file_get_contents($schemaFile);
            // PDO em geral nao suporta multiplas statements via prepare - splitamos por ';'
            // Estrategia simples: roda direto via exec (suporta multi-statement no mysql native)
            $pdo->exec($sql);

            // RODA as migrations em cima do schema (são idempotentes: CREATE/ADD IF NOT
            // EXISTS). Assim, mesmo que o schema.sql fique levemente atrás das migrations,
            // a instalação do zero sai COMPLETA - nada de tabela faltando pro cliente (ex:
            // player_grants, achievement_rewards_log, login_log). Depois marca como aplicadas.
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(150) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $stmtMig = $pdo->prepare("INSERT IGNORE INTO schema_migrations (filename) VALUES (?)");
                $migFiles = glob($ROOT . '/migrations/*.sql') ?: [];
                sort($migFiles); // ordem lexical = ordem de versão
                foreach ($migFiles as $mf) {
                    try { $pdo->exec((string) file_get_contents($mf)); }
                    catch (\Throwable $e) { /* "já existe" é benigno - o schema.sql já tinha o efeito */ }
                    $stmtMig->execute([basename($mf)]);
                }
            } catch (\Throwable $e) { /* não bloqueia o install */ }

            // Cria admin user com senha bcrypt
            $hash = password_hash($admin_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email) VALUES (?, ?, ?)");
            $stmt->execute([$admin_user, $hash, $admin_email ?: null]);

            // Atualiza settings.site_name e tagline (sobrescreve seeds)
            $up = $pdo->prepare("UPDATE settings SET `value` = ? WHERE `key` = ?");
            $up->execute([$site_name, 'site_name']);
            if ($site_tagline) $up->execute([$site_tagline, 'site_tagline']);

            // Dados-demo opcionais: deixa o site "vivo" (jogadores, compras, reviews,
            // anúncios fictícios) já no 1º acesso. Marcados como demo (steam 76561197000*
            // e títulos "[demo]") - remova depois com: php cli/seed-demo.php --clean
            // ATENÇÃO ao is_file() antes do require: se a pasta cli/ não subiu por FTP,
            // um `require` de arquivo ausente é FATAL e NÃO é pego por try/catch. Como o
            // checkbox de dados de exemplo vem MARCADO por padrão, isso matava a instalação
            // exatamente aqui: schema já importado e admin já criado, mas o config.php ainda
            // não gravado. Na segunda tentativa a salvaguarda anti-destruição via o banco com
            // dados e recusava, deixando o cliente travado sem entender por quê.
            // Bug real: o INSTALACAO.md esquecia de listar a pasta cli/ nos arquivos a subir.
            if ($seed_demo) {
                if (is_file($ROOT . '/cli/seed-demo-lib.php')) {
                    require_once $ROOT . '/cli/seed-demo-lib.php';
                    try { $seedResult = seed_demo_data($pdo); }
                    catch (\Throwable $e) { /* não bloqueia a instalação se o seed falhar */ }
                } else {
                    // Instala normalmente, só sem os dados fictícios, e avisa na tela final.
                    $seedIndisponivel = true;
                }
            }

            // Gera config.php
            $configContent = "<?php\n// Auto-gerado pelo install.php em " . date('Y-m-d H:i:s') . "\n\nreturn [\n";
            $configContent .= "    'site_name'       => " . var_export($site_name, true) . ",\n";
            $configContent .= "    'site_tagline'    => " . var_export($site_tagline ?: '', true) . ",\n";
            $configContent .= "    'site_url'        => " . var_export($site_url ?: ('http://' . $_SERVER['HTTP_HOST']), true) . ",\n";
            $configContent .= "    'default_locale'  => 'pt-br',\n\n";
            $configContent .= "    'db' => [\n";
            $configContent .= "        'host'    => " . var_export($db_host, true) . ",\n";
            $configContent .= "        'name'    => " . var_export($db_name, true) . ",\n";
            $configContent .= "        'user'    => " . var_export($db_user, true) . ",\n";
            $configContent .= "        'pass'    => " . var_export($db_pass, true) . ",\n";
            $configContent .= "        'charset' => 'utf8mb4',\n";
            $configContent .= "    ],\n\n";
            $configContent .= "    'admin_session_ttl' => 3600,\n\n";
            $configContent .= "    'agent_token' => " . var_export($agent_token, true) . ",\n\n";
            $configContent .= "    'mercado_pago' => [\n";
            $configContent .= "        'access_token'      => " . var_export($mp_token, true) . ",\n";
            $configContent .= "        'public_key'        => " . var_export($mp_public ?? '', true) . ",\n";
            $configContent .= "        'webhook_secret'    => " . var_export($mp_webhook_sec, true) . ",\n";
            $configContent .= "        'currency'          => 'BRL',\n";
            $configContent .= "        'min_purchase_brl'  => 5,\n";
            $configContent .= "    ],\n\n";
            $configContent .= "    // CFTools Cloud: habilita ranking de gameplay (kills/zumbis) + drop das caixas no jogo.\n";
            $configContent .= "    // Pode deixar vazio aqui e preencher depois em /admin (Configurações).\n";
            $configContent .= "    'cftools' => [\n";
            $configContent .= "        'app_id'        => " . var_export($cf_app_id, true) . ",\n";
            $configContent .= "        'secret'        => " . var_export($cf_secret, true) . ",\n";
            $configContent .= "        'server_api_id' => " . var_export($cf_server_api, true) . ",\n";
            $configContent .= "    ],\n\n";
            $configContent .= "    'mail' => [\n";
            $configContent .= "        'from'      => " . var_export('no-reply@' . preg_replace('#^https?://([^/]+).*#', '$1', $site_url ?: ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))), true) . ",\n";
            $configContent .= "        'from_name' => " . var_export($site_name, true) . ",\n";
            $configContent .= "        // Email via mail() do PHP. Pra entrega confiavel (sem cair em spam), troque por SMTP/remetente verificado do seu dominio.\n";
            $configContent .= "    ],\n\n";
            $configContent .= "    'show_payment_methods' => true,\n";
            $configContent .= "    'show_language_select' => true,\n";
            $configContent .= "];\n";

            if (!is_writable(dirname($configFile))) {
                $errors[] = 'Pasta config/ nao e gravavel. Ajuste permissoes (chmod 755 config/).';
            } else {
                file_put_contents($configFile, $configContent);
                $success = true;
                // Tenta auto-renomear o install.php pra travar acesso futuro (hardening)
                $self = __FILE__;
                $renamed = $self . '.installed-' . date('Ymd-His');
                @rename($self, $renamed);
                $installRemoved = !file_exists($self);
            }
        } catch (Throwable $e) {
            $errors[] = 'Erro durante a instalacao: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Helper pra gerar token sugerido
function suggested_token(): string {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $token = '';
    for ($i = 0; $i < 48; $i++) {
        $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $token;
}

// Versao do template (so pra exibir). Le do CHANGELOG.md se ele tiver subido.
$tplVersao = '';
if (is_readable($ROOT . '/CHANGELOG.md')) {
    $cabeca = (string) @file_get_contents($ROOT . '/CHANGELOG.md', false, null, 0, 2048);
    if (preg_match('/^##\s*\[?v?([0-9]+\.[0-9]+\.[0-9]+)/m', $cabeca, $m)) $tplVersao = 'v' . $m[1];
}

$urlPadrao = ($_POST['site_url'] ?? '')
    ?: ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

?><!DOCTYPE html>
<html lang="pt-br" class="nojs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" href="assets/img/tecplay.png">
<title>Instalação · DayZ Website Template</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* Cores LITERAIS de proposito: esta tela nao carrega o theme.css do site.
   Paleta = identidade Tecplay (roxo #a855f7 + dourado #facc15). */
:root{
  --bg:#0a0512; --bg-soft:#120a1f; --card:#170f29; --card-2:#1d1333;
  --line:rgba(168,85,247,.16); --line-2:rgba(168,85,247,.34);
  --brand:#a855f7; --brand-2:#c084fc; --brand-3:#7c3aed; --gold:#facc15;
  --ink:#ece7f8; --ink-2:#c9c1e0; --dim:#9a94ad;
  --ok:#22c55e; --ok-ink:#86efac; --err:#f87171; --err-ink:#fca5a5;
  --radius:16px;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{
  background:var(--bg); color:var(--ink);
  font-family:'Inter',system-ui,-apple-system,Segoe UI,sans-serif;
  line-height:1.55; padding:clamp(1rem,4vw,2.5rem) 1rem 3rem;
  -webkit-font-smoothing:antialiased;
}
/* fundo vivo, mas discreto: duas auroras + grade tenue */
body::before{
  content:''; position:fixed; inset:0; z-index:-2; pointer-events:none;
  background:
    radial-gradient(60rem 34rem at 12% -8%, rgba(124,58,237,.30), transparent 62%),
    radial-gradient(52rem 30rem at 92% 6%, rgba(168,85,247,.20), transparent 60%),
    radial-gradient(46rem 34rem at 50% 108%, rgba(250,204,21,.07), transparent 62%);
  animation:respira 16s ease-in-out infinite alternate;
}
body::after{
  content:''; position:fixed; inset:0; z-index:-1; pointer-events:none; opacity:.5;
  background-image:
    linear-gradient(rgba(168,85,247,.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(168,85,247,.05) 1px, transparent 1px);
  background-size:64px 64px;
  mask-image:radial-gradient(70% 55% at 50% 0%, #000 0%, transparent 78%);
  -webkit-mask-image:radial-gradient(70% 55% at 50% 0%, #000 0%, transparent 78%);
}
@keyframes respira{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(0,-1.6%,0) scale(1.05)}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}

.wrap{max-width:780px;margin:0 auto}
a{color:var(--brand-2)}
code{font-family:ui-monospace,'Cascadia Code',Consolas,monospace;font-size:.88em;
  background:rgba(0,0,0,.32);padding:.08rem .34rem;border-radius:5px;color:var(--brand-2);word-break:break-word}

/* ---------- topo ---------- */
.brand{display:flex;align-items:center;gap:1rem;justify-content:center;margin-bottom:.4rem}
.brand-mark{width:58px;height:58px;object-fit:contain;filter:drop-shadow(0 0 18px rgba(168,85,247,.55));flex:none}
.brand-txt h1{
  font-family:'Black Ops One',cursive,sans-serif; font-size:clamp(1.5rem,4.4vw,2.1rem);
  letter-spacing:.03em; line-height:1.1;
  background:linear-gradient(96deg,#ffffff 0%,var(--brand-2) 42%,var(--brand) 78%);
  -webkit-background-clip:text;background-clip:text;color:transparent;
}
.brand-txt p{color:var(--dim);font-size:.86rem;margin-top:.15rem}
.pill{
  display:inline-block;margin-left:.45rem;padding:.08rem .5rem;border-radius:999px;
  background:rgba(250,204,21,.12);border:1px solid rgba(250,204,21,.28);
  color:var(--gold);font-size:.72rem;font-weight:700;letter-spacing:.04em;vertical-align:1px;
}
.lead{text-align:center;color:var(--ink-2);font-size:.95rem;margin:.9rem auto 1.6rem;max-width:52ch}

/* ---------- stepper ---------- */
.steps{position:relative;display:grid;grid-template-columns:repeat(5,1fr);gap:.4rem;margin-bottom:1.4rem}
.steps::before,.steps .fill{
  content:'';position:absolute;top:17px;left:10%;right:10%;height:2px;border-radius:2px;background:rgba(168,85,247,.18)
}
.steps .fill{right:auto;width:0;background:linear-gradient(90deg,var(--brand-3),var(--brand-2));
  box-shadow:0 0 12px rgba(168,85,247,.6);transition:width .45s cubic-bezier(.4,0,.2,1)}
.stp{position:relative;display:flex;flex-direction:column;align-items:center;gap:.4rem;text-align:center}
.stp b{
  width:36px;height:36px;border-radius:50%;display:grid;place-items:center;
  background:var(--bg-soft);border:2px solid rgba(168,85,247,.25);color:var(--dim);
  font-size:.9rem;font-weight:700;transition:all .3s
}
.stp span{font-size:.7rem;color:var(--dim);font-weight:600;letter-spacing:.02em}
.stp.on b{border-color:var(--brand);color:#fff;background:linear-gradient(160deg,var(--brand-3),var(--brand));
  box-shadow:0 0 0 5px rgba(168,85,247,.14),0 6px 18px rgba(124,58,237,.4);transform:scale(1.06)}
.stp.on span{color:var(--ink)}
.stp.done b{border-color:var(--brand-2);color:var(--brand-2);background:rgba(168,85,247,.12)}
.stp.done b::after{content:'✓'}
.stp.done b i{display:none}
@media (max-width:560px){ .stp span{display:none} .steps::before,.steps .fill{top:17px} }

/* ---------- cards ---------- */
.card{
  background:linear-gradient(168deg,var(--card) 0%,var(--card-2) 100%);
  border:1px solid var(--line); border-radius:var(--radius);
  padding:clamp(1.2rem,3.4vw,1.9rem); margin-bottom:1.1rem;
  box-shadow:0 20px 50px -28px rgba(0,0,0,.9), inset 0 1px 0 rgba(255,255,255,.04);
}
.card>h2{
  font-size:1.08rem;font-weight:800;letter-spacing:.01em;display:flex;align-items:center;gap:.6rem;
  padding-bottom:.85rem;margin-bottom:1.15rem;border-bottom:1px solid var(--line)
}
.card>h2 .ic{
  width:32px;height:32px;border-radius:9px;flex:none;display:grid;place-items:center;
  background:rgba(168,85,247,.14);border:1px solid var(--line-2);color:var(--brand-2)
}
.card>h2 small{font-weight:500;color:var(--dim);font-size:.76rem;margin-left:auto;text-align:right}
.opt{color:var(--gold);font-weight:600}

.row{margin-bottom:1.05rem}
.row:last-child{margin-bottom:0}
.row label{display:block;font-size:.82rem;font-weight:600;margin-bottom:.4rem;color:var(--ink)}
.row label small{color:var(--dim);font-weight:400}
.row input[type=text],.row input[type=email],.row input[type=password],.row textarea{
  width:100%;padding:.72rem .9rem;background:rgba(10,5,18,.75);
  border:1px solid var(--line);border-radius:10px;color:var(--ink);
  font-family:inherit;font-size:.93rem;transition:border-color .18s,box-shadow .18s,background .18s
}
.row input::placeholder{color:#6c6580}
.row input:focus{outline:none;border-color:var(--brand);background:rgba(10,5,18,.95);
  box-shadow:0 0 0 4px rgba(168,85,247,.15)}
.row input.bad{border-color:var(--err);box-shadow:0 0 0 4px rgba(248,113,113,.12)}
.row input.good{border-color:rgba(34,197,94,.55)}
.split{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media (max-width:560px){.split{grid-template-columns:1fr}}
.hint{font-size:.78rem;color:var(--dim);margin-top:.35rem}
.hint a{color:var(--brand-2)}
.msg{font-size:.78rem;margin-top:.35rem;min-height:1em;color:var(--err-ink);font-weight:500}
.msg.ok{color:var(--ok-ink)}

/* senha: olho + medidor */
.pw{position:relative}
.pw .eye{
  position:absolute;right:.55rem;top:50%;transform:translateY(-50%);
  background:none;border:0;color:var(--dim);cursor:pointer;padding:.3rem;line-height:0;border-radius:6px
}
.pw .eye:hover{color:var(--brand-2);background:rgba(168,85,247,.1)}
.bars{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;margin-top:.5rem}
.bars i{height:4px;border-radius:3px;background:rgba(168,85,247,.15);transition:background .25s}
.bars.s1 i:nth-child(-n+1){background:#ef4444}
.bars.s2 i:nth-child(-n+2){background:#f59e0b}
.bars.s3 i:nth-child(-n+3){background:#84cc16}
.bars.s4 i{background:var(--ok)}

/* token */
.token{
  display:flex;align-items:center;gap:.6rem;background:rgba(10,5,18,.8);
  border:1px dashed var(--line-2);border-radius:10px;padding:.65rem .8rem;margin-bottom:.85rem
}
.token code{
  font-family:ui-monospace,'Cascadia Code',Consolas,monospace;font-size:.8rem;color:var(--gold);
  word-break:break-all;flex:1;user-select:all
}
.mini{
  background:rgba(168,85,247,.14);border:1px solid var(--line-2);color:var(--brand-2);
  border-radius:8px;padding:.4rem .7rem;font:600 .76rem/1 'Inter',sans-serif;cursor:pointer;
  white-space:nowrap;transition:all .18s
}
.mini:hover{background:rgba(168,85,247,.26);color:#fff}
.mini.okflash{background:rgba(34,197,94,.2);border-color:rgba(34,197,94,.45);color:var(--ok-ink)}

/* checkbox */
.check{display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;
  background:rgba(168,85,247,.06);border:1px solid var(--line);border-radius:12px;padding:.9rem 1rem}
.check:hover{border-color:var(--line-2)}
.check input{width:19px;height:19px;margin-top:.15rem;accent-color:var(--brand);flex:none;cursor:pointer}
.check span{font-size:.87rem;color:var(--ink-2)}

/* revisao */
.rev{display:grid;gap:.1rem}
.rev div{display:flex;justify-content:space-between;gap:1rem;padding:.6rem 0;border-bottom:1px solid rgba(168,85,247,.08);font-size:.87rem}
.rev div:last-child{border-bottom:0}
.rev dt{color:var(--dim)}
.rev dd{color:var(--ink);font-weight:600;text-align:right;word-break:break-all}
.rev dd.vazio{color:var(--dim);font-weight:400;font-style:italic}

/* ---------- botoes ---------- */
.nav{display:flex;gap:.8rem;align-items:center;justify-content:space-between;margin-top:.4rem}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:.55rem;
  padding:.85rem 1.7rem;border:0;border-radius:11px;cursor:pointer;text-decoration:none;
  font:700 .92rem/1 'Inter',sans-serif;letter-spacing:.01em;
  background:linear-gradient(135deg,var(--brand-3),var(--brand));color:#fff;
  box-shadow:0 10px 26px -12px rgba(124,58,237,.9);transition:transform .16s,box-shadow .16s,filter .16s
}
.btn:hover{transform:translateY(-2px);box-shadow:0 16px 34px -12px rgba(124,58,237,1);filter:brightness(1.08)}
.btn:active{transform:translateY(0)}
.btn.ghost{background:transparent;border:1px solid var(--line-2);color:var(--ink-2);box-shadow:none}
.btn.ghost:hover{background:rgba(168,85,247,.1);color:#fff}
.btn.go{background:linear-gradient(135deg,#16a34a,#22c55e);box-shadow:0 10px 26px -12px rgba(34,197,94,.9)}
.btn[disabled]{opacity:.55;cursor:progress;transform:none}
.btn .spin{width:15px;height:15px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;
  border-radius:50%;animation:gira .7s linear infinite;display:none}
.btn.loading .spin{display:block}
@keyframes gira{to{transform:rotate(360deg)}}

/* ---------- alertas ---------- */
.alert{border-radius:12px;padding:1.05rem 1.25rem;margin-bottom:1.3rem;font-size:.9rem;border:1px solid transparent}
/* so o strong-titulo vira bloco; strong no meio do texto continua inline
   (senao "todos os privilegios" quebra sozinho numa linha) */
.alert>strong:first-child{display:block;margin-bottom:.15rem}
.alert-err{background:rgba(248,113,113,.09);border-color:rgba(248,113,113,.3);color:var(--err-ink)}
.alert-ok{background:rgba(34,197,94,.09);border-color:rgba(34,197,94,.32);color:var(--ok-ink)}
.alert-info{background:rgba(168,85,247,.09);border-color:var(--line-2);color:var(--ink-2)}
.alert ul,.alert ol{margin:.55rem 0 0 1.25rem}
.alert li{margin:.25rem 0}
.alert code{background:rgba(0,0,0,.35);padding:.1rem .35rem;border-radius:5px;font-size:.85em}
.alert pre{margin-top:.5rem;padding:.8rem;background:rgba(0,0,0,.35);border-radius:9px;
  overflow:auto;font-size:.8rem;line-height:1.6;color:var(--ink-2)}

/* ---------- etapas ---------- */
.step{display:none}
.step.on{display:block;animation:sobe .32s cubic-bezier(.2,.7,.3,1)}
@keyframes sobe{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
/* sem JS: mostra tudo e some com a navegacao do wizard */
.nojs .step{display:block}
.nojs .steps,.nojs .nav .wiz{display:none}
.nojs .nav .fim{display:inline-flex}
.nav .fim{display:none}

/* ---------- sucesso ---------- */
.done-hero{text-align:center;padding:.6rem 0 1.4rem}
.done-hero .tick{
  width:74px;height:74px;margin:0 auto 1rem;border-radius:50%;display:grid;place-items:center;
  background:rgba(34,197,94,.12);border:2px solid rgba(34,197,94,.45);color:var(--ok);
  animation:pop .5s cubic-bezier(.2,1.4,.4,1)
}
@keyframes pop{0%{transform:scale(.5);opacity:0}100%{transform:scale(1);opacity:1}}
.done-hero h2{font-family:'Black Ops One',cursive,sans-serif;font-size:1.5rem;color:var(--ink)}
.done-hero p{color:var(--dim);font-size:.92rem;margin-top:.35rem}
.done-list{list-style:none;display:grid;gap:.55rem;margin:1.2rem 0}
/* block + ::before absoluto de proposito: com display:flex o <code> de dentro
   virava flex-item e a frase saia fora de ordem na tela. */
.done-list li{position:relative;padding-left:1.55rem;font-size:.89rem;color:var(--ink-2)}
.done-list li::before{content:'✓';position:absolute;left:0;top:0;color:var(--ok);font-weight:800}
.cta{display:flex;gap:.8rem;flex-wrap:wrap;justify-content:center;margin-top:1.4rem}

footer{text-align:center;color:var(--dim);font-size:.78rem;margin-top:2rem;opacity:.75}
footer b{color:var(--brand-2)}
</style>
</head>
<body>
<div class="wrap">

<header class="brand">
    <img class="brand-mark" src="assets/img/tecplay.png" alt="Tecplay" onerror="this.style.display='none'">
    <div class="brand-txt">
        <h1>INSTALADOR</h1>
        <p>DayZ Website Template<?= $tplVersao ? '<span class="pill">' . htmlspecialchars($tplVersao) . '</span>' : '' ?></p>
    </div>
</header>

<?php if ($structureMissing): ?>
    <div class="alert alert-err">
        <strong>Faltam arquivos essenciais no servidor, ainda não dá pra instalar.</strong>
        <p style="margin-top:.6rem;">O upload por FTP parece ter ficado incompleto. Estas pastas/arquivos
        precisam ficar <strong>um nível ACIMA</strong> da pasta pública (ao lado de <code>src/</code>),
        e não foram encontrados:</p>
        <ul>
            <?php foreach ($structureMissing as $m): ?><li><code><?= htmlspecialchars($m) ?></code></li><?php endforeach; ?>
        </ul>
        <?php if ($deployHint): ?>
            <p style="margin-top:.9rem;"><strong>Achei o problema: <?= $deployHint['titulo'] ?></strong></p>
            <p style="margin-top:.4rem;">Os arquivos <strong>já estão no servidor</strong>, só num nível
            fundo demais. Não precisa reenviar nada, é só mover:</p>
            <ol>
                <?php foreach ($deployHint['passos'] as $p): ?><li><?= $p ?></li><?php endforeach; ?>
            </ol>
            <p style="margin-top:.6rem;">Depois recarregue esta página.</p>
        <?php else: ?>
            <p style="margin-top:.6rem;">Reenvie o template <strong>completo</strong> (todas as pastas do ZIP) por
            FTP e recarregue esta página.</p>
        <?php endif; ?>

        <p style="margin-top:.9rem;">A estrutura correta é esta (o <code>install.php</code> fica
        <strong>direto</strong> na pasta pública, nunca dentro de outra pasta):</p>
        <pre>
&lt;pasta acima da publica&gt;/     <span style="opacity:.6"># no cPanel/Hostinger com dominio proprio:
                              # domains/SEUDOMINIO.com/</span>
├── src/  views/  lang/  config/  migrations/  schema.sql
└── <?= htmlspecialchars(basename($ROOT)) ?>/          <span style="opacity:.6"># a pasta publica (docroot)</span>
    ├── index.php
    ├── install.php          <span style="opacity:.6"># voce esta aqui: <?= htmlspecialchars(basename(__DIR__)) ?>/install.php</span>
    ├── .htaccess
    ├── assets/
    └── api/</pre>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="card">
        <div class="done-hero">
            <div class="tick">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h2>SITE INSTALADO</h2>
            <p>Tudo pronto. Seu servidor já tem site.</p>
        </div>
        <ul class="done-list">
            <li>Banco criado e populado com 6 pacotes de moedas (editáveis no painel)</li>
            <li>Usuário admin <code><?= htmlspecialchars($admin_user) ?></code> criado</li>
            <li>Configuração gravada em <code>config/config.php</code></li>
            <?php if (!empty($seedIndisponivel)): ?>
            <li>Dados de exemplo <strong>não foram criados</strong>: a pasta <code>cli/</code> não
                está no servidor. O site funciona normalmente, mas suba essa pasta por FTP (ela
                fica ao lado de <code>src/</code>) porque ela também é necessária pra atualizar
                o site depois.</li>
            <?php endif; ?>
            <?php if ($seedResult): ?>
            <li>Dados de exemplo: <?= (int)$seedResult['players'] ?> jogadores,
                <?= (int)$seedResult['purchases'] ?> compras, <?= (int)$seedResult['reviews'] ?> avaliações,
                <?= (int)$seedResult['announcements'] ?> anúncios.
                Antes de abrir pro público, remova com <code>php cli/seed-demo.php --clean</code></li>
            <?php endif; ?>
        </ul>
        <div class="cta">
            <a href="/" class="btn">Acessar o site</a>
            <a href="/admin" class="btn ghost">Entrar no painel</a>
        </div>
    </div>

    <div class="alert <?= !empty($installRemoved) ? 'alert-ok' : 'alert-err' ?>">
        <?php if (!empty($installRemoved)): ?>
            <strong>Segurança OK</strong>
            O instalador se auto-renomeou para <code>install.php.installed-*</code>, então ninguém mais
            consegue rodá-lo pela URL. Se quiser, apague esse arquivo pelo FTP.
        <?php else: ?>
            <strong>Apague o install.php agora</strong>
            Não consegui me renomear sozinho (permissão do servidor). Apague o arquivo
            <code>install.php</code> da pasta pública via FTP ou Gerenciador de Arquivos.
        <?php endif; ?>
    </div>

<?php elseif (!$structureMissing): ?>

    <p class="lead">Preencha as etapas abaixo. Leva poucos minutos e você pode testar a conexão
    do banco antes de instalar. Nada é gravado até você confirmar na última etapa.</p>

    <?php if ($structureAviso): ?>
        <div class="alert alert-info">
            <strong>Dá pra instalar, mas falta uma pasta no servidor</strong>
            Não encontrei
            <?php foreach ($structureAviso as $i => $a): ?><?= $i ? ' e ' : '' ?><code><?= htmlspecialchars($a) ?>/</code><?php endforeach; ?>
            ao lado de <code>src/</code>. O site vai funcionar, mas
            <?= in_array('cli', $structureAviso, true)
                ? 'sem a <code>cli/</code> você não consegue rodar <code>php cli/migrate.php</code> pra atualizar o site depois, e os dados de exemplo não serão criados.'
                : 'sem a <code>migrations/</code> as atualizações de banco não têm o que aplicar.' ?>
            Suba essa pasta por FTP e recarregue esta página.
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-err">
            <strong>Corrija isto antes de continuar:</strong>
            <ul>
                <?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
            </ul>
            <p style="margin-top:.6rem;font-size:.85em;opacity:.85">Por segurança, os campos de senha
            voltam em branco. Preencha-os de novo.</p>
        </div>
    <?php endif; ?>

    <div class="steps" id="stepper">
        <div class="fill" id="fill"></div>
        <div class="stp on" data-go="0"><b><i>1</i></b><span>Site</span></div>
        <div class="stp" data-go="1"><b><i>2</i></b><span>Banco</span></div>
        <div class="stp" data-go="2"><b><i>3</i></b><span>Admin</span></div>
        <div class="stp" data-go="3"><b><i>4</i></b><span>Integrações</span></div>
        <div class="stp" data-go="4"><b><i>5</i></b><span>Revisão</span></div>
    </div>

    <form method="POST" autocomplete="off" id="form">

        <!-- ===================== 1. SITE ===================== -->
        <section class="step on">
            <div class="card">
                <h2>
                    <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></span>
                    Identidade do site
                    <small>etapa 1 de 5</small>
                </h2>
                <div class="row">
                    <label>Nome do servidor <small>(aparece no topo e no título da aba)</small></label>
                    <input type="text" name="site_name" data-req="1" placeholder="Meu Servidor DayZ"
                           value="<?= htmlspecialchars($_POST['site_name'] ?? 'Meu Servidor DayZ') ?>">
                    <div class="msg"></div>
                </div>
                <div class="row">
                    <label>Frase de efeito <small>(opcional, aparece embaixo do nome)</small></label>
                    <input type="text" name="site_tagline"
                           value="<?= htmlspecialchars($_POST['site_tagline'] ?? 'Sobreviva. Sangue, suor e .50 BMG.') ?>">
                </div>
                <div class="row">
                    <label>Endereço do site <small>(usado nos links e no retorno do pagamento)</small></label>
                    <input type="text" name="site_url" data-req="1" value="<?= htmlspecialchars($urlPadrao) ?>">
                    <div class="hint">Já preenchi com o endereço que você usou pra abrir esta página.</div>
                    <div class="msg"></div>
                </div>
            </div>
        </section>

        <!-- ===================== 2. BANCO ===================== -->
        <section class="step">
            <div class="card">
                <h2>
                    <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg></span>
                    Banco de dados MySQL
                    <small>etapa 2 de 5</small>
                </h2>
                <div class="alert alert-info" style="margin-bottom:1.2rem">
                    Crie a database no painel da hospedagem antes (hPanel/cPanel), com um usuário
                    que tenha <strong>todos os privilégios</strong>. Use um banco <strong>vazio</strong>.
                </div>
                <div class="row split">
                    <div>
                        <label>Host</label>
                        <input type="text" name="db_host" data-req="1" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
                    </div>
                    <div>
                        <label>Nome da database</label>
                        <input type="text" name="db_name" data-req="1" placeholder="u000000000_site" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="row split">
                    <div>
                        <label>Usuário</label>
                        <input type="text" name="db_user" data-req="1" placeholder="u000000000_admin" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Senha</label>
                        <div class="pw">
                            <input type="password" name="db_pass" data-req="1">
                            <button type="button" class="eye" aria-label="Mostrar senha"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </div>
                    </div>
                </div>
                <div class="msg" id="dbmsg" style="margin-top:.2rem"></div>
                <div style="margin-top:.9rem">
                    <button type="button" class="mini" id="testdb" style="padding:.55rem .95rem;font-size:.82rem">
                        Testar conexão agora
                    </button>
                </div>
            </div>
        </section>

        <!-- ===================== 3. ADMIN ===================== -->
        <section class="step">
            <div class="card">
                <h2>
                    <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg></span>
                    Seu acesso ao painel
                    <small>etapa 3 de 5</small>
                </h2>
                <div class="row split">
                    <div>
                        <label>Usuário <small>(mín. 3 letras)</small></label>
                        <input type="text" name="admin_user" data-req="1" data-min="3" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>">
                        <div class="msg"></div>
                    </div>
                    <div>
                        <label>E-mail <small>(opcional)</small></label>
                        <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">
                    </div>
                </div>
                <div class="row split">
                    <div>
                        <label>Senha <small>(mín. 8 caracteres)</small></label>
                        <div class="pw">
                            <input type="password" name="admin_pass" data-req="1" data-min="8" id="pw1">
                            <button type="button" class="eye" aria-label="Mostrar senha"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </div>
                        <div class="bars" id="bars"><i></i><i></i><i></i><i></i></div>
                        <div class="msg"></div>
                    </div>
                    <div>
                        <label>Confirmar senha</label>
                        <div class="pw">
                            <input type="password" name="admin_pass2" data-req="1" data-match="admin_pass" id="pw2">
                            <button type="button" class="eye" aria-label="Mostrar senha"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </div>
                        <div class="msg"></div>
                    </div>
                </div>
                <div class="hint">Guarde esses dados. É com eles que você entra em <code>/admin</code>.</div>
            </div>
        </section>

        <!-- ===================== 4. INTEGRACOES ===================== -->
        <section class="step">
            <div class="card">
                <h2>
                    <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11V7a5 5 0 0 1 10 0v4"/><rect x="3" y="11" width="18" height="11" rx="2"/></svg></span>
                    Chave do agent
                    <small>etapa 4 de 5</small>
                </h2>
                <p class="hint" style="margin-bottom:.9rem">Senha que o <code>tecplay-agent.exe</code> usa
                pra falar com este site e entregar as compras no jogo. Já gerei uma forte pra você.</p>
                <?php $suggested = $_POST['agent_token'] ?? suggested_token(); ?>
                <div class="token">
                    <code id="tkview"><?= htmlspecialchars($suggested) ?></code>
                    <button type="button" class="mini" id="tkcopy">Copiar</button>
                    <button type="button" class="mini" id="tknew">Gerar outra</button>
                </div>
                <div class="row">
                    <label>Token <small>(mín. 16 caracteres)</small></label>
                    <input type="text" name="agent_token" id="tkinput" data-req="1" data-min="16" value="<?= htmlspecialchars($suggested) ?>">
                    <div class="msg"></div>
                </div>
            </div>

            <div class="card">
                <h2>
                    <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
                    Mercado Pago
                    <small class="opt">opcional, dá pra preencher depois</small>
                </h2>
                <div class="row">
                    <label>Access Token <small>(começa com APP_USR ou TEST)</small></label>
                    <input type="text" name="mp_token" value="<?= htmlspecialchars($_POST['mp_token'] ?? '') ?>">
                </div>
                <div class="row split">
                    <div>
                        <label>Public Key <small>(libera o cartão)</small></label>
                        <input type="text" name="mp_public" value="<?= htmlspecialchars($_POST['mp_public'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Webhook Secret <small>(opcional)</small></label>
                        <input type="text" name="mp_webhook_sec" value="<?= htmlspecialchars($_POST['mp_webhook_sec'] ?? '') ?>">
                    </div>
                </div>
                <div class="hint">Pega as duas chaves em
                <a href="https://www.mercadopago.com.br/developers/panel" target="_blank" rel="noopener">mercadopago.com.br/developers/panel</a>.
                Sem elas o site funciona, só não vende.</div>
            </div>

            <div class="card">
                <h2>
                    <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg></span>
                    CFTools Cloud
                    <small class="opt">opcional, dá pra preencher depois</small>
                </h2>
                <div class="row">
                    <label>Application ID</label>
                    <input type="text" name="cftools_app_id" value="<?= htmlspecialchars($_POST['cftools_app_id'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="row split">
                    <div>
                        <label>Application Secret</label>
                        <input type="text" name="cftools_secret" value="<?= htmlspecialchars($_POST['cftools_secret'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div>
                        <label>Server API ID</label>
                        <input type="text" name="cftools_server_api_id" value="<?= htmlspecialchars($_POST['cftools_server_api_id'] ?? '') ?>" autocomplete="off">
                    </div>
                </div>
                <div class="hint">Liga o ranking de kills/zumbis e faz as caixas caírem no jogo.
                Sem isso o ranking mostra só investimento.</div>
            </div>
        </section>

        <!-- ===================== 5. REVISAO ===================== -->
        <section class="step">
            <div class="card">
                <h2>
                    <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg></span>
                    Confira antes de instalar
                    <small>etapa 5 de 5</small>
                </h2>
                <dl class="rev" id="resumo"></dl>
            </div>

            <div class="card">
                <h2>
                    <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg></span>
                    Dados de exemplo
                    <small class="opt">recomendado</small>
                </h2>
                <label class="check">
                    <input type="checkbox" name="seed_demo" value="1" <?= isset($_POST['seed_demo']) ? (!empty($_POST['seed_demo']) ? 'checked' : '') : 'checked' ?>>
                    <span>Encher o site com <strong>jogadores, compras, avaliações e anúncios fictícios</strong>
                    pra ele não nascer vazio. Tudo marcado como demo e removível depois com
                    <code>php cli/seed-demo.php --clean</code> ou pelo painel.</span>
                </label>
            </div>
        </section>

        <div class="nav">
            <button type="button" class="btn ghost wiz" id="voltar" style="visibility:hidden">Voltar</button>
            <div style="flex:1"></div>
            <button type="button" class="btn wiz" id="avancar">
                Continuar
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
            <button type="submit" class="btn go fim" id="instalar">
                <span class="spin"></span>
                <span id="golabel">Instalar agora</span>
            </button>
        </div>
    </form>

<?php endif; ?>

<footer>
    Powered by <b>Tecplay</b> · Site para servidores de DayZ
</footer>

</div>

<script>
(function(){
  document.documentElement.classList.remove('nojs');
  var form = document.getElementById('form');
  if (!form) return;

  var steps   = [].slice.call(form.querySelectorAll('.step'));
  var stps    = [].slice.call(document.querySelectorAll('.stp'));
  var fill    = document.getElementById('fill');
  var btnPrev = document.getElementById('voltar');
  var btnNext = document.getElementById('avancar');
  var btnGo   = document.getElementById('instalar');
  var cur = 0;

  function pinta(){
    steps.forEach(function(s,i){ s.classList.toggle('on', i===cur); });
    stps.forEach(function(s,i){
      s.classList.toggle('on', i===cur);
      s.classList.toggle('done', i<cur);
    });
    fill.style.width = (cur/(steps.length-1)*80) + '%';
    btnPrev.style.visibility = cur===0 ? 'hidden' : 'visible';
    btnNext.style.display = cur===steps.length-1 ? 'none' : 'inline-flex';
    btnGo.style.display   = cur===steps.length-1 ? 'inline-flex' : 'none';
    if (cur===steps.length-1) resumo();
    window.scrollTo({top:0, behavior:'smooth'});
  }

  // ---------- validacao da etapa ----------
  function erro(inp, txt){
    inp.classList.add('bad'); inp.classList.remove('good');
    var box = inp.closest('.row') || inp.parentElement.parentElement;
    var m = box.querySelector('.msg'); if (m){ m.textContent = txt; m.classList.remove('ok'); }
  }
  function limpa(inp){
    inp.classList.remove('bad');
    var box = inp.closest('.row') || inp.parentElement.parentElement;
    var m = box.querySelector('.msg'); if (m && !m.classList.contains('ok')) m.textContent = '';
  }
  function valida(i){
    var campos = steps[i].querySelectorAll('[data-req],[data-min],[data-match]');
    var ok = true, primeiro = null;
    [].forEach.call(campos, function(inp){
      var v = inp.value.trim();
      limpa(inp);
      if (inp.hasAttribute('data-req') && v === ''){
        erro(inp, 'Precisa preencher este campo.'); ok=false; primeiro = primeiro||inp; return;
      }
      var min = parseInt(inp.getAttribute('data-min')||'0', 10);
      if (min && v.length < min){
        erro(inp, 'Precisa de pelo menos ' + min + ' caracteres.'); ok=false; primeiro = primeiro||inp; return;
      }
      var par = inp.getAttribute('data-match');
      if (par){
        var outro = form.querySelector('[name="'+par+'"]');
        if (outro && outro.value !== inp.value){
          erro(inp, 'As senhas não estão iguais.'); ok=false; primeiro = primeiro||inp; return;
        }
      }
      if (v !== '') inp.classList.add('good');
    });
    if (primeiro) primeiro.focus();
    return ok;
  }

  btnNext.addEventListener('click', function(){
    if (!valida(cur)) return;
    if (cur < steps.length-1){ cur++; pinta(); }
  });
  btnPrev.addEventListener('click', function(){ if (cur>0){ cur--; pinta(); } });

  // clicar no numero volta pra etapa ja preenchida
  stps.forEach(function(s,i){
    s.style.cursor = 'pointer';
    s.addEventListener('click', function(){ if (i < cur){ cur = i; pinta(); } });
  });

  // Enter avanca em vez de enviar o form no meio do caminho
  form.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && e.target.tagName === 'INPUT' && cur < steps.length-1){
      e.preventDefault(); btnNext.click();
    }
  });

  form.addEventListener('submit', function(e){
    for (var i=0; i<steps.length; i++){
      if (!valida(i)){ e.preventDefault(); cur = i; pinta(); return; }
    }
    btnGo.classList.add('loading'); btnGo.disabled = true;
    document.getElementById('golabel').textContent = 'Instalando, aguarde...';
  });

  // ---------- olho das senhas ----------
  [].forEach.call(document.querySelectorAll('.eye'), function(b){
    b.addEventListener('click', function(){
      var inp = b.parentElement.querySelector('input');
      inp.type = inp.type === 'password' ? 'text' : 'password';
      b.style.color = inp.type === 'text' ? '#c084fc' : '';
    });
  });

  // ---------- forca da senha ----------
  var pw1 = document.getElementById('pw1'), bars = document.getElementById('bars');
  if (pw1 && bars){
    pw1.addEventListener('input', function(){
      var v = pw1.value, s = 0;
      if (v.length >= 8) s++;
      if (v.length >= 12) s++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
      if (/[0-9]/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
      bars.className = 'bars' + (v ? ' s' + Math.max(1,s) : '');
    });
  }
  var pw2 = document.getElementById('pw2');
  if (pw2 && pw1){
    pw2.addEventListener('input', function(){
      if (!pw2.value) { limpa(pw2); return; }
      var box = pw2.closest('.row'), m = box.querySelector('.msg');
      if (pw2.value === pw1.value){
        pw2.classList.remove('bad'); pw2.classList.add('good');
        m.classList.add('ok'); m.textContent = 'As senhas conferem.';
      } else {
        m.classList.remove('ok'); m.textContent = '';
      }
    });
  }

  // ---------- token ----------
  var tkIn = document.getElementById('tkinput'), tkView = document.getElementById('tkview');
  var abc = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
  function flash(b, txt){
    var antes = b.textContent; b.textContent = txt; b.classList.add('okflash');
    setTimeout(function(){ b.textContent = antes; b.classList.remove('okflash'); }, 1600);
  }
  var tkNew = document.getElementById('tknew');
  if (tkNew) tkNew.addEventListener('click', function(){
    var t = '', a = new Uint32Array(48);
    (window.crypto || window.msCrypto).getRandomValues(a);
    for (var i=0;i<48;i++) t += abc[a[i] % abc.length];
    tkIn.value = t; tkView.textContent = t; limpa(tkIn); flash(tkNew, 'Nova gerada');
  });
  var tkCopy = document.getElementById('tkcopy');
  if (tkCopy) tkCopy.addEventListener('click', function(){
    var t = tkIn.value;
    if (navigator.clipboard) navigator.clipboard.writeText(t).then(function(){ flash(tkCopy,'Copiado'); });
    else { tkIn.select(); document.execCommand('copy'); flash(tkCopy,'Copiado'); }
  });
  if (tkIn) tkIn.addEventListener('input', function(){ tkView.textContent = tkIn.value; });

  // ---------- testar banco ----------
  var btnDb = document.getElementById('testdb'), dbMsg = document.getElementById('dbmsg');
  if (btnDb) btnDb.addEventListener('click', function(){
    var d = new FormData();
    ['db_host','db_name','db_user','db_pass'].forEach(function(n){
      d.append(n, form.querySelector('[name="'+n+'"]').value);
    });
    btnDb.disabled = true; btnDb.textContent = 'Testando...';
    dbMsg.classList.remove('ok'); dbMsg.textContent = '';
    fetch('install.php?probe=db', {method:'POST', body:d})
      .then(function(r){ return r.json(); })
      .then(function(j){
        dbMsg.textContent = j.msg;
        dbMsg.classList.toggle('ok', !!j.ok);
      })
      .catch(function(){ dbMsg.textContent = 'Não consegui testar agora. Tente de novo.'; })
      .then(function(){ btnDb.disabled = false; btnDb.textContent = 'Testar conexão agora'; });
  });

  // ---------- resumo da revisao ----------
  function linha(rot, val, vazio){
    return '<div><dt>' + rot + '</dt><dd' + (val ? '' : ' class="vazio"') + '>' +
           (val ? String(val).replace(/[<>&]/g, function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;'}[c];}) : vazio) +
           '</dd></div>';
  }
  function v(n){ var e = form.querySelector('[name="'+n+'"]'); return e ? e.value.trim() : ''; }
  function resumo(){
    var alvo = document.getElementById('resumo');
    var h = '';
    h += linha('Nome do site', v('site_name'), '');
    h += linha('Endereço', v('site_url'), '');
    h += linha('Banco', v('db_name') + ' em ' + v('db_host'), '');
    h += linha('Usuário do banco', v('db_user'), '');
    h += linha('Admin do painel', v('admin_user'), '');
    h += linha('Chave do agent', v('agent_token').slice(0,10) + '...' + v('agent_token').slice(-4), '');
    h += linha('Mercado Pago', v('mp_token') ? 'configurado' : '', 'em branco, dá pra preencher depois');
    h += linha('CFTools', v('cftools_app_id') ? 'configurado' : '', 'em branco, dá pra preencher depois');
    alvo.innerHTML = h;
  }

  // se o servidor devolveu erro, comeca do inicio com o aviso a vista
  pinta();
})();
</script>
</body>
</html>
