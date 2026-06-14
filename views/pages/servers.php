<?php /** @var array $config; @var array $servers */ ?>
<?php $title = 'Servidores'; ?>
<?php \App\View::extend('layouts.main'); ?>
<?php $sSite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'; ?>
<?php \App\View::with('title', 'Servidores — ' . $sSite . ' DayZ BR'); ?>
<?php \App\View::with('description', 'Conheça os servidores DayZ do ' . $sSite . '. Status, conexão e detalhes de cada servidor.'); ?>
<?php \App\View::section('content'); ?>

<section class="page-section">
    <div class="container">

        <div class="page-header">
            <span class="page-tag">// NOSSOS SERVIDORES</span>
            <h1 class="page-title">Servidores</h1>
            <p class="page-subtitle">Escolha o servidor que combina com seu estilo de jogo. Cada um tem suas regras e mapa.</p>
        </div>

        <?php if (empty($servers)): ?>
            <div class="empty-state">
                <span class="empty-icon">▣</span>
                <p>Nenhum servidor cadastrado ainda.</p>
            </div>
        <?php else: ?>
            <div class="servers-grid">
                <?php foreach ($servers as $s): ?>
                    <div class="server-public-card">
                        <header class="server-public-head">
                            <h3><?= e($s['name']) ?></h3>
                            <?php $live = $s['live'] ?? null; ?>
                            <?php if ($live && !empty($live['configured'])): ?>
                                <span class="server-status-pill <?= !empty($live['online']) ? 'online' : 'offline' ?>">
                                    <?= !empty($live['online']) ? '● online' : '○ offline' ?>
                                </span>
                            <?php endif; ?>
                        </header>

                        <?php if (!empty($s['description'])): ?>
                            <p class="server-public-desc"><?= e($s['description']) ?></p>
                        <?php endif; ?>

                        <ul class="server-public-meta">
                            <?php if (!empty($s['map'])): ?>
                                <li><span class="lab">Mapa</span><span><?= e($s['map']) ?></span></li>
                            <?php endif; ?>
                            <?php if ($live && !empty($live['configured'])): ?>
                                <li><span class="lab">Players</span><span><?= (int)($live['players'] ?? 0) ?>/<?= (int)($live['max'] ?? $s['max_players']) ?></span></li>
                            <?php else: ?>
                                <li><span class="lab">Capacidade</span><span><?= (int)$s['max_players'] ?></span></li>
                            <?php endif; ?>
                            <?php if (!empty($s['ip'])): ?>
                                <li><span class="lab">Endereço</span>
                                    <code><?= e($s['ip']) ?>:<?= (int)$s['port'] ?></code>
                                </li>
                            <?php endif; ?>
                        </ul>

                        <a href="/shop?server=<?= (int)$s['id'] ?>" class="btn btn-mini" style="display:block; text-align:center;">
                            Comprar moedas →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<style>
.servers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}
.server-public-card {
    background: var(--bg-1);
    border: 1px solid var(--border);
    border-left: 3px solid var(--hazard);
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    transition: border-color 0.2s, transform 0.2s;
}
.server-public-card:hover {
    border-color: var(--hazard);
    transform: translateY(-2px);
}
.server-public-head {
    display: flex; justify-content: space-between; align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}
.server-public-head h3 {
    margin: 0;
    font-family: var(--font-display);
    color: var(--bone);
    font-size: 1.1rem;
    text-transform: uppercase;
}
.server-status-pill {
    font-family: var(--font-mono);
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border: 1px solid currentColor;
    text-transform: uppercase;
}
.server-status-pill.online  { color: var(--moss); }
.server-status-pill.offline { color: var(--rust-2); }
.server-public-desc {
    color: var(--dim);
    font-size: 0.9rem;
    margin: 0 0 1rem;
    flex: 1;
}
.server-public-meta {
    list-style: none; padding: 0; margin: 0 0 1rem;
    display: flex; flex-direction: column; gap: 0.4rem;
    font-size: 0.85rem;
}
.server-public-meta li {
    display: flex; justify-content: space-between;
    padding-bottom: 0.3rem;
    border-bottom: 1px dashed var(--border);
}
.server-public-meta .lab {
    color: var(--dim);
    text-transform: uppercase;
    font-family: var(--font-mono);
    font-size: 0.7rem;
    letter-spacing: 0.5px;
}
.server-public-meta code {
    color: var(--moss);
    user-select: all;
}
</style>

<?php \App\View::endSection(); ?>
