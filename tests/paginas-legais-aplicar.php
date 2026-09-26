<?php
/**
 * Botao "Aplicar o texto novo" das paginas legais (3.8.0): o cliente roda o update e o proprio
 * painel troca Termos, Privacidade, Reembolso e a resposta do FAQ sobre menores pelo modelo
 * revisado, preenchido com os dados que ele cadastra. O texto anterior vai para page_versions.
 * A camada roda de verdade em SQLite na memoria; rota, painel e migration sao conferidos por texto.
 * Rodar: php tests/paginas-legais-aplicar.php
 */

namespace App {
    class Database {
        public static ?\PDO $pdo = null;
        private static function sql(string $s): string { return str_replace('NOW()', "datetime('now')", $s); }
        public static function query(string $sql, array $params = []): \PDOStatement { $st = self::$pdo->prepare(self::sql($sql)); $st->execute($params); return $st; }
        public static function execute(string $sql, array $params = []): int { return self::query($sql, $params)->rowCount(); }
        public static function fetchOne(string $sql, array $params = []): ?array { $r = self::query($sql, $params)->fetch(\PDO::FETCH_ASSOC); return $r === false ? null : $r; }
        public static function fetchColumn(string $sql, array $params = []) { return self::query($sql, $params)->fetchColumn(); }
    }
    class Settings {
        public static array $v = [];
        public static function get(string $k, $d = null) { return self::$v[$k] ?? $d; }
        public static function set(string $k, string $raw): bool { self::$v[$k] = $raw; return true; }
    }
    class AgeVerifierFactory { public const PROVIDERS = ['cpfhub' => 'CPFHub', 'serpro' => 'Serpro', 'flagcheck' => 'FlagCheck']; }
}

namespace {
    $falhas = 0;
    function ok(string $d): void { echo "  OK   $d\n"; }
    function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
    $ROOT = dirname(__DIR__);
    use App\Database, App\Settings, App\PaginasLegais;

    if (!is_file($ROOT . '/src/PaginasLegais.php')) { falha('src/PaginasLegais.php nao existe'); echo "\n1 FALHA(S).\n"; exit(1); }
    require_once $ROOT . '/src/PaginasLegais.php';

    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    Database::$pdo = $pdo;
    $pdo->exec("CREATE TABLE pages (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, title_ptbr TEXT, body_ptbr TEXT, body_enus TEXT)");
    $pdo->exec("CREATE TABLE page_versions (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT, body_ptbr TEXT, body_enus TEXT, motivo TEXT, saved_by TEXT, saved_at TEXT DEFAULT (datetime('now')))");
    foreach (['terms', 'privacy', 'refund'] as $s) $pdo->exec("INSERT INTO pages (slug, title_ptbr, body_ptbr, body_enus) VALUES ('$s', '$s', '<p>texto antigo do cliente $s</p>', '<p>old $s</p>')");
    $pdo->exec("INSERT INTO pages (slug, title_ptbr, body_ptbr, body_enus) VALUES ('faq', 'faq', '<details class=\"faq-item\"><summary class=\"faq-q\">Posso trocar?</summary><div class=\"faq-a\"><p>Nao.</p></div></details>\n<details class=\"faq-item\">\n<summary class=\"faq-q\">Sou menor de 18, posso comprar?</summary>\n<div class=\"faq-a\"><p>resposta antiga</p></div>\n</details>', '')");
    $corpo = fn(string $s) => (string) $pdo->query("SELECT body_ptbr FROM pages WHERE slug = '$s'")->fetchColumn();

    echo "\n1. Sem os dados obrigatorios, nao aplica\n";
    Settings::$v = ['age_provider' => 'cpfhub', 'discord_invite' => 'https://discord.gg/abcDEF123'];
    if (PaginasLegais::precisaAplicar()) ok('site que nunca aplicou precisa aplicar'); else falha('precisaAplicar falso sem nunca ter aplicado');
    $r = PaginasLegais::aplicar('Servidor Teste', 'teste.example', 'admin');
    if (!$r['ok'] && in_array('Razão social', $r['faltando'], true) && in_array('CNPJ', $r['faltando'], true) && in_array('E-mail de atendimento', $r['faltando'], true)) ok('recusa e diz o que falta'); else falha('aplicou sem os dados ou nao listou o que falta', json_encode($r));
    if (str_contains($corpo('terms'), 'texto antigo do cliente')) ok('pagina do cliente intacta'); else falha('mexeu na pagina sem aplicar');
    Settings::$v += ['legal_razao_social' => 'Empresa <b>Teste</b> & Cia', 'legal_cnpj' => '00.000.000/0001-00', 'legal_email' => 'nao-e-email', 'legal_hospedagem' => 'Hospedagem X'];
    $r = PaginasLegais::aplicar('Servidor Teste', 'teste.example', 'admin');
    if (!$r['ok'] && in_array('E-mail de atendimento', $r['faltando'], true)) ok('e-mail invalido conta como faltando'); else falha('aceitou e-mail invalido');

