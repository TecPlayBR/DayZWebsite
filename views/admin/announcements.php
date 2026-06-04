<?php /** @var array $config, $announcements */ ?>
<?php $title = 'Anúncios'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Anúncios</h1>
        <p>Banners no topo da home. Use pra anunciar wipe, evento, promoção, manutenção.</p>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert-toast">Salvo.</div><?php endif; ?>

<!-- Form de novo/edit (inline) -->
<form method="POST" action="/admin/announcements/save" class="stat-card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <?= \App\Csrf::field() ?>
    <input type="hidden" name="id" value="">
    <div class="label" style="margin-bottom: 1rem;">Novo anúncio</div>

    <div style="display: grid; gap: 0.8rem;">
        <input type="text" name="title" placeholder="Título (ex: Wipe Sexta 22h)" required
               style="padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
        <textarea name="body" rows="2" placeholder="Texto opcional"
                  style="padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit; resize:vertical;"></textarea>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.8rem;">
            <select name="kind" style="padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                <option value="info">◆ Info (azul)</option>
                <option value="success">✓ Sucesso (verde)</option>
                <option value="warning">⚠ Atenção (amarelo)</option>
                <option value="danger">⚡ Urgente (vermelho)</option>
            </select>
            <input type="datetime-local" name="starts_at" placeholder="Início"
                   style="padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
            <input type="datetime-local" name="ends_at" placeholder="Fim"
                   style="padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 0.8rem;">
            <input type="text" name="cta_label" placeholder="Texto botão (opcional)"
                   style="padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
            <input type="text" name="cta_url" placeholder="URL do botão (ex: /shop, https://discord.gg/...)"
                   style="padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
        </div>

        <label style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--bone); font-size: 0.9rem;">
            <input type="checkbox" name="published" checked style="width:18px; height:18px;">
            Publicar agora
        </label>

        <button type="submit" class="btn-mini" style="padding: 0.6rem 1.5rem; align-self: flex-start;">+ Criar anúncio</button>
    </div>
</form>

<!-- Lista existentes -->
<table class="admin-table">
    <thead>
        <tr>
            <th>Título</th>
            <th>Tipo</th>
            <th>Janela</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($announcements)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--dim);padding:2rem;">Nenhum anúncio ainda.</td></tr>
        <?php else: foreach ($announcements as $a): ?>
            <tr>
                <td>
                    <strong><?= e($a['title']) ?></strong>
                    <?php if (!empty($a['body'])): ?>
                        <div class="dim" style="font-size: 0.75rem; margin-top: 0.2rem;"><?= e(mb_strimwidth($a['body'], 0, 100, '...')) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php $cls = match($a['kind']) { 'success'=>'success','warning'=>'warning','danger'=>'danger', default=>'info' }; ?>
                    <span class="badge <?= $cls ?>"><?= e($a['kind']) ?></span>
                </td>
                <td class="dim" style="font-size:0.8rem;">
                    <?= $a['starts_at'] ? 'De ' . e($a['starts_at']) : 'Sempre' ?><br>
                    <?= $a['ends_at']   ? 'Até ' . e($a['ends_at']) : '' ?>
                </td>
                <td>
                    <?php if ((int)$a['published']): ?>
                        <span class="badge success">publicado</span>
                    <?php else: ?>
                        <span class="badge info">rascunho</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" action="/admin/announcements/<?= (int)$a['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Apagar este anúncio?');">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline" style="border-color: var(--danger-border); color: var(--text-danger);">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<p style="margin-top: 1.5rem; color: var(--dim); font-size: 0.85rem;">
    Edit completo via SQL ou interface dedicada chegará em update futuro. Por enquanto, pra editar — apaga e cria de novo.
</p>

<?php \App\View::endSection(); ?>
