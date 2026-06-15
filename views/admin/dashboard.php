<?php /** @var array $config, $stats, $recent_purchases, $pending_migrations */ ?>
<?php $title = 'Dashboard'; ?>
<?php \App\View::extend('admin.layout'); ?>

<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Dashboard</h1>
        <p>Visão geral do servidor e das compras.</p>
    </div>
    <div class="refresh-control">
        <span id="refresh-status" class="refresh-status">—</span>
        <button id="refresh-toggle" type="button" class="btn-mini outline" title="Pausa/retoma auto-refresh (30s)">⏸ Pausar</button>
    </div>
</div>

<?php if (!empty($pending_migrations)): ?>
    <div style="background:var(--danger-overlay,rgba(231,57,70,.12)); border-left:4px solid var(--hazard,#facc15); padding:1rem 1.2rem; margin-bottom:1.5rem; border-radius:3px;">
        <strong style="color:var(--hazard,#facc15);">⚠ Banco de dados desatualizado</strong>
        <p style="color:var(--bone); margin:.5rem 0 0; font-size:.9rem; line-height:1.6;">
            Há <strong><?= count($pending_migrations) ?> atualização(ões) de banco pendente(s)</strong> — você subiu os arquivos novos mas ainda não rodou a migration.
            Rode <strong>uma vez</strong>: <code style="background:var(--bg-2,#1c1230); padding:.1rem .4rem; border-radius:3px;">php cli/migrate.php</code>
            (sem SSH? Painel da hospedagem → <strong>Cron Jobs</strong> → cron "uma vez" com esse comando, rode e remova).
            Enquanto não rodar, recursos novos podem não funcionar.
            <br><span style="color:var(--dim); font-size:.82rem;">Pendente: <?= e(implode(', ', $pending_migrations)) ?></span>
        </p>
    </div>
<?php endif; ?>

<?php if (empty($config['delivery_active'])): ?>
    <div style="background:var(--danger-overlay,rgba(231,57,70,.12)); border-left:4px solid var(--rust-2); padding:1rem 1.2rem; margin-bottom:1.5rem; border-radius:3px;">
        <strong style="color:var(--text-danger,#ff6b6b);">⚠ Entrega in-game NÃO detectada</strong>
        <p style="color:var(--bone); margin:.5rem 0 0; font-size:.9rem; line-height:1.6;">
            As compras estão sendo <strong>registradas e creditadas no site</strong>, mas <strong>não há quem entregue as moedas/itens dentro do jogo</strong> automaticamente.
            Pra entrega automática você precisa do <strong>Tecplay Agent</strong> (roda no servidor) ou do <strong>Bot Discord</strong> integrado.
            Enquanto não ativar um dos dois, o site funciona como loja/carteira, mas a liberação no jogo é manual — e o site não promete "liberação automática" pros jogadores.
            <br><a href="https://tecplay.inf.br/produtos/detalhe/?slug=tecplay-agent" target="_blank" rel="noopener" style="color:var(--hazard);">Ver o Tecplay Agent →</a>
        </p>
    </div>
<?php endif; ?>

<div class="stats-grid" id="stats-grid">
    <div class="stat-card">
        <div class="label">Jogadores</div>
        <div class="value" data-stat="players_count"><?= number_format($stats['players_count'], 0, ',', '.') ?></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Moedas em circulação</div>
        <div class="value" data-stat="coins_total"><?= number_format($stats['coins_total'], 0, ',', '.') ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Receita total</div>
        <div class="value">R$ <span data-stat="revenue_total"><?= number_format($stats['revenue_total'], 2, ',', '.') ?></span></div>
    </div>
    <div class="stat-card">
        <div class="label">Compras hoje</div>
        <div class="value" data-stat="purchases_today"><?= $stats['purchases_today'] ?></div>
    </div>
    <div class="stat-card success">
        <div class="label">Receita hoje</div>
        <div class="value">R$ <span data-stat="revenue_today"><?= number_format($stats['revenue_today'], 2, ',', '.') ?></span></div>
    </div>
    <div class="stat-card accent">
        <div class="label">Pendentes</div>
        <div class="value" data-stat="pending_count"><?= $stats['pending_count'] ?></div>
    </div>
</div>

