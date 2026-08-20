<?php
/**
 * Prova que o NOME DO SITE nunca sai vazio, nas duas camadas.
 *
 * POR QUE ESTE TESTE EXISTE
 * -------------------------
 * Encontrado num cliente em 2026-08-19: a pagina /ranking imprimia
 * "Hall of Fame do  - ranking ao vivo..." — sem o nome do servidor no meio.
 *
 * A causa nao estava na pagina. O template inteiro le o nome assim:
 *
 *     $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'
 *
 * e `??` **so cai no fallback quando o valor e NULL ou ausente, nunca quando e
 * string VAZIA**. Um admin que limpou o campo "Nome do site" e salvou gravou
 * `''` no banco, e a partir dai 46 lugares passaram a imprimir vazio em vez do
 * fallback: `<title>`, `og:site_name`, nome do remetente do e-mail, e o
 * `statement_descriptor` da cobranca — que e o texto que aparece na FATURA DO
 * CARTAO do jogador.
 *
 * Consertar os 46 leitores seria caçar sintoma. As duas camadas sao:
 *   1. `Settings::NUNCA_VAZIO` — `set()` recusa gravar vazio e mantem o anterior.
 *   2. `site_name()` — leitura que trata vazio como ausente, pra instalacao que
 *      JA gravou vazio antes do conserto continuar renderizando algo.
 *
 * Rodar:  php tests/nome-do-site-nunca-vazio.php
 */

$falhas = 0;
function ok(string $desc): void { echo "  OK   $desc\n"; }
function falha(string $desc, string $detalhe = ''): void {
    global $falhas; $falhas++;
    echo "  FALHA $desc" . ($detalhe !== '' ? "\n         $detalhe" : '') . "\n";
}

// ---------------------------------------------------------------- camada 1
echo "\n1. Settings::set recusa apagar o nome do site\n";

$ROOT = dirname(__DIR__);
$fonte = file_get_contents($ROOT . '/src/Settings.php');

if (strpos($fonte, 'NUNCA_VAZIO') === false) {
    falha('a lista NUNCA_VAZIO existe', 'sem ela, o painel volta a poder gravar nome vazio');
} else {
    ok('a lista NUNCA_VAZIO existe');
}

if (!preg_match("/in_array\(\\\$key, self::NUNCA_VAZIO, true\)\s*&&\s*\\\$value === ''\)\s*return false;/", $fonte)) {
    falha('set() recusa valor vazio pra chave de NUNCA_VAZIO',
          'a guarda tem que rodar DEPOIS do normalize (o trim e la) e ANTES do INSERT');
} else {
    ok('set() recusa valor vazio pra chave de NUNCA_VAZIO');
}

// a guarda precisa vir depois do normalize: '   ' (so espaco) tambem e vazio
$posNorm  = strpos($fonte, '$value = self::normalize(');
$posGuard = strpos($fonte, 'NUNCA_VAZIO, true)');
$posInsert = strpos($fonte, 'INSERT INTO settings');
if ($posNorm === false || $posGuard === false || $posInsert === false) {
    falha('ordem das etapas em set()', 'nao achei normalize/guarda/INSERT');
} elseif ($posNorm < $posGuard && $posGuard < $posInsert) {
    ok('ordem certa: normalize -> guarda -> INSERT (espaco em branco tambem e recusado)');
} else {
    falha('ordem das etapas em set()', 'a guarda tem que ficar entre o normalize e o INSERT');
}

// ---------------------------------------------------------------- camada 2
echo "\n2. site_name() trata string vazia como ausente\n";

require_once $ROOT . '/src/helpers.php';

