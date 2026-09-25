<?php
/**
 * CSP sem 'unsafe-inline' nem 'unsafe-eval' no script: nonce por resposta, nenhum handler
 * inline, Chart.js servido pelo proprio site, PJAX do admin re-executando so script com nonce
 * valido e o antifraude do Mercado Pago recebendo o nonce.
 * Rodar: php tests/csp-nonce.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$ler = fn(string $rel) => is_file($ROOT . '/' . $rel) ? file_get_contents($ROOT . '/' . $rel) : '';

/** Todos os arquivos que geram HTML: views + as duas telas avulsas (install/update). */
function arquivos_html(string $root): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/views', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if (substr($f->getFilename(), -4) === '.php') $out[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
    $out[] = 'public/install.php';
    $out[] = 'public/update.php';
    sort($out);
    return $out;
}

/** Tags <script ...> reais (ignora linha de comentario que so CITA a tag). */
function tags_script(string $src): array {
    $tags = [];
    foreach (preg_split('/\R/', $src) as $n => $linha) {
        $t = ltrim($linha);
        if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '/*') || str_starts_with($t, '#') || str_starts_with($t, '<!--')) continue;
        // O grupo com <\? ... \? e > atravessa o bloco PHP dentro da tag (o nonce e impresso por PHP).
        if (preg_match_all('/<script\b(?:<\?.*?\?>|[^>])*>/i', $linha, $m)) foreach ($m[0] as $tag) $tags[] = [$n + 1, $tag];
    }
    return $tags;
}

/** Atributo de evento no HTML (fora dos blocos <script>) ou montado dentro de string JS. */
function handlers_inline(string $src): array {
    $achados = [];
    $semScript = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $src);
    if (preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $semScript, $m)) foreach ($m[0] as $x) $achados[] = trim($x);
    if (preg_match_all('#<script\b[^>]*>(.*?)</script>#is', $src, $blocos)) {
        foreach ($blocos[1] as $js) if (preg_match_all('/<[a-z][^<>]*\son[a-z]+=\\\\?["\']/i', $js, $m2)) foreach ($m2[0] as $x) $achados[] = trim($x);
    }
    return $achados;
}

echo "\n0. O detector de handler pega entrada ruim e nao da falso positivo\n";
$ruins = ['<form onsubmit="return confirm(1)">', "<img src=x onerror='this.remove()'>", '<button ONCLICK = "x()">', '<script>el.innerHTML = \'<b onclick="x()">\';</script>'];
$bons  = ['<script>img.onerror = function(){};</script>', '<p>use o botao de confirmar</p>', '<div data-confirm="Apagar?">', '<script>var once = 1;</script>'];
foreach ($ruins as $r) if (handlers_inline($r)) ok('pega: ' . $r); else falha('detector deixou passar: ' . $r);
foreach ($bons as $b) if (!handlers_inline($b)) ok('nao acusa: ' . $b); else falha('falso positivo: ' . $b);

echo "\n1. Nonce por resposta\n";
if (is_file($ROOT . '/src/Csp.php')) {
    require_once $ROOT . '/src/Csp.php';
    $n1 = \App\Csp::nonce(); $n2 = \App\Csp::nonce();
    if ($n1 === $n2) ok('mesmo nonce dentro da mesma resposta'); else falha('nonce muda dentro da resposta', 'as tags e o cabecalho nao bateriam');
    if (preg_match('/^[A-Za-z0-9_-]{22,}$/', $n1)) ok('nonce base64url com 22+ caracteres (' . strlen($n1) . ')'); else falha('nonce fraco ou fora do alfabeto', $n1);
    $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export($ROOT . '/src/Csp.php', true) . '; echo \App\Csp::nonce();');
    $a = trim((string)shell_exec($cmd)); $b = trim((string)shell_exec($cmd));
    if ($a !== '' && $a !== $b) ok('nonce muda entre respostas'); else falha('nonce repetido entre respostas', "$a / $b");
} else {
    falha('src/Csp.php nao existe');
}

