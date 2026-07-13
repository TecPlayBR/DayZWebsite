<?php /** @var array $config, $streamers; @var ?array $edit; @var array $coupons */ ?>
<?php $title = 'Gerenciar Streamers'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<?php
$field = 'width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); border-radius:6px;';
$e = $edit ?? [];
$photos = '';
$videos = '';
if ($e) {
    $p = json_decode($e['photos_json'] ?? '[]', true);
    if (is_array($p)) $photos = implode("\n", $p);
    $v = json_decode($e['video_urls_json'] ?? '[]', true);
    if (is_array($v)) $videos = implode("\n", $v);
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
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.5rem;">
    <h3 style="margin-top:0;"><?= $e ? 'Editar: ' . e($e['name']) : 'Novo streamer' ?></h3>
    <form method="POST" action="/admin/streamers/save" style="max-width:760px;">
        <?= \App\Csrf::field() ?>
        <?php if ($e): ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><?php endif; ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div>
                <label>Código do streamer (apoiar)</label>
                <input name="code" maxlength="40" value="<?= e($e['code'] ?? '') ?>" placeholder="EX: HARDO" required style="<?= $field ?>">
            </div>
            <div>
                <label>Nome exibido</label>
                <input name="name" maxlength="80" value="<?= e($e['name'] ?? '') ?>" placeholder="Hardo" required style="<?= $field ?>">
            </div>
        </div>
        <div style="margin-top:1rem;">
            <label>Bio / textinho sobre o streamer</label>
            <textarea name="bio" rows="4" placeholder="Conte um pouco sobre o streamer..." style="<?= $field ?>"><?= e($e['bio'] ?? '') ?></textarea>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem;">
            <div>
                <label>Avatar (URL)</label>
                <input name="avatar_url" maxlength="300" value="<?= e($e['avatar_url'] ?? '') ?>" placeholder="/assets/img/streamers/hardo.webp" style="<?= $field ?>">
            </div>
            <div>
                <label>Canal (Twitch/YouTube)</label>
                <input name="channel_url" maxlength="300" value="<?= e($e['channel_url'] ?? '') ?>" placeholder="https://twitch.tv/..." style="<?= $field ?>">
            </div>
        </div>
        <div style="margin-top:1rem;">
            <label>Fotos (uma URL por linha)</label>
            <textarea name="photos" rows="3" placeholder="/assets/img/streamers/hardo-1.webp&#10;/assets/img/streamers/hardo-2.webp" style="<?= $field ?>"><?= e($photos) ?></textarea>
        </div>
        <div style="margin-top:1rem;">
            <label>Vídeos (uma URL por linha)</label>
            <textarea name="videos" rows="2" placeholder="https://youtu.be/..." style="<?= $field ?>"><?= e($videos) ?></textarea>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem;">
            <div>
                <label>Cupom de desconto vinculado (opcional)</label>
                <input name="coupon_code" maxlength="40" value="<?= e($e['coupon_code'] ?? '') ?>" placeholder="EX: HARDO15" style="<?= $field ?>">
                <div style="font-size:0.78rem; color:var(--dim); margin-top:0.3rem;">Quem usar esse cupom OU clicar em Apoiar conta o cachê pro streamer.</div>
            </div>
            <div>
                <label>Ordem</label>
                <input name="sort_order" type="number" value="<?= (int)($e['sort_order'] ?? 0) ?>" style="<?= $field ?>">
            </div>
        </div>
        <div style="display:flex; gap:1.5rem; margin-top:1rem;">
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                <input type="checkbox" name="featured" value="1" <?= !empty($e['featured']) ? 'checked' : '' ?>> Destaque na home
            </label>
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                <input type="checkbox" name="active" value="1" <?= (!$e || !empty($e['active'])) ? 'checked' : '' ?>> Ativo
            </label>
        </div>
        <div style="margin-top:1.2rem; display:flex; gap:0.7rem;">
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
                    <form method="POST" action="/admin/streamers/delete" style="display:inline; margin-left:0.6rem;" onsubmit="return confirm('Remover o streamer <?= e($s['code']) ?>?')">
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
