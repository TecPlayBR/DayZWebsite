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
    <form method="POST" action="/admin/caixas/save" enctype="multipart/form-data" style="margin-top:0.8rem;display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <?= \App\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)$box['id'] ?>">
        <label>Nome<input type="text" name="name" value="<?= e($box['name']) ?>" required style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Slug (URL)<input type="text" name="slug" value="<?= e($box['slug']) ?>" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <div style="grid-column:1/3;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.3rem;">Capa da caixa (PNG transparente ~512×512)</label>
            <div style="display:flex;align-items:center;gap:1rem;">
                <?php if (!empty($box['image'])): ?><img src="<?= e($box['image']) ?>" alt="" style="width:64px;height:64px;object-fit:contain;background:var(--bg-0);border:1px solid var(--border);border-radius:4px;padding:3px;"><?php endif; ?>
                <div style="flex:1;">
                    <input type="file" name="image_file" accept="image/png,image/webp,image/jpeg" style="color:var(--bone);font-size:0.82rem;">
                    <input type="text" name="image" value="<?= e($box['image'] ?? '') ?>" placeholder="ou cole uma URL (deixe vazio pra remover)" style="width:100%;margin-top:0.4rem;padding:0.4rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);font-size:0.8rem;">
                </div>
            </div>
        </div>
        <label style="grid-column:1/3;">Descrição<textarea name="description" rows="2" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"><?= e($box['description'] ?? '') ?></textarea></label>
        <label>Custo (moedas)<input type="number" name="cost_coins" value="<?= (int)$box['cost_coins'] ?>" min="0" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Ordem na vitrine <span style="color:var(--dim);font-weight:400;">— menor = aparece primeiro</span><input type="number" name="sort_order" value="<?= (int)($box['sort_order'] ?? 0) ?>" min="0" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"></label>
        <label>Cooldown diária (horas) <span style="color:var(--dim);font-weight:400;">— 0 = sem espera</span><input type="number" name="cooldown_hours" value="<?= (int)$box['cooldown_hours'] ?>" min="0" style="width:100%;padding:0.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);"><small style="color:var(--dim);">Só vale pra caixa diária grátis. Caixa paga nunca tem cooldown.</small></label>
        <label style="display:flex;align-items:center;gap:0.4rem;"><input type="checkbox" name="is_daily" value="1" <?= (int)$box['is_daily']?'checked':'' ?>> Diária grátis (ignora custo)</label>
        <label style="display:flex;align-items:center;gap:0.4rem;"><input type="checkbox" name="enabled" value="1" <?= (int)$box['enabled']?'checked':'' ?>> Ativa</label>
        <div style="grid-column:1/3;"><button type="submit" class="btn">Salvar caixa</button></div>
    </form>
</div>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <div class="label" id="bi-form-title">Adicionar item ao pool</div>
    <form method="POST" action="/admin/caixas/<?= (int)$box['id'] ?>/items/save" enctype="multipart/form-data" class="bi-form" id="bi-itemform" style="margin-top:0.8rem;">
        <?= \App\Csrf::field() ?>
        <input type="hidden" name="item_id" id="bi-item-id" value="">
        <input type="hidden" name="sort_order" id="bi-sort" value="">
        <div style="display:grid;grid-template-columns:0.8fr 1.3fr 1.3fr 1.1fr;gap:0.5rem;">
            <label>Tipo
                <select name="type" id="bi-type">
                    <option value="item">🎁 Item</option>
                    <option value="coins">💰 Moedas</option>
                </select>
            </label>
            <label id="bi-class-l">Classname<input type="text" name="classname" id="bi-classname" placeholder="AKM"></label>
            <label>Nome exibido<input type="text" name="name" id="bi-name" placeholder="Fuzil AKM"></label>
            <label>Imagem (capa)<input type="file" name="image_file" accept="image/png,image/webp,image/jpeg"></label>
        </div>
        <div style="display:grid;grid-template-columns:0.6fr 1.2fr auto;gap:0.5rem;margin-top:0.5rem;align-items:end;">
            <label id="bi-qty-l">Qtd<input type="number" name="quantity" id="bi-qty" value="1" min="1"></label>
            <label>Raridade
                <select name="rarity" id="bi-rarity">
                    <option value="common">Comum</option><option value="uncommon">Incomum</option>
                    <option value="rare">Raro</option><option value="epic">Épico</option><option value="legendary">Lendário</option>
                </select>
            </label>
            <div style="display:flex;align-items:center;gap:0.6rem;">
                <label style="flex-direction:row;align-items:center;gap:0.35rem;"><input type="checkbox" name="enabled" id="bi-enabled" value="1" checked style="width:auto;"> Ativo</label>
                <button type="submit" class="btn" id="bi-submit">+ Add</button>
                <a href="#" id="bi-cancel" style="display:none;color:var(--rust-2);font-size:0.8rem;">cancelar</a>
            </div>
        </div>
        <p style="font-size:0.78rem;color:var(--dim);margin-top:0.5rem;" id="bi-hint">A <strong>raridade</strong> define a chance — lendário cai bem menos que comum, automático. <span id="bi-chance" style="color:var(--hazard);"></span></p>
    </form>
