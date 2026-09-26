<?php
/**
 * Paginas legais do modelo (revisao juridica de 26/09/2026): instalacao nova (schema.sql) e seed
 * (migration) trazem o MESMO texto, e ele e o revisado. Antes o schema.sql ficou com o texto antigo.
 * Rodar: php tests/paginas-legais-modelo.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);

/** Le os literais do VALUES ('slug', ...) respeitando '' dentro das strings. */
function tupla(string $sql, string $slug): array {
    $i = strpos($sql, "VALUES ('$slug'");
    if ($i === false) return [];
    $i += strlen('VALUES ('); $vals = []; $atual = ''; $dentro = false;
    for ($n = strlen($sql); $i < $n; $i++) {
        $c = $sql[$i];
        if ($dentro) {
            if ($c === "'" && ($sql[$i + 1] ?? '') === "'") { $atual .= "'"; $i++; continue; }
            if ($c === "'") { $dentro = false; $vals[] = $atual; $atual = ''; continue; }
            $atual .= $c;
        } elseif ($c === "'") { $dentro = true; } elseif ($c === ')') { break; }
    }
    return $vals;
}

$arquivos = ['schema.sql' => file_get_contents($ROOT . '/schema.sql'), 'seed' => file_get_contents($ROOT . '/migrations/v2.2.0_seed_legal_pages.sql')];
$esperado = [
    'terms'   => ['classificação indicativa de 18 anos', 'Política deste Servidor', 'Versão [VERSÃO]', 'Usuário verificado'],
    'privacy' => ['identificador derivado do CPF', 'Transferência internacional', '[PRAZO DE GUARDA DO FORNECEDOR]', '5 anos após a compra', 'criança'],
    'refund'  => ['analisado individualmente', 'depende do meio de pagamento'],
];
foreach ($arquivos as $nome => $sql) {
    echo "\n$nome\n";
    foreach ($esperado as $slug => $trechos) {
        $t = tupla($sql, $slug);
        $pt = $t[3] ?? ''; $en = $t[4] ?? '';
        $falta = array_filter($trechos, fn($x) => !str_contains($pt, $x));
        if ($pt !== '' && !$falta) ok("$slug: texto revisado"); else falha("$slug: falta " . implode(' | ', $falta));
        if (!preg_match('/irrevers|assistência expressa|Não realizamos estorno parcial|12 meses \(Marco Civil/iu', $pt . $en)) ok("$slug: sem as frases vetadas"); else falha("$slug: ainda tem frase vetada pela revisao");
        if (!str_contains($pt . $en, "\u{2014}") && !str_contains($pt . $en, "\u{2013}")) ok("$slug: sem travessao"); else falha("$slug: travessao");
        if (str_contains($en, 'available in Portuguese only')) ok("$slug: versao em ingles aponta para o texto em portugues"); else falha("$slug: ingles ainda e o texto antigo");
    }
    $faq = (tupla($sql, 'faq')[3] ?? '');
    if (str_contains($faq, 'classificação indicativa de 18 anos') && !preg_match('/irrevers/i', $faq)) ok('faq: resposta sobre menor revisada'); else falha('faq: resposta sobre menor antiga');
}
foreach (['terms', 'privacy', 'refund'] as $slug) {
    if ((tupla($arquivos['schema.sql'], $slug)[3] ?? 'a') === (tupla($arquivos['seed'], $slug)[3] ?? 'b')) ok("$slug: schema.sql e seed iguais"); else falha("$slug: schema.sql e seed divergem", 'instalacao nova e site atualizado receberiam textos diferentes');
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
