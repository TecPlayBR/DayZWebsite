<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// install.php - Wizard de instalacao
// ============================================================
// Form de 1 pagina. Quando cliente envia, valida tudo, conecta no DB,
// importa schema.sql, gera config/config.php, e auto-redireciona pra /.
// Apos sucesso, sugere apagar o install.php manualmente.
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

if (file_exists($configFile)) {
    http_response_code(403);
    die('<h1>Ja instalado.</h1><p>Apague <code>config/config.php</code> se quiser reinstalar (vai perder a senha admin atual).</p>');
}

$errors = [];
$success = false;

// =========== PROCESSA POST ===========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $structureMissing) {
    // Bloqueia a instalacao: faltam pastas/arquivos essenciais. Instalar assim
    // geraria um site quebrado (config gravado mas sem lang/views/etc.).
    $errors[] = 'Instalacao bloqueada: faltam arquivos essenciais no servidor (veja o aviso acima). '
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
    $mp_webhook_sec = trim($_POST['mp_webhook_sec'] ?? '');

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

            // Confere se ja tem tabelas (preventivo)
            $existing = $pdo->query("SHOW TABLES LIKE 'admin_users'")->fetch();
            if ($existing) {
                $errors[] = 'Banco ja tem tabelas (admin_users existe). Use um banco vazio ou apague as tabelas antes.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Erro ao conectar no banco: ' . htmlspecialchars($e->getMessage());
        }
    }

    if (!$errors) {
        try {
            // Importa schema.sql
            $sql = file_get_contents($schemaFile);
            // PDO em geral nao suporta multiplas statements via prepare — splitamos por ';'
            // Estrategia simples: roda direto via exec (suporta multi-statement no mysql native)
            $pdo->exec($sql);

            // Cria admin user com senha bcrypt
            $hash = password_hash($admin_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email) VALUES (?, ?, ?)");
            $stmt->execute([$admin_user, $hash, $admin_email ?: null]);

            // Atualiza settings.site_name e tagline (sobrescreve seeds)
            $up = $pdo->prepare("UPDATE settings SET `value` = ? WHERE `key` = ?");
            $up->execute([$site_name, 'site_name']);
            if ($site_tagline) $up->execute([$site_tagline, 'site_tagline']);

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
            $configContent .= "        'webhook_secret'    => " . var_export($mp_webhook_sec, true) . ",\n";
            $configContent .= "        'currency'          => 'BRL',\n";
            $configContent .= "        'min_purchase_brl'  => 5,\n";
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

?><!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalacao - DayZ Website Template</title>
<link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg: var(--bg-1); --bg2: var(--bg-2); --bone: var(--bone); --rust: var(--rust); --rust2: var(--rust-2);
    --moss: var(--moss); --dim: var(--dim); --hazard: var(--hazard); --border: rgba(212,197,169,0.10);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--bone); font-family: 'Inter', system-ui, sans-serif; line-height: 1.5; min-height: 100vh; padding: 2rem 1rem; }