</div>
<style>
.bi-form label { font-size:0.78rem; color:var(--dim); display:flex; flex-direction:column; gap:0.2rem; }
.bi-form input, .bi-form select { width:100%; padding:0.45rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); }
</style>
<script>
(function(){
    var t=document.getElementById('bi-type'), classL=document.getElementById('bi-class-l'), qtyL=document.getElementById('bi-qty-l'), hint=document.getElementById('bi-hint');
    if(!t) return;
    function upd(){
        var coins = t.value==='coins';
        classL.style.opacity = coins ? '0.4' : '1';
        classL.querySelector('input').placeholder = coins ? '(não usado p/ moedas)' : 'AKM';
        qtyL.querySelector('span,input'); qtyL.childNodes[0].nodeValue = coins ? 'Qtd de MOEDAS' : 'Qtd';
        hint.textContent = coins ? 'Moedas: o Classname é ignorado; a Qtd vira a quantidade de moedas creditadas no saldo.' : 'Chance = peso ÷ soma dos pesos. Imagem opcional (PNG transparente fica melhor).';
    }
    t.addEventListener('change',upd); upd();
})();
(function(){
    // Raridade DEFINE a chance (peso derivado server-side; sem campo manual).
    var RARITY_W = { common:100, uncommon:40, rare:15, epic:5, legendary:2 };
    var rar = document.getElementById('bi-rarity'), ch = document.getElementById('bi-chance');
    var TOTAL = <?= (int)$total_weight ?>; // soma dos pesos já no pool
    function chance(){ if(!rar||!ch) return; var ww=RARITY_W[rar.value]||10; ch.textContent = '≈ '+(ww/(TOTAL+ww)*100).toFixed(1).replace('.',',')+'% de chance neste pool.'; }
    if (rar) rar.addEventListener('change', chance);
    chance();
})();
</script>
<script>
/* Editar item do pool: preenche o form com os dados do item + manda item_id (o backend
   faz UPDATE). Imagem fica vazia = mantém a atual. "cancelar" volta pro modo adicionar. */
