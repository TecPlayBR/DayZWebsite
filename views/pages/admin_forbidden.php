<?php
// Página 403 do admin — renderizada quando user logado tenta acessar área sem permissão.
$u = \App\Auth::user();
$role = $u['role'] ?? '?';
$path = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso negado — Admin</title>
    <link rel="stylesheet" href="/assets/css/theme.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        body { background: var(--bg-0); color: var(--bone); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .forbidden-card {
            max-width: 520px;
            background: var(--bg-1);
            border: 1px solid var(--border);
            border-left: 4px solid var(--rust);
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .forbidden-icon { font-size: 3rem; margin-bottom: 1rem; }
        .forbidden-card h1 { font-family: var(--font-display); color: var(--rust); margin: 0 0 0.5rem; font-size: 1.6rem; }
        .forbidden-card .subtitle { color: var(--dim); font-size: 0.95rem; margin-bottom: 1.5rem; }
        .forbidden-meta {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--dim);
            background: var(--bg-0);
            padding: 0.8rem;
            margin: 1.5rem 0;
            border-left: 2px solid var(--border);
            text-align: left;
        }
        .forbidden-meta strong { color: var(--bone); }
        .forbidden-actions { display: flex; gap: 0.6rem; justify-content: center; flex-wrap: wrap; }
        .btn-mini { padding: 0.6rem 1.2rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="forbidden-card">
        <div class="forbidden-icon">🔒</div>
        <h1>Acesso negado</h1>
        <p class="subtitle">Você não tem permissão pra acessar essa área do painel.</p>

        <div class="forbidden-meta">
            <strong>Usuário:</strong> <?= htmlspecialchars($u['username'] ?? '?', ENT_QUOTES) ?><br>
            <strong>Papel:</strong> <?= htmlspecialchars($role, ENT_QUOTES) ?><br>
            <strong>Tentou acessar:</strong> <?= $path ?><br>
            <strong>Quando:</strong> <?= date('d/m/Y H:i:s') ?>
        </div>

        <p style="font-size: 0.85rem; color: var(--dim); margin-bottom: 1.5rem;">
            Tentativa registrada nos logs de auditoria. Se precisa desse acesso, fale com um Super Admin.
        </p>

        <div class="forbidden-actions">
            <a href="/admin" class="btn-mini">← Voltar ao painel</a>
            <a href="/admin/logout" class="btn-mini outline">Sair</a>
        </div>
    </div>
</body>
</html>