<style>
.refresh-control { display: flex; align-items: center; gap: 0.6rem; }
.refresh-status {
    font-family: var(--font-mono); font-size: 0.75rem; color: var(--dim);
    min-width: 9rem; text-align: right;
}
.stat-card .value { transition: color .3s; }
.stat-card .value.changed { color: var(--hazard) !important; }
</style>

<div class="chart-section">
    <h2 style="font-family: var(--font-display); color: var(--bone); font-size: 1.2rem; margin-bottom: 1rem; letter-spacing: 0.04em;">
        Vendas (últimos 30 dias)
    </h2>

    <div class="stat-card" style="padding: 1.5rem; margin-bottom: 2rem;">
        <div style="position: relative; height: 320px;">
            <canvas id="sales-chart"></canvas>
        </div>
    </div>
</div>

<style>
/* Chart escondido em mobile — dashboard mobile vai direto pra "Últimas compras". */
@media (max-width: 760px) {
    .chart-section { display: none; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(async function() {
    // Mobile: pula o chart inteiro (DOM escondido via CSS, mas evita fetch desnecessário)
    if (window.matchMedia('(max-width: 760px)').matches) return;
    const ctx0 = document.getElementById('sales-chart');
    if (!ctx0) return;
    const r = await fetch('/admin/sales-chart.json', {credentials: 'same-origin'});
    if (!r.ok) return;
    const data = await r.json();
    const ctx = document.getElementById('sales-chart').getContext('2d');
    const bone = 'var(--bone)', rust = 'var(--rust)', hazard = 'var(--hazard)', dim = 'var(--dim)', moss = 'var(--moss)';
    Chart.defaults.color = dim;
    Chart.defaults.borderColor = 'rgba(212,197,169,0.08)';
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    type: 'bar', label: '💰 Receita (R$)',
                    data: data.revenues, yAxisID: 'y',
                    backgroundColor: 'rgba(74,222,128,0.55)',
                    borderColor: '#4ade80', borderWidth: 1.5, borderRadius: 3,
                },
                {
                    type: 'line', label: '📦 Compras (quantidade)',
                    data: data.counts, yAxisID: 'y1',
                    borderColor: '#fde047',
                    backgroundColor: 'rgba(253,224,71,0.15)',
                    tension: 0.3, fill: false, pointRadius: 4, pointHoverRadius: 6, borderWidth: 3,
                    pointBackgroundColor: '#fde047', pointBorderColor: '#0a0612', pointBorderWidth: 1.5,
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },

            scales: {
                y:  { position: 'left',  beginAtZero: true,
                      ticks: { color: '#4ade80', callback: v => 'R$ ' + v, font: { size: 11 } },
                      grid: { color: 'rgba(255,255,255,0.06)' } },
                y1: { position: 'right', beginAtZero: true,
                      ticks: { color: '#fde047', stepSize: 1, font: { size: 11 } },
                      grid: { display: false } },
                x:  { ticks: { color: '#cbd5e1', font: { size: 11 } },
                      grid: { color: 'rgba(255,255,255,0.04)' } },
            },
            plugins: {
                // Legend bem visivel: pontos coloridos com tamanho generoso + texto branco
                legend: {
                    position: 'top',
                    align: 'start',
                    labels: {
                        color: '#f3eee0', // bone solido (sem var)
                        padding: 18,
                        font: { size: 13, weight: '600' },
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        boxWidth: 14,
                        boxHeight: 14,
                    },
                },
                tooltip: {
                    backgroundColor: '#0a0612', borderColor: '#c1440e', borderWidth: 1,
                    titleColor: '#f3eee0', bodyColor: '#f3eee0',
                    padding: 10,
                    callbacks: {
                        label: ctx => {
                            const v = ctx.parsed.y;
                            return ctx.dataset.yAxisID === 'y'
                                ? `${ctx.dataset.label}: R$ ${v.toFixed(2).replace('.', ',')}`
                                : `${ctx.dataset.label}: ${v}`;
                        },
                    },
                },
            },
        },
    });
})();
</script>

<h2 style="font-family: var(--font-display); color: var(--bone); font-size: 1.2rem; margin-bottom: 1rem; letter-spacing: 0.04em;">
    Últimas compras
</h2>

