<?php
/**
 * 20/10/2026: o Sparda de agosto le Api_Get/Api_Post (codigo do Sparda_Store_Server.pbo conferido, classe
 * DataConfigStore). O Config.json do SETORZ (outubro) traz GetAPI/PostAPI e outras chaves novas: pode ser versao
 * mais nova. O mod ignora chave desconhecida, entao o bloco leva OS DOIS pares e serve pras duas versoes.
 * Trava: um bloco so com os dois pares, sem "versao antiga", e a tela manda apagar as linhas de API antigas.
 * Rodar: php tests/sparda-bloco-duas-versoes.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$idx  = file_get_contents($ROOT . '/public/index.php');
$view = file_get_contents($ROOT . '/views/admin/sparda.php');
$inst = file_get_contents($ROOT . '/INSTALACAO.md');

if (preg_match('/\$jsonSnippet\s*=\s*"\\\\"EnableWebsiteAPI\\\\": 1,\\\\n\\\\"Api_Get\\\\"/', $idx)) ok('bloco comeca com EnableWebsiteAPI e Api_Get'); else falha('bloco nao comeca com Api_Get');
foreach (['Api_Post', 'GetAPI', 'PostAPI'] as $k) {
    if (str_contains($idx, '\"' . $k . '\": \"')) ok("bloco tem $k"); else falha("bloco sem $k");
}
if (!str_contains($idx, 'jsonSnippetAntigo') && !str_contains($view, 'jsonSnippetAntigo')) ok('sem bloco de versao antiga'); else falha('ainda existe o bloco de versao antiga');
if (str_contains($view, 'apague') && str_contains($view, 'GetAPI') && str_contains($view, 'Api_Get')) ok('tela manda apagar as linhas de API antigas dos dois nomes'); else falha('tela nao explica que tem que apagar as linhas antigas');
if (!str_contains($inst, 'versão antiga do Sparda') && str_contains($inst, 'Api_Get') && str_contains($inst, 'GetAPI')) ok('INSTALACAO.md explica os dois nomes'); else falha('INSTALACAO.md desatualizado');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
