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

echo "\n1. FlagCheck (contrato real: POST /api/felca/age-check, X-API-Key, data.is_adult, meta.request_id)\n";
$adulto = json_encode(['success' => true, 'data' => ['is_adult' => true, 'age' => 32, 'date_of_birth' => '1993-05-14', 'document' => ['type' => 'CPF', 'valid' => true]],
                       'meta' => ['request_id' => 'felca_a1b2c3', 'timestamp' => '2026-03-13T14:32:00Z']]);
$v = AgeVerifierFactory::make('flagcheck', 'chave-x', '', httpFalso(200, $adulto, $chamadas));
$r = $v->verify($CPF, '2000-01-01');
if ($r['result'] === 'adulto' && $r['ref'] === 'felca_a1b2c3' && $r['status'] === 'valid') ok('adulto com request_id como ref e situacao do documento'); else falha('flagcheck adulto', json_encode($r));
if (($chamadas[0]['m'] ?? '') === 'POST' && str_contains($chamadas[0]['url'], 'api.flagcheck.com.br/api/felca/age-check')) ok('POST no endpoint /api/felca/age-check'); else falha('flagcheck: metodo/url errados', $chamadas[0]['url'] ?? '');
if (str_contains(implode(' ', $chamadas[0]['h']), 'X-API-Key: chave-x')) ok('manda a chave em X-API-Key'); else falha('flagcheck sem X-API-Key');
if (json_decode((string) $chamadas[0]['b'], true) === ['cpf' => $CPF]) ok('corpo e {"cpf": ...}'); else falha('corpo errado', (string) $chamadas[0]['b']);
// Menor: is_adult false e sem age/date_of_birth (LGPD do proprio fornecedor)
$menor = json_encode(['success' => true, 'data' => ['is_adult' => false, 'document' => ['type' => 'CPF', 'valid' => true]], 'meta' => ['request_id' => 'felca_m9']]);
$r = AgeVerifierFactory::make('flagcheck', 'k', '', httpFalso(200, $menor, $chamadas))->verify($CPF, null);
if ($r['result'] === 'menor' && $r['ref'] === 'felca_m9') ok('menor'); else falha('flagcheck menor', json_encode($r));
// A pagina /api-parceiros mostra a resposta SEM o envelope: {"is_adult": true, "age": 32}. Aceitar os dois.
$r = AgeVerifierFactory::make('flagcheck', 'k', '', httpFalso(200, json_encode(['is_adult' => true, 'age' => 32]), $chamadas))->verify($CPF, null);
if ($r['result'] === 'adulto') ok('aceita resposta sem envelope (is_adult no topo)'); else falha('resposta sem envelope nao virou adulto', json_encode($r));
$r = AgeVerifierFactory::make('flagcheck', 'k', '', httpFalso(200, json_encode(['is_adult' => false]), $chamadas))->verify($CPF, null);
if ($r['result'] === 'menor') ok('menor sem envelope'); else falha('menor sem envelope', json_encode($r));
// Codigos documentados: 402 sem credito, 404 CPF nao encontrado, 422 CPF invalido. Nenhum e cobrado; nenhum vira adulto/menor.
foreach ([[402, 'cr'], [404, 'encontrado'], [422, 'inv']] as [$st, $trecho]) {
    $r = AgeVerifierFactory::make('flagcheck', 'k', '', httpFalso($st, '{}', $chamadas))->verify($CPF, null);
    if ($r['result'] === 'falhou' && stripos((string) $r['error'], $trecho) !== false) ok("HTTP $st = falhou com motivo proprio"); else falha("HTTP $st sem motivo proprio", json_encode($r));
}
// success:false (CPF inexistente/irregular) = falhou, nunca adulto
$r = AgeVerifierFactory::make('flagcheck', 'k', '', httpFalso(200, json_encode(['success' => false, 'error' => 'CPF invalido']), $chamadas))->verify($CPF, null);
if ($r['result'] === 'falhou' && str_contains((string) $r['error'], 'CPF invalido')) ok('success:false = falhou com o motivo'); else falha('success:false nao virou falhou', json_encode($r));

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

echo "\n3. CPFHub (contrato real: GET api.cpfhub.io/cpf/{cpf}, x-api-key, {success, data:{birthDate DD/MM/AAAA}})\n";
$log = [];
$envelope = json_encode(['success' => true, 'data' => ['cpf' => $CPF, 'name' => 'X', 'birthDate' => '01/01/2000', 'gender' => 'M']]);
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(200, $envelope, $log))->verify($CPF, null);
if ($r['result'] === 'adulto') ok('adulto por data.birthDate'); else falha('cpfhub adulto (envelope data)', json_encode($r));
if (($log[0]['m'] ?? '') === 'GET' && str_contains($log[0]['url'], 'api.cpfhub.io/cpf/' . $CPF)) ok('GET api.cpfhub.io/cpf/{cpf}'); else falha('cpfhub url', $log[0]['url'] ?? '');
if (str_contains(implode(' ', $log[0]['h']), 'x-api-key: k')) ok('chave em x-api-key'); else falha('cpfhub sem x-api-key');
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(200, json_encode(['success' => true, 'data' => ['birthDate' => '01/01/2012']]), $log))->verify($CPF, null);
if ($r['result'] === 'menor') ok('menor por data.birthDate'); else falha('cpfhub menor', json_encode($r));
// Docs oficiais (25/09): erro e OBJETO {"error":{"message":...}} nos 4xx, e STRING no 401.
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(404, json_encode(['success' => false, 'data' => null, 'error' => ['message' => 'CPF não encontrado']]), $log))->verify($CPF, null);
if ($r['result'] === 'falhou' && str_contains((string) $r['error'], 'encontrado') && !str_contains((string) $r['error'], 'Array')) ok('erro-objeto vira motivo legivel'); else falha('erro-objeto do CPFHub', json_encode($r));
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(401, json_encode(['success' => false, 'error' => 'API Key inválida']), $log))->verify($CPF, null);
if ($r['result'] === 'falhou' && str_contains((string) $r['error'], 'recusada')) ok('401 com erro-string = chave recusada'); else falha('401 do CPFHub', json_encode($r));
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(400, json_encode(['success' => false, 'data' => null, 'error' => ['message' => 'CPF inválido']]), $log))->verify($CPF, null);
if ($r['result'] === 'falhou' && stripos((string) $r['error'], 'CPF inv') !== false && !str_contains((string) $r['error'], 'Array')) ok('400 = CPF invalido (motivo do corpo, sem "Array")'); else falha('400 do CPFHub', json_encode($r));
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(200, json_encode(['success' => false, 'data' => null, 'error' => ['message' => 'Limite excedido']]), $log))->verify($CPF, null);
if ($r['result'] === 'falhou' && str_contains((string) $r['error'], 'Limite excedido')) ok('erro-objeto com HTTP 200 vira motivo legivel'); else falha('erro-objeto em 200', json_encode($r));
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(200, json_encode(['success' => false, 'message' => 'CPF nao encontrado']), $log))->verify($CPF, null);
if ($r['result'] === 'falhou') ok('success:false = falhou'); else falha('cpfhub success:false virou ' . $r['result']);
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(200, json_encode(['name' => 'X', 'birthDate' => '01/01/2000']), $log))->verify($CPF, null);
if ($r['result'] === 'adulto') ok('aceita tambem sem envelope (compatibilidade)'); else falha('cpfhub sem envelope', json_encode($r));

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