<div class="admin-table-wrap">
<table class="admin-table" id="recent-purchases-table">
    <thead>
        <tr>
            <th>SteamID</th>
            <th>Pacote</th>
            <th>Moedas</th>
            <th>Valor</th>
            <th>Status</th>
            <th>Data</th>
        </tr>
    </thead>
    <tbody id="recent-purchases-body">
        <?php if (empty($recent_purchases)): ?>
            <tr><td colspan="6" style="text-align: center; color: var(--dim); padding: 2rem;">Nenhuma compra ainda.</td></tr>
        <?php else: foreach ($recent_purchases as $p): ?>
            <tr>
                <td class="mono"><?= e($p['steam_id']) ?></td>
                <td><?= e($p['package_id']) ?></td>
                <td class="mono"><?= $p['coins_total'] ?></td>
                <td>R$ <?= number_format($p['price_brl'], 2, ',', '.') ?></td>
                <td>
                    <?php
                    $status = $p['mp_status'] ?? 'pending';
                    $cls = match($status) {
                        'approved' => 'success',
                        'rejected', 'cancelled', 'refunded' => 'danger',
                        'pending' => 'warning',
                        default => 'info'
                    };
                    ?>
                    <span class="badge <?= $cls ?>"><?= e($status) ?></span>
                </td>
                <td class="dim"><?= e($p['created_at']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<script>
// ============ Auto-refresh do dashboard ============
(function() {
    const REFRESH_MS = 30000;
    const statusEl = document.getElementById('refresh-status');
    const toggleBtn = document.getElementById('refresh-toggle');
    const tbody = document.getElementById('recent-purchases-body');
    let paused = false;
    let lastFetchAt = Date.now();
    let timer = null;
    let uiTimer = null;

    const formatBRL = v => v.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const formatNum = v => v.toLocaleString('pt-BR');

    function updateStat(key, val, isMoney = false) {
        const el = document.querySelector('[data-stat="' + key + '"]');
        if (!el) return;
        const newText = isMoney ? formatBRL(val) : formatNum(val);
        if (el.textContent !== newText) {
            el.textContent = newText;
            el.classList.add('changed');
            setTimeout(() => el.classList.remove('changed'), 1500);
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function renderPurchases(rows) {
        if (!tbody) return;
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--dim); padding:2rem;">Nenhuma compra ainda.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(p => `
            <tr>
                <td class="mono">${escapeHtml(p.steam_id)}</td>
                <td>${escapeHtml(p.package_id)}</td>
                <td class="mono">${p.coins_total}</td>
                <td>R$ ${p.price_brl_fmt}</td>
                <td><span class="badge ${p.status_class}">${escapeHtml(p.mp_status || 'pending')}</span></td>
                <td class="dim">${escapeHtml(p.created_at)}</td>
            </tr>
        `).join('');
    }

    async function refresh() {
        if (paused) return;
        try {
            const r = await fetch('/admin/dashboard.json', {credentials: 'same-origin'});
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const data = await r.json();
            updateStat('players_count',   data.stats.players_count);
            updateStat('coins_total',     data.stats.coins_total);
            updateStat('revenue_total',   data.stats.revenue_total, true);
            updateStat('purchases_today', data.stats.purchases_today);
            updateStat('revenue_today',   data.stats.revenue_today, true);
            updateStat('pending_count',   data.stats.pending_count);
            renderPurchases(data.recent_purchases || []);
            lastFetchAt = Date.now();
        } catch (e) {
            console.warn('Dashboard refresh failed:', e);
        }
    }

    function updateUi() {
        if (paused) { statusEl.textContent = 'auto-refresh pausado'; return; }
        const ago = Math.round((Date.now() - lastFetchAt) / 1000);
        statusEl.textContent = ago < 5 ? 'atualizado agora' : `atualizado ${ago}s atrás`;
    }

    function start() {
        stop();
        timer   = setInterval(refresh,  REFRESH_MS);
        uiTimer = setInterval(updateUi, 1000);
        lastFetchAt = Date.now();
        updateUi();
    }
    function stop() {
        if (timer)   { clearInterval(timer);   timer = null; }
        if (uiTimer) { clearInterval(uiTimer); uiTimer = null; }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            paused = !paused;
            if (paused) {
                toggleBtn.textContent = '▶ Retomar';
            } else {
                toggleBtn.textContent = '⏸ Pausar';
                refresh();
            }
            updateUi();
        });
    }

    start();
})();
</script>

<?php \App\View::endSection(); ?>
