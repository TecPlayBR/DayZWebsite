<?php
/**
 * Aviso do painel quando ninguem consegue abrir caixa (3.9.1, 02/10/2026).
 * Num site com caixas ativas, o Danoninho-Z ficou 6 dias sem a chave do fornecedor de
 * verificacao. Desde a 3.6.0 a caixa diaria tambem exige CPF, entao NINGUEM abria caixa
 * (o jogador via "verificacao indisponivel"). O painel so dizia "ECA Digital em transicao",
 * em amarelo; no modo Verificado sem chave nao dizia nada. E com chave no modo Declaracao
 * dizia "caixas fechadas" sem ser verdade.
 * Regra nova: o aviso olha o que acontece com o jogador (chave, caixas ativas, falhas), nao o
 * nome do modo. Os modos Declaracao e Verificado se comportam igual (AgeGate).
 * Rodar: php tests/eca-aviso-painel.php
 */

namespace App {
    class Database {
        public static ?\PDO $pdo = null;
        public static function pdo(): \PDO { return self::$pdo; }
        private static function sql(string $s): string {
            $s = preg_replace('/NOW\(\) - INTERVAL \? HOUR/', "datetime('now', '-' || ? || ' hours')", $s);
            return str_replace('NOW()', "datetime('now')", $s);
        }
        public static function query(string $sql, array $params = []): \PDOStatement { $st = self::$pdo->prepare(self::sql($sql)); $st->execute($params); return $st; }
        public static function fetchOne(string $sql, array $params = []): ?array { $r = self::query($sql, $params)->fetch(\PDO::FETCH_ASSOC); return $r === false ? null : $r; }
        public static function fetchAll(string $sql, array $params = []): array { return self::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC); }
        public static function fetchColumn(string $sql, array $params = []) { return self::query($sql, $params)->fetchColumn(); }
    }
    class Settings {
        public static array $v = [];
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
    use App\AgeGate, App\AgeVerification, App\Database, App\Settings;

    echo "\n1. Regra pura: o aviso segue o que o jogador vive, nao o nome do modo\n";
    if (!method_exists(AgeGate::class, 'avisoPainel')) {
        falha('AgeGate::avisoPainel nao existe');
    } else {
        $casos = [
            // modo, chave, caixas ativas, falhas 24h => aviso
            ['verificado', false, 2, 0, 'sem_chave', 'Verificado sem chave e com caixa: ninguem abre (o buraco que nao avisava)'],
            ['declaracao', false, 1, 0, 'sem_chave', 'Declaracao sem chave e com caixa: mesmo aviso forte'],
            ['verificado', false, 0, 0, null,        'sem caixa ativa nao incomoda o dono'],
            ['declaracao', false, 0, 0, null,        'Declaracao sem caixa ativa tambem nao'],
            ['declaracao', true,  3, 0, null,        'Declaracao COM chave: caixas abrem, nada de "caixas fechadas"'],
            ['verificado', true,  3, 0, null,        'Verificado com chave e sem falha: nenhum aviso'],
            ['verificado', true,  3, 3, 'falhando',  'chave que falha 3 vezes em 24 h: creditos acabaram ou chave morreu'],
            ['verificado', true,  3, 2, null,        'duas falhas ainda nao avisam (instabilidade passageira)'],
            ['desligado',  false, 3, 0, 'desligado', 'modo desligado continua avisando que as caixas estao indisponiveis'],
            ['desligado',  true,  0, 5, 'desligado', 'desligado vence os outros avisos'],
        ];
        foreach ($casos as [$modo, $chave, $cx, $fa, $esperado, $desc]) {
            $r = AgeGate::avisoPainel($modo, $chave, $cx, $fa);
            if ($r === $esperado) ok($desc); else falha($desc, 'veio ' . var_export($r, true) . ', esperado ' . var_export($esperado, true));
        }
    }

    echo "\n2. AgeVerification::avisoPainel le o banco de verdade\n";
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    Database::$pdo = $pdo;
    $pdo->exec("CREATE TABLE age_verifications (id INTEGER PRIMARY KEY AUTOINCREMENT, steam_id TEXT, method TEXT, result TEXT, created_at TEXT DEFAULT (datetime('now')))");
    $pdo->exec("CREATE TABLE boxes (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, is_daily INTEGER DEFAULT 0, enabled INTEGER DEFAULT 1)");
    $pdo->exec("INSERT INTO boxes (name, is_daily, enabled) VALUES ('Diaria', 1, 1), ('Rara', 0, 1), ('Velha', 0, 0)");
    Settings::$v = ['age_gate_mode' => 'verificado', 'age_provider' => 'cpfhub', 'age_provider_key' => ''];
    if (!method_exists(AgeVerification::class, 'avisoPainel')) {
        falha('AgeVerification::avisoPainel nao existe');
    } else {
        $a = AgeVerification::avisoPainel();
        if (($a['cod'] ?? null) === 'sem_chave' && ($a['caixas'] ?? null) === 2 && ($a['diaria'] ?? null) === true) ok('sem chave: conta so as caixas ATIVAS (2) e sabe que a diaria esta entre elas');
        else falha('leitura do banco errada', json_encode($a));
        $pdo->exec("UPDATE boxes SET enabled = 0 WHERE is_daily = 1");
        $a = AgeVerification::avisoPainel();
        if (($a['diaria'] ?? null) === false && ($a['caixas'] ?? null) === 1) ok('diaria desativada: o aviso nao fala dela'); else falha('diaria desativada contada', json_encode($a));
        Settings::$v['age_provider_key'] = 'chave';
        if (AgeVerification::avisoPainel() === null) ok('com chave e sem falha: nenhum aviso'); else falha('avisou com chave', json_encode(AgeVerification::avisoPainel()));
        for ($i = 0; $i < 3; $i++) $pdo->exec("INSERT INTO age_verifications (steam_id, method, result) VALUES ('x', 'cpfhub', 'falhou')");
        $a = AgeVerification::avisoPainel();
        if (($a['cod'] ?? null) === 'falhando' && ($a['falhas'] ?? null) === 3) ok('3 falhas do fornecedor em 24 h: aviso de falha com a contagem'); else falha('falhas nao avisaram', json_encode($a));
        $pdo->exec("DROP TABLE boxes");
        Settings::$v['age_provider_key'] = '';
        try { $a = AgeVerification::avisoPainel(); ok('sem a tabela de caixas o painel nao cai (aviso: ' . var_export($a['cod'] ?? null, true) . ')'); }
        catch (\Throwable $e) { falha('painel cai sem a tabela de caixas', $e->getMessage()); }
    }

    echo "\n3. O painel usa a regra nova em todas as telas\n";
    $lay = file_get_contents($ROOT . '/views/admin/layout.php');
    if (str_contains($lay, 'AgeVerification::avisoPainel()')) ok('layout pergunta a regra'); else falha('layout nao chama AgeVerification::avisoPainel()');
    if (!preg_match('/\$ecaModo\s*!==\s*\'verificado\'/', $lay)) ok('acabou o aviso pelo NOME do modo'); else falha('layout ainda decide pelo nome do modo', 'Verificado sem chave fica sem aviso');
    if (substr_count($lay, 'falhasRecentes(') === 0) ok('falha do fornecedor entrou na mesma regra (um aviso por vez)'); else falha('aviso de falha ainda separado da regra');
    if (str_contains($lay, '/admin/settings#eca')) ok('o aviso leva direto ao campo da chave'); else falha('aviso sem link pro campo da chave');
    if (preg_match('/di[aá]ria/u', $lay)) ok('o texto fala da caixa diaria'); else falha('texto nao cita a diaria (foi ela que travou os jogadores)');
    if (str_contains($lay, 'try') && preg_match('/try\s*\{[^}]*avisoPainel/s', $lay)) ok('falha ao montar o aviso nao derruba o painel'); else falha('avisoPainel no layout sem try');

    echo "\n4. Os textos dos modos nao prometem o que nao acontece\n";
    $set = file_get_contents($ROOT . '/views/admin/settings.php');
    $eca = file_get_contents($ROOT . '/views/admin/eca.php');
    $guia = file_get_contents($ROOT . '/ECA-DIGITAL.md');
    if (!str_contains($set, 'aviso amarelo') && !str_contains($guia, 'aviso amarelo')) ok('nada promete o aviso amarelo que deixou de existir'); else falha('settings ou ECA-DIGITAL.md ainda prometem aviso amarelo');
    if (!str_contains($eca, 'caixas fechadas até colar a chave e mudar para Verificado')) ok('/admin/eca nao diz que Declaracao com chave fecha as caixas'); else falha('/admin/eca diz que Declaracao fecha caixas mesmo com chave');
    $iSem = strpos($eca, '!$tem_chave'); $iConf = strpos($eca, '· conforme');
    if ($iSem !== false && $iConf !== false && $iSem < $iConf && str_contains($eca, 'ninguém abre caixa')) ok('/admin/eca so diz "conforme" com chave; sem chave diz que ninguem abre caixa');
    else falha('/admin/eca mostra "conforme" no modo Verificado sem chave', 'foi assim que o Danoninho-Z ficou 6 dias');
    if (preg_match('/border-left:3px solid <\?= \$tem_chave && \$modo === \'verificado\' \? \'var\(--moss\)\'/', $eca)) ok('borda verde do cartao de modo so com chave'); else falha('cartao de modo fica verde no Verificado sem chave');

    echo "\n" . str_repeat('-', 62) . "\n";
    if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
    echo "$falhas FALHA(S).\n"; exit(1);
}
