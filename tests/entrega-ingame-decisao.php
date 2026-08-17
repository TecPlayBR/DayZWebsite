<?php
/**
 * Prova a TABELA DE DECISAO que evita moeda em dobro e evita moeda perdida.
 *
 * Contexto: quando o pagamento e aprovado, o site avisa o bot ANTES de creditar o saldo
 * dele. Se o bot responder que ficou com a moeda (entrega dentro do jogo), o site NAO soma
 * no saldo, senao o jogador recebe nos dois lugares e gasta o mesmo dinheiro duas vezes.
 *
 * POR QUE ESTE TESTE EXISTE
 * -------------------------
 * A condicao correta e uma LISTA de respostas, nao um teste de "veio algo". Trocar por
 * `if ($marca)` parece uma simplificacao inofensiva e nao e:
 *
 *   - `adiada`   = o bot NAO conseguiu pegar a compra agora  -> o site TEM que creditar
 *   - `invalida` = o bot recusou os dados                    -> o site TEM que creditar
 *
 * Nos dois casos um teste de verdade/falsidade faria o site pular o credito, e a moeda que
 * o jogador PAGOU sumiria dos dois lados. Erro pior que o dobro, porque o cliente reclama
 * com razao e nao ha como saber quantos foram afetados.
 *
 * Rodar:  php tests/entrega-ingame-decisao.php
 */

// A MESMA expressao do `public/api/mp-webhook.php`. Se mudar la, muda aqui e o teste diz
// se a mudanca quebrou alguma linha da tabela.
function botFicouComAMoeda(?string $respostaJson, int $httpStatus): bool
{
    if ($respostaJson === null || $httpStatus !== 200) {
        return false;                       // bot fora do ar / erro -> site credita
    }
    $dados = json_decode($respostaJson, true);
    $marca = $dados['entrega_ingame'] ?? null;
    return in_array($marca, ['enfileirada', 'ja_entregue'], true);
}

$casos = [
    // [resposta do bot, http, bot ficou com a moeda?, o que e]
    ['{"ok":true,"entrega_ingame":"enfileirada"}', 200, true,
        'bot assumiu agora -> site NAO credita'],
    ['{"ok":true,"entrega_ingame":"ja_entregue"}', 200, true,
        'retry do MP, bot ja tinha assumido -> site NAO credita'],
    ['{"ok":true,"entrega_ingame":null}',          200, false,
        'entrega in-game desligada -> site credita (comportamento de sempre)'],
    ['{"ok":true}',                                200, false,
        'resposta sem o campo -> site credita'],
    ['{"ok":true,"entrega_ingame":"adiada"}',      200, false,
        'bot NAO conseguiu pegar -> site credita (senao a moeda paga desaparece)'],
    ['{"ok":true,"entrega_ingame":"invalida"}',    200, false,
        'bot recusou os dados -> site credita'],
    ['{"ok":false,"error":"unauthorized"}',        401, false,
        'token errado -> site credita'],
    ['nao é json',                                 200, false,
        'resposta corrompida -> site credita'],
    [null,                                         0,   false,
        'bot fora do ar / timeout -> site credita'],
];

$falhas = 0;
foreach ($casos as [$resp, $http, $esperado, $descricao]) {
    $obtido = botFicouComAMoeda($resp, $http);
    $ok = $obtido === $esperado;
    printf("  %-6s %s\n", $ok ? 'ok' : 'FALHA', $descricao);
    if (!$ok) {
        $falhas++;
        printf("         esperava %s e obtive %s (http %d, resposta %s)\n",
            var_export($esperado, true), var_export($obtido, true), $http,
            var_export($resp, true));
    }
}

// Contraprova: o atalho tentador tem que REPROVAR nesta tabela.
$atalhoErrado = function (?string $r, int $h): bool {
    if ($r === null || $h !== 200) { return false; }
    $d = json_decode($r, true);
    return (bool)($d['entrega_ingame'] ?? null);   // <- o "if ($marca)" ingenuo
};
$atalhoQuebra = false;
foreach ($casos as [$resp, $http, $esperado, $_d]) {
    if ($atalhoErrado($resp, $http) !== $esperado) { $atalhoQuebra = true; break; }
}
printf("\n  %-6s o atalho `if (\$marca)` reprova nesta tabela (era pra reprovar)\n",
    $atalhoQuebra ? 'ok' : 'FALHA');
if (!$atalhoQuebra) { $falhas++; }

echo "\n";
if ($falhas) {
    echo "FALHOU: {$falhas}\n";
    exit(1);
}
echo "TODOS OS CASOS PASSARAM\n";
