<?php
// ============================================================
// (c) 2026 Tecplay - DayZ Website Template
// update.php - tela de atualizacao do banco pelo navegador
// ============================================================
// POR QUE EXISTE: atualizar exigia SSH (`php cli/migrate.php`) ou montar um cron
// com o caminho absoluto certo, que muda de hospedagem pra hospedagem. Era a parte
// mais chata e mais facil de errar do processo. Aqui o cliente clica um botao.
//
// SEGURANCA (esta tela mexe no BANCO, entao ela e trancada em 3 camadas):
//   1. exige SESSAO de admin logado (a mesma do /admin);
//   2. exige papel super_admin (nem finance/support/editor entram);
//   3. exige token CSRF pra executar, e so executa em POST.
// Sem login nao vaza NADA: nem versao, nem lista de migration, nem se existe banco.
// O motor e o MESMO do cli/migrate.php (cli/migrate-lib.php), pra nao divergirem.
// ============================================================

declare(strict_types=1);

$ROOT       = dirname(__DIR__);
$configFile = $ROOT . '/config/config.php';

// Sem config = site nao instalado. Manda instalar, nao atualizar.
if (!is_file($configFile)) {
    http_response_code(404);
    die();
}

$config = require $configFile;

require_once $ROOT . '/src/Database.php';
require_once $ROOT . '/src/Auth.php';
require_once $ROOT . '/src/Csrf.php';

// Sessao com as MESMAS flags do index.php, senao o cookie de login nao e reconhecido.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $isHttps,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

$logado = \App\Auth::check();
$user   = \App\Auth::user();
$souSuper = $logado && (($user['role'] ?? '') === 'super_admin');

// ---------------------------------------------------------------- estado do banco
$erroBanco = null;
$pendentes = [];
$total = 0;
$aplicadas = 0;
$resultado = null;

if ($souSuper) {
    try {
        \App\Database::init($config['db']);
        $pdo = \App\Database::pdo();
        require_once $ROOT . '/cli/migrate-lib.php';
        $dir = $ROOT . '/migrations';

        if (!is_dir($dir)) {
            $erroBanco = 'A pasta migrations/ nao esta no servidor. Suba ela por FTP '
                       . '(ela fica ao lado de src/) e recarregue esta pagina.';
        } else {
            mig_garante_tabela($pdo);

            // Executa so em POST com CSRF valido e confirmacao explicita.
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!\App\Csrf::check()) {
                    $erroBanco = 'Sessao expirou enquanto a pagina estava aberta. Recarregue e tente de novo.';
                } elseif (empty($_POST['confirmo'])) {
                    $erroBanco = 'Marque a confirmacao antes de atualizar.';
                } else {
                    $resultado = mig_aplicar_pendentes($pdo, $dir);
                    if (class_exists('\App\AuditLog')) {
                        try {
                            \App\AuditLog::record('update.migrate', 'schema', null, [
                                'aplicadas'    => $resultado['aplicadas'],
                                'ja_presentes' => $resultado['ja_presentes'],
                                'falhou'       => $resultado['falhou']['arquivo'] ?? null,
                            ]);
                        } catch (\Throwable $e) { /* auditoria nao bloqueia o update */ }
                    }
                }
            }

            $total     = count(mig_ordenar(glob($dir . '/*.sql') ?: []));
            $pendentes = mig_pendentes($pdo, $dir);
            $aplicadas = $total - count($pendentes);
        }
    } catch (\Throwable $e) {
        $erroBanco = 'Nao consegui falar com o banco: ' . $e->getMessage();
    }
}

// Versao do template, se o CHANGELOG.md subiu junto
$versao = '';
if (is_readable($ROOT . '/CHANGELOG.md')) {
    $cab = (string) @file_get_contents($ROOT . '/CHANGELOG.md', false, null, 0, 2048);
    if (preg_match('/^##\s*\[?v?([0-9]+\.[0-9]+\.[0-9]+)/m', $cab, $m)) $versao = 'v' . $m[1];
}

