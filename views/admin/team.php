<?php /** @var array $config, $admins */ ?>
<?php $title = 'Equipe'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Equipe</h1>
        <p>Administradores do painel. Todos têm acesso igual — não há papéis (roles).</p>
    </div>
</div>

<?php
$err = $_GET['err'] ?? null;
$ok  = $_GET['ok']  ?? null;
$errMsg = match($err) {
    'username'  => 'Usuário inválido (mín. 3 chars, só letras/números/_.-)',
    'password'  => 'Senha precisa de pelo menos 8 caracteres',
    'duplicate' => 'Já existe usuário com esse nome',
    'self'      => 'Você não pode deletar sua própria conta. Peça pra outro admin fazer.',
    'last'      => 'Não dá pra deletar o último admin — você ficaria sem acesso.',
    default => null,
};
$okMsg = match($ok) {
    'created'  => 'Admin criado.',
    'deleted'  => 'Admin removido.',
    'password' => 'Senha atualizada.',
    default => null,
};
?>
<?php if ($errMsg): ?>
    <div style="background:rgba(231,76,60,0.12);border-left:3px solid var(--rust-2);padding:0.8rem 1rem;margin-bottom:1.5rem;color:#fca5a5;">
        <?= e($errMsg) ?>
    </div>
<?php elseif ($okMsg): ?>
    <div class="alert-toast"><?= e($okMsg) ?></div>
<?php endif; ?>

<!-- Form de criar novo admin -->
<form method="POST" action="/admin/team/create" class="stat-card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <?= \App\Csrf::field() ?>
    <div class="label" style="margin-bottom: 1rem;">+ Adicionar novo admin</div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 0.8rem; align-items: end;">
        <div>
            <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Usuário</label>
            <input type="text" name="username" required minlength="3" pattern="[a-zA-Z0-9_.-]+"
                   placeholder="ex: joao"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
        </div>
        <div>
            <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">E-mail <small>(opcional)</small></label>
            <input type="email" name="email"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
        </div>
        <div>
            <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Senha <small>(min 8 chars)</small></label>
            <input type="password" name="password" required minlength="8"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
        </div>
        <button type="submit" class="btn-mini" style="padding: 0.6rem 1.2rem;">Criar</button>
    </div>
</form>

<!-- Lista de admins -->
<table class="admin-table">
    <thead>
        <tr>
            <th>Usuário</th>
            <th>E-mail</th>
            <th>Criado</th>
            <th>Último login</th>
            <th style="width: 250px;">Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php $me = \App\Auth::user(); foreach ($admins as $a): ?>
            <tr>
                <td>
                    <strong><?= e($a['username']) ?></strong>
                    <?php if ((int)$a['id'] === (int)($me['id'] ?? 0)): ?>
                        <span class="badge info" style="margin-left: 0.4rem;">você</span>
                    <?php endif; ?>
                </td>
                <td class="dim"><?= e($a['email'] ?? '—') ?></td>
                <td class="dim"><?= e($a['created_at']) ?></td>
                <td class="dim"><?= e($a['last_login_at'] ?? 'nunca') ?></td>
                <td style="white-space: nowrap;">
                    <!-- Trocar senha -->
                    <details style="display: inline-block; vertical-align: middle;">
                        <summary class="btn-mini outline" style="display: inline-block; cursor: pointer; padding: 0.3rem 0.7rem;">Mudar senha</summary>
                        <form method="POST" action="/admin/team/<?= (int)$a['id'] ?>/password" style="display: inline-flex; gap: 0.4rem; margin-left: 0.5rem; align-items: center;">
                            <?= \App\Csrf::field() ?>
                            <input type="password" name="password" required minlength="8" placeholder="nova senha (8+)"
                                   style="padding: 0.3rem 0.5rem; background: var(--bg-0); border: 1px solid var(--border); color: var(--bone); font-size: 0.8rem; width: 160px;">
                            <button type="submit" class="btn-mini" style="padding: 0.3rem 0.7rem; font-size: 0.75rem;">OK</button>
                        </form>
                    </details>

                    <?php if ((int)$a['id'] !== (int)($me['id'] ?? 0)): ?>
                        <form method="POST" action="/admin/team/<?= (int)$a['id'] ?>/delete" style="display: inline;"
                              onsubmit="return confirm('Remover admin <?= e($a['username']) ?> permanentemente?');">
                            <?= \App\Csrf::field() ?>
                            <button type="submit" class="btn-mini outline" style="border-color: rgba(231,76,60,0.4); color: #fca5a5;">✕ Remover</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="stat-card" style="margin-top: 2rem; padding: 1.2rem; border-left-color: var(--hazard);">
    <div class="label" style="color: var(--hazard);">⚠ Boas práticas</div>
    <ul style="margin: 0.8rem 0 0 1.2rem; color: var(--bone); font-size: 0.9rem; line-height: 1.7;">
        <li>Cada membro da staff deve ter <strong>seu próprio usuário</strong>. Não compartilhe senha.</li>
        <li>Use senhas com <strong>12+ caracteres</strong>, misturando letras/números/símbolos.</li>
        <li>Quando alguém sair da staff, <strong>remova imediatamente</strong>.</li>
        <li>Não há sistema de auditoria — qualquer admin pode fazer qualquer ação. Confie só em pessoas conhecidas.</li>
    </ul>
</div>

<?php \App\View::endSection(); ?>