if (!function_exists('site_name')) {
    falha('o helper site_name() existe');
} else {
    ok('o helper site_name() existe');

    $casos = [
        'settings preenchido'          => [['settings' => ['site_name' => 'Renascer Z'], 'site_name' => 'X'], 'Renascer Z'],
        'settings VAZIO cai pro config'=> [['settings' => ['site_name' => ''], 'site_name' => 'Do Config'], 'Do Config'],
        'settings so com espaco'       => [['settings' => ['site_name' => '   '], 'site_name' => 'Do Config'], 'Do Config'],
        'os dois vazios usam fallback' => [['settings' => ['site_name' => ''], 'site_name' => ''], 'FALLBACK'],
        'os dois ausentes'             => [[], 'FALLBACK'],
        'settings null'                => [['settings' => ['site_name' => null], 'site_name' => 'Do Config'], 'Do Config'],
        'espaco em volta e aparado'    => [['settings' => ['site_name' => '  Nome  ']], 'Nome'],
    ];
    foreach ($casos as $desc => [$cfg, $esperado]) {
        $GLOBALS['config'] = $cfg;
        $got = site_name('FALLBACK');
        if ($got === $esperado) {
            ok("$desc -> " . var_export($got, true));
        } else {
            falha($desc, 'esperava ' . var_export($esperado, true) . ', veio ' . var_export($got, true));
        }
    }

    // aceitar o array explicitamente: dentro de uma view o $config e LOCAL, e
    // depender do global la criaria divergencia silenciosa.
    $GLOBALS['config'] = ['settings' => ['site_name' => 'DO GLOBAL']];
    if (site_name('FB', ['settings' => ['site_name' => 'DO PARAMETRO']]) !== 'DO PARAMETRO') {
        falha('site_name() ignora o array passado e usa o global');
    } else {
        ok('array passado por parametro ganha do global');
    }
    if (site_name('FB', ['settings' => ['site_name' => '']]) !== 'FB') {
        falha('array passado com nome vazio nao caiu no fallback');
    } else {
        ok('array passado com nome vazio cai no fallback (nao volta pro global)');
    }
    unset($GLOBALS['config']);
    if (trim(site_name('FB')) === '') {
        falha('site_name() sem $config global nenhum devolveu vazio');
    } else {
        ok('sem $config global nenhum, ainda devolve o fallback');
    }

    // o retorno NUNCA pode ser vazio, seja qual for a entrada
    foreach ([[], ['settings' => ['site_name' => '']], ['site_name' => '  ']] as $i => $cfg) {
        $GLOBALS['config'] = $cfg;
        if (trim(site_name()) === '') {
            falha("site_name() devolveu vazio no caso $i", 'e o unico resultado que nunca pode acontecer');
        }
    }
    ok('site_name() nunca devolve vazio');
}

// ------------------------------------------------ o caminho de dinheiro
echo "\n3. O descritor da fatura do cartao nunca sai vazio\n";

// Os dois arquivos de API NAO carregam helpers.php, entao eles NAO podem chamar
// site_name() — seria erro fatal num caminho de dinheiro. A checagem la e inline,
// e este teste existe pra ninguem "limpar" isso depois trocando pelo helper.
$semHelper = ['public/api/bot-integration.php', 'public/api/mp-webhook.php'];
foreach ($semHelper as $rel) {
    $src = file_get_contents($ROOT . '/' . $rel);
    $carregaHelpers = strpos($src, "helpers.php") !== false;
    $chamaHelper    = preg_match('/\bsite_name\s*\(/', $src) === 1;
    if ($chamaHelper && !$carregaHelpers) {
        falha("$rel chama site_name() sem carregar helpers.php",
              'isso e erro fatal em runtime, num caminho de dinheiro');
    } else {
        ok("$rel nao chama helper que nao carrega");
    }
}

// e o descritor tem que ter alguma defesa contra vazio nos tres lugares
$descritores = [
    'public/index.php'               => "statement_descriptor",
    'public/api/bot-integration.php' => "statement_descriptor",
];
foreach ($descritores as $rel => $agulha) {
    $src = file_get_contents($ROOT . '/' . $rel);
    if (!preg_match('/' . $agulha . '.{0,400}/s', $src, $m)) {
        falha("$rel tem statement_descriptor");
        continue;
    }
    $trecho = $m[0];
    $defendido = strpos($trecho, 'site_name(') !== false || strpos($trecho, "!== ''") !== false;
    if ($defendido) {
        ok("$rel: descritor protegido contra nome vazio");
    } else {
        falha("$rel: descritor usa so `??`",
              "`??` nao pega string vazia; a fatura do jogador sairia em branco");
    }
}

// ---------------------------------------------------------------- resultado
echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) {
    echo "TUDO OK - o nome do site nao tem como sair vazio.\n";
    exit(0);
}
echo "$falhas FALHA(S).\n";
exit(1);
