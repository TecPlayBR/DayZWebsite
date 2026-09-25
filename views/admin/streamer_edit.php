<?php /** @var array $config, $streamers; @var ?array $edit; @var array $coupons */ ?>
<?php $title = 'Gerenciar Streamers'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<?php
$e = $edit ?? [];
$photos = '';
$videos = '';
if ($e) {
    $p = json_decode($e['photos_json'] ?? '[]', true);
    if (is_array($p)) $photos = implode("\n", $p);
    $v = json_decode($e['video_urls_json'] ?? '[]', true);
    if (is_array($v)) $videos = implode("\n", $v);
}
$socialVals = [];
if ($e) {
    $sj = json_decode($e['socials_json'] ?? '[]', true);
    if (is_array($sj)) $socialVals = $sj;
}
?>

<div class="admin-page-head">
    <div>
        <h1>🎮 Gerenciar Streamers</h1>
        <p>Cadastre e edite os streamers parceiros (código de apoio, fotos, canal, vídeos, destaque). O <a href="/admin/streamers" style="color:var(--hazard);">relatório de cachê</a> mostra quanto cada um gerou.</p>
    </div>
</div>

<?php if (isset($_GET['ok'])): ?>
    <div class="stat-card" style="margin-bottom:1.2rem; border-left:3px solid var(--moss);">✓ Salvo.</div>
