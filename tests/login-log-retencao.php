<?php
/**
 * Registro de login guardado por 6 meses (Marco Civil, art. 15) e depois eliminado.
 * A eliminacao so roda com a retencao LIGADA: instalacao nova ja nasce ligada, site que ja
 * existia nasce desligado e o dono decide no painel (nunca apagamos dado de cliente sozinhos).
 * A camada roda de verdade em SQLite na memoria; rotas, painel e seeds sao conferidos por texto.
 * Rodar: php tests/login-log-retencao.php
 */

namespace App {
    class Database {
        public static ?\PDO $pdo = null;
        public static int $consultas = 0;
        private static function sql(string $s): string {
            $s = preg_replace('/NOW\(\) - INTERVAL (\d+) MONTH/', "datetime('now', '-$1 months')", $s);
            return str_replace('NOW()', "datetime('now')", $s);
        }
        public static function query(string $sql, array $params = []): \PDOStatement { self::$consultas++; $st = self::$pdo->prepare(self::sql($sql)); $st->execute($params); return $st; }
        public static function execute(string $sql, array $params = []): int { return self::query($sql, $params)->rowCount(); }
        public static function fetchColumn(string $sql, array $params = []) { return self::query($sql, $params)->fetchColumn(); }
    }
    class Settings {
        public static array $v = [];
        public static function get(string $k, $d = null) { return self::$v[$k] ?? $d; }
        public static function getBool(string $k, bool $d = false): bool { return isset(self::$v[$k]) ? self::$v[$k] === '1' : $d; }
        public static function set(string $k, string $raw): bool { self::$v[$k] = $raw; return true; }
    }
}

namespace {
    $falhas = 0;
    function ok(string $d): void { echo "  OK   $d\n"; }
    function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
    $ROOT = dirname(__DIR__);
    use App\Database, App\Settings, App\LoginLog;

    if (!is_file($ROOT . '/src/LoginLog.php')) { falha('src/LoginLog.php nao existe'); echo "\n1 FALHA(S).\n"; exit(1); }
    require_once $ROOT . '/src/LoginLog.php';

    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    Database::$pdo = $pdo;
    $pdo->exec("CREATE TABLE login_log (id INTEGER PRIMARY KEY AUTOINCREMENT, steam_id TEXT, ip TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')))");
    $semeia = function () use ($pdo) {
        $pdo->exec("DELETE FROM login_log");
        foreach (["-8 months", "-7 months", "-5 months", "-1 day", "-0 days"] as $quando)
            $pdo->exec("INSERT INTO login_log (steam_id, ip, created_at) VALUES ('76561190000000001', '10.0.0.1', datetime('now', '$quando'))");
    };
    $total = fn() => (int) $pdo->query("SELECT COUNT(*) FROM login_log")->fetchColumn();

    echo "\n1. Com a retencao desligada nada e apagado\n";
    $semeia(); Settings::$v = ['login_log_retencao' => '0'];
    LoginLog::limparSeDevido();
    if ($total() === 5) ok('desligada: os 5 registros ficam'); else falha('apagou com a retencao desligada', 'dado de cliente sem autorizacao do dono');
    if (LoginLog::antigos() === 2) ok('antigos() conta os 2 com mais de 6 meses'); else falha('antigos() errado: ' . LoginLog::antigos());
    Settings::$v = [];
    LoginLog::limparSeDevido();
    if ($total() === 5) ok('sem a chave no banco tambem nao apaga (padrao desligado)'); else falha('padrao da retencao e ligado', 'site antigo perderia dado sem o dono decidir');

    echo "\n2. Ligada: apaga so o que passou de 6 meses\n";
    Settings::$v = ['login_log_retencao' => '1'];
    LoginLog::limparSeDevido();
    $restam = $pdo->query("SELECT COUNT(*) FROM login_log WHERE created_at >= datetime('now', '-6 months')")->fetchColumn();
    if ($total() === 3 && (int) $restam === 3) ok('apagou os 2 antigos e manteve os 3 dentro dos 6 meses'); else falha('limpeza errada: restaram ' . $total());
    if ((Settings::$v['login_log_limpo_em'] ?? '') === date('Y-m-d')) ok('marca o dia da ultima limpeza'); else falha('nao marcou login_log_limpo_em');

