<?php
/**
 * Login em duas etapas (opcional) do admin, executado de verdade: TOTP contra os vetores da
 * RFC 6238, base32 da RFC 4648, cifra do segredo, codigos de recuperacao de uso unico, e a
 * camada de banco em SQLite na memoria. Mais o fio da meada nas rotas.
 * Rodar: php tests/admin-2fa.php
 */

namespace App {
    class Database {
        public static ?\PDO $pdo = null;
        public static function pdo(): \PDO { return self::$pdo; }
        private static function sql(string $s): string { return str_replace('NOW()', "datetime('now')", $s); }
        public static function query(string $sql, array $params = []): \PDOStatement { $st = self::$pdo->prepare(self::sql($sql)); $st->execute($params); return $st; }
        public static function execute(string $sql, array $params = []): int { return self::query($sql, $params)->rowCount(); }
        public static function fetchOne(string $sql, array $params = []): ?array { $r = self::query($sql, $params)->fetch(\PDO::FETCH_ASSOC); return $r === false ? null : $r; }
        public static function fetchAll(string $sql, array $params = []): array { return self::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC); }
        public static function fetchColumn(string $sql, array $params = []) { return self::query($sql, $params)->fetchColumn(); }
    }
}

namespace {
    $falhas = 0;
    function ok(string $d): void { echo "  OK   $d\n"; }
    function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
    $ROOT = dirname(__DIR__);
    foreach (['Totp', 'DoisFatores'] as $c) {
        if (!is_file("$ROOT/src/$c.php")) { falha("src/$c.php nao existe"); echo "\nFALHOU: $falhas\n"; exit(1); }
        require_once "$ROOT/src/$c.php";
    }
    use App\Totp, App\DoisFatores, App\Database;