<?php elseif (isset($_GET['err'])): ?>
    <div class="stat-card" style="margin-bottom:1.2rem; border-left:3px solid var(--danger-border); background:var(--danger-overlay);">
        <strong>Não salvou.</strong>
        <?php if (!empty($_GET['msg'])): ?>
            <div style="margin-top:.4rem; font-family:var(--font-mono); font-size:.85rem; color:var(--dim);"><?= e((string) $_GET['msg']) ?></div>
        <?php endif; ?>
        <div style="margin-top:.4rem; font-size:.9rem; color:var(--dim);">Se a mensagem fala em coluna desconhecida, falta rodar uma migration: abra o <code>/update.php</code>.</div>
    </div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <h3 style="margin-top:0;"><?= $e ? 'Editar: ' . e($e['name']) : 'Novo streamer' ?></h3>
    <p class="adm-legenda"><span class="adm-req">*</span> obrigatório. Só o código e o nome são obrigatórios; o resto melhora a página do streamer.</p>
    <form method="POST" action="/admin/streamers/save" enctype="multipart/form-data" class="adm-grid-2" style="max-width:760px;" data-adm-form>
        <?= \App\Csrf::field() ?>
        <?php if ($e): ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><?php endif; ?>
        <?= admin_campo(['label' => 'Código do streamer', 'name' => 'code', 'value' => (string) ($e['code'] ?? ''), 'required' => true, 'attrs' => 'maxlength="40"', 'placeholder' => 'EX: HARDO',
            'hint' => 'É o que o jogador digita em "Apoie seu Streamer". Vira maiúsculo. Não é o cupom de desconto.']) ?>
        <?= admin_campo(['label' => 'Nome exibido', 'name' => 'name', 'value' => (string) ($e['name'] ?? ''), 'required' => true, 'attrs' => 'maxlength="80"', 'placeholder' => 'Hardo',
            'hint' => 'Aparece na home (se em destaque) e na página do streamer.']) ?>
        <div class="adm-span-2"><?= admin_campo(['label' => 'Bio', 'name' => 'bio', 'type' => 'textarea', 'rows' => 4, 'value' => (string) ($e['bio'] ?? ''),
            'hint' => 'Um parágrafo sobre o streamer. Na home corta em 180 letras.']) ?></div>
        <?= admin_campo_imagem(['label' => 'Avatar', 'name' => 'avatar_url', 'value' => (string) ($e['avatar_url'] ?? '')]) ?>
        <?= admin_campo(['label' => 'Canal principal', 'name' => 'channel_url', 'type' => 'url', 'value' => (string) ($e['channel_url'] ?? ''), 'attrs' => 'maxlength="300"', 'placeholder' => 'https://twitch.tv/...',
            'hint' => 'Twitch ou YouTube. Vira o botão principal da página.']) ?>
        <div class="adm-span-2 adm-campo">
            <label class="adm-label" for="f-photos">Fotos</label>
            <input type="file" id="f-photo-files" name="photo_files[]" accept="image/png,image/webp,image/jpeg" multiple>
            <details class="adm-imagem-url" style="margin-top:.4rem;"><summary>ou colar links (um por linha)</summary>
                <textarea class="field mono" id="f-photos" name="photos" rows="3" placeholder="/assets/img/streamers/hardo-1.webp"><?= e($photos) ?></textarea>
            </details>
            <small class="adm-hint">Galeria da página do streamer. Envie do computador (PNG, JPG ou WEBP, até 5 MB cada); as enviadas somam às que já estão. Link do Discord expira, Google Drive e GitHub não servem.</small>
        </div>
        <div class="adm-span-2"><?= admin_campo(['label' => 'Vídeos', 'name' => 'videos', 'type' => 'textarea', 'rows' => 2, 'value' => $videos, 'placeholder' => 'https://youtu.be/...',
            'hint' => 'Um link por linha (YouTube, Twitch). Só http/https.']) ?></div>
        <div class="adm-span-2 adm-campo">
            <label class="adm-label">Redes</label>
            <div class="adm-grid-2">
                <?php foreach (\App\Streamer::SOCIAL_PLATFORMS as $sk => $slbl): ?>
                    <input class="field" name="social_<?= e($sk) ?>" maxlength="300" value="<?= e($socialVals[$sk] ?? '') ?>" placeholder="<?= e($slbl) ?> (link)" aria-label="<?= e($slbl) ?>">
                <?php endforeach; ?>
            </div>
            <small class="adm-hint">Preencha só as que existem: aparece um botão para cada uma. Só http/https.</small>
        </div>
        <?= admin_campo(['label' => 'Cupom de desconto vinculado', 'name' => 'coupon_code', 'value' => (string) ($e['coupon_code'] ?? ''), 'attrs' => 'maxlength="40"', 'placeholder' => 'EX: HARDO15',
            'hint' => 'Cupom criado na tela Cupons. Quem usa esse cupom OU clica em Apoiar conta o cachê pro streamer.']) ?>
        <?= admin_campo(['label' => 'Ordem', 'name' => 'sort_order', 'type' => 'number', 'value' => (string) (int) ($e['sort_order'] ?? 0), 'hint' => 'Menor aparece primeiro.']) ?>
        <div class="adm-span-2" style="display:flex; gap:1.5rem; flex-wrap:wrap;">
            <label class="adm-check"><input type="checkbox" name="featured" value="1" <?= !empty($e['featured']) ? 'checked' : '' ?>> Streamer OFICIAL: destaque exclusivo na home. <strong>Sem isso, só aparece em /streamers.</strong></label>
            <label class="adm-check"><input type="checkbox" name="active" value="1" <?= (!$e || !empty($e['active'])) ? 'checked' : '' ?>> Ativo (desmarcado some de tudo)</label>
        </div>
        <div class="adm-span-2" style="margin-top:.4rem; display:flex; gap:0.7rem;">
            <button type="submit" class="btn-mini" style="padding:0.7rem 1.6rem;">Salvar</button>
            <?php if ($e): ?><a href="/streamer/<?= e(strtolower($e['code'])) ?>" target="_blank" class="btn-mini outline">Ver página →</a><?php endif; ?>
            <a href="/admin/streamers/manage" class="btn-mini outline">Novo / limpar</a>
        </div>
    </form>
</div>

<div class="stat-card">
    <h3 style="margin-top:0;">Streamers cadastrados</h3>
    <table style="width:100%;">
        <thead><tr><th>Código</th><th>Nome</th><th>Cupom</th><th>Destaque</th><th>Ativo</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($streamers)): ?>
            <tr><td colspan="6" style="color:var(--dim);">Nenhum streamer ainda.</td></tr>
        <?php else: foreach ($streamers as $s): ?>
            <tr>
                <td><code><?= e($s['code']) ?></code></td>
                <td><?= e($s['name']) ?></td>
                <td><?= e($s['coupon_code'] ?: '-') ?></td>
                <td><?= !empty($s['featured']) ? '⭐' : '-' ?></td>
                <td><?= !empty($s['active']) ? 'sim' : 'não' ?></td>
                <td style="white-space:nowrap;">
                    <a href="/admin/streamers/manage?id=<?= (int)$s['id'] ?>" style="color:var(--hazard);">Editar</a>
                    <form method="POST" action="/admin/streamers/delete" style="display:inline; margin-left:0.6rem;" data-confirm="Remover o streamer <?= e($s['code']) ?>?">
                        <?= \App\Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="btn-mini outline" style="color:var(--rust);">Remover</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php \App\View::endSection(); ?>