    echo "\n3. No maximo uma limpeza por dia (roda no login, nao pode pesar)\n";
    $pdo->exec("INSERT INTO login_log (steam_id, ip, created_at) VALUES ('76561190000000001', '10.0.0.1', datetime('now', '-9 months'))");
    Database::$consultas = 0;
    LoginLog::limparSeDevido();
    if (Database::$consultas === 0 && $total() === 4) ok('segunda chamada no mesmo dia nao consulta o banco'); else falha('limpou de novo no mesmo dia (' . Database::$consultas . ' consultas)');
    Settings::$v['login_log_limpo_em'] = date('Y-m-d', strtotime('-1 day'));
    LoginLog::limparSeDevido();
    if ($total() === 3) ok('no dia seguinte limpa de novo'); else falha('nao limpou no dia seguinte');

    echo "\n4. limpar() direto devolve quantos apagou\n";
    $semeia();
    if (LoginLog::limpar() === 2 && $total() === 3) ok('limpar() apagou 2 e devolveu 2'); else falha('limpar() nao devolve a contagem');
    if (LoginLog::MESES === 6) ok('prazo de 6 meses (Marco Civil, art. 15)'); else falha('prazo diferente de 6 meses');

    echo "\n5. Login, painel e seeds\n";
    $idx = file_get_contents($ROOT . '/public/index.php');
    $ins = strpos($idx, 'INSERT INTO login_log');
    $lim = strpos($idx, 'LoginLog::limparSeDevido()');
    if ($ins !== false && $lim !== false && $lim > $ins && $lim - $ins < 800) ok('o login chama limparSeDevido logo depois de gravar'); else falha('limpeza nao esta ligada ao login');
    $r = strpos($idx, "Router::post('/admin/eca/retencao-login'");
    $rota = $r === false ? '' : substr($idx, $r, 1500);
    if ($rota !== '' && str_contains($rota, "Auth::requireCan('settings')") && str_contains($rota, 'Csrf::check()')) ok('rota do painel com permissao e CSRF'); else falha('rota /admin/eca/retencao-login ausente ou sem protecao');
    if (str_contains($rota, "AuditLog::record('login_log.retencao'")) ok('ligar e desligar ficam no log de auditoria (a decisao e do dono)'); else falha('decisao do dono nao auditada');
    $eca = file_get_contents($ROOT . '/views/admin/eca.php');
    if (str_contains($eca, 'action="/admin/eca/retencao-login"') && preg_match('/data-confirm="[^"]*apaga/i', $eca)) ok('painel tem o botao, e ligar avisa que apaga os antigos'); else falha('painel sem o controle da retencao ou sem confirmacao');
    $set = file_get_contents($ROOT . '/src/Settings.php');
    if (str_contains($set, "'login_log_retencao'") && str_contains($set, "'login_log_limpo_em'")) ok('chaves no SCHEMA'); else falha('chaves fora do SCHEMA');
    $schema = file_get_contents($ROOT . '/schema.sql');
    if (preg_match("/\('login_log_retencao',\s*'1'\)/", $schema)) ok('instalacao nova nasce com a retencao ligada'); else falha('schema.sql sem login_log_retencao = 1');
    $mig = @file_get_contents($ROOT . '/migrations/v3.6.0_login_log_retencao.sql') ?: '';
    if (preg_match("/INSERT IGNORE INTO settings[^;]*\('login_log_retencao',\s*'0'\)/s", $mig)) ok('site existente nasce desligado, sem sobrescrever instalacao nova'); else falha('migration nao grava login_log_retencao = 0 com INSERT IGNORE');
    if (!preg_match('/--[^\n]*;/', $mig)) ok('migration sem ; em comentario'); else falha('; em comentario parte a migration');

    echo "\n" . str_repeat('-', 62) . "\n";
    if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
    echo "$falhas FALHA(S).\n"; exit(1);
}
