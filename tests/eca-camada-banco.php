<?php
/**
 * Camada de banco da verificacao de idade EXECUTADA de verdade, em SQLite na memoria.
 * A revisao do 3.3.0 pegou tres defeitos que os testes por grep deixaram passar
 * (beco sem saida, menor redeclarando, UNIQUE em 500). Este arquivo roda declarar/
 * verificar/revogar/consentir contra um banco real com um fornecedor falso.
 *
 * Como: um App\Database de teste (mesma API estatica) por cima de PDO sqlite::memory:,
 * um App\Settings de teste em memoria, e AgeVerification::usarVerificador() pra injetar
 * o fornecedor. Nao encosta em MySQL nem em rede.
 * Rodar: php tests/eca-camada-banco.php
 */

namespace App {
    class Database {
        public static ?\PDO $pdo = null;
        public static function pdo(): \PDO { return self::$pdo; }
        private static function sql(string $s): string {
            // MariaDB -> SQLite: so o que a camada usa.
            $s = preg_replace('/NOW\(\) - INTERVAL \? HOUR/', "datetime('now', '-' || ? || ' hours')", $s);
            return str_replace('NOW()', "datetime('now')", $s);
        }
        public static function query(string $sql, array $params = []): \PDOStatement { $st = self::$pdo->prepare(self::sql($sql)); $st->execute($params); return $st; }
        public static function execute(string $sql, array $params = []): int { return self::query($sql, $params)->rowCount(); }
        public static function fetchOne(string $sql, array $params = []): ?array { $r = self::query($sql, $params)->fetch(\PDO::FETCH_ASSOC); return $r === false ? null : $r; }
        public static function fetchAll(string $sql, array $params = []): array { return self::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC); }
        public static function fetchColumn(string $sql, array $params = []) { return self::query($sql, $params)->fetchColumn(); }
    }
    class Settings {
        public static array $v = ['age_gate_mode' => 'verificado', 'age_provider' => 'cpfhub', 'age_provider_key' => 'k', 'age_hash_salt' => 'sal-teste', 'terms_version' => '7', 'age_daily_box_gated' => '1'];
        public static function get(string $k, $d = null) { return self::$v[$k] ?? $d; }
        public static function getBool(string $k, bool $d = false): bool { return isset(self::$v[$k]) ? self::$v[$k] === '1' : $d; }
        public static function getInt(string $k, int $d = 0): int { return (int) (self::$v[$k] ?? $d); }
        public static function set(string $k, string $raw): bool { self::$v[$k] = $raw; return true; }
    }
    class RateLimit { public static function clientIp(): string { return '127.0.0.1'; } }
}

namespace {
    $falhas = 0;
    function ok(string $d): void { echo "  OK   $d\n"; }
    function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
    $ROOT = dirname(__DIR__);
    require_once $ROOT . '/src/AgeGate.php';
    require_once $ROOT . '/src/AgeVerifier.php';
    require_once $ROOT . '/src/AgeVerification.php';

    class VerificadorFalso implements \App\AgeVerifier {
        public array $respostas = []; public int $chamadas = 0;
        public function verify(string $cpf, ?string $n): array { $this->chamadas++; return array_shift($this->respostas) ?: ['result' => 'falhou', 'ref' => null, 'status' => null, 'error' => 'sem resposta']; }
        public function test(): array { return ['ok' => true, 'msg' => 'falso']; }
        public function nome(): string { return 'Falso'; }
    }

