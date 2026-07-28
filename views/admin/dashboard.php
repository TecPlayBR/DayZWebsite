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
        <span id="refresh-status" class="refresh-status">-</span>
        <button id="refresh-toggle" type="button" class="btn-mini outline" title="Pausa/retoma auto-refresh (30s)">⏸ Pausar</button>
    </div>
</div>

<?php if (!empty($pending_migrations)): ?>
    <div style="background:var(--danger-overlay,rgba(231,57,70,.12)); border-left:4px solid var(--hazard,#facc15); padding:1rem 1.2rem; margin-bottom:1.5rem; border-radius:3px;">
        <strong style="color:var(--hazard,#facc15);">⚠ Banco de dados desatualizado</strong>
        <p style="color:var(--bone); margin:.5rem 0 0; font-size:.9rem; line-height:1.6;">
            Há <strong><?= count($pending_migrations) ?> atualização(ões) de banco pendente(s)</strong> - você subiu os arquivos novos mas ainda não rodou a migration.
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
            Enquanto não ativar um dos dois, o site funciona como loja/carteira, mas a liberação no jogo é manual - e o site não promete "liberação automática" pros jogadores.
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
/* Chart escondido em mobile - dashboard mobile vai direto pra "Últimas compras". */
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
            layout: { padding: { top: 8 } },  // respiro pra legenda nao encostar no 1o tick do eixo Y

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
                    align: 'center',
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

<?php
$insOn = $insights_on ?? ['pay'=>true,'pkgs'=>true,'status'=>true,'grants'=>true,'coins'=>true,'boxes'=>true,'newplayers'=>true,'cupons'=>true,'afiliados'=>true,'reviews'=>true];
foreach (['pay','pkgs','status','grants','coins','boxes','newplayers','cupons','afiliados','reviews'] as $_k) { $insOn[$_k] = $insOn[$_k] ?? true; }
$insMeta = ['pay'=>'💳 Forma de pagamento','pkgs'=>'🏆 Top pacotes','status'=>'✅ Pagamentos','grants'=>'💎 VIP & Passe','coins'=>'🪙 Economia de moedas','boxes'=>'🎁 Caixas & raridade','newplayers'=>'📈 Novos jogadores','cupons'=>'🎟 Cupons','afiliados'=>'🤝 Afiliados','reviews'=>'⭐ Avaliações'];
?>
<div class="ins-head">
    <h2 style="font-family: var(--font-display); color: var(--bone); font-size: 1.2rem; margin: 0; letter-spacing: 0.04em;">
        Insights <span style="color:var(--dim);font-size:0.8rem;font-family:var(--font-mono);">// últimos 30 dias</span>
    </h2>
    <div class="ins-cfg-wrap">
        <button type="button" id="ins-cfg-btn" class="btn-mini outline" title="Escolher quais widgets aparecem">⚙ Widgets</button>
        <div id="ins-cfg" class="ins-cfg" hidden>
            <div class="ins-cfg-title">Mostrar no dashboard:</div>
            <?php foreach ($insMeta as $k => $lbl): ?>
            <label><input type="checkbox" data-widget="<?= e($k) ?>" <?= $insOn[$k] ? 'checked' : '' ?>> <?= e($lbl) ?></label>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="insights-grid">
    <?php if ($insOn['pay']): ?><div class="insight-card"><h3>💳 Forma de pagamento</h3><div class="ic-canvas"><canvas id="ins-pay"></canvas></div></div><?php endif; ?>
    <?php if ($insOn['pkgs']): ?><div class="insight-card"><h3>🏆 Top pacotes</h3><div class="ic-canvas"><canvas id="ins-pkgs"></canvas></div></div><?php endif; ?>
    <?php if ($insOn['status']): ?><div class="insight-card"><h3>✅ Pagamentos</h3><div id="ins-status" class="ic-body"></div></div><?php endif; ?>
    <?php if ($insOn['grants']): ?><div class="insight-card"><h3>💎 VIP &amp; Passe</h3><div id="ins-grants" class="ic-body"></div></div><?php endif; ?>
    <?php if ($insOn['coins']): ?><div class="insight-card"><h3>🪙 Economia de moedas</h3><div class="ic-canvas"><canvas id="ins-coins"></canvas></div></div><?php endif; ?>
    <?php if ($insOn['boxes']): ?><div class="insight-card"><h3>🎁 Caixas &amp; raridade</h3><div class="ic-canvas"><canvas id="ins-boxes"></canvas></div></div><?php endif; ?>
    <?php if ($insOn['newplayers']): ?><div class="insight-card"><h3>📈 Novos jogadores</h3><div class="ic-canvas"><canvas id="ins-newplayers"></canvas></div></div><?php endif; ?>
    <?php if ($insOn['cupons']): ?><div class="insight-card"><h3>🎟 Cupons</h3><div id="ins-cupons" class="ic-body"></div></div><?php endif; ?>
    <?php if ($insOn['afiliados']): ?><div class="insight-card"><h3>🤝 Afiliados</h3><div id="ins-afiliados" class="ic-body"></div></div><?php endif; ?>
    <?php if ($insOn['reviews']): ?><div class="insight-card"><h3>⭐ Avaliações</h3><div id="ins-reviews" class="ic-body"></div></div><?php endif; ?>
    <?php if (!array_filter($insOn)): ?><div class="ins-empty" style="grid-column:1/-1">Todos os widgets estão ocultos. Clique em <b>⚙ Widgets</b> pra mostrar.</div><?php endif; ?>
