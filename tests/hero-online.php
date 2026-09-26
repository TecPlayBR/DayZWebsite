<?php
/**
 * Chip "online agora" do topo da home: nunca mostra "0 jogadores" (prova social ao contrario),
 * atualiza sozinho pelo /status-servidor.json (que so expoe online/jogadores/maximo) e o dono
 * pode desligar nas Configuracoes, com o interruptor nascendo LIGADO.
 * Rodar: php tests/hero-online.php
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
    $parcial = $ROOT . '/views/partials/hero-online.php';

    function rende(string $arq, array $dados): string {
        if (!is_file($arq)) return '__SEM_ARQUIVO__';
        extract($dados);
        ob_start(); include $arq; return ob_get_clean();
    }
    $base = ['discordUrl' => 'https://discord.gg/exemplo', 'showRank' => false, 'mostrar' => true];

    echo "\n1. O chip renderizado em cada situacao\n";
    $h = rende($parcial, $base + ['ss' => ['configured' => true, 'online' => true, 'players' => 37, 'max' => 60]]);
    if ($h === '__SEM_ARQUIVO__') { falha('views/partials/hero-online.php nao existe'); echo "\nFALHOU: $falhas\n"; exit(1); }
    if (str_contains($h, '>37<') && str_contains($h, '/60') && str_contains($h, '[hero.status_players]')) ok('online com gente: mostra 37/60 jogadores'); else falha('online com gente sem a contagem', $h);
    if (preg_match('/class="hs-contagem"(?![^>]*hidden)/', $h)) ok('contagem visivel'); else falha('contagem escondida com gente online');

    $h0 = rende($parcial, $base + ['ss' => ['configured' => true, 'online' => true, 'players' => 0, 'max' => 60]]);
    if (str_contains($h0, '[hero.status_online]') && preg_match('/class="hs-contagem"[^>]*\bhidden\b/', $h0)) ok('online e vazio: mostra "Online" e esconde a contagem (sem "0 jogadores")'); else falha('mostra "0 jogadores" ou some com o Online', $h0);

    $hoff = rende($parcial, $base + ['ss' => ['configured' => true, 'online' => false, 'players' => 0, 'max' => 0]]);
    if (str_contains($hoff, '[hero.status_voltando]') && !str_contains($hoff, 'hs-contagem') && !str_contains($hoff, 'data-status-url')) ok('fora do ar: "voltando em breve", sem contagem e sem atualizar sozinho'); else falha('fora do ar errado', $hoff);

    $hnc = rende($parcial, $base + ['ss' => ['configured' => false]]);
    if (trim($hnc) === '') ok('sem servidor configurado: nada'); else falha('renderizou sem servidor configurado', $hnc);

    $hdes = rende($parcial, ['mostrar' => false] + $base + ['ss' => ['configured' => true, 'online' => true, 'players' => 37, 'max' => 60]]);
    if (trim($hdes) === '') ok('dono desligou: nada'); else falha('renderizou com o interruptor desligado');

    echo "\n2. Atualiza sozinho, sem pesar\n";
    if (str_contains($h, 'data-status-url="/status-servidor.json"')) ok('aponta pro /status-servidor.json'); else falha('sem data-status-url');
    if (preg_match('/<script nonce="[^"]+">/', $h) && preg_match('/\(function\s*\(\)\s*\{/', $h)) ok('script com nonce e escopo proprio'); else falha('script sem nonce ou sem IIFE');
    if (str_contains($h, 'visibilityState') && preg_match('/setInterval\([^,]+,\s*60000\)/', $h)) ok('a cada 60s e so com a aba visivel'); else falha('atualizacao sem limite de ritmo');

    echo "\n3. Rota, helper, home e configuracao\n";
    $idx = file_get_contents("$ROOT/public/index.php");
    $hlp = file_get_contents("$ROOT/src/helpers.php");
    if (preg_match('/function status_servidor\(array \$config\): array/', $hlp)) ok('helper status_servidor() (uma fonte pra home e pro JSON)'); else falha('falta status_servidor()');
    $ini = strpos($idx, "Router::get('/status-servidor.json'");
    $rota = $ini !== false ? substr($idx, $ini, 1400) : '';
    if ($rota && str_contains($rota, 'status_servidor($config)') && str_contains($rota, "'online'") && str_contains($rota, "'players'") && str_contains($rota, "'max'") && !preg_match("/'(name|map|ip|host|port|rank)'\s*=>/", $rota)) ok('JSON so com online/jogadores/maximo (sem nome, IP ou porta)'); else falha('rota do JSON ausente ou expondo campo a mais');
    if ($rota && str_contains($rota, 'Cache-Control: public, max-age=30')) ok('JSON com cache de 30s no navegador/CDN'); else falha('JSON sem cache');
    if ($rota && str_contains($rota, "getBool('hero_online_enabled', true)")) ok('JSON respeita o interruptor do dono'); else falha('JSON ignora o interruptor');
    $home = file_get_contents("$ROOT/views/pages/home.php");
    if (str_contains($home, "partial('partials.hero-online'")) ok('home usa o parcial'); else falha('home nao usa o parcial');
    if (preg_match('/\$onlineNow\s*=.*>\s*0/', $home)) ok('"jogando agora" dos numeros do topo tambem some com 0'); else falha('"0 jogando agora" continua aparecendo');
    $cfg = file_get_contents("$ROOT/src/Settings.php");
    if (preg_match("/'hero_online_enabled'\s*=>\s*'bool'/", $cfg)) ok('hero_online_enabled no SCHEMA'); else falha('falta hero_online_enabled no SCHEMA');
    if (preg_match("/\\\$toggles = \[[^\]]*'hero_online_enabled'/s", $idx)) ok('interruptor na lista do POST de configuracoes'); else falha('interruptor fora da lista (salvar nao grava)');
    $sv = file_get_contents("$ROOT/views/admin/settings.php");
    if (preg_match('/name="hero_online_enabled" value="1" <\?= \\\\App\\\\Settings::getBool\(\'hero_online_enabled\', true\)/', $sv)) ok('caixa nasce MARCADA pra quem nunca salvou (salvar nao desliga sem querer)'); else falha('caixa pode nascer desmarcada', 'o proximo salvar desligaria o chip de todo mundo');

    echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
    exit($falhas ? 1 : 0);
}
