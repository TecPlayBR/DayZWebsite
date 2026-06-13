<?php
/** @var array $config, $box, $items; @var int $total_weight */
?>
<?php $title = 'Editar caixa'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🎁 <?= e($box['name']) ?></h1>
        <p><a href="/admin/caixas" style="color:var(--dim);">← Todas as caixas</a></p>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert-toast">Salvo!</div><?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <div class="label">Configuração da caixa</div>
    <form method="POST" action="/admin/caixas/save" style="margin-top:0.8rem;display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <?= \App\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)$box['id'] ?>">
        <label>Nome<input type="text" name="name" value="<?= e($box['name']) ?>" required style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Slug (URL)<input type="text" name="slug" value="<?= e($box['slug']) ?>" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="grid-column:1/3;">Imagem (URL)<input type="text" name="image" value="<?= e($box['image'] ?? '') ?>" placeholder="https://.../caixa.png" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="grid-column:1/3;">Descrição<textarea name="description" rows="2" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"><?= e($box['description'] ?? '') ?></textarea></label>
        <label>Custo (moedas)<input type="number" name="cost_coins" value="<?= (int)$box['cost_coins'] ?>" min="0" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Cooldown diária (horas)<input type="number" name="cooldown_hours" value="<?= (int)$box['cooldown_hours'] ?>" min="1" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="display:flex;align-items:center;gap:0.4rem;"><input type="checkbox" name="is_daily" value="1" <?= (int)$box['is_daily']?'checked':'' ?>> Diária grátis (ignora custo)</label>
        <label style="display:flex;align-items:center;gap:0.4rem;"><input type="checkbox" name="enabled" value="1" <?= (int)$box['enabled']?'checked':'' ?>> Ativa</label>
        <div style="grid-column:1/3;"><button type="submit" class="btn">Salvar caixa</button></div>
    </form>
</div>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <div class="label">Adicionar item ao pool</div>
    <form method="POST" action="/admin/caixas/<?= (int)$box['id'] ?>/items/save" style="margin-top:0.8rem;display:grid;grid-template-columns:1.5fr 1.5fr 0.7fr 0.7fr 1fr auto;gap:0.5rem;align-items:end;">
        <?= \App\Csrf::field() ?>
        <label style="font-size:0.78rem;color:var(--dim);">Classname<input type="text" name="classname" required placeholder="AKM" style="width:100%;padding:0.45rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="font-size:0.78rem;color:var(--dim);">Nome exibido<input type="text" name="name" placeholder="Fuzil AKM" style="width:100%;padding:0.45rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="font-size:0.78rem;color:var(--dim);">Qtd<input type="number" name="quantity" value="1" min="1" style="width:100%;padding:0.45rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="font-size:0.78rem;color:var(--dim);">Peso<input type="number" name="weight" value="10" min="1" style="width:100%;padding:0.45rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label style="font-size:0.78rem;color:var(--dim);">Raridade
            <select name="rarity" style="width:100%;padding:0.45rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
                <option value="common">Comum</option><option value="uncommon">Incomum</option>
                <option value="rare">Raro</option><option value="epic">Épico</option><option value="legendary">Lendário</option>
            </select>
        </label>
        <input type="hidden" name="enabled" value="1">
        <button type="submit" class="btn">+ Add</button>
    </form>
    <p style="font-size:0.78rem;color:var(--dim);margin-top:0.5rem;">Imagem do item é opcional — adicione editando depois. Chance = peso ÷ soma dos pesos.</p>
</div>

<div class="stat-card">
    <div class="label">Pool de itens (<?= count($items) ?>) — soma dos pesos: <?= (int)$total_weight ?></div>
    <?php if (empty($items)): ?>
        <p style="color:var(--dim);margin-top:0.8rem;">Nenhum item ainda. Adicione acima — a caixa só abre com itens.</p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;margin-top:0.8rem;">
            <thead><tr style="text-align:left;color:var(--dim);border-bottom:1px solid var(--border);">
                <th style="padding:0.5rem 0.4rem;">Item</th><th>Classname</th><th>Qtd</th><th>Peso</th><th>Chance</th><th>Raridade</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($items as $it): $pct = $total_weight > 0 ? round((int)$it['weight']/$total_weight*100, 2) : 0; ?>
                <tr style="border-bottom:1px solid var(--border);<?= (int)$it['enabled']?'':'opacity:0.5;' ?>">
                    <td style="padding:0.5rem 0.4rem;color:var(--bone);"><?= e($it['name']) ?></td>
                    <td><code style="font-size:0.78rem;"><?= e($it['classname']) ?></code></td>
                    <td><?= (int)$it['quantity'] ?></td>
                    <td><?= (int)$it['weight'] ?></td>
                    <td style="color:var(--hazard);font-weight:600;"><?= $pct ?>%</td>
                    <td><?= e($it['rarity']) ?></td>
                    <td style="text-align:right;">
                        <form method="POST" action="/admin/caixas/<?= (int)$box['id'] ?>/items/<?= (int)$it['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Remover <?= e(addslashes($it['name'])) ?>?');">
                            <?= \App\Csrf::field() ?>
                            <button type="submit" style="background:none;border:none;color:var(--rust-2);cursor:pointer;">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<form method="POST" action="/admin/caixas/<?= (int)$box['id'] ?>/delete" style="margin-top:1.5rem;" onsubmit="return confirm('Excluir a caixa inteira e todos os itens?');">
    <?= \App\Csrf::field() ?>
    <button type="submit" style="background:var(--rust);color:#fff;border:none;padding:0.5rem 1rem;border-radius:4px;cursor:pointer;">🗑 Excluir caixa</button>
</form>

<?php \App\View::endSection(); ?>
