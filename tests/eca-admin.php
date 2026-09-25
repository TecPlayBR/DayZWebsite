<?php
/**
 * Painel do ECA Digital: chaves no whitelist do handler, secret nunca ecoado, rotas com
 * permissao, relatorio sem PII, aviso por modo. Estrutural.
 * Rodar: php tests/eca-admin.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$idx = file_get_contents($ROOT . '/public/index.php');
$set = file_get_contents($ROOT . '/views/admin/settings.php');
$lay = file_get_contents($ROOT . '/views/admin/layout.php');

echo "\n1. Handler de settings\n";
$h = substr($idx, strpos($idx, "Router::post('/admin/settings'"), 6000);
foreach (['age_gate_mode', 'age_provider'] as $k) { if (str_contains($h, "'$k'")) ok("$k no whitelist"); else falha("$k fora do whitelist", 'salvar seria no-op'); }
if (str_contains($h, "'age_daily_box_gated'")) ok('age_daily_box_gated nos toggles'); else falha('age_daily_box_gated fora dos toggles');
if (preg_match("/age_provider_key[^;]*trim\(\(string\)\s*\\\$_POST\[\\\$k\]\)\s*!==\s*''/", $h) || str_contains($h, "trim((string)\$_POST['age_provider_key']) !== ''")) ok('chave so grava se digitada (vazio mantem)'); else falha('chave do fornecedor sobrescreve com vazio');
if (str_contains($h, "AuditLog::record('age.settings'")) ok('audita mudanca de config'); else falha('nao audita age.settings');
if (!str_contains($h, "'age_hash_salt'")) ok('sal do hash NAO e editavel pelo painel'); else falha('sal do hash editavel pelo painel', 'trocar o sal invalida todo hash');

echo "\n2. Form de settings\n";
if (str_contains($set, 'name="age_gate_mode"') && str_contains($set, 'name="age_provider"') && str_contains($set, 'name="age_provider_key"')) ok('campos no form'); else falha('faltam campos no form');
if (!preg_match('/name="age_provider_key"[^>]*value="<\?=/', $set)) ok('a chave nao e ecoada no HTML'); else falha('chave do fornecedor ecoada no HTML');

echo "\n3. Rotas admin\n";
foreach (["Router::get('/admin/eca'", "Router::post('/admin/eca/revogar'", "Router::get('/admin/eca/relatorio'", "Router::get('/admin/eca/export.csv'", "Router::post('/admin/eca/testar-chave'"] as $r) {
    $i = strpos($idx, $r);
    if ($i !== false && str_contains(substr($idx, $i, 400), "Auth::requireCan('settings')")) ok("$r com requireCan"); else falha("$r ausente ou sem requireCan");
}
$rv = substr($idx, (int) strpos($idx, "Router::post('/admin/eca/revogar'"), 900);
if (str_contains($rv, 'Csrf::check()') && str_contains($rv, "AuditLog::record('age.revoked'")) ok('revogar com CSRF e auditoria'); else falha('revogar sem CSRF ou sem auditoria');

echo "\n4. Views\n";
$eca = @file_get_contents($ROOT . '/views/admin/eca.php') ?: ''; $rel = @file_get_contents($ROOT . '/views/admin/eca_relatorio.php') ?: '';
if ($eca !== '' && $rel !== '') ok('views existem'); else falha('faltam views eca.php / eca_relatorio.php');
if (!preg_match('/cpf_hash/', $eca) && !preg_match('/birth_year/', $eca)) ok('tabela do admin nao mostra hash nem ano de nascimento'); else falha('tabela do admin expoe hash ou ano', 'metadados so: resultado, metodo, data, ref');
if (str_contains($rel, 'Lei 15.211/2025') && str_contains($rel, 'Decreto 12.880/2026')) ok('relatorio cita a base legal'); else falha('relatorio sem base legal');

echo "\n5. Menu e aviso\n";
if (str_contains($lay, "'/admin/eca'")) ok('entrada de menu'); else falha('sem entrada de menu');
if (str_contains($lay, 'AgeVerification::modo()') && str_contains($lay, 'desligado')) ok('layout mostra aviso por modo'); else falha('layout sem aviso por modo');

echo "\n6. Revisao: alerta de falha do fornecedor e copia honesta dos modos\n";
if (str_contains($lay, 'AgeVerification::falhasRecentes(')) ok('layout do admin avisa quando o fornecedor esta falhando'); else falha('admin so descobre falha do fornecedor se abrir a tela', 'creditos acabam e todo jogador falha em silencio');
if (preg_match('/value="desligado"[^>]*>[^<]*loja continua/i', $set)) ok('opcao desligado diz que a loja continua pedindo idade e aceite'); else falha('opcao desligado promete "como era antes", e nao e');
if (preg_match('/value="declaracao"[^>]*>[^<]*caixas s(o|ó) abrem com CPF/iu', $set)) ok('opcao declaracao diz que caixa abre com CPF verificado'); else falha('opcao declaracao diz que caixas ficam fechadas, e nao ficam pra quem verifica');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
