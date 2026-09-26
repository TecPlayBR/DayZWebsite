<?php
/**
 * Entrar num cla exige o consentimento dos DOIS lados, executado de verdade em SQLite na memoria:
 * jogador so entra por convite que existe; dono so aceita pedido que existe; convite nao vira
 * pedido nem o contrario. Mais o filtro de URL das fotos de streamer.
 * Rodar: php tests/clan-consentimento.php
 */

namespace App {
    class Database {
        public static ?\PDO $pdo = null;
        public static function pdo(): \PDO { return self::$pdo; }
        public static function query(string $sql, array $params = []): \PDOStatement { $st = self::$pdo->prepare($sql); $st->execute($params); return $st; }
        public static function execute(string $sql, array $params = []): int { return self::query($sql, $params)->rowCount(); }
        public static function fetchOne(string $sql, array $params = []): ?array { $r = self::query($sql, $params)->fetch(\PDO::FETCH_ASSOC); return $r === false ? null : $r; }
        public static function fetchAll(string $sql, array $params = []): array { return self::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC); }
        public static function fetchColumn(string $sql, array $params = []) { return self::query($sql, $params)->fetchColumn(); }
    }
    class ClanEvent {
        public static function onMemberJoin(int $c, string $s): void {}
        public static function onMemberLeave(int $c, string $s): void {}
        public static function onClanDisband(int $c): void {}
    }
}

namespace {
    $falhas = 0;
    function ok(string $d): void { echo "  OK   $d\n"; }
    function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
    $ROOT = dirname(__DIR__);
    require_once $ROOT . '/src/Clan.php';
    require_once $ROOT . '/src/Streamer.php';
    use App\Clan, App\Database;

    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    Database::$pdo = $pdo;
    $pdo->exec("CREATE TABLE clans (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, tag TEXT UNIQUE, owner_steam_id TEXT, description TEXT, discord_url TEXT, logo TEXT, member_cap INTEGER DEFAULT 20, status TEXT DEFAULT 'active', created_at TEXT DEFAULT (datetime('now')))");
    $pdo->exec("CREATE TABLE clan_members (id INTEGER PRIMARY KEY AUTOINCREMENT, clan_id INTEGER, steam_id TEXT UNIQUE, role TEXT DEFAULT 'member', joined_at TEXT DEFAULT (datetime('now')))");
    $pdo->exec("CREATE TABLE clan_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, clan_id INTEGER, steam_id TEXT, kind TEXT DEFAULT 'request', created_at TEXT DEFAULT (datetime('now')), UNIQUE (clan_id, steam_id))");
    $pdo->exec("CREATE TABLE clan_activity_log (id INTEGER PRIMARY KEY AUTOINCREMENT, clan_id INTEGER, steam_id TEXT, action TEXT, actor_steam_id TEXT, created_at TEXT DEFAULT (datetime('now')))");

    $DONO = '76561190000000001'; $X = '76561190000000002'; $Y = '76561190000000003'; $Z = '76561190000000004';
    [$cid] = Clan::create($DONO, 'Cla de Teste', 'TST', null, null, null);
    $membro = fn(string $s) => (bool) Database::fetchColumn("SELECT 1 FROM clan_members WHERE clan_id = ? AND steam_id = ?", [$cid, $s]);

    echo "\n1. Jogador so entra por CONVITE que existe\n";
    $e = Clan::accept($cid, $X, 'invite');
    if ($e !== null && !$membro($X)) ok("sem convite: recusado ($e)"); else falha('jogador entrou no cla SEM convite', 'qualquer um entraria em qualquer cla, inclusive no campeao antes do premio');
    Clan::invite($cid, $DONO, $X);
    $e = Clan::accept($cid, $X, 'invite');
    if ($e === null && $membro($X)) ok('com convite: entrou'); else falha('convite valido nao funcionou', (string) $e);

    echo "\n2. Dono so aceita PEDIDO que existe\n";
    $e = Clan::accept($cid, $Y, 'request');
    if ($e !== null && !$membro($Y)) ok("sem pedido: recusado ($e)"); else falha('dono pos jogador no cla SEM pedido dele', 'entrada forcada, sem consentimento');
    Clan::requestJoin($cid, $Y);
    $e = Clan::accept($cid, $Y, 'request');
    if ($e === null && $membro($Y)) ok('com pedido: entrou'); else falha('pedido valido nao funcionou', (string) $e);

    echo "\n3. Convite nao vira pedido, pedido nao vira convite\n";
    Clan::invite($cid, $DONO, $Z);                 // o DONO criou um convite pro Z...
    $e = Clan::accept($cid, $Z, 'request');        // ...e tenta "aceitar o pedido" do Z
    if ($e !== null && !$membro($Z)) ok('dono nao usa o proprio convite como se fosse pedido'); else falha('dono converteu convite em entrada forcada');
    Clan::dropRequest($cid, $Z);
    Clan::requestJoin($cid, $Z);                   // o Z pediu...
    $e = Clan::accept($cid, $Z, 'invite');         // ...e tenta se aceitar sozinho como se fosse convite
    if ($e !== null && !$membro($Z)) ok('jogador nao se aprova com o proprio pedido'); else falha('jogador se aprovou sozinho');
    if (!str_starts_with(Clan::errorMessage('no_invite'), 'Não foi possível') && !str_starts_with(Clan::errorMessage('no_request'), 'Não foi possível')) ok('mensagens proprias pra no_invite e no_request'); else falha('sem mensagem pros codigos novos');

    echo "\n4. Fotos de streamer so aceitam http(s) ou caminho do proprio site\n";
    $fotos = \App\Streamer::photos(['photos_json' => json_encode(['https://i.imgur.com/a.png', '/assets/img/streamers/st_1.png', 'javascript:alert(1)', 'data:text/html,x', '//evil.example/x.png', ' JAVASCRIPT:alert(2)'])]);
    if ($fotos === ['https://i.imgur.com/a.png', '/assets/img/streamers/st_1.png']) ok('fica so https e caminho interno'); else falha('filtro de foto errado', json_encode($fotos));

    echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
    exit($falhas ? 1 : 0);
}
