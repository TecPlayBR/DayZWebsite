<?php
/** @var array $config, $items, $attachments; @var ?array $edit */
$e = $edit ?: [];
$val = fn($k, $d = '') => e((string)($e[$k] ?? $d));
$inp = 'width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);';
?>
<?php $title = 'Loja de Pontos'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>⭐ Loja de Pontos</h1>
        <p>Itens que o jogador compra com <strong>pontos</strong> (ganhos abrindo caixa) e que dropam in-game via CFTools — igual às caixas. Item pode ter <strong>anexos/kit</strong> (arma + mira + carregador…). ⚠️ Os <strong>classnames</strong> têm que existir nos types do servidor.</p>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert-toast">Salvo!</div><?php endif; ?>
<?php if (($_GET['err'] ?? '') !== ''): ?><div class="alert-toast" style="background:var(--danger-overlay);color:var(--text-danger);"><?= e(match($_GET['err']){'req'=>'Nome e classname são obrigatórios.','csrf'=>'Sessão expirada.',default=>'Erro.'}) ?></div><?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <div class="label"><?= $edit ? 'Editar item' : 'Novo item' ?></div>
    <form method="POST" action="/admin/point-shop/save" enctype="multipart/form-data" style="margin-top:0.8rem;display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <?= \App\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
        <label>Nome<input type="text" name="name" value="<?= $val('name') ?>" required placeholder="M4A1 Código Vermelho" style="<?= $inp ?>"></label>
        <label>Categoria<input type="text" name="category" value="<?= $val('category','Geral') ?>" placeholder="ARMAS / ROUPAS / FERRAMENTAS…" style="<?= $inp ?>"></label>
        <label>Classname (principal)<input type="text" name="classname" value="<?= $val('classname') ?>" required placeholder="BM_Don_M4A1" style="<?= $inp ?>"></label>
        <label>Quantidade<input type="number" name="quantity" value="<?= (int)($e['quantity'] ?? 1) ?>" min="1" style="<?= $inp ?>"></label>
        <label>Custo (pontos)<input type="number" name="point_cost" value="<?= (int)($e['point_cost'] ?? 0) ?>" min="0" style="<?= $inp ?>"></label>
        <label>Imagem <small style="color:var(--dim);">(opcional)</small><input type="file" name="image_file" accept="image/png,image/webp,image/jpeg" style="<?= $inp ?>"></label>
        <label style="grid-column:1/3;">Descrição<textarea name="description" rows="2" style="<?= $inp ?>"><?= $val('description') ?></textarea></label>
        <label style="display:flex;align-items:center;gap:0.4rem;"><input type="checkbox" name="vip_only" value="1" <?= !empty($e['vip_only'])?'checked':'' ?>> 🔒 Só pra VIP</label>
        <label style="display:flex;align-items:center;gap:0.4rem;"><input type="checkbox" name="enabled" value="1" <?= (!isset($e['enabled'])||$e['enabled'])?'checked':'' ?>> Visível</label>
        <label>Ordem<input type="number" name="sort_order" value="<?= (int)($e['sort_order'] ?? 0) ?>" style="<?= $inp ?>"></label>
        <div style="grid-column:1/3;display:flex;gap:0.6rem;">
            <button type="submit" class="btn"><?= $edit ? 'Salvar' : 'Criar item' ?></button>
            <?php if ($edit): ?><a href="/admin/point-shop" class="btn btn-outline">+ Novo</a><?php endif; ?>
        </div>
    </form>
</div>

<?php if ($edit): ?>
    <div class="stat-card" style="margin-bottom:1.5rem;">
        <div class="label">Anexos / Kit deste item <small style="color:var(--dim);">(spawnam junto; caem no chão se não anexar)</small></div>
        <?php if (!empty($attachments)): ?>
            <table class="admin-table" data-nofilter style="margin:.6rem 0;">
                <thead><tr><th>Classname</th><th>Qtd</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($attachments as $a): ?>
                    <tr>
                        <td class="mono"><?= e($a['classname']) ?></td>
                        <td><?= (int)$a['quantity'] ?></td>
                        <td style="text-align:right;">
                            <form method="POST" action="/admin/point-shop/attachments/<?= (int)$a['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Remover anexo?');">
                                <?= \App\Csrf::field() ?><button type="submit" style="background:none;border:none;color:var(--rust-2);cursor:pointer;">✕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?><p style="color:var(--dim);font-size:.85rem;">Nenhum anexo. É opcional.</p><?php endif; ?>
        <form method="POST" action="/admin/point-shop/<?= (int)$e['id'] ?>/attachments/add" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-top:.5rem;">
            <?= \App\Csrf::field() ?>
            <input type="text" name="classname" placeholder="classname do anexo (ex: CS_Mag_STANAG_30Rnd)" required style="<?= $inp ?>flex:1;min-width:240px;">
            <input type="number" name="quantity" value="1" min="1" title="quantidade" style="width:80px;padding:.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
            <button type="submit" class="btn-sm btn">+ Anexo</button>
        </form>
    </div>
<?php endif; ?>

<?php if (!empty($items)): ?>
    <table class="admin-table">
        <thead><tr><th>Item</th><th>Categoria</th><th>Custo</th><th>VIP</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr<?= (int)$it['enabled']?'':' style="opacity:0.5;"' ?>>
                <td><strong style="color:var(--bone);"><?= e($it['name']) ?></strong> <code style="color:var(--dim);font-size:.72rem;"><?= e($it['classname']) ?></code></td>
                <td><?= e($it['category']) ?></td>
                <td class="mono">⭐ <?= (int)$it['point_cost'] ?></td>
                <td><?= (int)$it['vip_only'] ? '🔒' : '—' ?></td>
                <td><?= (int)$it['enabled'] ? '<span style="color:var(--moss)">● on</span>' : '<span style="color:var(--dim)">○ off</span>' ?></td>
                <td style="text-align:right;white-space:nowrap;">
                    <a href="/admin/point-shop/<?= (int)$it['id'] ?>" class="btn btn-sm">Editar</a>
                    <form method="POST" action="/admin/point-shop/<?= (int)$it['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Excluir item e seus anexos?');">
                        <?= \App\Csrf::field() ?><button type="submit" style="background:none;border:none;color:var(--rust-2);cursor:pointer;">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p style="color:var(--dim);">Nenhum item na loja de pontos ainda. Crie o primeiro acima.</p>
<?php endif; ?>

<?php \App\View::endSection(); ?>
