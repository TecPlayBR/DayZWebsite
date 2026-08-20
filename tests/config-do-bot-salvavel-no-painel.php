<?php
/**
 * Prova que `bot_endpoint` e `bot_token` podem ser SALVOS pelo painel, testados
 * antes de alguem depender deles, e que a falha aparece na tela.
 *
 * POR QUE ESTE TESTE EXISTE
 * -------------------------
 * Os dois estavam FORA do `Settings::SCHEMA`, e `Settings::set()` rejeita
 * qualquer chave fora do whitelist (devolve `false`). O laco que salva o form do
 * admin ignora esse retorno. Resultado: o dono do site digitava, salvava, a tela
 * dizia "Atualizado", e nada era gravado — a unica forma de setar era INSERT
 * direto no banco.
 *
 * O estrago apareceu num cliente assim: a moeda dele mora DENTRO DO JOGO, e o
 * ajuste de moedas no painel precisa avisar o bot pra escrever no servidor. Sem
 * os dois valores o site nao alcanca o bot, o ajuste nao se aplica, e o saldo
 * "volta sozinho" pro valor do jogo toda vez. Sem nenhum erro visivel, porque a
 * tela de jogadores tambem nao mostrava o motivo que o handler ja mandava.
 *
 * Rodar:  php tests/config-do-bot-salvavel-no-painel.php
 */

$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void {
    global $falhas; $falhas++;
    echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n";
}

/** Recorta um trecho a partir de uma agulha. Por POSICAO, nao por regex: regex
 *  com `$` dentro de string PHP e convite a interpolacao acidental — foi o que
 *  quebrou a primeira versao deste proprio teste. */
