<?php /** @var array $config; @var ?array $r */ ?>
<?php
$r = array_merge(['id'=>null,'version'=>'','category'=>'atualizacao','title'=>'','body'=>'','released_at'=>'','published'=>1,'sort_order'=>0], is_array($r ?? null) ? $r : []);
$isNew = empty($r['id']);
$fld = 'width:100%;padding:.65rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);';
$relDate = !empty($r['released_at']) ? substr((string)$r['released_at'], 0, 10) : date('Y-m-d');
?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div><h1><?= $isNew ? '➕ Nova novidade' : 'Editar: ' . e($r['title']) ?></h1></div>
    <a href="/admin/releases" class="btn-mini outline">← Voltar</a>
</div>

<?php if (($_GET['err'] ?? '') === 'title'): ?>
    <div style="background:var(--danger-overlay);border-left:3px solid var(--rust-2);padding:.7rem 1rem;margin-bottom:1.2rem;color:var(--text-danger);">O título é obrigatório.</div>
<?php endif; ?>

<form method="POST" action="/admin/releases/save" style="max-width:820px;">
    <?= \App\Csrf::field() ?>
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?php endif; ?>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div style="display:grid;grid-template-columns:1fr 160px 170px;gap:1rem;">
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Título</label>
                <input type="text" name="title" required maxlength="160" value="<?= e($r['title']) ?>" style="<?= $fld ?>" placeholder="Ex: Armas corrigidas">
            </div>
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Categoria</label>
                <select name="category" style="<?= $fld ?>">
                    <?php foreach (\App\Releases::CATEGORIES as $k=>$c): ?>
                        <option value="<?= e($k) ?>" <?= $r['category']===$k?'selected':'' ?>><?= e($c[1] . ' ' . $c[0]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Data</label>
                <input type="date" name="released_at" value="<?= e($relDate) ?>" style="<?= $fld ?>">
            </div>
        </div>
        <div style="margin-top:1rem;">
            <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Versão (opcional) - ex: v2.5.2</label>
            <input type="text" name="version" maxlength="40" value="<?= e($r['version']) ?>" style="max-width:220px;<?= $fld ?>">
        </div>
    </div>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div class="label">O que mudou</div>
        <p style="font-size:.78rem;color:var(--dim);margin:.3rem 0 .6rem;">Clique nos botões pra inserir estrutura pronta. Escreva o texto e intercale imagens. O <strong>preview ao lado</strong> mostra como fica antes de salvar.</p>
        <div class="rel-toolbar" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.55rem;">
            <button type="button" class="btn-mini outline" data-ins="h3">＋ Título</button>
            <button type="button" class="btn-mini outline" data-ins="p">＋ Parágrafo</button>
            <button type="button" class="btn-mini outline" data-ins="ul">＋ Lista</button>
            <button type="button" class="btn-mini outline" data-ins="b">Negrito</button>
            <button type="button" class="btn-mini outline" data-ins="a">Link</button>
            <button type="button" class="btn-mini outline" data-ins="img">Imagem (URL)</button>
            <button type="button" class="btn-mini outline" data-ins="hr">Separador</button>
            <label class="btn-mini" style="cursor:pointer;margin:0;">📎 Enviar imagem
                <input type="file" id="rel-upload" accept="image/png,image/webp,image/jpeg" style="display:none;">
            </label>
        </div>
        <div class="rel-editor-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start;">
            <textarea name="body" id="rel-body" rows="16" style="<?= $fld ?> font-family:var(--font-mono);font-size:.85rem;resize:vertical;" placeholder="<p>Foi corrigido o carregador das armas que...</p>&#10;<ul><li>...</li></ul>"><?= e($r['body']) ?></textarea>
            <div style="border:1px solid var(--border);border-radius:6px;background:var(--bg-0);">
                <div style="font-size:.72rem;color:var(--dim);padding:.4rem .7rem;border-bottom:1px solid var(--border);">PREVIEW</div>
                <div id="rel-preview" style="padding:1rem;min-height:160px;max-height:520px;overflow:auto;color:var(--bone);"></div>
            </div>
        </div>
        <div id="rel-upmsg" style="font-size:.78rem;color:var(--dim);margin-top:.45rem;min-height:1em;"></div>
    </div>

    <div class="stat-card" style="margin-bottom:1.2rem;">
        <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;color:var(--bone);">
                <input type="checkbox" name="published" value="1" <?= (int)$r['published']?'checked':'' ?> style="width:16px;height:16px;"> Publicar (visível em /novidades)
            </label>
            <div>
                <label style="font-size:.8rem;color:var(--dim);margin-right:.5rem;">Ordem (desempate na mesma data)</label>
                <input type="number" name="sort_order" value="<?= (int)$r['sort_order'] ?>" style="width:90px;padding:.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
            </div>
        </div>
    </div>

    <button type="submit" class="btn-mini" style="padding:.7rem 1.6rem;">Salvar novidade</button>
</form>

<script>
(function(){
  var ta = document.getElementById('rel-body'); if (!ta) return;
  var prev = document.getElementById('rel-preview');
  var snip = {
    h3:'\n<h3>Título da seção</h3>\n',
    p:'\n<p>Escreva seu texto aqui.</p>\n',
    ul:'\n<ul>\n  <li>Primeiro item</li>\n  <li>Segundo item</li>\n</ul>\n',
    b:'<strong>texto em negrito</strong>',
    a:'<a href="https://" target="_blank">texto do link</a>',
    img:'\n<img src="/assets/img/help/arquivo.png" alt="">\n',
    hr:'\n<hr>\n'
  };
  function insert(t){
    var s = ta.selectionStart || 0, e = ta.selectionEnd || 0;
    ta.value = ta.value.slice(0, s) + t + ta.value.slice(e);
    ta.focus(); ta.selectionStart = ta.selectionEnd = s + t.length;
    render();
  }
  document.querySelectorAll('.rel-toolbar [data-ins]').forEach(function(b){
    b.addEventListener('click', function(){ insert(snip[b.getAttribute('data-ins')] || ''); });
  });
  function render(){ prev.innerHTML = ta.value; }
  ta.addEventListener('input', render); render();

  var up = document.getElementById('rel-upload'), msg = document.getElementById('rel-upmsg');
  var csrfEl = document.querySelector('form [name=_csrf]');
  var csrf = csrfEl ? csrfEl.value : '';
  if (up) up.addEventListener('change', function(){
    if (!up.files || !up.files[0]) return;
    msg.textContent = 'Enviando imagem...';
    var fd = new FormData(); fd.append('file', up.files[0]); fd.append('_csrf', csrf);
    fetch('/admin/help/upload', { method:'POST', body: fd, headers: { 'X-CSRF-Token': csrf } })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d && d.url) { insert('\n<img src="' + d.url + '" alt="">\n'); msg.textContent = 'Imagem inserida: ' + d.url; }
        else { msg.textContent = 'Falha no upload da imagem.'; }
        up.value = '';
      })
      .catch(function(){ msg.textContent = 'Erro no upload.'; });
  });
})();
</script>
<style>
#rel-preview h3{color:var(--bone);margin:.6rem 0 .3rem;} #rel-preview p{color:var(--dim);margin:.4rem 0;line-height:1.5;}
#rel-preview img{max-width:100%;border-radius:6px;margin:.5rem 0;} #rel-preview ul{color:var(--dim);padding-left:1.2rem;}
#rel-preview hr{border:0;border-top:1px solid var(--border);margin:1rem 0;}
@media(max-width:760px){ .rel-editor-grid{grid-template-columns:1fr!important;} }
</style>

<?php \App\View::endSection(); ?>
