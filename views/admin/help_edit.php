<?php /** @var array $config; @var ?array $a */ ?>
<?php
$a = array_merge(['id'=>null,'category'=>'comecando','title'=>'','summary'=>'','body'=>'','video_url'=>'','image'=>null,'published'=>1,'sort_order'=>0], is_array($a ?? null) ? $a : []);
$isNew = empty($a['id']);
$title = $isNew ? 'Novo artigo' : 'Editar artigo';
$fld = 'width:100%;padding:.65rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);';
?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div><h1><?= $isNew ? '➕ Novo artigo' : 'Editar: ' . e($a['title']) ?></h1></div>
    <a href="/admin/help" class="btn-mini outline">← Voltar</a>
</div>

<?php if (($_GET['err'] ?? '') === 'title'): ?>
    <div style="background:var(--danger-overlay);border-left:3px solid var(--rust-2);padding:.7rem 1rem;margin-bottom:1.2rem;color:var(--text-danger);">O título é obrigatório.</div>
<?php endif; ?>

<form method="POST" action="/admin/help/save" enctype="multipart/form-data" style="max-width:820px;">
    <?= \App\Csrf::field() ?>
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><?php endif; ?>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div style="display:grid;grid-template-columns:1fr 200px;gap:1rem;">
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Título</label>
                <input type="text" name="title" required maxlength="160" value="<?= e($a['title']) ?>" style="<?= $fld ?>">
            </div>
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Categoria</label>
                <select name="category" style="<?= $fld ?>">
                    <?php foreach (\App\Help::CATEGORIES as $k=>$lbl): ?>
                        <option value="<?= e($k) ?>" <?= $a['category']===$k?'selected':'' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top:1rem;">
            <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Resumo (1 linha - aparece no card e no Google)</label>
            <input type="text" name="summary" maxlength="300" value="<?= e($a['summary']) ?>" style="<?= $fld ?>">
        </div>
    </div>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div class="label">Conteúdo</div>
        <p style="font-size:.78rem;color:var(--dim);margin:.3rem 0 .6rem;">Clique nos botões pra inserir estrutura pronta. Escreva o texto e intercale imagens. O <strong>preview ao lado</strong> mostra como fica antes de salvar.</p>
        <div class="help-toolbar" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.55rem;">
            <button type="button" class="btn-mini outline" data-ins="h3">＋ Título</button>
            <button type="button" class="btn-mini outline" data-ins="p">＋ Parágrafo</button>
            <button type="button" class="btn-mini outline" data-ins="ul">＋ Lista</button>
            <button type="button" class="btn-mini outline" data-ins="b">Negrito</button>
            <button type="button" class="btn-mini outline" data-ins="a">Link</button>
            <button type="button" class="btn-mini outline" data-ins="img">Imagem (URL)</button>
            <button type="button" class="btn-mini outline" data-ins="hr">Separador</button>
            <label class="btn-mini" style="cursor:pointer;margin:0;">📎 Enviar imagem
                <input type="file" id="help-upload" accept="image/png,image/webp,image/jpeg" style="display:none;">
            </label>
        </div>
        <div class="help-editor-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start;">
            <textarea name="body" id="help-body" rows="18" style="<?= $fld ?> font-family:var(--font-mono);font-size:.85rem;resize:vertical;"><?= e($a['body']) ?></textarea>
            <div style="border:1px solid var(--border);border-radius:6px;background:var(--bg-0);">
                <div style="font-size:.72rem;color:var(--dim);padding:.4rem .7rem;border-bottom:1px solid var(--border);">PREVIEW</div>
                <div id="help-preview" style="padding:1rem;min-height:180px;max-height:520px;overflow:auto;color:var(--bone);"></div>
            </div>
        </div>
        <div id="help-upmsg" style="font-size:.78rem;color:var(--dim);margin-top:.45rem;min-height:1em;"></div>
    </div>

    <div class="stat-card" style="margin-bottom:1rem;">
        <div class="label">Mídia</div>
        <div style="margin-top:1rem;">
            <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Vídeo do YouTube (link) <small>- embuto automático</small></label>
            <input type="url" name="video_url" value="<?= e($a['video_url']) ?>" placeholder="https://youtu.be/..." style="<?= $fld ?>">
        </div>
        <div style="margin-top:1rem;display:flex;align-items:center;gap:1rem;">
            <?php if (!empty($a['image'])): ?><img src="<?= e($a['image']) ?>" alt="" style="width:90px;height:60px;object-fit:cover;border:1px solid var(--border);border-radius:5px;"><?php endif; ?>
            <div>
                <label style="display:block;font-size:.8rem;color:var(--dim);margin-bottom:.3rem;">Imagem <?= !empty($a['image']) ? '(trocar)' : '(opcional)' ?></label>
                <input type="file" name="image_file" accept="image/png,image/webp,image/jpeg" style="color:var(--bone);font-size:.85rem;">
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom:1.2rem;">
        <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;color:var(--bone);">
                <input type="checkbox" name="published" value="1" <?= (int)$a['published']?'checked':'' ?> style="width:16px;height:16px;"> Visível no site
            </label>
            <div>
                <label style="font-size:.8rem;color:var(--dim);margin-right:.5rem;">Ordem</label>
                <input type="number" name="sort_order" value="<?= (int)$a['sort_order'] ?>" style="width:90px;padding:.5rem;background:var(--bg-0);border:1px solid var(--border);color:var(--bone);">
            </div>
        </div>
    </div>

    <button type="submit" class="btn-mini" style="padding:.7rem 1.6rem;">Salvar artigo</button>
</form>

<script>
(function(){
  var ta = document.getElementById('help-body'); if (!ta) return;
  var prev = document.getElementById('help-preview');
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
  document.querySelectorAll('.help-toolbar [data-ins]').forEach(function(b){
    b.addEventListener('click', function(){ insert(snip[b.getAttribute('data-ins')] || ''); });
  });
  function render(){ prev.innerHTML = ta.value; }
  ta.addEventListener('input', render); render();

  var up = document.getElementById('help-upload'), msg = document.getElementById('help-upmsg');
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
#help-preview h3{color:var(--bone);margin:.6rem 0 .3rem;} #help-preview p{color:var(--dim);margin:.4rem 0;line-height:1.5;}
#help-preview img{max-width:100%;border-radius:6px;margin:.5rem 0;} #help-preview ul{color:var(--dim);padding-left:1.2rem;}
#help-preview hr{border:0;border-top:1px solid var(--border);margin:1rem 0;}
@media(max-width:760px){ .help-editor-grid{grid-template-columns:1fr!important;} }
</style>

<?php \App\View::endSection(); ?>
