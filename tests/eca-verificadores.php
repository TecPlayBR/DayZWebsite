<?php
/**
 * Os tres drivers de verificacao, com HTTP falso. Nenhum teste toca fornecedor real.
 * O que importa: adulto/menor/falhou saem certos, o CPF nao vaza no 'ref', e falha de
 * rede nunca vira 'menor' nem 'adulto'.
 * Rodar: php tests/eca-verificadores.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/AgeGate.php';
require_once $ROOT . '/src/AgeVerifier.php';
use App\AgeVerifierFactory;

$CPF = '52998224725';
$chamadas = [];
function httpFalso(int $status, string $body, array &$log): callable {
    return function (string $m, string $url, array $h, ?string $b) use ($status, $body, &$log): array {
        $log[] = ['m' => $m, 'url' => $url, 'h' => $h, 'b' => $b];
        return ['status' => $status, 'body' => $body];
    };
}

echo "\n1. FlagCheck\n";
$v = AgeVerifierFactory::make('flagcheck', 'chave-x', '', httpFalso(200, json_encode(['maior_de_18' => true, 'status_cpf' => 'regular', 'audit_token' => 'aud_123']), $chamadas));
$r = $v->verify($CPF, '2000-01-01');
if ($r['result'] === 'adulto' && $r['ref'] === 'aud_123' && $r['status'] === 'regular') ok('adulto com audit_token'); else falha('flagcheck adulto', json_encode($r));
if (($chamadas[0]['m'] ?? '') === 'POST' && str_contains($chamadas[0]['url'], 'flagcheck.com.br')) ok('POST no endpoint do FlagCheck'); else falha('flagcheck: metodo/url errados');
if (str_contains(implode(' ', $chamadas[0]['h']), 'Bearer chave-x')) ok('manda a chave como Bearer'); else falha('flagcheck sem Bearer');
$r = AgeVerifierFactory::make('flagcheck', 'k', '', httpFalso(200, json_encode(['maior_de_18' => false, 'status_cpf' => 'regular', 'audit_token' => 'aud_9']), $chamadas))->verify($CPF, null);
if ($r['result'] === 'menor') ok('menor'); else falha('flagcheck menor', json_encode($r));

echo "\n2. Serpro v3\n";
$log = [];
$tok = json_encode(['access_token' => 'tk', 'expires_in' => 3600]);
$cons = json_encode(['ni' => $CPF, 'nome' => 'FULANO', 'situacao' => ['codigo' => '0', 'descricao' => 'Regular'], 'nascimento' => '01012000']);
$seq = [ ['status' => 200, 'body' => $tok], ['status' => 200, 'body' => $cons] ];
$http = function (string $m, string $url, array $h, ?string $b) use (&$seq, &$log): array { $log[] = [$m, $url, $h, $b]; return array_shift($seq); };
$r = AgeVerifierFactory::make('serpro', 'consumer', 'secret', $http)->verify($CPF, '2000-01-01');
if ($r['result'] === 'adulto' && $r['status'] === 'Regular') ok('adulto pela data de nascimento devolvida'); else falha('serpro adulto', json_encode($r));
if (count($log) === 2 && $log[0][0] === 'POST' && str_contains($log[0][1], '/token')) ok('pega token OAuth2 antes'); else falha('serpro sem token');
if (str_contains($log[1][1], '/consulta-cpf-df/v3/')) ok('consulta na v3'); else falha('serpro nao usa v3', $log[1][1] ?? '');
if (!str_contains((string) $r['ref'], $CPF)) ok('ref nao contem o CPF'); else falha('ref vazou o CPF');
$seq = [ ['status' => 200, 'body' => $tok], ['status' => 200, 'body' => json_encode(['ni' => $CPF, 'nome' => 'X', 'situacao' => ['codigo' => '0', 'descricao' => 'Regular'], 'nascimento' => '01012012'])] ];
$r = AgeVerifierFactory::make('serpro', 'c', 's', $http)->verify($CPF, '2012-01-01');
if ($r['result'] === 'menor') ok('menor pela data devolvida'); else falha('serpro menor', json_encode($r));

echo "\n3. CPFHub\n";
$log = [];
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(200, json_encode(['name' => 'X', 'gender' => 'M', 'birthDate' => '01/01/2000']), $log))->verify($CPF, null);
if ($r['result'] === 'adulto') ok('adulto por birthDate'); else falha('cpfhub adulto', json_encode($r));
if (str_contains($log[0]['url'], 'cpfhub.io')) ok('endpoint do CPFHub'); else falha('cpfhub url');

echo "\n4. Falhas nunca viram adulto nem menor\n";
foreach ([[500, '{}'], [401, '{"error":"unauthorized"}'], [200, 'nao-e-json'], [200, '{}'], [0, '']] as [$st, $body]) {
    foreach (['flagcheck', 'cpfhub'] as $p) {
        $r = AgeVerifierFactory::make($p, 'k', '', httpFalso($st, $body, $log))->verify($CPF, null);
        if ($r['result'] === 'falhou' && !empty($r['error'])) ok("$p: status $st / body " . substr($body, 0, 12) . " = falhou com erro"); else falha("$p: status $st nao virou falhou", json_encode($r));
    }
}
$seq = [ ['status' => 401, 'body' => '{}'] ];
$r = AgeVerifierFactory::make('serpro', 'c', 's', $http)->verify($CPF, '2000-01-01');
if ($r['result'] === 'falhou') ok('serpro: token negado = falhou'); else falha('serpro token negado nao falhou');

echo "\n5. Fabrica e test()\n";
try { AgeVerifierFactory::make('inventado', 'k', '', null); falha('fabrica aceitou provider inexistente'); }
catch (\InvalidArgumentException $e) { ok('fabrica recusa provider inexistente'); }
$r = AgeVerifierFactory::make('flagcheck', '', '', httpFalso(200, '{}', $log))->test();
if ($r['ok'] === false) ok('test() sem chave diz que nao esta pronto'); else falha('test() sem chave disse ok');

echo "\n6. test() nunca consulta um CPF de pessoa (revisao: LGPD + consulta paga)\n";
foreach (['flagcheck', 'cpfhub'] as $p) {
    $log = [];
    $r = AgeVerifierFactory::make($p, 'k', '', httpFalso(422, '{"error":"cpf invalido"}', $log))->test();
    $enviado = (string) (($log[0]['b'] ?? '') . ' ' . ($log[0]['url'] ?? ''));
    if (!str_contains($enviado, '52998224725') && str_contains($enviado, '00000000000')) ok("$p: test() manda so o CPF nulo 000.000.000-00"); else falha("$p: test() mandou um CPF valido ao fornecedor", $enviado);
    if ($r['ok'] === true) ok("$p: 422 (CPF invalido) = chave aceita, sem gastar consulta"); else falha("$p: 422 deveria significar chave ok", json_encode($r));
    $r = AgeVerifierFactory::make($p, 'k', '', httpFalso(401, '{}', $log))->test();
    if ($r['ok'] === false) ok("$p: 401 = chave recusada"); else falha("$p: 401 deveria ser chave recusada");
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
