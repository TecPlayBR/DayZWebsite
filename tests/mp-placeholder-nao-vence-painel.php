<?php
/**
 * Prova que um PLACEHOLDER de Mercado Pago esquecido no `config.php` nao consegue
 * (a) passar por token de verdade nem (b) sombrear o token digitado no painel.
 *
 * POR QUE ESTE TESTE EXISTE
 * -------------------------
 * O template tinha DOIS textos de exemplo pro access token e so um deles era
 * reconhecido:
 *
 *   - `config/config.example.php` distribui `ALTERE_AQUI_ACCESS_TOKEN_MP`
 *   - `MercadoPago::isConfigured()` so recusava `<MP_ACCESS_TOKEN>`
 *
 * Com isso, uma instalacao que copiou o exemplo e nao editou a chave ficava em dois
 * estados ruins ao mesmo tempo:
 *
 *   1. `isConfigured()` respondia SIM pro placeholder, entao em vez da tela clara
 *      de "MP nao configurado" o jogador levava um erro da API do Mercado Pago no
 *      meio do checkout.
 *   2. o merge do painel (`public/index.php`) usa precedencia "config.php vence",
 *      e placeholder e nao-vazio. Ou seja: o dono do site digitava o token no
 *      painel, o painel VALIDAVA contra a API do MP, auditava, avisava no Discord
 *      — e o checkout continuava usando o texto de exemplo. Configuracao que
 *      confirma e nao vale e pior que configuracao que recusa.
 *
 * O conserto foi ter UM criterio, `MercadoPago::ehPlaceholder()`, usado pelos dois
 * lugares que decidem. Este teste amarra os dois no mesmo critério.
 *
 * Rodar:  php tests/mp-placeholder-nao-vence-painel.php
 */

