<?php
// ============================================================
// cli/reset-password.php - RESET de senha do admin SEM depender de email.
// ============================================================
// Recuperacao a prova de tudo (email em shared hosting e instavel). So roda
// por LINHA DE COMANDO (o navegador NAO executa -> nao e backdoor exposta).
//
// Uso (SSH / terminal):
//     php cli/reset-password.php <usuario> <nova_senha>
//
// Sem SSH (Hostinger/cPanel): Painel -> Cron Jobs -> adicione um cron "uma vez"
//     com o comando:  php /home/SEU_USER/public_html/cli/reset-password.php admin NovaSenha123
//     (rode 1x e remova o cron). O caminho exato voce ve no File Manager.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script so roda pela linha de comando (CLI), nao pelo navegador.\n");
}

$root = dirname(__DIR__);
if (!file_exists($root . '/config/config.php')) {
    fwrite(STDERR, "Erro: config/config.php nao encontrado (rode da raiz do projeto).\n");
    exit(1);
}
$config = require $root . '/config/config.php';
require_once $root . '/src/Database.php';
\App\Database::init($config['db']);

$user = $argv[1] ?? '';
$pass = $argv[2] ?? '';

if ($user === '' || strlen($pass) < 8) {
    fwrite(STDERR, "Uso: php cli/reset-password.php <usuario> <nova_senha (min 8 chars)>\n");
    $all = \App\Database::fetchAll("SELECT username FROM admin_users");
    $names = array_map(static fn($r) => $r['username'], $all ?: []);
    fwrite(STDERR, "Usuarios admin existentes: " . (implode(', ', $names) ?: '(nenhum)') . "\n");
    exit(1);
}

$row = \App\Database::fetchOne("SELECT id FROM admin_users WHERE username = ? LIMIT 1", [$user]);
if (!$row) {
    fwrite(STDERR, "Usuario '$user' nao existe. Confira o nome.\n");
    exit(1);
}

// Hash gerado AQUI (PHP) -> sem problema de escaping de '$' como no SQL manual.
\App\Database::query(
    "UPDATE admin_users SET password_hash = ? WHERE id = ?",
    [password_hash($pass, PASSWORD_BCRYPT), (int)$row['id']]
);

echo "OK: senha do admin '$user' redefinida. Faca login em /admin/login com a nova senha.\n";
