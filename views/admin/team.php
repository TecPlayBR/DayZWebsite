<?php /** @var array $config, $admins */ ?>
<?php $title = 'Equipe'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Equipe</h1>
        <p>Administradores do painel. Cada um tem um <strong>papel</strong> que define o que pode acessar.</p>
    </div>
</div>

<?php
$err = $_GET['err'] ?? null;
$ok  = $_GET['ok']  ?? null;
$errMsg = match($err) {
    'username'    => 'Usuário inválido (mín. 3 chars, só letras/números/_.-)',
    'password'    => 'Senha precisa de pelo menos 8 caracteres',
    'duplicate'   => 'Já existe usuário com esse nome',
    'self'        => 'Você não pode deletar sua própria conta. Peça pra outro admin fazer.',
    'last'        => 'Não dá pra deletar o último admin — você ficaria sem acesso.',
    'invalid_role'=> 'Papel inválido.',
    'self_demote' => 'Você não pode rebaixar seu próprio papel. Peça pra outro super admin fazer.',
    'last_super'  => 'Não dá pra rebaixar — você é o último super admin.',
    default => null,
};
$okMsg = match($ok) {
    'created'  => 'Admin criado.',
    'deleted'  => 'Admin removido.',
    'password' => 'Senha atualizada.',
    'role'     => 'Papel atualizado.',
    default => null,
};
$roles = \App\Auth::availableRoles();
?>
<?php if ($errMsg): ?>
    <div style="background:var(--danger-overlay);border-left:3px solid var(--rust-2);padding:0.8rem 1rem;margin-bottom:1.5rem;color:var(--text-danger);">
        <?= e($errMsg) ?>
    </div>
<?php elseif ($okMsg): ?>
    <div class="alert-toast"><?= e($okMsg) ?></div>
<?php endif; ?>

<!-- Form de criar novo admin -->
<form method="POST" action="/admin/team/create" class="stat-card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <?= \App\Csrf::field() ?>
    <div class="label" style="margin-bottom: 1rem;">+ Adicionar novo admin</div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 0.8rem; align-items: end;">
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
            <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Senha <small>(min 8)</small></label>
            <input type="password" name="password" required minlength="8"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
        </div>
        <div>
            <label style="display:block; font-size:0.8rem; color:var(--dim); margin-bottom:0.3rem;">Papel</label>
            <select name="role" style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
                <?php foreach ($roles as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $key === 'support' ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-mini" style="padding: 0.6rem 1.2rem;">Criar</button>
    </div>
</form>

<!-- Lista de admins -->
<table class="admin-table">
    <thead>
        <tr>
            <th>Usuário</th>
            <th>Papel</th>
            <th>E-mail</th>
            <th>Criado</th>
            <th>Último login</th>
            <th style="width: 320px;">Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php $me = \App\Auth::user(); foreach ($admins as $a):
            $userRole = $a['role'] ?? 'super_admin';
            $roleLabel = $roles[$userRole] ?? $userRole;
            $isSelf = (int)$a['id'] === (int)($me['id'] ?? 0);
        ?>
            <tr>
                <td>
                    <strong><?= e($a['username']) ?></strong>
                    <?php if ($isSelf): ?>
                        <span class="badge info" style="margin-left: 0.4rem;">você</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= $userRole === 'super_admin' ? 'danger' : 'info' ?>"
                          title="<?= e($roleLabel) ?>"><?= e($userRole) ?></span>
                </td>
                <td class="dim"><?= e($a['email'] ?? '—') ?></td>
                <td class="dim"><?= e($a['created_at']) ?></td>
                <td class="dim"><?= e($a['last_login_at'] ?? 'nunca') ?></td>
                <td style="white-space: nowrap;">
                    <!-- Editar role -->
                    <details style="display: inline-block; vertical-align: middle; margin-right: 0.3rem;">
                        <summary class="btn-mini outline" style="display: inline-block; cursor: pointer; padding: 0.3rem 0.7rem;">Papel</summary>
                        <form method="POST" action="/admin/team/<?= (int)$a['id'] ?>/role" style="display: inline-flex; gap: 0.4rem; margin-left: 0.5rem; align-items: center;">
                            <?= \App\Csrf::field() ?>
                            <select name="role" style="padding: 0.3rem 0.5rem; background: var(--bg-0); border: 1px solid var(--border); color: var(--bone); font-size: 0.8rem;">
                                <?php foreach ($roles as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $key === $userRole ? 'selected' : '' ?>><?= e($key) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-mini" style="padding: 0.3rem 0.7rem; font-size: 0.75rem;">OK</button>
                        </form>
                    </details>
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
                            <button type="submit" class="btn-mini outline" style="border-color: var(--danger-border); color: var(--text-danger);">✕ Remover</button>
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
        <li>Atribua o <strong>menor papel necessário</strong>. Não dê super_admin pra todo mundo.</li>
        <li>Todas as ações são registradas em <a href="/admin/audit" style="color: var(--hazard);">Audit Log</a> com usuário + horário.</li>
    </ul>
</div>

<div class="stat-card" style="margin-top: 1rem; padding: 1.2rem;">
    <div class="label">Matriz de permissões</div>
    <table class="admin-table" data-nofilter style="margin-top: 1rem;">
        <thead>
            <tr>
                <th>Papel</th>
                <th>Acessa</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>super_admin</strong></td>
                <td class="dim">Tudo — gerencia equipe, settings, integrações e dados sensíveis</td>
            </tr>
            <tr>
                <td><strong>finance</strong></td>
                <td class="dim">Dashboard, Pacotes, Combos, Compras, Cupons (tudo financeiro)</td>
            </tr>
            <tr>
                <td><strong>support</strong></td>
                <td class="dim">Só Jogadores e Avaliações — atende player, ajusta moedas. <strong style="color: var(--text-danger);">Não vê valor financeiro</strong></td>
            </tr>
            <tr>
                <td><strong>editor</strong></td>
                <td class="dim">Páginas, Galeria, Anúncios, Visual (conteúdo do site)</td>
            </tr>
        </tbody>
    </table>
</div>

<?php \App\View::endSection(); ?>