$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void {
    global $falhas; $falhas++;
    echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n";
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/MercadoPago.php';

use App\MercadoPago;

// ------------------------------------------------------- o criterio
echo "\n1. ehPlaceholder() reconhece o que o template distribui\n";

$placeholders = [
    ''                             => 'vazio',
    '   '                          => 'so espaco',
    '<MP_ACCESS_TOKEN>'            => 'o sentinela antigo',
    'ALTERE_AQUI_ACCESS_TOKEN_MP'  => 'o que o config.example.php envia HOJE',
    'altere_aqui_access_token_mp'  => 'idem, minusculo',
    'ALTERE_AQUI'                  => 'prefixo generico',
    'ALTERE_AQUI_QUALQUER_COISA'   => 'qualquer coisa com o prefixo',
    'SEU_ACCESS_TOKEN'             => 'variante comum',
];
foreach ($placeholders as $v => $desc) {
    if (MercadoPago::ehPlaceholder($v)) {
        ok("recusa $desc");
    } else {
        falha("NAO recusa $desc", 'isso passaria por token de verdade');
    }
}

echo "\n2. e nao confunde token de verdade com placeholder\n";

// formato dos tokens reais do MP (valores fabricados, nao sao de ninguem)
$reais = [
    'APP_USR-0000000000000000-010101-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-000000000',
    'TEST-0000000000000000-010101-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb-000000000',
];
foreach ($reais as $t) {
    if (MercadoPago::ehPlaceholder($t)) {
        falha('achou que um token real e placeholder', substr($t, 0, 20) . '...');
    } else {
        ok('aceita token no formato ' . strtok($t, '-'));
    }
}

// ------------------------------------------------------- isConfigured
echo "\n3. isConfigured() usa o mesmo criterio\n";

foreach (array_keys($placeholders) as $ph) {
    $mp = new MercadoPago((string) $ph);
    if ($mp->isConfigured()) {
        falha('isConfigured() disse SIM pra ' . var_export($ph, true),
              'o jogador levaria erro da API do MP em vez da tela de "nao configurado"');
    }
}
ok('isConfigured() diz NAO pra todo placeholder');

$mp = new MercadoPago($reais[0]);
if (!$mp->isConfigured()) {
    falha('isConfigured() disse NAO pra um token real');
} else {
    ok('isConfigured() diz SIM pro token real');
}

// ------------------------------------------------------- o merge do painel
echo "\n4. O merge do painel deixa o painel assumir de um placeholder\n";

// Reproduz a MESMA logica de public/index.php. Se mudar la, muda aqui e o teste
// diz se a mudanca reabriu o buraco.
function mergeMP(array $mpCfg, array $settings): array {
    foreach (['access_token' => 'mp_access_token',
              'public_key'   => 'mp_public_key',
              'webhook_secret' => 'mp_webhook_secret'] as $campo => $chave) {
        $atual = (string) ($mpCfg[$campo] ?? '');
        $vazio = $campo === 'access_token'
                 ? MercadoPago::ehPlaceholder($atual)
                 : trim($atual) === '' || stripos(trim($atual), 'ALTERE_AQUI') === 0;
        if ($vazio) {
            $salvo = trim((string) ($settings[$chave] ?? ''));
            if ($salvo !== '' && stripos($salvo, 'ALTERE_AQUI') !== 0) {
                $mpCfg[$campo] = $salvo;
            }
        }
    }
    return $mpCfg;
}

$tokenDoPainel = $reais[0];

// o caso que estava quebrado
$r = mergeMP(['access_token' => 'ALTERE_AQUI_ACCESS_TOKEN_MP'], ['mp_access_token' => $tokenDoPainel]);
if (($r['access_token'] ?? '') === $tokenDoPainel) {
    ok('placeholder no config.php nao sombreia o token do painel');
} else {
    falha('placeholder ainda sombreia o painel',
          'o dono salva no painel, o painel valida, e o checkout usa o texto de exemplo');
}

// e o caso que NAO pode mudar: config.php com token real continua vencendo
$r = mergeMP(['access_token' => $reais[1]], ['mp_access_token' => $tokenDoPainel]);
if (($r['access_token'] ?? '') === $reais[1]) {
    ok('config.php com token REAL continua vencendo o painel (precedencia preservada)');
} else {
    falha('a precedencia do config.php foi quebrada',
          'toda instalacao que fixou no arquivo mudaria de conta de recebimento sozinha');
}

// vazio no config.php tambem cede pro painel
$r = mergeMP([], ['mp_access_token' => $tokenDoPainel]);
if (($r['access_token'] ?? '') === $tokenDoPainel) {
    ok('config.php sem a chave cede pro painel');
} else {
    falha('config.php ausente nao cedeu pro painel');
}

// e placeholder no PAINEL nao vira token
$r = mergeMP([], ['mp_access_token' => 'ALTERE_AQUI_ACCESS_TOKEN_MP']);
if (MercadoPago::ehPlaceholder($r['access_token'] ?? '')) {
    ok('placeholder digitado no painel tambem nao vale');
} else {
    falha('placeholder do painel virou token');
}

// public_key e webhook_secret seguem o mesmo caminho
$r = mergeMP(['public_key' => 'ALTERE_AQUI_PUBLIC_KEY'], ['mp_public_key' => 'APP_USR-abc']);
if (($r['public_key'] ?? '') === 'APP_USR-abc') {
    ok('public_key: placeholder cede pro painel (cartao transparente depende dela)');
} else {
    falha('public_key ficou presa no placeholder',
          'sem ela o site cai pra so-Pix sem dizer por que');
}

// ------------------------------------------------------- o exemplo distribuido
echo "\n5. O placeholder que o template distribui e reconhecido\n";

$ex = file_get_contents($ROOT . '/config/config.example.php');
if (preg_match("/'access_token'\s*=>\s*'([^']*)'/", $ex, $m)) {
    if (MercadoPago::ehPlaceholder($m[1])) {
        ok('o valor do config.example.php (' . $m[1] . ') e reconhecido como placeholder');
    } else {
        falha('o config.example.php envia um valor que ehPlaceholder() NAO reconhece: ' . $m[1],
              'e exatamente assim que o bug nasceu: os dois lados usavam textos diferentes');
    }
} else {
    falha('nao achei o access_token no config.example.php');
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) {
    echo "TUDO OK - placeholder nao passa por token e nao sombreia o painel.\n";
    exit(0);
}
echo "$falhas FALHA(S).\n";
exit(1);