/** Nome legivel a partir do arquivo: "v2.22.0_streamers.sql" -> "v2.22.0 - streamers" */
function mig_titulo(string $arquivo): string {
    $n = preg_replace('/\.sql$/', '', basename($arquivo));
    $n = str_replace('_', ' ', (string) $n);
    return preg_replace('/^(v[\d.]+)\s/', '$1 - ', (string) $n) ?? $n;
}
?><!DOCTYPE html>
<html lang="pt-br" class="nojs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Atualização · DayZ Website Template</title>
<link rel="icon" type="image/png" href="assets/img/tecplay.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* Mesma identidade do install.php, e cores LITERAIS pelo mesmo motivo:
   esta tela nao carrega o theme.css do site. */
:root{
  --bg:#0a0512; --bg-soft:#120a1f; --card:#170f29; --card-2:#1d1333;
  --line:rgba(168,85,247,.16); --line-2:rgba(168,85,247,.34);
  --brand:#a855f7; --brand-2:#c084fc; --brand-3:#7c3aed; --gold:#facc15;
  --ink:#ece7f8; --ink-2:#c9c1e0; --dim:#9a94ad;
  --ok:#22c55e; --ok-ink:#86efac; --err:#f87171; --err-ink:#fca5a5;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--ink);font-family:'Inter',system-ui,sans-serif;
  line-height:1.55;padding:clamp(1rem,4vw,2.5rem) 1rem 3rem;-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;inset:0;z-index:-2;pointer-events:none;
  background:radial-gradient(60rem 34rem at 12% -8%, rgba(124,58,237,.30), transparent 62%),
             radial-gradient(52rem 30rem at 92% 6%, rgba(168,85,247,.20), transparent 60%);
  animation:respira 16s ease-in-out infinite alternate}