    use App\AgeVerification, App\Database, App\Settings;

    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    Database::$pdo = $pdo;
    $pdo->exec("CREATE TABLE players (id INTEGER PRIMARY KEY AUTOINCREMENT, steam_id TEXT UNIQUE, coins INTEGER DEFAULT 0, origin TEXT, last_seen_at TEXT, age_status TEXT NOT NULL DEFAULT 'desconhecido', age_verified_at TEXT)");
    $pdo->exec("CREATE TABLE age_verifications (id INTEGER PRIMARY KEY AUTOINCREMENT, steam_id TEXT NOT NULL, method TEXT, result TEXT, birth_year INTEGER, cpf_hash TEXT UNIQUE, cpf_hash_revoked TEXT, provider_ref TEXT, provider_status TEXT, ip TEXT, user_agent TEXT, created_at TEXT DEFAULT (datetime('now')), revoked_at TEXT, revoked_reason TEXT)");
    $pdo->exec("CREATE TABLE consents (id INTEGER PRIMARY KEY AUTOINCREMENT, steam_id TEXT, kind TEXT, version TEXT, text_hash TEXT, ip TEXT, user_agent TEXT, created_at TEXT DEFAULT (datetime('now')))");
    $pdo->exec("CREATE TABLE pages (slug TEXT, content TEXT)");
    $pdo->exec("INSERT INTO pages VALUES ('terms', 'TERMOS V7'), ('privacy', 'PRIV V7')");
    $A = '76561198000000001'; $B = '76561198000000002';
    $CPF = '52998224725';
    $falso = new VerificadorFalso(); AgeVerification::usarVerificador($falso);
    $adulto = ['result' => 'adulto', 'ref' => 'ref-1', 'status' => 'valid', 'error' => null];

    echo "\n1. Declarar\n";
    $r = AgeVerification::declarar($A, '2000-01-01');
    if ($r['ok'] && $r['status'] === 'adulto_declarado' && AgeVerification::statusDe($A) === 'adulto_declarado') ok('adulto declarado grava e le'); else falha('declarar adulto', json_encode($r));
    $r = AgeVerification::declarar($B, '2012-05-05');
    if ($r['ok'] && AgeVerification::statusDe($B) === 'menor') ok('menor declarado'); else falha('declarar menor', json_encode($r));
    $r = AgeVerification::declarar($B, '2000-01-01');
    if (!$r['ok'] && $r['cod'] === 'menor_so_cpf' && AgeVerification::statusDe($B) === 'menor') ok('menor NAO vira adulto redeclarando (revisao I2)'); else falha('menor redeclarou e passou', json_encode($r));
    $r = AgeVerification::declarar($A, 'abc');
    if (!$r['ok'] && $r['cod'] === 'nasc_invalida') ok('data invalida recusada'); else falha('data invalida aceita');
    if (!AgeVerification::podeAbrirCaixa($A, false) && AgeVerification::podeComprar($A)) ok('declarado compra mas nao abre caixa'); else falha('gate do declarado errado');

    echo "\n2. Verificar\n";
    $falso->respostas = [$adulto];
    $r = AgeVerification::verificar($A, '529.982.247-25', null);
    if ($r['ok'] && $r['status'] === 'adulto_verificado' && AgeVerification::podeAbrirCaixa($A, false)) ok('verificado abre caixa'); else falha('verificar adulto', json_encode($r));
    $linha = Database::fetchOne("SELECT * FROM age_verifications WHERE steam_id = ? AND method = 'cpfhub'", [$A]);
    if ($linha && strlen((string) $linha['cpf_hash']) === 64 && !str_contains(json_encode($linha), $CPF) && $linha['provider_ref'] === 'ref-1') ok('so o hash e o ref foram gravados; o CPF nao esta em lugar nenhum'); else falha('linha de verificacao', json_encode($linha));
    $falso->chamadas = 0; $falso->respostas = [$adulto];
    $r = AgeVerification::verificar($A, $CPF, null);
    if ($r['ok'] && $falso->chamadas === 0) ok('mesmo jogador de novo: reaplica sem chamar o fornecedor (revisao I3)'); else falha('segundo clique chamou fornecedor ou falhou', json_encode($r));
    $falso->respostas = [$adulto];
    $r = AgeVerification::verificar($B, $CPF, '2000-01-01');
    if (!$r['ok'] && $r['cod'] === 'cpf_taken' && AgeVerification::statusDe($B) === 'menor') ok('mesmo CPF em outra conta = cpf_taken, status intacto'); else falha('cpf_taken', json_encode($r));
    $falso->respostas = [['result' => 'falhou', 'ref' => null, 'status' => null, 'error' => 'caiu']];
    $antes = AgeVerification::statusDe($B);
    $r = AgeVerification::verificar($B, '11144477735', '2000-01-01');
    if (!$r['ok'] && $r['cod'] === 'failed_retry' && AgeVerification::statusDe($B) === $antes && Database::fetchColumn("SELECT COUNT(*) FROM age_verifications WHERE result = 'falhou'") == 1) ok('fornecedor caiu: falhou registrado, status intacto (foco 3)'); else falha('falha do fornecedor', json_encode($r));
    if (AgeVerification::falhasRecentes(24) === 1) ok('falhasRecentes conta a falha'); else falha('falhasRecentes', (string) AgeVerification::falhasRecentes(24));
    $r = AgeVerification::verificar($A, '123', null);
    if (!$r['ok'] && $r['cod'] === 'cpf_invalido') ok('CPF invalido nem chama o fornecedor'); else falha('cpf invalido', json_encode($r));