    echo "\n1. base32 (RFC 4648) e TOTP (RFC 6238, SHA1)\n";
    foreach (['' => '', 'f' => 'MY', 'fo' => 'MZXQ', 'foo' => 'MZXW6', 'foob' => 'MZXW6YQ', 'fooba' => 'MZXW6YTB', 'foobar' => 'MZXW6YTBOI'] as $bin => $b32) {
        if (Totp::base32Encode($bin) !== $b32) falha("base32('$bin')", Totp::base32Encode($bin));
        if (Totp::base32Decode($b32) !== $bin) falha("base32Decode('$b32')");
    }
    ok('base32 bate com os vetores da RFC 4648 nos dois sentidos');
    if (Totp::base32Decode('mzxw 6ytb oi') === 'foobar') ok('decodifica minusculo e com espaco (como o app mostra)'); else falha('base32Decode nao tolera minusculo/espaco');
    $seg = Totp::base32Encode('12345678901234567890');   // segredo do apendice B da RFC 6238
    $vetores = [59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'];
    $errados = [];
    foreach ($vetores as $t => $esperado) {
        if (Totp::codigo($seg, intdiv($t, 30), 8) !== $esperado) $errados[] = "$t";
        if (Totp::codigo($seg, intdiv($t, 30), 6) !== substr($esperado, -6)) $errados[] = "$t(6)";
    }
    if (!$errados) ok('6 vetores da RFC 6238 certos, com 8 e 6 digitos'); else falha('vetor errado: ' . implode(', ', $errados));
    $novo = Totp::novoSegredo();
    if (preg_match('/^[A-Z2-7]{32}$/', $novo) && strlen(Totp::base32Decode($novo)) === 20 && $novo !== Totp::novoSegredo()) ok('segredo novo: 160 bits aleatorios em base32'); else falha('segredo novo fora do padrao', $novo);
    $uri = Totp::uri('Meu Site DayZ', 'admin', $novo);
    if (str_starts_with($uri, 'otpauth://totp/') && str_contains($uri, 'secret=' . $novo) && str_contains($uri, 'issuer=Meu%20Site%20DayZ') && str_contains($uri, 'digits=6') && str_contains($uri, 'period=30')) ok('URI otpauth pro QR'); else falha('URI otpauth errada', $uri);

    echo "\n2. Verificar: janela de 30s pra cada lado, sem repetir codigo\n";
    $agora = 1234567890; $passo = intdiv($agora, 30);
    $c0 = Totp::codigo($seg, $passo); $cAnt = Totp::codigo($seg, $passo - 1); $cProx = Totp::codigo($seg, $passo + 1); $cVelho = Totp::codigo($seg, $passo - 3);
    if (Totp::verificar($seg, $c0, null, $agora) === $passo) ok('codigo do momento vale'); else falha('codigo do momento recusado');
    if (Totp::verificar($seg, $cAnt, null, $agora) === $passo - 1 && Totp::verificar($seg, $cProx, null, $agora) === $passo + 1) ok('relogio do celular 30s adiantado ou atrasado ainda passa'); else falha('janela de tolerancia errada');
    if (Totp::verificar($seg, $cVelho, null, $agora) === null) ok('codigo de 90s atras nao vale'); else falha('aceitou codigo velho');
    if (Totp::verificar($seg, $c0, $passo, $agora) === null) ok('mesmo codigo nao vale duas vezes (replay)'); else falha('aceitou o mesmo codigo de novo');
    if (Totp::verificar($seg, substr($c0, 0, 3) . ' ' . substr($c0, 3), null, $agora) === $passo) ok('aceita "123 456" (espaco que o app mostra)'); else falha('nao tolera espaco no codigo');
    if (Totp::verificar($seg, 'abcdef', null, $agora) === null && Totp::verificar($seg, '', null, $agora) === null && Totp::verificar('', $c0, null, $agora) === null) ok('lixo e segredo vazio sao recusados'); else falha('aceitou entrada invalida');

    echo "\n3. Cifra do segredo (a chave fica num arquivo, fora do banco)\n";
    $dirChave = sys_get_temp_dir() . '/dzw-2fa-teste-' . bin2hex(random_bytes(4));
    DoisFatores::usarDirChave($dirChave);
    $blob = DoisFatores::cifrar($novo);
    if ($blob !== $novo && !str_contains($blob, $novo) && DoisFatores::decifrar($blob) === $novo) ok('cifra e decifra; o segredo nao aparece no texto cifrado'); else falha('cifra falhou');
    if (DoisFatores::cifrar($novo) !== $blob) ok('cada cifra usa IV novo'); else falha('IV repetido');
    $adulterado = substr($blob, 0, -3) . (substr($blob, -3, 1) === 'A' ? 'B' : 'A') . substr($blob, -2);
    if (DoisFatores::decifrar($adulterado) === null) ok('texto cifrado adulterado e recusado (GCM)'); else falha('aceitou cifra adulterada');
    if (is_file($dirChave . '/2fa.key') && strlen(trim(file_get_contents($dirChave . '/2fa.key'))) === 64) ok('chave de 256 bits criada no primeiro uso'); else falha('arquivo de chave errado');

    echo "\n4. Camada de banco: ligar, entrar com codigo, codigo de recuperacao, desligar\n";
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    Database::$pdo = $pdo;
    $pdo->exec("CREATE TABLE admin_users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, password_hash TEXT, role TEXT, last_login_at TEXT, totp_secret TEXT, totp_enabled INTEGER NOT NULL DEFAULT 0, totp_last_step INTEGER, totp_recovery TEXT, totp_enabled_at TEXT)");
    $pdo->exec("INSERT INTO admin_users (username, password_hash, role) VALUES ('dono', 'x', 'super_admin'), ('outro', 'x', 'editor')");
    $s1 = Totp::novoSegredo();
    $errado = str_pad((string) (((int) Totp::codigo($s1, Totp::passoAtual())) + 1) % 1000000, 6, '0', STR_PAD_LEFT);
    if (DoisFatores::ativar(1, $s1, $errado) === null && !DoisFatores::ativoPara(1)) ok('codigo errado na ativacao nao liga'); else falha('ligou com codigo errado');
    $pAtiv = Totp::passoAtual();   // passo fixo: o teste nao pode depender da virada dos 30s
    $cods = DoisFatores::ativar(1, $s1, Totp::codigo($s1, $pAtiv));
    if (is_array($cods) && count($cods) === 8 && count(array_unique($cods)) === 8 && DoisFatores::ativoPara(1)) ok('ligou e devolveu 8 codigos de recuperacao diferentes'); else falha('ativacao nao devolveu os 8 codigos', json_encode($cods));
    $linha = Database::fetchOne("SELECT * FROM admin_users WHERE id = 1");
    $semTexto = true; foreach ((array) $cods as $c) if (str_contains((string) $linha['totp_recovery'], $c)) $semTexto = false;
    if ($semTexto && !str_contains((string) $linha['totp_secret'], $s1)) ok('banco guarda segredo cifrado e codigos so como hash'); else falha('segredo ou codigo em texto no banco');
    if (!DoisFatores::ativoPara(2)) ok('outro admin continua sem a protecao (e opcional)'); else falha('ligou pra outro admin');
    // o codigo usado na ativacao nao pode ser reusado no login
    if (!DoisFatores::conferir(1, Totp::codigo($s1, $pAtiv))) ok('codigo da ativacao nao entra de novo no login (replay)'); else falha('replay aceito no login');
    if (DoisFatores::conferir(1, Totp::codigo($s1, $pAtiv + 1))) ok('login aceita o codigo seguinte do app'); else falha('login recusou codigo valido');
    if (!DoisFatores::conferir(2, $cods[0])) ok('codigo de um admin nao serve pra outro'); else falha('codigo cruzou de admin');
    if (DoisFatores::conferir(1, strtoupper($cods[0]))) ok('codigo de recuperacao entra (maiusculo tambem)'); else falha('codigo de recuperacao recusado');
    if (!DoisFatores::conferir(1, $cods[0])) ok('codigo de recuperacao e de uso unico'); else falha('codigo de recuperacao usado duas vezes');
    if (DoisFatores::estado(1)['codigos_restantes'] === 7) ok('sobram 7 codigos'); else falha('contagem de codigos errada', json_encode(DoisFatores::estado(1)));
    $novos = DoisFatores::regenerarCodigos(1);
    if (count($novos) === 8 && !DoisFatores::conferir(1, $cods[1]) && DoisFatores::estado(1)['codigos_restantes'] === 8) ok('gerar codigos novos invalida os antigos'); else falha('codigos antigos seguiram valendo');
    DoisFatores::desligar(1);
    $l2 = Database::fetchOne("SELECT * FROM admin_users WHERE id = 1");
    if (!DoisFatores::ativoPara(1) && $l2['totp_secret'] === null && $l2['totp_recovery'] === null) ok('desligar apaga segredo e codigos'); else falha('desligar deixou rastro');
    if (!DoisFatores::conferir(1, $novos[0])) ok('desligado, nenhum codigo vale'); else falha('codigo valeu com a protecao desligada');

    echo "\n5. Sem a chave (arquivo perdido), o codigo de recuperacao ainda salva\n";
    $s2 = Totp::novoSegredo();
    $c2 = DoisFatores::ativar(1, $s2, Totp::codigo($s2, Totp::passoAtual()));
    @unlink($dirChave . '/2fa.key');
    DoisFatores::usarDirChave($dirChave . '-outra');
    if (!DoisFatores::conferir(1, Totp::codigo($s2, Totp::passoAtual() + 1))) ok('sem a chave o codigo do app nao passa (nao decifra)'); else falha('decifrou sem a chave');
    if (DoisFatores::conferir(1, $c2[0])) ok('codigo de recuperacao continua entrando'); else falha('perdeu a chave e ficou trancado');

    echo "\n6. Fio da meada: login, rotas, migration\n";
    $idx = file_get_contents("$ROOT/public/index.php");
    $auth = file_get_contents("$ROOT/src/Auth.php");
    $ini = strpos($idx, "Router::post('/admin/login',"); $login = substr($idx, $ini, strpos($idx, '\App\Router::', $ini + 10) - $ini);
    if (str_contains($login, 'Auth::credenciais(') && str_contains($login, "['admin_2fa_pendente']") && strpos($login, 'totp_enabled') < strpos($login, 'Auth::entrar(')) ok('login com a protecao ligada vira pendente ANTES de abrir a sessao'); else falha('login abre sessao sem pedir o codigo');
    if (preg_match('/function attempt\(.*?totp_enabled.*?return false/s', $auth)) ok('Auth::attempt nunca abre sessao de quem tem a protecao ligada'); else falha('Auth::attempt ainda abre sessao de admin com 2 etapas');
    $ini2 = strpos($idx, "Router::post('/admin/login/2fa',"); $l2fa = $ini2 !== false ? substr($idx, $ini2, strpos($idx, '\App\Router::', $ini2 + 10) - $ini2) : '';
    if ($l2fa && str_contains($l2fa, 'Csrf::check()') && str_contains($l2fa, 'RateLimit::check(') && str_contains($l2fa, 'DoisFatores::conferir(') && preg_match('/\\$p\[.em.\].*300/s', $l2fa)) ok('etapa do codigo: CSRF, limite de tentativas e pendente de 5 minutos'); else falha('rota /admin/login/2fa incompleta');
    foreach (["/admin/conta/2fa/iniciar", "/admin/conta/2fa/confirmar", "/admin/conta/2fa/desligar", "/admin/conta/2fa/codigos", "/admin/team/{id}/2fa-desligar"] as $r)
        if (!str_contains($idx, "Router::post('$r'")) falha("rota $r nao existe");
    ok('rotas da conta e da equipe existem (protecao e CSRF: tests/rotas-protegidas.php)');
    $ini3 = strpos($idx, "Router::post('/admin/conta/2fa/desligar',"); $des = substr($idx, $ini3, 900);
    if (str_contains($des, 'Auth::credenciais(') && str_contains($des, 'DoisFatores::conferir(')) ok('desligar a propria exige senha E codigo'); else falha('desligar sem senha e codigo');
    // O portao global de POST do admin exige sessao; a etapa do codigo roda ANTES dela existir.
    if (preg_match('/\$publicAdminPost\s*=\s*in_array\(\$path,\s*\[[^\]]*\'\/admin\/login\/2fa\'/', $idx)) ok('portao global de POST do admin deixa passar /admin/login/2fa'); else falha('portao global barra a etapa do codigo', 'requireAdmin manda pro login antes da rota rodar');
    $mig = is_file("$ROOT/migrations/v3.4.0_admin_2fa.sql") ? file_get_contents("$ROOT/migrations/v3.4.0_admin_2fa.sql") : '';
    $cols = ['totp_secret', 'totp_enabled', 'totp_last_step', 'totp_recovery', 'totp_enabled_at'];
    $faltam = array_filter($cols, fn($c) => !preg_match("/ADD COLUMN IF NOT EXISTS $c\b/", $mig));
    if ($mig && !$faltam) ok('migration idempotente com as 5 colunas'); else falha('migration faltando: ' . implode(', ', $faltam));

    echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
    exit($falhas ? 1 : 0);
}