function trechoApos(string $texto, string $agulha, int $tam): string {
    $i = strpos($texto, $agulha);
    return $i === false ? '' : substr($texto, $i, $tam);
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/Settings.php';
$idx = file_get_contents($ROOT . '/public/index.php');

echo "\n1. As duas chaves estao no whitelist\n";
foreach (['bot_endpoint', 'bot_token'] as $k) {
    if (isset(\App\Settings::SCHEMA[$k])) {
        ok("$k esta no SCHEMA (tipo: " . \App\Settings::SCHEMA[$k] . ")");
    } else {
        falha("$k NAO esta no SCHEMA", 'Settings::set() rejeita e o painel salva no vazio');
    }
}

echo "\n2. Os campos ficam na tela de INTEGRACAO, com a rota que salva\n";

// ONDE importa tanto quanto SE existe. Isto nasceu em Configuracoes e o Bryan
// procurou sem achar: a aba que o cliente abre mostra Sparda, Bot e Agent. Metade
// do link com o bot enterrada em outra pagina e invisivel na pratica.
$tela = file_get_contents($ROOT . '/views/admin/discord_integration.php');
foreach (['name="bot_endpoint"'                  => 'campo do endereco',
          'name="bot_token"'                     => 'campo do token',
          '/admin/discord-integration/bot-link'  => 'action do formulario'] as $ag => $desc) {
    if (strpos($tela, $ag) !== false) {
        ok("a tela de Integracao Discord tem o $desc");
    } else {
        falha("a tela de Integracao Discord NAO tem o $desc");
    }
}
if (strpos(file_get_contents($ROOT . '/views/admin/settings.php'), 'name="bot_token"') !== false) {
    falha('o campo do token ficou DUPLICADO em Configuracoes',
          'dois lugares pra mesma coisa divergem e confundem quem opera');
} else {
    ok('nao ficou duplicado em Configuracoes');
}

// a rota que salva precisa existir e tratar os dois campos com regras DIFERENTES
if (strpos($idx, "Router::post('/admin/discord-integration/bot-link'") !== false) {
    ok('a rota que salva existe');
} else {
    falha('a rota que salva nao existe', 'o formulario postaria pro nada');
}
$rotaSalvar = trechoApos($idx, "/admin/discord-integration/bot-link', function", 1400);
if ($rotaSalvar !== '' && strpos($rotaSalvar, "Settings::set('bot_endpoint'") !== false) {
    ok('endereco: e gravado (campo vazio apaga, da pra desligar o aviso)');
} else {
    falha('a rota nao grava o endereco');
}
if ($rotaSalvar !== '' && strpos($rotaSalvar, "Settings::set('bot_token'") !== false
    && strpos($rotaSalvar, "!== ''") !== false) {
    ok('token: campo vazio MANTEM (a tela nao echoa o token; apagar seria armadilha)');
} else {
    falha('o token nao tem o tratamento write-only',
          'quem mexer so no endereco perderia o token');
}
if ($rotaSalvar !== '' && strpos($rotaSalvar, 'Csrf::check') !== false) {
    ok('a rota confere o CSRF');
} else {
    falha('a rota nao confere o CSRF');
}

echo "\n3. A tela nunca mostra o token salvo\n";
$form = file_get_contents($ROOT . '/views/admin/discord_integration.php');
if (preg_match('/name="bot_token"[^>]*value="([^"]*)"/', $form, $m)) {
    if (trim($m[1]) === '') {
        ok('o campo do token vai sempre vazio (o painel nao e lugar de LER credencial)');
    } else {
        falha('o campo do token echoa um valor: ' . $m[1]);
    }
} else {
    falha('nao achei o campo bot_token no form');
}
if (strpos(trechoApos($form, 'name="bot_token"', 60), 'value=""') !== false || strpos(trechoApos($form, 'bot_token', 400), 'password') !== false) {
    ok('o campo do token e do tipo password');
} else {
    falha('o campo do token nao e do tipo password');
}

echo "\n4. Existe como TESTAR antes de depender disso\n";
if (strpos($idx, '/admin/discord-integration/testar-bot') !== false) {
    ok('a rota de teste existe');
} else {
    falha('nao existe rota de teste', '"salvei" e "o bot recebe" sao coisas diferentes');
}
// cada resultado precisa de nome proprio: "falhou" faz o dono do site adivinhar
foreach (['bot=token' => 'token recusado (401)',
          'bot=rede'  => 'endereco inalcancavel',
          'bot=ok'    => 'sucesso',
          'bot=falta' => 'faltou preencher'] as $q => $desc) {
    if (strpos($idx, $q) !== false) {
        ok("o teste distingue: $desc");
    } else {
        falha("o teste nao distingue: $desc");
    }
}
// testar tem que ser SO LEITURA
$rota = trechoApos($idx, "Router::get('/admin/discord-integration/testar-bot'", 1800);
if ($rota === '') {
    falha('nao consegui recortar a rota de teste pra conferir');
} else {
    $escreve = false;
    foreach (['Settings::set', 'UPDATE ', 'INSERT ', 'DELETE '] as $e) {
        if (strpos($rota, $e) !== false) {
            falha("a rota de teste faz escrita ($e)", 'testar tem que ser inofensivo');
            $escreve = true;
        }
    }
    if (!$escreve) ok('a rota de teste nao escreve nada');
    if (strpos($rota, '/health') !== false) {
        ok('o teste pergunta no /health do bot');
    } else {
        falha('o teste nao usa o /health');
    }
}

echo "\n5. A tela de jogadores mostra o motivo da falha\n";
$players = file_get_contents($ROOT . '/views/admin/players.php');
if (strpos($players, "_GET['err']") === false) {
    falha('players.php nao le o err', 'o ajuste falha e o valor "volta sozinho" sem explicacao');
} else {
    ok('players.php le o err da URL');
}
if (strpos($players, 'bot_nao_configurado') === false) {
    falha('players.php nao traduz bot_nao_configurado',
          'e justamente o motivo que aparece quando faltam endpoint/token');
} else {
    ok('players.php traduz bot_nao_configurado em texto util');
}
// e o banner de erro tem que ganhar do de sucesso: mostrar "Atualizado" junto com
// a falha seria pior que nao mostrar nada
$posErr = strpos($players, 'errMsg !== null');
$posOk  = strpos($players, "_GET['ok']");
if ($posErr !== false && $posOk !== false && $posErr < $posOk) {
    ok('o banner de erro vem ANTES do de sucesso (nao mostra os dois)');
} else {
    falha('o banner de sucesso pode aparecer junto com a falha');
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) {
    echo "TUDO OK - da pra configurar o bot no painel, testar, e ver o erro.\n";
    exit(0);
}
echo "$falhas FALHA(S).\n";
exit(1);
