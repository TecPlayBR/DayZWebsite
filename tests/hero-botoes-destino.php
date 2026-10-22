<?php
/**
 * Botoes do topo da home levam aonde dizem, na home e no menu do celular: "Entrar Agora" vai para
 * "Como Conectar" (/page/connect) e "Conheca as Regras" vai para /rules.
 * Rodar: php tests/hero-botoes-destino.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d): void { global $falhas; $falhas++; echo "  FALHA $d\n"; }
$ROOT = dirname(__DIR__);

foreach (['views/pages/home.php' => 'home', 'views/partials/header.php' => 'menu do celular'] as $arq => $onde) {
    $s = file_get_contents("$ROOT/$arq");
    if (preg_match('#<a href="/page/connect"[^>]*>\s*<\?= e\(__\(\'hero\.cta\'\)\) \?>#', $s)) ok("$onde: Entrar Agora vai para Como Conectar"); else falha("$onde: Entrar Agora nao vai para /page/connect");
    if (preg_match('#<a href="/rules"[^>]*>\s*<\?= e\(__\(\'hero\.cta_alt\'\)\) \?>#', $s)) ok("$onde: Conheca as Regras vai para /rules"); else falha("$onde: Conheca as Regras nao vai para /rules");
}

echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
exit($falhas ? 1 : 0);