    echo "\n3. Revogar e reverificar\n";
    $id = (int) Database::fetchColumn("SELECT id FROM age_verifications WHERE steam_id = ? AND cpf_hash IS NOT NULL", [$A]);
    if (AgeVerification::revogar($id, 'teste') && AgeVerification::statusDe($A) === 'adulto_declarado') ok('revogar volta pra declarado'); else falha('revogar');
    $l = Database::fetchOne("SELECT cpf_hash, cpf_hash_revoked, revoked_reason FROM age_verifications WHERE id = ?", [$id]);
    if ($l['cpf_hash'] === null && strlen((string) $l['cpf_hash_revoked']) === 64 && $l['revoked_reason'] === 'teste') ok('hash moveu pra cpf_hash_revoked (libera o UNIQUE)'); else falha('hash nao moveu', json_encode($l));
    $falso->respostas = [$adulto];
    $r = AgeVerification::verificar($A, $CPF, null);
    if ($r['ok'] && $r['status'] === 'adulto_verificado') ok('reverificar com o mesmo CPF depois de revogado passa (foco 4)'); else falha('reverificar apos revogar', json_encode($r));
    $falso->respostas = [$adulto];
    $r = AgeVerification::verificar($B, $CPF, '2000-01-01');
    if (!$r['ok'] && $r['cod'] === 'cpf_taken') ok('e a outra conta continua barrada'); else falha('outra conta passou apos reverificacao', json_encode($r));

    echo "\n4. Consentimento\n";
    AgeVerification::consentirTudo($A, 'rotulo');
    $c = Database::fetchAll("SELECT kind, version, text_hash FROM consents WHERE steam_id = ? ORDER BY id", [$A]);
    if (count($c) === 3 && $c[0]['version'] === '7' && AgeVerification::temConsentimento($A, 'termos', '7') && !AgeVerification::temConsentimento($A, 'termos', '8')) ok('3 tipos, versao atual, temConsentimento distingue versao'); else falha('consentimento', json_encode($c));
    if (($c[0]['text_hash'] ?? '') === hash('sha256', 'TERMOS V7') && ($c[1]['text_hash'] ?? '') === hash('sha256', 'PRIV V7')) ok('hash e do CONTEUDO das paginas legais, nao do rotulo (revisao menor)'); else falha('hash do consentimento nao vem das paginas', json_encode($c));

    echo "\n5. Menor com modo desligado\n";
    Settings::$v['age_gate_mode'] = 'desligado';
    if (!AgeVerification::podeAbrirCaixa($B, true) && !AgeVerification::podeComprar($B)) ok('menor segue bloqueado com o modo desligado (foco 5)'); else falha('desligado reabriu pro menor');

    echo "\n" . str_repeat('-', 62) . "\n";
    if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
    echo "$falhas FALHA(S).\n"; exit(1);
}