    echo "\n2. Aplica com hospedagem no Brasil\n";
    Settings::$v['legal_email'] = 'contato@teste.example';
    Settings::$v['legal_pais'] = 'Brasil';
    $r = PaginasLegais::aplicar('Servidor Teste', 'teste.example', 'admin');
    if ($r['ok'] ?? false) ok('aplicou'); else falha('nao aplicou com os dados completos', json_encode($r));
    $t = $corpo('terms'); $p = $corpo('privacy'); $re = $corpo('refund');
    $tudo = $t . $p . $re;
    if (!preg_match('/\[[A-ZÁÉÍÓÚÂÊÔÃÕÇ ]{4,}[^\]]*\]/u', $tudo)) ok('nenhum campo entre colchetes sobrou'); else { preg_match_all('/\[[^\]]{4,60}\]/u', $tudo, $m); falha('sobraram campos', implode(' ', array_unique($m[0]))); }
    if (str_contains($t, 'Empresa &lt;b&gt;Teste&lt;/b&gt; &amp; Cia') && !str_contains($tudo, '<b>Teste</b>')) ok('dado do dono entra escapado'); else falha('razao social entrou sem escapar');
    if (str_contains($t, '00.000.000/0001-00') && str_contains($t, 'teste.example') && str_contains($t, 'Servidor Teste')) ok('CNPJ, dominio e nome do servidor preenchidos'); else falha('campos basicos nao preenchidos');
    if (str_contains($p, 'mailto:contato@teste.example') && str_contains($p, 'https://discord.gg/abcDEF123')) ok('e-mail e Discord viram link'); else falha('e-mail ou Discord sem link');
    if (str_contains($p, 'CPFHub') && str_contains($p, '12 meses')) ok('fornecedor e prazo dele vem do painel (CPFHub: 12 meses)'); else falha('fornecedor ou prazo do fornecedor errado');
    if (str_contains($p, 'Hospedagem X') && str_contains($p, 'no Brasil') && !preg_match('#<li><strong>Hospedagem X:</strong> todos os dados do site#', $p)) ok('hospedagem no Brasil sai da lista de transferencia internacional'); else falha('hospedagem no Brasil continua como transferencia internacional');
    if (!str_contains($t, 'SE O DONO LIGAR')) ok('sem o trecho condicional da compra por CPF'); else falha('trecho condicional ficou no texto');
    $en = (string) $pdo->query("SELECT body_enus FROM pages WHERE slug = 'terms'")->fetchColumn();
    if (str_contains($en, 'available in Portuguese only') && str_contains($en, 'Servidor Teste')) ok('ingles aponta para o texto em portugues'); else falha('ingles errado');
    $faq = $corpo('faq');
    if (str_contains($faq, 'classificação indicativa de 18 anos') && !str_contains($faq, 'resposta antiga') && str_contains($faq, 'Posso trocar?')) ok('FAQ: so a resposta sobre menor trocou, o resto ficou'); else falha('FAQ errado');
    $v = $pdo->query("SELECT slug, body_ptbr FROM page_versions ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (count($v) === 4 && str_contains($v['terms'] ?? '', 'texto antigo do cliente') && str_contains($v['faq'] ?? '', 'resposta antiga')) ok('texto anterior das 4 paginas guardado no historico'); else falha('historico nao guardou o texto anterior', json_encode(array_keys($v)));
    if (!PaginasLegais::precisaAplicar() && Settings::$v['legal_modelo_aplicado'] === PaginasLegais::MODELO) ok('marca o modelo aplicado'); else falha('nao marcou legal_modelo_aplicado');
    if ((Settings::$v['terms_version'] ?? '') === date('Y-m-d')) ok('versao dos Termos muda: jogadores aceitam o texto novo'); else falha('terms_version nao mudou');

    echo "\n3. Hospedagem fora do Brasil exige o fundamento\n";
    Settings::$v['legal_pais'] = 'Estados Unidos'; Settings::$v['legal_modelo_aplicado'] = '';
    $r = PaginasLegais::aplicar('Servidor Teste', 'teste.example', 'admin');
    if (!$r['ok'] && in_array('Fundamento da transferência da hospedagem', $r['faltando'], true)) ok('pede o fundamento quando o pais nao e o Brasil'); else falha('aplicou hospedagem no exterior sem fundamento');
    Settings::$v['legal_mecanismo_hospedagem'] = 'cláusulas-padrão da ANPD';
    $r = PaginasLegais::aplicar('Servidor Teste', 'teste.example', 'admin');
    $p = $corpo('privacy');
    if (($r['ok'] ?? false) && preg_match('#<li><strong>Hospedagem X:</strong> todos os dados do site, em Estados Unidos\. Fundamento: cláusulas-padrão da ANPD\.</li>#u', $p)) ok('hospedagem no exterior entra na lista com o fundamento'); else falha('hospedagem no exterior errada');

    echo "\n4. Modelo, rota, painel e historico no editor\n";
    $seed = file_get_contents($ROOT . '/migrations/v2.2.0_seed_legal_pages.sql');
    foreach (['terms', 'privacy', 'refund'] as $s) {
        $arq = (string) @file_get_contents($ROOT . "/src/legal/$s.html");
        if ($arq !== '' && str_contains($seed, str_replace("'", "''", trim(explode("\n", $arq)[4] ?? 'x')))) ok("src/legal/$s.html e o mesmo texto do seed"); else falha("src/legal/$s.html ausente ou diferente do seed");
    }
    $idx = file_get_contents($ROOT . '/public/index.php');
    $i = strpos($idx, "Router::post('/admin/eca/paginas-legais'");
    $rota = $i === false ? '' : substr($idx, $i, 2500);
    if ($rota !== '' && str_contains($rota, "Auth::requireCan('settings')") && str_contains($rota, 'Csrf::check()') && str_contains($rota, "AuditLog::record('paginas.legais_aplicadas'")) ok('rota com permissao, CSRF e auditoria'); else falha('rota /admin/eca/paginas-legais ausente ou sem protecao');
    $sv = substr($idx, (int) strpos($idx, "Router::post('/admin/pages/save'"), 2500);
    if (str_contains($sv, 'INSERT INTO page_versions') && strpos($sv, 'INSERT INTO page_versions') < strpos($sv, 'UPDATE pages SET')) ok('editor de paginas guarda a versao anterior antes de salvar'); else falha('editor de paginas nao guarda historico');
    $eca = file_get_contents($ROOT . '/views/admin/eca.php');
    foreach (['legal_razao_social', 'legal_cnpj', 'legal_email', 'legal_hospedagem', 'legal_pais'] as $c) { if (str_contains($eca, 'name="' . $c . '"') || str_contains($eca, "'name' => '$c'")) ok("painel tem o campo $c"); else falha("painel sem o campo $c"); }
    if (str_contains($eca, 'value="aplicar"') && preg_match('/data-confirm="[^"]*anterior[^"]*"/iu', $eca)) ok('botao aplicar avisa que o texto anterior vai para o historico'); else falha('botao aplicar sem confirmacao');
    $mig = (string) @file_get_contents($ROOT . '/migrations/v3.8.0_paginas_versoes.sql');
    if (stripos($mig, 'CREATE TABLE IF NOT EXISTS page_versions') !== false && stripos(file_get_contents($ROOT . '/schema.sql'), 'CREATE TABLE page_versions') !== false) ok('page_versions na migration e no schema.sql'); else falha('page_versions faltando na migration ou no schema');
    if (!preg_match('/--[^\n]*;/', $mig)) ok('migration sem ; em comentario'); else falha('; em comentario parte a migration');
    $set = file_get_contents($ROOT . '/src/Settings.php');
    foreach (['legal_razao_social', 'legal_cnpj', 'legal_email', 'legal_hospedagem', 'legal_pais', 'legal_mecanismo_hospedagem', 'legal_prazo_fornecedor', 'legal_modelo_aplicado'] as $c) { if (!str_contains($set, "'$c'")) falha("$c fora do SCHEMA"); }
    ok('chaves legal_* conferidas no SCHEMA');

    echo "\n" . str_repeat('-', 62) . "\n";
    if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
    echo "$falhas FALHA(S).\n"; exit(1);
}