.wrap { max-width: 720px; margin: 0 auto; }
header { text-align: center; margin-bottom: 2rem; }
header h1 { font-family: 'Black Ops One', sans-serif; font-size: 2rem; color: var(--rust); letter-spacing: 0.05em; }
header p { color: var(--dim); margin-top: 0.5rem; font-size: 0.95rem; }
.card { background: var(--bg2); border: 1px solid var(--border); border-radius: 4px; padding: 2rem; margin-bottom: 1.5rem; }
.card h2 { font-family: 'Black Ops One', sans-serif; color: var(--bone); font-size: 1.2rem; margin-bottom: 1.2rem; padding-bottom: 0.6rem; border-bottom: 2px solid var(--rust); }
.row { margin-bottom: 1.2rem; }
.row label { display: block; font-size: 0.85rem; color: var(--bone); margin-bottom: 0.4rem; font-weight: 600; }
.row label small { color: var(--dim); font-weight: 400; }
.row input, .row textarea {
    width: 100%; padding: 0.7rem 0.9rem; background: var(--bg); border: 1px solid var(--border);
    color: var(--bone); font-family: inherit; font-size: 0.95rem; border-radius: 2px;
}
.row input:focus, .row textarea:focus { outline: none; border-color: var(--rust); }
.row.split { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 520px) { .row.split { grid-template-columns: 1fr; } }
.btn { display: inline-block; padding: 0.9rem 2rem; background: var(--rust); color: var(--bone); border: none; cursor: pointer; font-family: 'Black Ops One', sans-serif; letter-spacing: 0.08em; font-size: 0.95rem; border-radius: 2px; text-transform: uppercase; transition: background .2s; }
.btn:hover { background: var(--rust2); }
.alert { padding: 1rem 1.2rem; border-radius: 3px; margin-bottom: 1.5rem; font-size: 0.9rem; }
.alert-err { background: var(--danger-overlay); border-left: 3px solid var(--rust2); color: var(--text-danger); }
.alert-ok  { background: rgba(34,197,94,0.1); border-left: 3px solid var(--moss); color: var(--text-success); }
.alert ul { margin: 0.5rem 0 0 1.2rem; }
.token-suggest { font-family: 'Cascadia Code', monospace; background: var(--bg); padding: 0.5rem 0.8rem; font-size: 0.85rem; color: var(--hazard); display: inline-block; margin-top: 0.5rem; cursor: pointer; user-select: all; word-break: break-all; }
.token-suggest:hover { color: var(--rust2); }
.hint { font-size: 0.8rem; color: var(--dim); margin-top: 0.3rem; }
</style>
</head>
<body>
<div class="wrap">

<header>
    <h1>DAYZ WEBSITE TEMPLATE</h1>
    <p>Instalacao em uma pagina — preencha os campos abaixo</p>
</header>

<?php if ($structureMissing): ?>
    <div class="alert alert-err">
        <strong>⛔ Faltam arquivos essenciais no servidor — NAO da pra instalar ainda.</strong>
        <p style="margin-top:.6rem;">O upload por FTP parece ter ficado incompleto. Estas pastas/arquivos
        precisam ficar <strong>um nivel ACIMA</strong> da pasta publica (ao lado de <code>src/</code>),
        e nao foram encontrados:</p>
        <ul>
            <?php foreach ($structureMissing as $m): ?><li><code><?= htmlspecialchars($m) ?></code></li><?php endforeach; ?>
        </ul>
        <p style="margin-top:.6rem;">Reenvie o template <strong>completo</strong> (todas as pastas do ZIP) por
        FTP e recarregue esta pagina. So o conteudo de <code>public/</code> vai pra raiz publica
        (<code>public_html</code>); <code>src/ views/ lang/ config/ migrations/</code> + <code>schema.sql</code>
        ficam um nivel acima.</p>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-ok">
        <strong>Instalado com sucesso!</strong>
        <ul>
            <li>Banco populado com 6 pacotes de moedas (editaveis no admin)</li>
            <li>Usuario admin <code><?= htmlspecialchars($admin_user) ?></code> criado</li>
            <li>Config gravado em <code>config/config.php</code></li>
        </ul>
        <p style="margin-top: 1rem;">
            <a href="/" class="btn">Acessar o site</a>
            <a href="/admin" class="btn" style="margin-left: 0.5rem;">Painel Admin</a>
        </p>
        <p style="margin-top: 1rem; color: var(--text-danger);">
            <?php if (!empty($installRemoved)): ?>
                <strong>✓ SEGURANCA OK:</strong> o instalador foi auto-renomeado pra <code>install.php.installed-*</code>.
                Por seguranca, voce pode apagar esse arquivo via FTP.
            <?php else: ?>
                <strong>⚠ APAGUE O INSTALL.PHP:</strong> nao consegui renomear sozinho (permissao).
                Apague manualmente o arquivo <code>public/install.php</code> via FTP/cPanel agora —
                quem acessar essa URL pode reinstalar (apos apagar config.php).
            <?php endif; ?>
        </p>
    </div>