echo "\n2. Politica\n";
$pol = class_exists('\App\Csp') ? \App\Csp::politica() : '';
$dir = [];
foreach (explode(';', $pol) as $p) { $p = trim($p); if ($p === '') continue; $k = strtok($p, ' '); $dir[$k] = substr($p, strlen($k)); }
$ss = $dir['script-src'] ?? '';
if ($ss !== '' && str_contains($ss, "'nonce-" . (class_exists('\App\Csp') ? \App\Csp::nonce() : '?') . "'")) ok('script-src carrega o nonce da resposta'); else falha('script-src sem o nonce', $ss);
if (!str_contains($ss, 'unsafe-inline')) ok("script-src sem 'unsafe-inline'"); else falha("script-src ainda tem 'unsafe-inline'");
if (!str_contains($ss, 'unsafe-eval')) ok("script-src sem 'unsafe-eval'"); else falha("script-src ainda tem 'unsafe-eval'", 'nenhum codigo nosso usa eval e o SDK do MP monta sem ele');
if (!str_contains($ss, 'jsdelivr')) ok('script-src sem CDN de terceiro (Chart.js e nosso)'); else falha('script-src ainda confia no jsdelivr');
foreach (['https://sdk.mercadopago.com', 'https://*.mlstatic.com'] as $h) if (str_contains($ss, $h)) ok("script-src mantem $h (cartao)"); else falha("script-src perdeu $h", 'o cartao transparente para');
if (trim($dir['script-src-attr'] ?? '') === "'none'") ok("script-src-attr 'none' (handler inline nao roda)"); else falha("falta script-src-attr 'none'");
foreach (["frame-ancestors" => "'self'", "base-uri" => "'self'", "default-src" => "'self'"] as $k => $v) if (trim($dir[$k] ?? '') === $v) ok("$k $v"); else falha("$k mudou", $dir[$k] ?? '(ausente)');
if (str_contains($dir['report-uri'] ?? '', '/api/csp-report.php')) ok('report-uri continua no endpoint de report'); else falha('perdeu o report-uri');
if (str_contains($dir['form-action'] ?? '', 'mercadopago.com')) ok('form-action ainda aceita o Mercado Pago'); else falha('form-action perdeu o MP');
if (str_contains($dir['frame-src'] ?? '', 'youtube')) ok('frame-src mantem o YouTube'); else falha('frame-src perdeu o YouTube');
if (str_contains($dir['connect-src'] ?? '', 'api.mercadopago.com')) ok('connect-src mantem a API do MP'); else falha('connect-src perdeu a API do MP');

echo "\n3. .htaccess nao sobrescreve a CSP do PHP\n";
$ht = $ler('public/.htaccess');
$global = preg_replace('#<FilesMatch\b.*?</FilesMatch>#is', '', $ht);
// A Hostinger TROCA a CSP que o PHP manda por "upgrade-insecure-requests" (provado no staging
// em 26/09/2026) e respeita a do .htaccess. Entao o PHP manda a politica tambem em X-Tecplay-Csp
// e o .htaccess so COPIA esse cabecalho pra CSP. Texto fixo de CSP no .htaccess continua proibido.
$linhasCsp = array_values(array_filter(preg_split('/\R/', $global), fn($l) => preg_match('/^\s*Header\b.*Content-Security-Policy/i', $l)));
$copia = 'Header always set Content-Security-Policy "expr=%{resp:X-Tecplay-Csp}" "expr=-n %{resp:X-Tecplay-Csp}"';
$fixas = array_filter($linhasCsp, fn($l) => trim($l) !== $copia);
if (!$fixas) ok('nenhuma CSP de texto fixo global (apagaria o nonce)'); else falha('.htaccess seta CSP fixa global', trim(reset($fixas)));
if (count($linhasCsp) === 1 && trim($linhasCsp[0]) === $copia) ok('.htaccess copia X-Tecplay-Csp pra CSP (so quando o PHP mandou)'); else falha('.htaccess nao copia a CSP do PHP', 'na Hostinger a CSP do PHP e trocada por upgrade-insecure-requests');
if (preg_match('/^\s*Header always unset X-Tecplay-Csp\s*$/m', $global) && preg_match('/^\s*Header unset X-Tecplay-Csp\s*$/m', $global)) ok('cabecalho auxiliar some da resposta (tabelas always e onsuccess)'); else falha('X-Tecplay-Csp vaza na resposta');
$fonteCsp = $ler('src/Csp.php');
if (str_contains($fonteCsp, "header('X-Tecplay-Csp: '") && str_contains($fonteCsp, "header('Content-Security-Policy: '")) ok('PHP manda a politica nos dois cabecalhos (nginx usa a direta)'); else falha('PHP nao manda os dois cabecalhos');
if (array_key_exists('upgrade-insecure-requests', $dir)) ok('upgrade-insecure-requests mantido (era o que a Hostinger punha)'); else falha('perdeu upgrade-insecure-requests');
if (preg_match('#<FilesMatch "[^"]*svg[^"]*">.*?Content-Security-Policy "default-src \'none\'#is', $ht)) ok('arquivo estatico (svg/html) recebe CSP fechada'); else falha('estatico ficou sem CSP');

