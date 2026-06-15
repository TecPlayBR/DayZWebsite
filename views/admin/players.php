<?php /** @var array $config, $players, $q, $origin, $sort; @var int $page, $per_page, $total, $last_page */ ?>
<?php $title = 'Jogadores'; ?>
<?php \App\View::extend('admin.layout'); ?>

<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Jogadores</h1>
        <p>Edite moedas direto na tabela. O agent propaga pro JSON em até 15s.</p>
    </div>
    <div style="color: var(--dim); font-family: var(--font-mono); font-size: 0.85rem;">
        <?= number_format($total, 0, ',', '.') ?> jogadores
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert-toast">Atualizado.</div>
<?php endif; ?>

<!-- Search + filtros -->
<form method="GET" action="/admin/players" class="stat-card" style="padding: 1rem 1.2rem; margin-bottom: 1.5rem;">
    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto auto; gap: 0.6rem; align-items: end;">
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase; letter-spacing: 0.08em;">Busca</label>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="SteamID ou nome…"
                   style="width:100%; padding:0.55rem 0.8rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono); font-size:0.85rem;">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase; letter-spacing: 0.08em;">Origem</label>
            <select name="origin" style="width:100%; padding:0.55rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                <option value="">Todas</option>
                <option value="agent"   <?= $origin==='agent'   ? 'selected' : '' ?>>Agent</option>
                <option value="payment" <?= $origin==='payment' ? 'selected' : '' ?>>Payment</option>
                <option value="panel"   <?= $origin==='panel'   ? 'selected' : '' ?>>Panel</option>
                <option value="manual"  <?= $origin==='manual'  ? 'selected' : '' ?>>Manual</option>
                <option value="bot"     <?= $origin==='bot'     ? 'selected' : '' ?>>Bot Discord</option>
            </select>
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase; letter-spacing: 0.08em;">Ordenar por</label>
            <select name="sort" style="width:100%; padding:0.55rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                <option value="coins"  <?= $sort==='coins'  ? 'selected' : '' ?>>Moedas (↓)</option>
                <option value="spent"  <?= $sort==='spent'  ? 'selected' : '' ?>>Total gasto (↓)</option>
                <option value="recent" <?= $sort==='recent' ? 'selected' : '' ?>>Atividade recente</option>
                <option value="name"   <?= $sort==='name'   ? 'selected' : '' ?>>Nome (A-Z)</option>
            </select>
        </div>
        <button type="submit" class="btn-mini" style="padding: 0.55rem 1.2rem;">Filtrar</button>
        <?php if ($q || $origin || $sort !== 'coins'): ?>
            <a href="/admin/players" class="btn-mini outline" style="padding: 0.55rem 1rem; text-decoration: none;">Limpar</a>
        <?php endif; ?>
    </div>
</form>

<table class="admin-table">
    <thead>
        <tr>
            <th>SteamID</th>
            <th>Nome</th>
            <th>Moedas</th>
            <th>Total Gasto</th>
            <th>Última atividade</th>
            <th>Origem</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($players) && !$q && !$origin): ?>
            <tr><td colspan="7" style="text-align: center; color: var(--dim); padding: 2rem;">
                Nenhum jogador ainda. Eles aparecem aqui quando o agent rodar pela primeira vez ou alguém comprar.
            </td></tr>
        <?php elseif (empty($players)): ?>
            <tr><td colspan="7" style="text-align: center; color: var(--dim); padding: 2rem;">
                Nenhum jogador encontrado com esses filtros. <a href="/admin/players" style="color: var(--rust-2);">Limpar busca</a>
            </td></tr>
        <?php else: foreach ($players as $p): ?>
            <tr>
                <td class="mono"><?= e($p['steam_id']) ?></td>
                <td><?= e($p['display_name'] ?? '—') ?></td>
                <td>
                    <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/coins" class="inline-form">
                        <?= \App\Csrf::field() ?>
                        <input type="number" name="coins" value="<?= (int)$p['coins'] ?>" min="0" max="999999">
                        <button type="submit" class="btn-mini" title="Salvar">✓</button>
                    </form>
                </td>
                <td>R$ <?= number_format($p['total_spent_brl'], 2, ',', '.') ?></td>
                <td class="dim"><?= e($p['last_seen_at'] ?? 'nunca') ?></td>
                <td>
                    <?php
                    $origin = $p['origin'] ?? 'agent';
                    $cls = match($origin) {
                        'agent'   => 'info',
                        'payment' => 'success',
                        'panel'   => 'warning',
                        'bot'     => 'pro',
                        default   => 'info'
                    };
                    ?>
                    <span class="badge <?= $cls ?>"><?= e($origin) ?></span>
                </td>
                <td class="dim">
                    <a href="/admin/players/<?= (int)$p['id'] ?>" style="color: var(--rust-2); text-decoration: none; font-size: 0.85rem;">Detalhes →</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php
// Paginação
if ($last_page > 1):
    $qsBase = http_build_query(array_filter(['q'=>$q,'origin'=>$origin,'sort'=>$sort]));
    $qsBase = $qsBase ? '&' . $qsBase : '';
    $start = max(1, $page - 2);
    $end   = min($last_page, $page + 2);
?>
    <nav class="pagination" aria-label="Páginas">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?><?= $qsBase ?>" class="page-link">← Anterior</a>
        <?php endif; ?>

        <?php if ($start > 1): ?>
            <a href="?page=1<?= $qsBase ?>" class="page-link">1</a>
            <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="page-link page-current"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?><?= $qsBase ?>" class="page-link"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $last_page): ?>
            <?php if ($end < $last_page - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
            <a href="?page=<?= $last_page ?><?= $qsBase ?>" class="page-link"><?= $last_page ?></a>
        <?php endif; ?>

        <?php if ($page < $last_page): ?>
            <a href="?page=<?= $page + 1 ?><?= $qsBase ?>" class="page-link">Próxima →</a>
        <?php endif; ?>
    </nav>
    <p style="text-align: center; color: var(--dim); font-size: 0.8rem; margin-top: 0.5rem; font-family: var(--font-mono);">
        Página <?= $page ?> de <?= $last_page ?> · <?= number_format($total, 0, ',', '.') ?> jogadores no total
    </p>
<?php endif; ?>

<style>
.pagination {
    display: flex; gap: 0.3rem; justify-content: center; flex-wrap: wrap;
    margin-top: 2rem;
}
.page-link {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 38px; padding: 0.4rem 0.7rem;
    background: var(--bg-1); border: 1px solid var(--border);
    color: var(--bone); text-decoration: none;
    font-family: var(--font-mono); font-size: 0.85rem;
    transition: border-color .15s, color .15s;
}
.page-link:hover { border-color: var(--rust); color: var(--hazard); }
.page-link.page-current {
    background: var(--rust); border-color: var(--rust); color: var(--bone);
    cursor: default;
}
.page-ellipsis {
    color: var(--dim); padding: 0.4rem 0.3rem; font-family: var(--font-mono);
}
</style>

<?php \App\View::endSection(); ?>