<?php elseif (!$structureMissing): ?>

    <?php if ($errors): ?>
        <div class="alert alert-err">
            <strong>Verifica os erros:</strong>
            <ul>
                <?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <div class="card">
            <h2>Site</h2>
            <div class="row">
                <label>Nome do site <small>(aparece no header/title)</small></label>
                <input type="text" name="site_name" value="<?= htmlspecialchars($_POST['site_name'] ?? 'Meu Servidor DayZ') ?>" required>
            </div>
            <div class="row">
                <label>Tagline / subtitulo <small>(opcional)</small></label>
                <input type="text" name="site_tagline" value="<?= htmlspecialchars($_POST['site_tagline'] ?? 'Sobreviva. Sangue, suor e .50 BMG.') ?>">
            </div>
            <div class="row">
                <label>URL completa <small>(ex: https://meusite.com.br)</small></label>
                <input type="text" name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? ('http://' . $_SERVER['HTTP_HOST'])) ?>">
            </div>
        </div>

        <div class="card">
            <h2>Banco de Dados MySQL</h2>
            <p class="hint" style="margin-bottom: 1rem;">Crie a database no cPanel antes. Usuario precisa de permissao ALL.</p>
            <div class="row split">
                <div>
                    <label>Host</label>
                    <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                </div>
                <div>
                    <label>Database</label>
                    <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="row split">
                <div>
                    <label>Usuario</label>
                    <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Senha</label>
                    <input type="password" name="db_pass" required>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Admin do Painel</h2>
            <div class="row split">
                <div>
                    <label>Usuario</label>
                    <input type="text" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>" required>
                </div>
                <div>
                    <label>E-mail <small>(opcional)</small></label>
                    <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">
                </div>
            </div>
            <div class="row split">
                <div>
                    <label>Senha <small>(min 8 chars)</small></label>
                    <input type="password" name="admin_pass" required>
                </div>
                <div>
                    <label>Confirmar senha</label>
                    <input type="password" name="admin_pass2" required>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>AGENT_TOKEN <small style="font-size: 0.75rem; color: var(--dim);">(usado pelo tecplay-agent.exe)</small></h2>
            <p class="hint">Token que autentica o agent contra a API deste site. Pode usar este sugerido (clique pra selecionar):</p>
            <?php $suggested = $_POST['agent_token'] ?? suggested_token(); ?>
            <div class="token-suggest" onclick="document.getElementsByName('agent_token')[0].value = this.textContent.trim();"><?= htmlspecialchars($suggested) ?></div>
            <div class="row" style="margin-top: 1rem;">
                <label>Token</label>
                <input type="text" name="agent_token" value="<?= htmlspecialchars($suggested) ?>" required>
            </div>
        </div>

        <div class="card">
            <h2>Mercado Pago <small style="font-size: 0.75rem; color: var(--dim);">(deixe vazio se ainda nao tem conta)</small></h2>
            <div class="row">
                <label>Access Token <small>(TEST-... ou APP_USR-...)</small></label>
                <input type="text" name="mp_token" value="<?= htmlspecialchars($_POST['mp_token'] ?? '') ?>">
            </div>
            <div class="row">
                <label>Webhook Secret <small>(opcional)</small></label>
                <input type="text" name="mp_webhook_sec" value="<?= htmlspecialchars($_POST['mp_webhook_sec'] ?? '') ?>">
            </div>
            <p class="hint">Pega ambos em https://www.mercadopago.com.br/developers/panel</p>
        </div>

        <div style="text-align: center;">
            <button type="submit" class="btn">Instalar</button>
        </div>
    </form>

<?php endif; ?>

</div>
</body>
</html>
