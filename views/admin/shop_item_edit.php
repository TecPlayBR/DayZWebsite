<?php /** @var array $config; @var ?array $item */ ?>
<?php
    $isNew = empty($item['id']);
    $deliverArr = [];
    if (!empty($item['deliver_json'])) {
        $decoded = json_decode($item['deliver_json'], true);
        if (is_array($decoded)) $deliverArr = $decoded;
    }
    $title = $isNew ? 'Novo item da loja' : 'Editar item';
?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1><?= $isNew ? 'Novo item da loja' : 'Editar: ' . e($item['name']) ?></h1>
        <p>O bot lista no <code>/loja</code>, debita a moeda e o servidor entrega o(s) classname(s) in-game.</p>
    </div>
    <a href="/admin/shop" class="btn-mini outline">← Voltar</a>
</div>

<div style="background:rgba(102,192,244,0.08); border-left:3px solid #66c0f4; padding:0.8rem 1.1rem; margin-bottom:1.5rem; font-size:0.88rem; color:var(--bone);">
    🤖 <strong>Esta é a loja do Discord (Bot Pro).</strong> Os itens aqui aparecem no comando <code>/loja</code> do <strong>Bot Tecplay</strong>: o site guarda o catálogo + o saldo, e o Bot mostra/processa a compra e manda o servidor entregar. <strong>Precisa do Bot Pro rodando e integrado</strong> (Admin → 🤖 Integração Discord). Pra dropar item direto pelo site sem o bot, use as <a href="/admin/caixas" style="color:#66c0f4;">🎁 Caixas</a>.
</div>

<?php if (!empty($_GET['err'])): ?>
    <div style="background:var(--danger-overlay);border-left:3px solid var(--rust-2);padding:0.7rem 1rem;margin-bottom:1.5rem;color:var(--text-danger);font-size:0.9rem;">
        <?php $errMsgs = [
            'invalid'      => 'Verifique: SKU e nome obrigatórios, custo ≥ 0.',
            'sku_taken'    => 'Esse SKU já existe - escolha outro.',
            'bad_deliver'  => 'Entrega (deliver) inválida: precisa ser uma lista JSON com pelo menos um objeto contendo "classname".',
        ]; echo e($errMsgs[$_GET['err']] ?? 'Erro ao salvar.'); ?>
    </div>
<?php endif; ?>

<form method="POST" action="/admin/shop/save" style="max-width: 800px;">
    <?= \App\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)($item['id'] ?? 0) ?>">

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Informações básicas</div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 80px; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Nome do item</label>
                <input type="text" name="name" required value="<?= e($item['name'] ?? '') ?>" placeholder="ex: Kit VIP 7 dias" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Ícone</label>
                <input type="text" name="icon" value="<?= e($item['icon'] ?? '💎') ?>" maxlength="8" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); text-align:center; font-size:1.2rem;">
            </div>
        </div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 120px 100px; gap: 1rem; align-items:end;">
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">SKU (id único, sem espaços)</label>
                <input type="text" name="sku" required value="<?= e($item['sku'] ?? '') ?>" placeholder="vip_kit_7d" pattern="[A-Za-z0-9_\-]+" <?= $isNew ? '' : 'readonly style="opacity:0.6;"' ?> style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
                <?php if (!$isNew): ?><div class="dim" style="font-size:0.72rem; margin-top:0.25rem;">SKU não é editável após criado (o bot já referencia).</div><?php endif; ?>
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Custo (moedas)</label>
                <input type="number" name="coins_cost" min="0" required value="<?= (int)($item['coins_cost'] ?? 0) ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Ordem</label>
                <input type="number" name="sort_order" value="<?= (int)($item['sort_order'] ?? 0) ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
        </div>
        <label style="display:inline-flex; align-items:center; gap:0.4rem; margin-top:1rem; font-size:0.85rem; color:var(--bone); cursor:pointer;">
            <input type="checkbox" name="enabled" <?= (!isset($item['enabled']) || (int)$item['enabled']) ? 'checked' : '' ?> style="width:18px; height:18px;">
            Habilitado (aparece no <code>/loja</code>)
        </label>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Entrega in-game (o que o servidor dropa)</div>
        <p style="margin-top:0.5rem; font-size:0.82rem; color:var(--dim);">
            Monte os itens abaixo - <strong>o JSON é gerado sozinho</strong>, você não escreve código.
            Um kit pode entregar vários itens. <strong>Anexos</strong> = peças da arma (mira, pente…).
            <strong>Cargo</strong> = itens DENTRO (só roupa/mochila - <em>arma com cargo dá erro no jogo</em>).
        </p>
        <div id="deliver-rows" style="margin-top:0.8rem; display:flex; flex-direction:column; gap:0.6rem;"></div>
        <button type="button" id="add-deliver" class="btn-mini outline" style="margin-top:0.7rem;">+ Adicionar item</button>
        <input type="hidden" name="deliver" id="deliver-json">
    </div>

    <div style="display: flex; gap: 0.6rem;">
        <button type="submit" class="btn-mini" style="padding: 0.7rem 1.6rem;">Salvar</button>
        <a href="/admin/shop" class="btn-mini outline" style="padding:0.7rem 1.6rem; text-decoration:none; display:inline-flex; align-items:center;">Cancelar</a>
        <?php if (!$isNew): ?>
            <form method="POST" action="/admin/shop/<?= (int)$item['id'] ?>/delete" style="margin-left:auto;" onsubmit="return confirm('Excluir este item da loja? O bot deixa de listá-lo.');">
                <?= \App\Csrf::field() ?>
                <button type="submit" class="btn-mini outline" style="padding:0.7rem 1.2rem; color:var(--text-danger); border-color:var(--rust-2);">Excluir</button>
            </form>
        <?php endif; ?>
    </div>