body::after{content:'';position:fixed;inset:0;z-index:-1;pointer-events:none;opacity:.5;
  background-image:linear-gradient(rgba(168,85,247,.05) 1px,transparent 1px),
                   linear-gradient(90deg,rgba(168,85,247,.05) 1px,transparent 1px);
  background-size:64px 64px;
  mask-image:radial-gradient(70% 55% at 50% 0%,#000 0%,transparent 78%);
  -webkit-mask-image:radial-gradient(70% 55% at 50% 0%,#000 0%,transparent 78%)}
@keyframes respira{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(0,-1.6%,0) scale(1.05)}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
.wrap{max-width:780px;margin:0 auto}
a{color:var(--brand-2)}
code{font-family:ui-monospace,'Cascadia Code',Consolas,monospace;font-size:.88em;
  background:rgba(0,0,0,.32);padding:.08rem .34rem;border-radius:5px;color:var(--brand-2);word-break:break-word}
.brand{display:flex;align-items:center;gap:1rem;justify-content:center;margin-bottom:.4rem}
.brand-mark{width:58px;height:58px;object-fit:contain;filter:drop-shadow(0 0 18px rgba(168,85,247,.55));flex:none}
.brand-txt h1{font-family:'Black Ops One',cursive,sans-serif;font-size:clamp(1.5rem,4.4vw,2.1rem);
  letter-spacing:.03em;line-height:1.1;
  background:linear-gradient(96deg,#fff 0%,var(--brand-2) 42%,var(--brand) 78%);
  -webkit-background-clip:text;background-clip:text;color:transparent}
.brand-txt p{color:var(--dim);font-size:.86rem;margin-top:.15rem}
.pill{display:inline-block;margin-left:.45rem;padding:.08rem .5rem;border-radius:999px;
  background:rgba(250,204,21,.12);border:1px solid rgba(250,204,21,.28);
  color:var(--gold);font-size:.72rem;font-weight:700;vertical-align:1px}
.lead{text-align:center;color:var(--ink-2);font-size:.95rem;margin:.9rem auto 1.6rem;max-width:56ch}
.card{background:linear-gradient(168deg,var(--card) 0%,var(--card-2) 100%);
  border:1px solid var(--line);border-radius:16px;padding:clamp(1.2rem,3.4vw,1.9rem);
  margin-bottom:1.1rem;box-shadow:0 20px 50px -28px rgba(0,0,0,.9), inset 0 1px 0 rgba(255,255,255,.04)}
.card>h2{font-size:1.08rem;font-weight:800;display:flex;align-items:center;gap:.6rem;
  padding-bottom:.85rem;margin-bottom:1.15rem;border-bottom:1px solid var(--line)}
.card>h2 .ic{width:32px;height:32px;border-radius:9px;flex:none;display:grid;place-items:center;
  background:rgba(168,85,247,.14);border:1px solid var(--line-2);color:var(--brand-2)}
.card>h2 small{font-weight:500;color:var(--dim);font-size:.76rem;margin-left:auto}
.num{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem}
.num div{flex:1;min-width:120px;background:rgba(10,5,18,.55);border:1px solid var(--line);
  border-radius:12px;padding:.9rem 1rem}
.num b{display:block;font-size:1.7rem;font-weight:800;line-height:1.1}
.num span{font-size:.76rem;color:var(--dim)}
.num.pend b{color:var(--gold)}
.num.ok b{color:var(--ok-ink)}
ul.mig{list-style:none;display:grid;gap:.4rem}
ul.mig li{display:flex;align-items:center;gap:.6rem;font-size:.87rem;color:var(--ink-2);
  background:rgba(10,5,18,.5);border:1px solid var(--line);border-radius:9px;padding:.55rem .8rem}
ul.mig li .tag{margin-left:auto;font-size:.7rem;font-weight:700;padding:.1rem .45rem;border-radius:999px}
ul.mig li.pend .tag{background:rgba(250,204,21,.14);color:var(--gold)}
ul.mig li.feita .tag{background:rgba(34,197,94,.14);color:var(--ok-ink)}
ul.mig li.ruim .tag{background:rgba(248,113,113,.15);color:var(--err-ink)}
.check{display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;
  background:rgba(168,85,247,.06);border:1px solid var(--line);border-radius:12px;padding:.9rem 1rem;margin-bottom:1.1rem}
.check input{width:19px;height:19px;margin-top:.15rem;accent-color:var(--brand);flex:none;cursor:pointer}
.check span{font-size:.87rem;color:var(--ink-2)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.55rem;
  padding:.85rem 1.7rem;border:0;border-radius:11px;cursor:pointer;text-decoration:none;
  font:700 .92rem/1 'Inter',sans-serif;
  background:linear-gradient(135deg,var(--brand-3),var(--brand));color:#fff;
  box-shadow:0 10px 26px -12px rgba(124,58,237,.9);transition:transform .16s,filter .16s}
.btn:hover{transform:translateY(-2px);filter:brightness(1.08)}
.btn.ghost{background:transparent;border:1px solid var(--line-2);color:var(--ink-2);box-shadow:none}
.btn.go{background:linear-gradient(135deg,#16a34a,#22c55e);box-shadow:0 10px 26px -12px rgba(34,197,94,.9)}
.btn[disabled]{opacity:.5;cursor:not-allowed;transform:none}
.alert{border-radius:12px;padding:1.05rem 1.25rem;margin-bottom:1.3rem;font-size:.9rem;border:1px solid transparent}
.alert>strong:first-child{display:block;margin-bottom:.15rem}
.alert-err{background:rgba(248,113,113,.09);border-color:rgba(248,113,113,.3);color:var(--err-ink)}
.alert-ok{background:rgba(34,197,94,.09);border-color:rgba(34,197,94,.32);color:var(--ok-ink)}
.alert-info{background:rgba(168,85,247,.09);border-color:var(--line-2);color:var(--ink-2)}
.alert ul{margin:.55rem 0 0 1.25rem}
.cta{display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1.2rem}
.tick{width:74px;height:74px;margin:0 auto 1rem;border-radius:50%;display:grid;place-items:center;
  background:rgba(34,197,94,.12);border:2px solid rgba(34,197,94,.45);color:var(--ok)}
.hero{text-align:center;padding:.4rem 0 1rem}
.hero h2{font-family:'Black Ops One',cursive,sans-serif;font-size:1.45rem}
.hero p{color:var(--dim);font-size:.92rem;margin-top:.35rem}
footer{text-align:center;color:var(--dim);font-size:.78rem;margin-top:2rem;opacity:.75}
footer b{color:var(--brand-2)}
</style>
</head>
<body>
<div class="wrap">

<header class="brand">
    <img class="brand-mark" src="assets/img/tecplay.png" alt="Tecplay" onerror="this.style.display='none'">
    <div class="brand-txt">
        <h1>ATUALIZAÇÃO</h1>
        <p>DayZ Website Template<?= $versao ? '<span class="pill">' . htmlspecialchars($versao) . '</span>' : '' ?></p>
    </div>
</header>

<?php if (!$logado): ?>

    <p class="lead">Esta página atualiza o banco de dados do seu site. Só o administrador
    pode usá-la.</p>
    <div class="card">
        <h2><span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11V7a5 5 0 0 1 10 0v4"/><rect x="3" y="11" width="18" height="11" rx="2"/></svg></span>
            Faça login primeiro</h2>
        <p style="font-size:.92rem;color:var(--ink-2)">Entre no painel com a sua conta de
        administrador e volte para esta página.</p>
        <div class="cta"><a href="/admin/login" class="btn">Ir para o login</a></div>
    </div>

<?php elseif (!$souSuper): ?>

    <div class="alert alert-err">
        <strong>Sua conta não tem permissão para atualizar</strong>
        Atualizar o banco é restrito ao papel <code>super_admin</code>. Você está logado como
        <code><?= htmlspecialchars((string)($user['username'] ?? '?')) ?></code>
        (<code><?= htmlspecialchars((string)($user['role'] ?? '?')) ?></code>).
        Peça para o responsável pelo site executar esta etapa.
    </div>
    <div class="cta"><a href="/admin" class="btn ghost">Voltar ao painel</a></div>

<?php elseif ($erroBanco): ?>

    <div class="alert alert-err">
        <strong>Não deu pra continuar</strong>
        <?= htmlspecialchars($erroBanco) ?>
    </div>
    <div class="cta"><a href="update.php" class="btn">Tentar de novo</a>
        <a href="/admin" class="btn ghost">Voltar ao painel</a></div>

<?php elseif ($resultado): ?>

    <?php if ($resultado['falhou']): ?>
        <div class="alert alert-err">
            <strong>Uma atualização falhou e eu parei aqui</strong>
            A migration <code><?= htmlspecialchars($resultado['falhou']['arquivo']) ?></code>
            deu erro e <strong>não foi registrada</strong>. Parei nela de propósito, para não
            deixar o banco pela metade. As que passaram antes já estão aplicadas e não repetem.
            <p style="margin-top:.6rem"><strong>Motivo:</strong>
            <code><?= htmlspecialchars($resultado['falhou']['erro']) ?></code></p>
            <p style="margin-top:.6rem">Mande esse texto para o suporte Tecplay. Seus dados não
            foram alterados por esta migration.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="hero">
                <div class="tick"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                <h2>BANCO ATUALIZADO</h2>
                <p><?= count($resultado['aplicadas']) ?> atualizaç<?= count($resultado['aplicadas']) === 1 ? 'ão' : 'ões' ?>
                aplicada<?= count($resultado['aplicadas']) === 1 ? '' : 's' ?>.
                Nenhum dado seu foi apagado.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($resultado['aplicadas'] || $resultado['ja_presentes']): ?>
        <div class="card">
            <h2><span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg></span>
                O que rodou agora</h2>
            <ul class="mig">
                <?php foreach ($resultado['aplicadas'] as $n): ?>
                    <li class="feita"><?= htmlspecialchars(mig_titulo($n)) ?><span class="tag">aplicada</span></li>
                <?php endforeach; ?>
                <?php foreach ($resultado['ja_presentes'] as $n): ?>
                    <li class="feita"><?= htmlspecialchars(mig_titulo($n)) ?><span class="tag">já estava ok</span></li>
                <?php endforeach; ?>
                <?php if ($resultado['falhou']): ?>
                    <li class="ruim"><?= htmlspecialchars(mig_titulo($resultado['falhou']['arquivo'])) ?><span class="tag">falhou</span></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <strong>Última coisa</strong>
        Abra o site e o painel e confira se está tudo no lugar. Se o menu aparecer como
        <code>NAV.RULES</code>, faltou subir a pasta <code>lang/</code>.
    </div>
    <div class="cta">
        <a href="/" class="btn">Ver o site</a>
        <a href="/admin" class="btn ghost">Ir para o painel</a>
    </div>

<?php else: ?>

    <p class="lead">Você subiu os arquivos novos por FTP. Falta só o banco: esta tela aplica
    o que a versão nova precisa. <strong>Só adiciona</strong>, nunca apaga suas moedas,
    jogadores, compras, páginas ou cores.</p>

    <div class="card">
        <h2><span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg></span>
            Situação do seu banco
            <small>logado como <?= htmlspecialchars((string)($user['username'] ?? '')) ?></small></h2>

        <div class="num">
            <div class="ok"><b><?= (int)$aplicadas ?></b><span>já aplicadas</span></div>
            <div class="pend"><b><?= count($pendentes) ?></b><span>pendentes</span></div>
            <div><b><?= (int)$total ?></b><span>total no template</span></div>
        </div>

        <?php if (!$pendentes): ?>
            <div class="alert alert-ok" style="margin-bottom:0">
                <strong>Nada pendente</strong>
                Seu banco já está na versão desta pasta de arquivos. Não precisa fazer nada aqui.
            </div>
        <?php else: ?>
            <ul class="mig">
                <?php foreach ($pendentes as $p): ?>
                    <li class="pend"><?= htmlspecialchars(mig_titulo($p)) ?><span class="tag">vai rodar</span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if ($pendentes): ?>
        <div class="card">
            <h2><span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.2-8.6"/><path d="M21 3v6h-6"/></svg></span>
                Aplicar agora</h2>

            <div class="alert alert-info">
                <strong>Faça um backup do banco antes</strong>
                É rápido: painel da hospedagem, phpMyAdmin, selecione seu banco, aba
                <strong>Exportar</strong>, <strong>Executar</strong>. Guarde o arquivo
                <code>.sql</code>. Se você tem SSH, dá pra usar <code>php cli/backup.php</code>.
                Rodar esta atualização duas vezes não causa problema: ela só aplica o que falta.
            </div>

            <form method="POST">
                <?= \App\Csrf::field() ?>
                <label class="check">
                    <input type="checkbox" name="confirmo" value="1" id="cf">
                    <span>Fiz o backup do banco (ou aceito seguir sem ele) e quero aplicar as
                    <strong><?= count($pendentes) ?></strong> atualizações listadas acima.</span>
                </label>
                <?php /* SEM disabled no HTML de proposito: se o JS nao rodar, o botao
                         funciona e o PHP ainda exige o checkbox marcado. O JS abaixo e
                         que desabilita enquanto a confirmacao nao esta marcada. */ ?>
                <button type="submit" class="btn go" id="bt">Atualizar o banco</button>
            </form>
        </div>
    <?php endif; ?>

<?php endif; ?>

<footer>Powered by <b>Tecplay</b> · Site para servidores de DayZ</footer>
</div>

<script>
document.documentElement.classList.remove('nojs');
// So libera o botao depois da confirmacao. Sem JS o botao nasce habilitado
// pelo atributo ser removido abaixo, e o PHP ainda exige o checkbox marcado.
(function () {
    var cf = document.getElementById('cf'), bt = document.getElementById('bt');
    if (!cf || !bt) return;
    var sinc = function () { bt.disabled = !cf.checked; };
    cf.addEventListener('change', sinc);
    sinc();
})();
</script>
</body>
</html>
