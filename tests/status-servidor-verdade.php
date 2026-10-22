<?php
/**
 * O status do servidor mostrado no site so afirma o que foi provado. A lista de jogadores do CFTools vem vazia
 * tambem com o servidor desligado, e uma falha de consulta do BattleMetrics nao e "offline".
 * Regra: online so com prova (CFTools com jogador ou BattleMetrics dizendo online); fora do ar so com prova
 * (BattleMetrics respondendo offline); sem prova = online null e o chip some (nao afirma nada).
 * Rodar: php tests/status-servidor-verdade.php
 */

namespace App {
    class Lang { public static function get(string $k, array $p = [], ?string $d = null): string { return '[' . $k . ']'; } }
}

namespace {
    $falhas = 0;
    function ok(string $d): void { echo "  OK   $d\n"; }
    function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
    $ROOT = dirname(__DIR__);
    require_once $ROOT . '/src/Csp.php';
    require_once $ROOT . '/src/helpers.php';

    echo "\n1. Decisao (status_combinar)\n";
    if (!function_exists('status_combinar')) { falha('falta status_combinar() em src/helpers.php'); echo "\nFALHOU: $falhas\n"; exit(1); }
    $bmFalhou = ['configured' => true, 'online' => false, 'players' => 0, 'max' => 0, 'error' => 'HTTP 403'];
    $bmOff    = ['configured' => true, 'online' => false, 'players' => 0, 'max' => 60];
    $bmOn     = ['configured' => true, 'online' => true, 'players' => 4, 'max' => 60];
    $semBm    = ['configured' => false];
    $dois     = [['steam_id' => '1'], ['steam_id' => '2']];

    $r = status_combinar($bmFalhou, [], true);
    if (array_key_exists('online', $r) && $r['online'] === null) ok('CFTools vazio + BattleMetrics falhou: sem confirmacao (nao diz Online)'); else falha('lista vazia ainda vira Online ou Fora do ar', var_export($r, true));
    $r = status_combinar($semBm, [], true);
    if (!empty($r['configured']) && $r['online'] === null) ok('so CFTools, vazio: sem confirmacao'); else falha('so CFTools vazio errado', var_export($r, true));
    $r = status_combinar($bmFalhou, $dois, true);
    if ($r['online'] === true && $r['players'] === 2) ok('CFTools com 2 jogadores: Online com 2'); else falha('CFTools com jogador nao prova online', var_export($r, true));
    $r = status_combinar($bmOn, [], true);
    if ($r['online'] === true) ok('BattleMetrics online e CFTools vazio: Online (servidor vazio)'); else falha('BattleMetrics online ignorado', var_export($r, true));
    $r = status_combinar($bmOff, [], true);
    if ($r['online'] === false) ok('BattleMetrics respondeu offline: Fora do ar'); else falha('BattleMetrics offline ignorado', var_export($r, true));
    $r = status_combinar($bmFalhou, null, false);
    if ($r['online'] === null) ok('BattleMetrics falhou e sem CFTools: sem confirmacao (nao diz Fora do ar)'); else falha('falha do BattleMetrics virou Fora do ar', var_export($r, true));
    $r = status_combinar($bmFalhou, null, true);
    if ($r['online'] === null) ok('CFTools falhou (null) e BattleMetrics falhou: sem confirmacao'); else falha('duas falhas viraram afirmacao', var_export($r, true));
    $r = status_combinar($semBm, null, false);
    if (empty($r['configured'])) ok('nada configurado: continua nao configurado'); else falha('inventou configuracao', var_export($r, true));

    echo "\n2. O chip nao afirma nada sem confirmacao\n";
    $parcial = $ROOT . '/views/partials/hero-online.php';
    $base = ['discordUrl' => 'https://discord.gg/exemplo', 'showRank' => false, 'mostrar' => true];
    extract($base); $ss = ['configured' => true, 'online' => null, 'players' => 0, 'max' => 0];
    ob_start(); include $parcial; $h = ob_get_clean();
    if (trim($h) === '') ok('online null: o chip some'); else falha('chip aparece sem confirmacao', $h);

    echo "\n3. O JSON publico tambem nao afirma\n";
    $idx = file_get_contents("$ROOT/public/index.php");
    $ini = strpos($idx, "Router::get('/status-servidor.json'");
    $rota = $ini !== false ? substr($idx, $ini, 1400) : '';
    if ($rota && preg_match("/'online'\s*=>[^\n]*\?\?\s*null/", $rota)) ok('JSON nao converte null em false'); else falha('JSON transforma "sem confirmacao" em online false');

    echo "\n4. A pagina /server-status usa a mesma regra\n";
    $ini2 = strpos($idx, "Router::get('/server-status'");
    $rota2 = $ini2 !== false ? substr($idx, $ini2, 2200) : '';
    if ($rota2 && str_contains($rota2, 'status_combinar(') && !preg_match("/\\\$status\['online'\]\s*=\s*true/", $rota2)) ok('rota usa status_combinar, sem cravar online = true'); else falha('rota /server-status ainda crava online com lista vazia');
    $view = file_get_contents("$ROOT/views/pages/server_status.php");
    if (preg_match('/\.server-dot-unknown\s*\{[^}]*background/', $view)) ok('bolinha cinza para sem confirmacao (nao fica invisivel)'); else falha('sem estilo para server-dot-unknown');
    if (str_contains($view, "server.unknown")) ok('tela mostra "sem confirmacao" no lugar de OFFLINE quando nao ha prova'); else falha('tela trata sem confirmacao como OFFLINE');
    foreach (['pt-br', 'en-us'] as $l) {
        $lang = file_get_contents("$ROOT/lang/$l.php");
        if (preg_match("/'unknown'\s*=>/", $lang)) ok("texto server.unknown em $l"); else falha("falta server.unknown em $l");
    }

    echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
    exit($falhas ? 1 : 0);
}