</form>

<style>
.deliver-row { display:grid; grid-template-columns: 1.4fr 70px 1.4fr 1.2fr 70px 34px; gap:0.5rem; align-items:center; }
.deliver-row input { padding:0.5rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono); font-size:0.8rem; width:100%; }
.deliver-row .d-rm { background:none; border:1px solid var(--border); color:var(--rust-2); cursor:pointer; padding:0.45rem; border-radius:4px; }
.deliver-head { display:grid; grid-template-columns: 1.4fr 70px 1.4fr 1.2fr 70px 34px; gap:0.5rem; font-size:0.7rem; color:var(--dim); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.2rem; }
@media (max-width:720px){ .deliver-row, .deliver-head { grid-template-columns: 1fr 1fr; } .deliver-head { display:none; } }
</style>
<script>
(function(){
    const rows = document.getElementById('deliver-rows');
    const form = rows.closest('form');
    const existing = <?= json_encode(array_values($deliverArr), JSON_UNESCAPED_UNICODE) ?>;

    // cabeçalho das colunas
    const head = document.createElement('div'); head.className='deliver-head';
    ['Classname','Qtd','Anexos (vírgula)','Cargo (avançado)','Vida','']
        .forEach(t=>{ const s=document.createElement('span'); s.textContent=t; head.appendChild(s); });
    rows.parentNode.insertBefore(head, rows);

    function mkInput(cls, ph, val, type){
        const i=document.createElement('input'); i.className=cls; i.placeholder=ph||'';
        if(type) i.type=type; if(val!==undefined&&val!==null) i.value=val; return i;
    }
    function addRow(d){
        d=d||{};
        const row=document.createElement('div'); row.className='deliver-row';
        row.appendChild(mkInput('d-class','ex: M4A1', d.classname||''));
        row.appendChild(mkInput('d-qty','1', d.quantity!=null?d.quantity:1, 'number'));
        row.appendChild(mkInput('d-att','mira, pente', Array.isArray(d.attachments)?d.attachments.join(', '):''));
        row.appendChild(mkInput('d-cargo','', Array.isArray(d.cargo)?d.cargo.join(', '):''));
        row.appendChild(mkInput('d-health','1', d.health!=null?d.health:1, 'number'));
        const rm=document.createElement('button'); rm.type='button'; rm.className='d-rm'; rm.textContent='✕';
        rm.onclick=()=>row.remove();
        row.appendChild(rm);
        rows.appendChild(row);
    }
    (existing.length?existing:[{}]).forEach(addRow);
    document.getElementById('add-deliver').onclick=()=>addRow();

    form.addEventListener('submit', function(){
        const out=[];
        rows.querySelectorAll('.deliver-row').forEach(r=>{
            const cn=r.querySelector('.d-class').value.trim(); if(!cn) return;
            const att=r.querySelector('.d-att').value.split(',').map(s=>s.trim()).filter(Boolean);
            const cargo=r.querySelector('.d-cargo').value.split(',').map(s=>s.trim()).filter(Boolean);
            let h=parseFloat(r.querySelector('.d-health').value); if(isNaN(h)) h=1; h=Math.max(0,Math.min(1,h));
            out.push({ classname:cn, quantity:Math.max(1,parseInt(r.querySelector('.d-qty').value)||1), attachments:att, cargo:cargo, health:h });
        });
        document.getElementById('deliver-json').value=JSON.stringify(out);
    });
})();
</script>

<?php \App\View::endSection(); ?>
