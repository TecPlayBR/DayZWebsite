<?php /** @var array $config; @var array $servers */ ?>
<?php $title = 'Servidores'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Servidores DayZ</h1>
        <p>Cadastre 1 ou mais servidores. Quando houver mais de um ativo, o checkout pede pra escolher.</p>
    </div>
    <div style="text-align:right;">
        <a href="/servidores" target="_blank" class="btn-mini outline">↗ Página pública</a>
    </div>
</div>

<?php if (!empty($_GET['err'])): $err = $_GET['err']; ?>
    <div class="admin-alert error">
        <?= match($err) {
            'invalid' => 'Nome ou slug inválido.',
            'slug'    => 'Slug já em uso.',
            'has_purchases' => 'Não dá pra excluir: existem compras vinculadas a esse servidor.',
            default   => 'Erro.',
        } ?>
    </div>
<?php elseif (!empty($_GET['ok'])): ?>
    <div class="admin-alert ok">✓ Alterações salvas com sucesso.</div>
<?php endif; ?>

<details class="admin-card">
    <summary>Adicionar novo servidor</summary>
    <form method="POST" action="/admin/servers/create" class="admin-form" style="margin-top:1rem;">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Csrf::token()) ?>">
        <div class="form-grid">
            <label>Nome <input type="text" name="name" required maxlength="80" placeholder="Ex: Hardcore Wipe Semanal"></label>
            <label>Slug (URL) <input type="text" name="slug" required maxlength="40" placeholder="ex: hardcore" pattern="[a-z0-9-]+"></label>
            <label>Mapa <input type="text" name="map" value="Chernarus" maxlength="40"></label>
            <label>Max Players <input type="number" name="max_players" value="60" min="1" max="999"></label>
            <label>IP <input type="text" name="ip" placeholder="123.45.67.89" maxlength="45"></label>
            <label>Porta <input type="number" name="port" value="2302" min="1" max="65535"></label>
            <label>BattleMetrics ID <input type="text" name="battlemetrics_id" placeholder="Ex: 12345678" maxlength="40"></label>
            <label>Ordem <input type="number" name="sort_order" value="0"></label>
        </div>
        <label>Descrição <textarea name="description" rows="2" maxlength="255" placeholder="Ex: PvP intenso, wipe toda quinta."></textarea></label>
        <button type="submit" class="btn-mini">+ Criar servidor</button>
    </form>
</details>

<?php foreach ($servers as $s): ?>
    <div class="admin-card server-card">
        <form method="POST" action="/admin/servers/update" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Csrf::token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

            <div class="server-card-head">
                <div>
                    <h3>
                        <?= e($s['name']) ?>
                        <?php if (!$s['active']): ?><span class="server-inactive-tag">inativo</span><?php endif; ?>
                    </h3>
                    <code class="server-slug">/<?= e($s['slug']) ?></code>
                </div>
                <label class="checkbox" style="white-space:nowrap;">
                    <input type="checkbox" name="active" value="1" <?= $s['active'] ? 'checked' : '' ?>> Ativo
                </label>
            </div>

            <div class="form-grid">
                <label>Nome <input type="text" name="name" value="<?= e($s['name']) ?>" required maxlength="80"></label>
                <label>Mapa <input type="text" name="map" value="<?= e($s['map']) ?>" maxlength="40"></label>
                <label>Max Players <input type="number" name="max_players" value="<?= (int)$s['max_players'] ?>" min="1" max="999"></label>
                <label>IP <input type="text" name="ip" value="<?= e($s['ip'] ?? '') ?>" maxlength="45"></label>
                <label>Porta <input type="number" name="port" value="<?= (int)$s['port'] ?>" min="1" max="65535"></label>
                <label>BattleMetrics ID <input type="text" name="battlemetrics_id" value="<?= e($s['battlemetrics_id'] ?? '') ?>" maxlength="40"></label>
                <label>Ordem <input type="number" name="sort_order" value="<?= (int)$s['sort_order'] ?>"></label>
            </div>
            <label>Descrição <textarea name="description" rows="2" maxlength="255"><?= e($s['description'] ?? '') ?></textarea></label>

            <div class="server-token">
                <div class="server-token-label">Agent Token</div>
                <code class="server-token-code"><?= e($s['agent_token']) ?></code>
                <button type="button" class="btn-mini outline"
                        onclick="navigator.clipboard.writeText('<?= e($s['agent_token']) ?>'); this.textContent='✓ Copiado'; setTimeout(()=>this.textContent='Copiar', 1500);">
                    Copiar
                </button>
            </div>
            <p class="server-token-hint">
                Esse token vai no <code>tecplay-agent.exe</code> do servidor (variável <code>TECPLAY_AGENT_TOKEN</code>). É como o agente prova que está autorizado.
            </p>

            <div class="admin-form-actions">
                <button type="submit" class="btn-mini">Salvar alterações</button>
                <button type="submit" formaction="/admin/servers/regen-token" class="btn-mini outline"
                        onclick="return confirm('Regenerar o token? O agente vai precisar do novo token pra continuar funcionando.');">
                    Regenerar token
                </button>
                <?php if (count($servers) > 1): ?>
                    <button type="submit" formaction="/admin/servers/delete" class="btn-mini danger"
                            onclick="return confirm('Excluir esse servidor? Só funciona se ainda não houver compras vinculadas.');">
                        Excluir servidor
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
<?php endforeach; ?>

<style>
.server-card-head {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px dashed var(--border);
}
.server-card-head h3 {
    font-family: var(--font-display, sans-serif);
    color: var(--bone);
    font-size: 1.05rem;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex; align-items: center; gap: 0.6rem;
}
.server-inactive-tag {
    background: var(--danger-overlay);
    color: var(--text-danger);
    padding: 0.15rem 0.5rem;
    font-size: 0.65rem;
    font-family: var(--font-mono, monospace);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1px solid var(--danger-border);
}
.server-slug {
    color: var(--moss);
    font-family: var(--font-mono, monospace);
    font-size: 0.78rem;
    display: inline-block;
    margin-top: 0.3rem;
    user-select: all;
}
.server-token {
    background: var(--bg-0);
    padding: 0.85rem 1rem;
    border: 1px solid var(--border);
    border-left: 3px solid var(--hazard);
    margin: 1rem 0 0.3rem;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.8rem;
    align-items: center;
}
.server-token-label {
    color: var(--dim);
    font-family: var(--font-mono, monospace);
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    white-space: nowrap;
}
.server-token-code {
    color: var(--moss);
    font-family: var(--font-mono, monospace);
    font-size: 0.72rem;
    user-select: all;
    word-break: break-all;
    line-height: 1.4;
}
.server-token-hint {
    font-size: 0.78rem;
    color: var(--dim);
    margin: 0 0 1.25rem;
    line-height: 1.5;
}
.server-token-hint code {
    background: var(--bg-0);
    padding: 0.1rem 0.4rem;
    color: var(--hazard);
    font-family: var(--font-mono, monospace);
    font-size: 0.78rem;
}
@media (max-width: 640px) {
    .server-card-head { flex-direction: column; }
    .server-token { grid-template-columns: 1fr; }
    .server-token-label { font-size: 0.7rem; }
}
</style>

<?php \App\View::endSection(); ?>