</div>
<style>
/* Responsivo FLUIDO (mesmo padrao dos stat-cards do admin): auto-fit min 320px.
   Celular = 1 col, notebook = 2-3, desktop = 3-4, telas grandes/27" = 4-6 - sem breakpoint fixo. */
.insights-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1rem; margin-bottom:2rem; }
@media (min-width:1900px){ .insights-grid{ gap:1.25rem; } .ic-canvas{ height:210px; } .ic-body{ min-height:210px; } }
.insight-card { background:var(--bg-1); border:1px solid var(--border); border-left:3px solid var(--moss); border-radius:8px; padding:1rem 1.1rem; }
.insight-card h3 { color:var(--bone); font-family:var(--font-display); font-size:0.9rem; letter-spacing:0.04em; margin:0 0 0.8rem; padding-bottom:0.5rem; border-bottom:1px solid var(--border); }
.ic-canvas { position:relative; height:190px; }
.ic-body { min-height:190px; }
.ins-empty { display:flex; align-items:center; justify-content:center; height:190px; color:var(--dim); font-size:0.82rem; }
.ins-pills { display:flex; gap:0.5rem; flex-wrap:wrap; }
.ins-pills .pill { flex:1; min-width:70px; background:var(--bg-0); border:1px solid var(--border); border-radius:6px; padding:0.6rem; text-align:center; }
.ins-pills .pill b { display:block; font-size:1.4rem; font-family:var(--font-display); }
.ins-pills .pill span { font-size:0.68rem; color:var(--dim); text-transform:uppercase; letter-spacing:0.05em; }
.pill.ok b{color:var(--moss);} .pill.bad b{color:var(--rust-2,#ef4444);} .pill.warn b{color:var(--hazard);}
.pill.vip b{color:#a78bfa;} .pill.passe b{color:var(--hazard);}
.ins-conv { margin-top:0.7rem; color:var(--dim); font-size:0.85rem; text-align:center; }
.ins-conv b { color:var(--bone); }
.ins-sub { margin:0.9rem 0 0.4rem; font-size:0.72rem; color:var(--dim); text-transform:uppercase; letter-spacing:0.06em; }
.ins-list { list-style:none; margin:0; padding:0; font-size:0.82rem; max-height:110px; overflow-y:auto; }
.ins-list li { padding:0.3rem 0; border-bottom:1px solid var(--border); color:var(--bone); }
.ins-list .tag { background:var(--bg-0); border:1px solid var(--border); border-radius:4px; padding:0 0.35rem; font-size:0.7rem; }
.ins-list .dim, .ins-list .dim li { color:var(--dim); }
.ins-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin:2rem 0 1rem; flex-wrap:wrap; }
.ins-cfg-wrap { position:relative; }
.ins-cfg { position:absolute; right:0; top:calc(100% + 6px); z-index:30; background:var(--bg-1); border:1px solid var(--border); border-radius:8px; padding:0.7rem 0.9rem; min-width:230px; box-shadow:0 12px 30px rgba(0,0,0,0.55); }
.ins-cfg-title { font-size:0.7rem; color:var(--dim); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:0.5rem; }
.ins-cfg label { display:flex; align-items:center; gap:0.5rem; padding:0.35rem 0; color:var(--bone); font-size:0.85rem; cursor:pointer; }
.ins-cfg input { accent-color:var(--moss); }
</style>
<script>
(async function(){
    let d;
    try { d = await (await fetch('/admin/insights.json', {credentials:'same-origin'})).json(); }
    catch(e){ return; }
    if (!window.Chart) return;
    const C = { green:'#4ade80', yellow:'#fde047', red:'#ef4444', blue:'#60a5fa', purple:'#a78bfa', dim:'#94a3b8' };
    Chart.defaults.color = C.dim; Chart.defaults.font.size = 11;
    const money = v => 'R$ ' + Number(v).toLocaleString('pt-BR',{minimumFractionDigits:0});
    // Escapa string controlada pelo jogador (nick, nome de cupom) antes de ir pro innerHTML.
    // Nick vem do Steam/in-game -> um jogador poderia injetar <script>/<img onerror> aqui.
    const esc = s => { const d=document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; };
    function empty(id){ const c=document.getElementById(id); if(c&&c.parentElement) c.parentElement.innerHTML='<div class="ins-empty">Sem dados ainda.</div>'; }

    // 1) Forma de pagamento (rosca, por receita)
    const payK = Object.keys(d.pay), payV = payK.map(k=>d.pay[k].rev);
    const cPay = document.getElementById('ins-pay');
    if (cPay && payV.some(v=>v>0)) new Chart(cPay, { type:'doughnut',
        data:{ labels:payK, datasets:[{ data:payV, backgroundColor:[C.green,C.blue,C.dim], borderColor:'#0a0612', borderWidth:2 }] },
        options:{ maintainAspectRatio:false, cutout:'60%', plugins:{ legend:{ position:'bottom' },
            tooltip:{ callbacks:{ label:c=>c.label+': '+money(c.parsed) } } } } });
    else empty('ins-pay');

    // 2) Top pacotes (barra horizontal, por receita)
    const cPkgs = document.getElementById('ins-pkgs');
    if (cPkgs && d.top_pkgs && d.top_pkgs.length) new Chart(cPkgs, { type:'bar',
        data:{ labels:d.top_pkgs.map(p=>(p.icon?p.icon+' ':'')+p.name),
            datasets:[{ data:d.top_pkgs.map(p=>Number(p.rev)), backgroundColor:'rgba(74,222,128,0.55)', borderColor:C.green, borderWidth:1, borderRadius:3 }] },
        options:{ indexAxis:'y', maintainAspectRatio:false, plugins:{ legend:{ display:false },
            tooltip:{ callbacks:{ label:c=>money(c.parsed.x) } } }, scales:{ x:{ ticks:{ callback:money } } } } });
    else empty('ins-pkgs');

    // 3) Pagamentos (pills + conversão)
    const s=d.status, tot=s.approved+s.rejected+s.pending, conv=tot?Math.round(s.approved/tot*100):0;
    const stEl=document.getElementById('ins-status'); if (stEl) stEl.innerHTML =
        '<div class="ins-pills">'
        + '<div class="pill ok"><b>'+s.approved+'</b><span>aprovados</span></div>'
        + '<div class="pill bad"><b>'+s.rejected+'</b><span>recusados</span></div>'
        + '<div class="pill warn"><b>'+s.pending+'</b><span>pendentes</span></div></div>'
        + '<div class="ins-conv">Taxa de aprovação: <b>'+conv+'%</b></div>';

    // 4) VIP & Passe (ativos + expirando)
    const g=d.grants;
    const ex = (g.expiring && g.expiring.length)
        ? g.expiring.map(e=>'<li>'+esc(e.nickname||e.steam_id)+' <span class="tag">'+(e.type==='vip'?'VIP':'Passe')+'</span> <span class="dim">'+esc(e.expiration_date)+'</span></li>').join('')
        : '<li class="dim">Ninguém expira em 7 dias.</li>';
    const grEl=document.getElementById('ins-grants'); if (grEl) grEl.innerHTML =
        '<div class="ins-pills"><div class="pill vip"><b>'+g.vip+'</b><span>VIP ativos</span></div>'
        + '<div class="pill passe"><b>'+g.passe+'</b><span>Passe ativos</span></div></div>'
        + '<div class="ins-sub">Expirando em 7 dias</div><ul class="ins-list">'+ex+'</ul>';

    // 5) Economia de moedas (entraram x saíram)
    const cCoins = document.getElementById('ins-coins');
    if (cCoins) new Chart(cCoins, { type:'bar',
        data:{ labels:['Entraram','Saíram'], datasets:[{ data:[d.coins.faucet, d.coins.sink], backgroundColor:[C.green,C.red], borderRadius:4 }] },
        options:{ maintainAspectRatio:false, plugins:{ legend:{ display:false },
            tooltip:{ callbacks:{ label:c=>Number(c.parsed.y).toLocaleString('pt-BR')+' 🪙' } } } } });

    // 6) Caixas & raridade (rosca)
    const cBoxes = document.getElementById('ins-boxes');
    if (cBoxes) {
        const rar = (d.boxes && d.boxes.rarity) || [];
        if (rar.length) {
            const rc = { common:'#94a3b8', uncommon:'#4ade80', rare:'#60a5fa', epic:'#a78bfa', legendary:'#fbbf24', mythic:'#f43f5e' };
            new Chart(cBoxes, { type:'doughnut',
                data:{ labels:rar.map(r=>r.rarity), datasets:[{ data:rar.map(r=>Number(r.c)), backgroundColor:rar.map(r=>rc[String(r.rarity).toLowerCase()]||'#94a3b8'), borderColor:'#0a0612', borderWidth:2 }] },
                options:{ maintainAspectRatio:false, cutout:'58%', plugins:{ legend:{ position:'bottom' },
                    title:{ display:true, text:((d.boxes.total)||0)+' caixas abertas (30d)', color:C.dim, font:{size:11} } } } });
        } else empty('ins-boxes');
    }

    // 7) Novos jogadores (linha 30d)
    const cNp = document.getElementById('ins-newplayers');
    if (cNp) {
        const np = d.newplayers || {days:[],data:[]};
        if (np.data && np.data.some(v=>v>0)) new Chart(cNp, { type:'line',
            data:{ labels:np.days, datasets:[{ data:np.data, borderColor:C.blue, backgroundColor:'rgba(96,165,250,0.15)', fill:true, tension:0.3, pointRadius:2, borderWidth:2 }] },
            options:{ maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } } } });
        else empty('ins-newplayers');
    }

    // 8) Cupons (pills)
    const cCup = document.getElementById('ins-cupons');
    if (cCup) { const cc = d.cupons || {}; cCup.innerHTML =
        '<div class="ins-pills"><div class="pill ok"><b>'+money(cc.discount||0)+'</b><span>desconto dado</span></div>'
        + '<div class="pill"><b>'+(cc.uses||0)+'</b><span>compras c/ cupom</span></div></div>'
        + '<div class="ins-conv">Cupons ativos: <b>'+(cc.active||0)+'</b></div>'; }

    // 9) Afiliados (lista por usos)
    const cAff = document.getElementById('ins-afiliados');
    if (cAff) { const a = d.afiliados || [];
        cAff.innerHTML = a.length
            ? '<ul class="ins-list">'+a.map(x=>'<li>'+esc(x.name)+' <span class="dim">'+esc(x.used_count)+' usos</span></li>').join('')+'</ul>'
            : '<div class="ins-empty">Nenhum cupom de afiliado ainda.</div>'; }

    // 10) Avaliações (nota + estrelas)
    const cRv = document.getElementById('ins-reviews');
    if (cRv) { const r = d.reviews || {count:0,avg:0}; const full = Math.max(0, Math.min(5, Math.round(r.avg)));
        cRv.innerHTML = '<div style="text-align:center;padding:1.1rem 0">'
            + '<div style="font-size:2.4rem;font-family:var(--font-display);color:var(--hazard)">'+Number(r.avg).toFixed(1)+'</div>'
            + '<div style="color:#fbbf24;font-size:1.3rem;letter-spacing:3px">'+('★'.repeat(full))+('☆'.repeat(5-full))+'</div>'
            + '<div class="dim" style="margin-top:.5rem">'+(r.count||0)+' avaliações aprovadas</div></div>'; }
})();

// ===== Config: mostrar/ocultar cada widget de Insights =====
(function(){
    const btn = document.getElementById('ins-cfg-btn');
    const panel = document.getElementById('ins-cfg');
    if (!btn || !panel) return;
    btn.addEventListener('click', (e) => { e.stopPropagation(); panel.hidden = !panel.hidden; });
    document.addEventListener('click', (e) => { if (!panel.hidden && !panel.contains(e.target) && e.target !== btn) panel.hidden = true; });
    const CSRF = <?= json_encode(\App\Csrf::token()) ?>;
    panel.querySelectorAll('input[data-widget]').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.disabled = true;
            const body = new URLSearchParams({ key: cb.dataset.widget, on: cb.checked ? '1' : '', _csrf: CSRF });
            fetch('/admin/insights-toggle', { method:'POST',
                headers:{ 'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-TOKEN':CSRF },
                body: body.toString(), credentials:'same-origin' })
                .then(() => location.reload()).catch(() => location.reload());
        });
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