(function(){
    var form=document.getElementById('bi-itemform'); if(!form) return;
    var g=function(id){return document.getElementById(id);};
    var f={id:g('bi-item-id'),type:g('bi-type'),classname:g('bi-classname'),name:g('bi-name'),
        qty:g('bi-qty'),rarity:g('bi-rarity'),enabled:g('bi-enabled'),sort:g('bi-sort'),
        submit:g('bi-submit'),cancel:g('bi-cancel'),title:g('bi-form-title')};
    function fireType(){ if(f.type){ f.type.dispatchEvent(new Event('change')); } }
    function reset(){
        f.id.value='';f.classname.value='';f.name.value='';f.qty.value='1';f.type.value='item';
        f.rarity.value='common';f.enabled.checked=true;f.sort.value='';
        f.submit.textContent='+ Add';f.cancel.style.display='none';
        f.title.textContent='Adicionar item ao pool';fireType();
    }
    document.querySelectorAll('.bi-edit').forEach(function(b){
        b.addEventListener('click',function(){
            f.id.value=b.dataset.id;f.type.value=b.dataset.type;f.classname.value=b.dataset.classname;
            f.name.value=b.dataset.name;f.qty.value=b.dataset.qty;f.rarity.value=b.dataset.rarity;
            f.enabled.checked=(b.dataset.enabled==='1');f.sort.value=b.dataset.sort;
            f.submit.textContent='💾 Salvar alterações';f.cancel.style.display='';
            f.title.textContent='Editando: '+b.dataset.name+' — imagem: deixe vazio pra manter a atual';
            fireType();form.scrollIntoView({behavior:'smooth',block:'center'});
        });
    });
    if(f.cancel) f.cancel.addEventListener('click',function(e){e.preventDefault();reset();});
})();
</script>

<div class="stat-card">
    <div class="label">Pool de itens (<?= count($items) ?>) — soma dos pesos: <?= (int)$total_weight ?></div>
    <?php if (empty($items)): ?>
        <p style="color:var(--dim);margin-top:0.8rem;">Nenhum item ainda. Adicione acima — a caixa só abre com itens.</p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;margin-top:0.8rem;">
            <thead><tr style="text-align:left;color:var(--dim);border-bottom:1px solid var(--border);">
                <th style="padding:0.5rem 0.4rem;">Item</th><th>Classname</th><th>Qtd</th><th>Chance</th><th>Raridade</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($items as $it): $pct = $total_weight > 0 ? round((int)$it['weight']/$total_weight*100, 2) : 0; ?>
                <tr style="border-bottom:1px solid var(--border);<?= (int)$it['enabled']?'':'opacity:0.5;' ?>">
                    <td style="padding:0.5rem 0.4rem;color:var(--bone);">
                        <?php if (!empty($it['image'])): ?><img src="<?= e($it['image']) ?>" alt="" style="width:28px;height:28px;object-fit:contain;vertical-align:middle;margin-right:6px;"><?php endif; ?>
                        <?= ($it['type'] ?? 'item') === 'coins' ? '💰 ' : '' ?><?= e($it['name']) ?>
                    </td>
                    <td><?php if (($it['type'] ?? 'item') === 'coins'): ?><span style="color:var(--hazard);">moedas</span><?php else: ?><code style="font-size:0.78rem;"><?= e($it['classname']) ?></code><?php endif; ?></td>
                    <td><?= ($it['type'] ?? 'item') === 'coins' ? (int)$it['quantity'] . ' 🪙' : (int)$it['quantity'] ?></td>
                    <td style="color:var(--hazard);font-weight:600;"><?= $pct ?>%</td>
                    <td><?= e($it['rarity']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="bi-edit" title="Editar item"
                            data-id="<?= (int)$it['id'] ?>"
                            data-type="<?= e($it['type'] ?? 'item') ?>"
                            data-classname="<?= e($it['classname']) ?>"
                            data-name="<?= e($it['name']) ?>"
                            data-qty="<?= (int)$it['quantity'] ?>"
                            data-rarity="<?= e($it['rarity']) ?>"
                            data-enabled="<?= (int)$it['enabled'] ?>"
                            data-sort="<?= (int)($it['sort_order'] ?? 0) ?>"
                            style="background:none;border:none;color:var(--rust-2);cursor:pointer;margin-right:8px;font-size:0.95rem;">✎</button>
                        <form method="POST" action="/admin/caixas/<?= (int)$box['id'] ?>/items/<?= (int)$it['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Remover <?= e(addslashes($it['name'])) ?>?');">
                            <?= \App\Csrf::field() ?>
                            <button type="submit" style="background:none;border:none;color:var(--rust-2);cursor:pointer;" title="Remover">✕</button>
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