echo "\n4. Quem gera HTML manda o cabecalho\n";
$idx = $ler('public/index.php');
if (str_contains($idx, "/src/Csp.php'")) ok('index.php carrega src/Csp.php'); else falha('index.php nao carrega src/Csp.php');
$pEnv = strpos($idx, '\App\Csp::enviar()'); $pSes = strpos($idx, 'session_start();');
if ($pEnv !== false && $pSes !== false && $pEnv < $pSes) ok('index.php manda a CSP antes de qualquer saida'); else falha('index.php nao chama \App\Csp::enviar() no bootstrap');
foreach (['public/install.php', 'public/update.php'] as $f) {
    $s = $ler($f);
    if (str_contains($s, "/src/Csp.php'") && str_contains($s, '\App\Csp::enviar()')) ok("$f manda a CSP"); else falha("$f sem CSP", 'perdeu a do .htaccess');
}

echo "\n5. Toda tag <script> carrega o nonce\n";
$semNonce = [];
$total = 0;
foreach (arquivos_html($ROOT) as $f) {
    foreach (tags_script($ler($f)) as [$ln, $tag]) {
        $total++;
        if (!preg_match('/\snonce="<\?= (csp_nonce\(\)|\\\\App\\\\Csp::nonce\(\)) \?>"/', $tag)) $semNonce[] = "$f:$ln $tag";
    }
}
if ($total > 30 && !$semNonce) ok("$total tags, todas com nonce"); else falha(count($semNonce) . " de $total tags sem nonce", implode("\n         ", array_slice($semNonce, 0, 12)));
$helpers = $ler('src/helpers.php');
if (preg_match('/function csp_nonce\(\)\s*:\s*string/', $helpers)) ok('helper csp_nonce() existe pras views'); else falha('falta csp_nonce() em helpers.php');

echo "\n6. Nenhum handler inline (nonce nao cobre atributo de evento)\n";
$achados = [];
foreach (arquivos_html($ROOT) as $f) foreach (handlers_inline($ler($f)) as $h) $achados[] = "$f: $h";
if (!$achados) ok('zero onclick/onsubmit/oninput/onerror em views, install e update'); else falha(count($achados) . ' handlers inline', implode("\n         ", array_slice($achados, 0, 15)));
$confirmSujo = [];
foreach (arquivos_html($ROOT) as $f) if (preg_match_all('/data-confirm="[^"]*(addslashes|\\\\\')/', $ler($f), $m)) $confirmSujo[] = $f;
if (!$confirmSujo) ok('data-confirm sem addslashes (a barra apareceria pro usuario)'); else falha('data-confirm com escape de JS', implode(', ', $confirmSujo));

