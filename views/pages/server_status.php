<?php /** @var array $config, $status, $players */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background2.png'); // LCP preload sync ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 40vh; padding-bottom: 2rem;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background2.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// <?= e(__('server.kicker')) ?></span>
        <h1 class="hero-title"><?= e(__('server.title_1')) ?><br><span class="accent"><?= e(__('server.title_2')) ?></span></h1>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container">

        <?php if (empty($status['configured'])): ?>
            <div style="text-align: center; padding: 4rem 1rem; color: var(--dim);">
                <p><?= e(__('server.not_configured')) ?></p>
            </div>
        <?php else: ?>

            <!-- Cards principais -->
            <div class="server-grid">
                <div class="server-card">
                    <div class="server-card-label"><?= e(__('server.label_status')) ?></div>
                    <div class="server-card-value">
                        <span class="server-dot server-dot-<?= $status['online'] ? 'online' : 'offline' ?>"></span>
                        <?= $status['online'] ? e(__('server.online')) : e(__('server.offline')) ?>
                    </div>
                </div>
                <div class="server-card">
                    <div class="server-card-label"><?= e(__('server.label_players')) ?></div>
                    <div class="server-card-value server-card-big">
                        <?= (int)$status['players'] ?><span style="color: var(--dim); font-size: 0.6em;">/<?= (int)$status['max'] ?: 60 ?></span>
                    </div>
                </div>
                <?php if (!empty($status['rank'])): ?>
                <div class="server-card">
                    <div class="server-card-label"><?= e(__('server.label_rank')) ?></div>
                    <div class="server-card-value server-card-big" style="color: var(--hazard);">#<?= (int)$status['rank'] ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($status['map'])): ?>
                <div class="server-card">
                    <div class="server-card-label"><?= e(__('server.label_map')) ?></div>
                    <div class="server-card-value"><?= e($status['map']) ?></div>
                </div>
                <?php endif; ?>
                <?php $rs = $config['restart'] ?? null; if ($rs): ?>
                <div class="server-card <?= $rs['warn'] ? 'server-card-warn' : '' ?>">
                    <div class="server-card-label"><?= e(__('restart.next')) ?></div>
                    <div class="server-card-value" style="font-size:1.3rem;">
                        🔄 <?= e($rs['at']) ?>
                        <span style="color:var(--dim);font-size:0.7em;"><?= e(__('restart.in', ['t' => $rs['relative']])) ?></span>
                    </div>
                    <?php if ($rs['warn']): ?>
                        <div style="color:var(--rust-2);font-size:0.75rem;margin-top:0.3rem;">⚠ <?= e(__('restart.warn')) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Conectar via Steam -->
            <?php if (!empty($status['ip']) && !empty($status['port'])): ?>
                <div class="connect-block">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--dim); text-transform: uppercase; letter-spacing: 0.1em;"><?= e(__('server.address')) ?></div>
                        <div class="connect-ip"><?= e($status['ip']) ?>:<?= (int)$status['port'] ?></div>
                    </div>
                    <a href="steam://connect/<?= e($status['ip']) ?>:<?= (int)$status['port'] ?>" class="btn">
                        🎮 <?= e(__('server.connect_steam')) ?>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Players online -->
            <?php if ($status['online'] && !empty($players)): ?>
                <h2 style="font-family: var(--font-display); color: var(--bone); font-size: 1.4rem; margin: 3rem 0 1.5rem; letter-spacing: 0.04em;">
                    <?= e(__('server.players_online')) ?> <span style="color: var(--dim); font-family: var(--font-mono); font-size: 1rem;">(<?= count($players) ?>)</span>
                </h2>
                <div class="players-grid">
                    <?php foreach ($players as $p): ?>
                        <div class="player-chip">
                            <span class="player-dot"></span>
                            <span class="player-name" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></span>
                            <?php if ($p['connected_at']): ?>
                                <?php $elapsed = time() - $p['connected_at']; ?>
                                <?php
                                $h = floor($elapsed / 3600);
                                $m = floor(($elapsed % 3600) / 60);
                                $playtime = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                                ?>
                                <span class="player-time"><?= $playtime ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($status['online']): ?>
                <p style="text-align: center; color: var(--dim); margin-top: 3rem;">
                    <?= e(__('server.empty_online')) ?> <strong style="color: var(--hazard);"><?= e(__('server.be_first')) ?></strong>
                </p>
            <?php endif; ?>

            <p class="status-meta">
                <?php if (($status['source'] ?? '') === 'cftools'): ?>
                    <?= e(__('server.source_cftools')) ?>
                <?php else: ?>
                    <?= e(__('server.source_bm')) ?>
                <?php endif; ?>
                <?= e(__('server.last_update')) ?> <?= e(time_ago($status['fetched_at'] ?? time())) ?>
            </p>

        <?php endif; ?>
    </div>
</section>

<style>
.server-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.server-card {
    background: linear-gradient(180deg, var(--bg-2) 0%, var(--bg-1) 100%);
    border: 1px solid var(--border);
    border-left: 3px solid var(--rust);
    padding: 1.2rem 1.4rem;
}
.server-card-label {
    font-size: 0.7rem; color: var(--dim); text-transform: uppercase;
    letter-spacing: 0.1em; margin-bottom: 0.6rem;
}
.server-card-value {
    font-family: var(--font-display); font-size: 1.6rem; color: var(--bone);
    display: flex; align-items: center; gap: 0.6rem;
}
.server-card-big { font-size: 2.4rem; line-height: 1; }
.server-card-warn { border-left-color: var(--rust-2); background: linear-gradient(180deg, rgba(231,57,70,0.08) 0%, var(--bg-1) 100%); }
.server-dot { width: 12px; height: 12px; border-radius: 50%; }
.server-dot-online  { background: var(--moss); box-shadow: 0 0 12px var(--moss); animation: pulse 2s infinite; }
.server-dot-offline { background: var(--rust-2); box-shadow: 0 0 12px var(--rust-2); }

.connect-block {
    background: var(--bg-1);
    border: 1px solid var(--border);
    padding: 1.5rem;
    display: flex; align-items: center; justify-content: space-between;
    gap: 1.5rem; flex-wrap: wrap;
    margin-bottom: 2rem;
}
.connect-ip {
    font-family: var(--font-mono); font-size: 1.4rem; color: var(--hazard);
    user-select: all; margin-top: 0.3rem;
}

.players-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.5rem;
    margin-bottom: 2rem;
}
.player-chip {
    display: flex; align-items: center; gap: 0.5rem;
    background: var(--bg-2);
    border: 1px solid var(--border);
    padding: 0.55rem 0.8rem;
    font-size: 0.85rem;
    color: var(--bone);
    transition: border-color .2s;
}
.player-chip:hover { border-color: var(--moss); }
.player-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--moss); flex-shrink: 0; }
.player-name {
    font-family: var(--font-mono); flex: 1;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.player-time {
    color: var(--dim); font-size: 0.75rem; font-family: var(--font-mono);
    background: var(--bg-0); padding: 0.1rem 0.4rem; border-radius: 2px;
}

.status-meta {
    margin-top: 2rem;
    text-align: center;
    font-size: 0.8rem;
    color: var(--dim);
    font-family: var(--font-mono);
}
</style>

<?php \App\View::endSection(); ?>
