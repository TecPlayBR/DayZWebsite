<?php
/**
 * Cabecalho em celular estreito (360 px, muito Android): o botao do menu ficava 32 px fora da tela
 * (auditoria de 27/09/2026). O espaco fixo de 2rem entre a marca e os botoes nao cabia.
 * Rodar: php tests/cabecalho-celular.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$css = file_get_contents(dirname(__DIR__) . '/public/assets/css/theme.css');
if (preg_match('/@media \(max-width: 860px\) \{[^}]*\.header-inner \{[^}]*gap: *0?\.\d+rem/s', $css)) ok('no celular o espaco entre marca e botoes encolhe'); else falha('espaco de 2rem continua no celular');
if (preg_match('/\.brand \{[^}]*min-width: *0/s', $css)) ok('a marca pode encolher (min-width: 0), o menu nunca sai da tela'); else falha('marca nao encolhe e empurra o menu');
if (preg_match('/\.header-actions \{[^}]*flex-shrink: *0/s', $css)) ok('idioma e menu nao encolhem'); else falha('botoes do cabecalho podem ser espremidos');
if (preg_match('/@media \(max-width: 400px\) \{[^}]*\.lang-trigger \.lang-code \{ *display: *none/s', $css)) ok('em celular estreito o idioma mostra so a bandeira'); else falha('seletor de idioma largo demais em 360 px');
echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