echo "\n7. Comportamentos delegados no app.js\n";
$app = $ler('public/assets/js/app.js');
if (preg_match("/closest\('\[data-confirm\]'\)/", $app) && str_contains($app, "addEventListener('submit'")) ok('data-confirm: confirma no clique e no envio de form'); else falha('app.js nao trata data-confirm');
if (str_contains($app, 'data-filtro') && str_contains($app, "'slug'") && str_contains($app, "'codigo'") && str_contains($app, "'tag'")) ok('data-filtro: slug, codigo, tag'); else falha('app.js nao trata data-filtro');
if (preg_match("/addEventListener\('error',[^;]*,\s*true\)/s", $app) && str_contains($app, 'naturalWidth')) ok('data-img-falha: listener em captura + varredura de imagem que falhou antes do script'); else falha('app.js nao trata data-img-falha direito', "error nao borbulha (captura) e a imagem pode falhar antes do app.js carregar");
if (str_contains($app, '[data-recarregar]')) ok('data-recarregar'); else falha('app.js nao trata data-recarregar');
foreach (['views/admin/layout.php', 'views/pages/maintenance.php', 'views/layouts/main.php'] as $f) if (str_contains($ler($f), "asset('js/app.js')")) ok("$f carrega o app.js"); else falha("$f nao carrega o app.js");

echo "\n8. PJAX do admin so re-executa script com nonce valido\n";
$lay = $ler('views/admin/layout.php');
if (str_contains($lay, "res.headers.get('Content-Security-Policy')")) ok('le o nonce do cabecalho da resposta buscada'); else falha('PJAX nao le o nonce da resposta');
// Compara pela PROPRIEDADE .nonce: ao entrar num documento com CSP o navegador esvazia o
// atributo nonce (pra CSS/script de terceiro nao lerem), e getAttribute devolveria ''.
if (preg_match('/old\.nonce\s*!==\s*nonceResposta/', $lay)) ok('pula script cujo nonce nao e o da resposta (injetado)'); else falha('PJAX re-executa qualquer script');
if (!preg_match("/getAttribute\('nonce'\)/", $lay)) ok('nao le o nonce pelo atributo (vem vazio depois do innerHTML)'); else falha('PJAX le o nonce por getAttribute', 'depois do innerHTML o atributo vem vazio e todo script seria descartado');
if (preg_match('/s\.nonce\s*=\s*NONCE_PAGINA/', $lay) && str_contains($lay, 'document.currentScript')) ok('script re-executado recebe o nonce da pagina aberta'); else falha('PJAX copia o nonce da outra resposta', 'o navegador bloquearia todo script de tela ao navegar pelo menu');

echo "\n9. Chart.js servido pelo site\n";
$dash = $ler('views/admin/dashboard.php');
if (str_contains($dash, "asset('js/lib/chart.umd.min.js')")) ok('dashboard usa o Chart.js local'); else falha('dashboard ainda busca o Chart.js fora');
$chart = $ROOT . '/public/assets/js/lib/chart.umd.min.js';
if (is_file($chart) && hash_file('sha256', $chart) === '0e2326c6868072bec1592760c6729043caeea2960a2b46cee6a2192aac6abff0') ok('chart.umd.min.js 4.4.0 com o hash conferido'); else falha('chart.umd.min.js ausente ou diferente');
$externos = [];
foreach (arquivos_html($ROOT) as $f) foreach (tags_script($ler($f)) as [$ln, $tag]) if (preg_match('/src="https?:\/\/([^\/"]+)/', $tag, $m) && $m[1] !== 'sdk.mercadopago.com') $externos[] = "$f:$ln {$m[1]}";
if (!$externos) ok('nenhum script externo fora o SDK do Mercado Pago'); else falha('script externo', implode(', ', $externos));

echo "\n10. Antifraude do Mercado Pago recebe o nonce\n";
$chk = $ler('views/pages/checkout_pix.php');
if (preg_match('/new MercadoPago\(PUBKEY,\s*\{[^}]*deviceProfileCspNonce:\s*<\?= json_encode\(csp_nonce\(\)\) \?>/', $chk)) ok('deviceProfileCspNonce passado ao SDK'); else falha('SDK sem deviceProfileCspNonce', 'o script de perfil de dispositivo seria bloqueado');

echo "\n" . ($falhas ? "FALHOU: $falhas\n" : "TUDO OK\n");
exit($falhas ? 1 : 0);
