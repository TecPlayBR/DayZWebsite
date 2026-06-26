<?php /** @var array $config; @var string $mode; @var array $gameplay_stats; @var bool $cftools_on */ ?>
<?php
$mode           = $mode ?? 'invest';
$top            = $top ?? [];
$lb             = $lb ?? [];
$gameplay_stats = $gameplay_stats ?? [];
$cftools_on     = $cftools_on ?? false;
$stat           = $stat ?? 'invest';
$rewards        = $rewards ?? [];
$online         = $online ?? [];
// SEO: título/descrição únicos por aba (investimento vs gameplay).
$rkSite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor';
if ($mode === 'gameplay') {
    \App\View::with('title', 'Ranking ' . ($gameplay_stats[$stat] ?? 'Gameplay') . ' — ' . $rkSite . ' DayZ BR');
    \App\View::with('description', 'Os melhores do ' . $rkSite . ' em ' . ($gameplay_stats[$stat] ?? 'combate') . ' — ranking ao vivo, direto do servidor DayZ brasileiro.');
} else {
    \App\View::with('title', 'Ranking de Investimento — ' . $rkSite . ' DayZ BR');
    \App\View::with('description', 'Top dos jogadores que mais apoiam o ' . $rkSite . '. Ranking do servidor DayZ BR, atualizado direto da loja.');
}
?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background5.png'); // LCP preload sync ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 40vh; padding-bottom: 2rem;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background5.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// HALL OF FAME</span>
        <h1 class="hero-title">Os <span class="accent">Sobreviventes</span><br>Mais Resistentes.</h1>
        <p class="hero-subtitle"><?= $mode === 'gameplay' ? 'Os melhores do servidor em combate — direto do jogo.' : 'Ranking dos jogadores que mais investiram no servidor. Cada centavo trocado por mais chance de continuar inteiro.' ?></p>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container">

        <p class="rank-intro">
            Hall of Fame do <strong><?= e($rkSite) ?></strong> — ranking ao vivo dos sobreviventes que mais
            investiram, mais kills acumularam e mais tempo passaram em Chernarus. Os dados atualizam
            automaticamente após cada sessão de jogo. Quer aparecer aqui? Conecte ao servidor, jogue e
            <a href="/shop">compre moedas</a> pra equipar.
        </p>

        <!-- Online agora (CFTools) -->
        <?php if (!empty($online)): ?>
            <div class="online-box">
                <div class="online-head"><span class="online-dot"></span> <?= count($online) ?> online agora</div>
                <?php if (!\App\Settings::getBool('hide_online_players')): ?>
                <div class="online-list">
                    <?php foreach ($online as $o): ?>
                        <a class="online-player" href="/player/<?= e($o['steam_id']) ?>" title="<?= e($o['name']) ?><?= $o['ping'] ? ' · ping ' . (int)$o['ping'] . 'ms' : '' ?>">
                            <?php if (!empty($o['avatar'])): ?>
                                <img src="<?= e($o['avatar']) ?>" alt="" width="22" height="22" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <span><?= clan_tag($o['steam_id']) ?><?= e($o['name']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Abas (só as visíveis — admin escolhe em /admin/rewards) -->
        <?php $invest_visible = $invest_visible ?? true; ?>
        <?php if ($cftools_on && ($invest_visible || !empty($gameplay_stats))): ?>
            <div class="rank-tabs">
                <?php if ($invest_visible): ?>
                    <a class="rank-tab <?= $mode === 'invest' ? 'active' : '' ?>" href="/ranking">Investimento</a>
                <?php endif; ?>
                <?php foreach ($gameplay_stats as $sk => $sl): ?>
                    <a class="rank-tab <?= ($mode === 'gameplay' && $stat === $sk) ? 'active' : '' ?>" href="/ranking?stat=<?= e($sk) ?>"><?= e($sl) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="rank-results">
        <?php if ($mode === 'gameplay'): ?>
            <?php if (empty($lb)): ?>
                <div style="text-align:center; padding:4rem 1rem; color:var(--dim);">
                    <p>Ainda sem dados de <strong><?= e($gameplay_stats[$stat] ?? $stat) ?></strong> — o servidor pode não ter estatísticas registradas ainda.</p>
                </div>
            <?php else: ?>
                <?php
                // Premiação ativa pra esta categoria?
                $rw   = $rewards['cats'][$stat] ?? [];
                $rwOn = !empty($rewards['enabled']) && !empty($rw['enabled']);
                ?>
                <table class="ranking-table">
                    <thead><tr><th style="width:60px;">#</th><th>Jogador</th><th><?= e($gameplay_stats[$stat] ?? $stat) ?></th><?php if ($rwOn): ?><th>🪙 Prêmio</th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php foreach ($lb as $i => $p):
                            $pos  = (int)($p['rank'] ?? $i + 1);
                            $name = $p['latest_name'] ?? '?';
                            $raw  = $p[$stat] ?? 0;
                            if ($stat === 'playtime')       { $val = intdiv((int)$raw, 3600) . 'h ' . intdiv((int)$raw % 3600, 60) . 'm'; }
                            elseif ($stat === 'kdratio')    { $val = number_format((float)$raw, 2, ',', '.'); }
                            elseif ($stat === 'longest_kill') { $val = (int)$raw . ' m'; }
                            else                            { $val = number_format((int)$raw, 0, ',', '.'); }
                            $prize = ($rwOn && $i < 3) ? (int)($rw['coins'][(string)($i + 1)] ?? 0) : 0;
                        ?>
                            <tr<?= $prize > 0 ? ' class="rank-prized"' : '' ?>>
                                <td class="rank-num"><?= $i < 3 ? ['🥇','🥈','🥉'][$i] : '#' . $pos ?></td>
                                <td><strong><?= e($name) ?></strong></td>
                                <td class="rank-spent"><?= e($val) ?></td>
                                <?php if ($rwOn): ?>
                                    <td><?= $prize > 0 ? '<span class="rank-prize">🪙 ' . number_format($prize, 0, ',', '.') . '</span>' : '<span class="dim">—</span>' ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="text-align:center; margin-top:2rem; color:var(--dim); font-size:.85rem;">Dados do servidor via CFTools · atualiza a cada poucos minutos<?= $rwOn ? ' · premiação mensal em moedas pros melhores' : '' ?></p>
            <?php endif; ?>

        <?php elseif (empty($top)): ?>
            <div style="text-align: center; padding: 4rem 1rem;">
                <p style="color: var(--dim); margin-bottom: 1.5rem;">O ranking ainda está vazio. Seja o primeiro a entrar pra história.</p>
                <a href="/shop" class="btn">Comprar e dominar →</a>
            </div>
        <?php else: ?>

            <!-- Pódio (top 3) -->
            <?php $podium = array_slice($top, 0, 3); ?>
            <?php if (count($podium) >= 1): ?>
                <div class="podium">
                    <?php
                    // Ordem visual no pódio: 2º, 1º, 3º
                    $order = [];
                    if (isset($podium[1])) $order[] = [2, $podium[1]];
                    if (isset($podium[0])) $order[] = [1, $podium[0]];
                    if (isset($podium[2])) $order[] = [3, $podium[2]];
                    foreach ($order as [$pos, $p]):
                        $cls = $pos === 1 ? 'podium-1' : ($pos === 2 ? 'podium-2' : 'podium-3');
                        $trophy = $pos === 1 ? '🥇' : ($pos === 2 ? '🥈' : '🥉');
                    ?>
                        <div class="podium-step <?= $cls ?>">
                            <div class="podium-trophy"><?= $trophy ?></div>
                            <div class="podium-pos">#<?= $pos ?></div>
                            <?php $pName = $p['display_name'] ?? 'Anônimo'; ?>
                            <div class="podium-name" title="<?= e($pName) ?>"><?= clan_tag($p['steam_id']) ?><a href="/player/<?= e($p['steam_id']) ?>" style="color:inherit;text-decoration:none;"><?= e($pName) ?></a></div>
                            <div class="podium-value">R$ <?= number_format((float)$p['total_spent_brl'], 2, ',', '.') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Resto do ranking (4º+) -->
            <?php if (count($top) > 3): ?>
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Jogador</th>
                            <th>Total investido</th>
                            <th class="hide-mobile">Saldo atual</th>
                            <th class="hide-mobile">Última atividade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($top, 3) as $i => $p): $pos = $i + 4; ?>
                            <tr>
                                <td class="rank-num">#<?= $pos ?></td>
                                <td>
                                    <strong><?= clan_tag($p['steam_id']) ?><a href="/player/<?= e($p['steam_id']) ?>" style="color:var(--bone);text-decoration:none;"><?= e($p['display_name'] ?? 'Anônimo') ?></a></strong>
                                    <div class="rank-steamid"><?= e(substr($p['steam_id'], 0, 8) . '...' . substr($p['steam_id'], -4)) ?></div>
                                </td>
                                <td class="rank-spent">R$ <?= number_format((float)$p['total_spent_brl'], 2, ',', '.') ?></td>
                                <td class="hide-mobile"><?= number_format((int)$p['coins'], 0, ',', '.') ?> moedas</td>
                                <td class="hide-mobile rank-time"><?= e(time_ago($p['last_seen_at'] ?? null, 'nunca conectou')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p style="text-align: center; margin-top: 2rem; color: var(--dim); font-size: 0.85rem;">
                Total no ranking: <?= count($top) ?> jogadores · Atualizado em tempo real
            </p>

        <?php endif; ?>
        </div><!-- /#rank-results -->
    </div>
</section>

<script>
// Troca de aba do ranking SEM recarregar a página (mantém o scroll, atualiza a URL).
// Fallback: se o fetch falhar ou JS estiver off, os links funcionam normal.
(function(){
  function setActive(url){
    document.querySelectorAll('.rank-tab').forEach(function(t){
      t.classList.toggle('active', t.getAttribute('href') === url);
    });
  }
  function load(url, push){
    var box = document.getElementById('rank-results');
    if (!box) { window.location = url; return; }
    box.style.opacity = '0.45';
    fetch(url).then(function(r){ return r.text(); }).then(function(html){
      var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('rank-results');
      box.innerHTML = fresh ? fresh.innerHTML : box.innerHTML;
      box.style.opacity = '';
      setActive(url);
      if (push) history.pushState({}, '', url);
    }).catch(function(){ window.location = url; });
  }
  document.addEventListener('click', function(e){
    var a = e.target.closest('.rank-tab');
    if (!a) return;
    e.preventDefault();
    load(a.getAttribute('href'), true);
  });
  window.addEventListener('popstate', function(){ load(location.pathname + location.search, false); });
})();
</script>

<style>
#rank-results { transition: opacity .15s ease; }
.rank-intro { max-width:780px; margin:0 auto 2rem; text-align:center; color:var(--dim); font-size:0.92rem; line-height:1.7; }
.rank-intro strong { color:var(--bone); }
.rank-intro a { color:var(--hazard); text-decoration:none; }
.rank-intro a:hover { text-decoration:underline; }
.online-box { background:var(--bg-1); border:1px solid var(--border); border-left:3px solid var(--moss); border-radius:4px; padding:1rem 1.2rem; margin-bottom:1.5rem; }
.online-head { font-family:var(--font-display); color:var(--bone); font-size:.95rem; margin-bottom:.7rem; display:flex; align-items:center; gap:.5rem; }
.online-dot { width:9px; height:9px; border-radius:50%; background:var(--moss); box-shadow:0 0 8px var(--moss); animation:onlinePulse 2s infinite; }
@keyframes onlinePulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }
.online-list { display:flex; flex-wrap:wrap; gap:.6rem; }
.online-player { display:flex; align-items:center; gap:.4rem; background:var(--bg-2); border:1px solid var(--border); border-radius:3px; padding:.3rem .6rem; color:var(--bone); text-decoration:none; font-size:.82rem; }
.online-player:hover { border-color:var(--moss); }
.online-player img { width:22px; height:22px; border-radius:2px; }
.rank-tabs { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:3rem; justify-content:center; }
#rank-results { margin-top:1rem; }
.rank-tab {
    padding:.5rem 1rem; border:1px solid var(--border); border-radius:3px;
    color:var(--dim); text-decoration:none; font-size:.85rem; letter-spacing:.03em;
    background:var(--bg-1); transition:all .15s;
}
.rank-tab:hover { color:var(--bone); border-color:var(--rust); }
.rank-tab.active { background:var(--rust); color:var(--bone); border-color:var(--rust); }
.rank-prize { color:var(--hazard); font-family:var(--font-display); white-space:nowrap; }
.rank-prized td { background:rgba(217,164,65,.06); }
.podium {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1rem;
    margin: 2rem 0 3rem;
    align-items: end;
}
.podium-step {
    background: linear-gradient(180deg, var(--bg-2) 0%, var(--bg-1) 100%);
    border: 1px solid var(--border);
    padding: 1.5rem 1rem 2rem;
    text-align: center;
    border-radius: 2px;
    position: relative;
}
.podium-1 {
    border-color: var(--hazard);
    box-shadow: 0 0 30px var(--hazard-border);
    padding-top: 2.5rem;
    padding-bottom: 3rem;
    transform: translateY(-20px);
}
.podium-2 { border-color: rgba(212,197,169,0.4); }
.podium-3 { border-color: var(--rust); opacity: 0.95; }
.podium-trophy { font-size: 3rem; margin-bottom: 0.5rem; line-height: 1; }
.podium-pos {
    font-family: var(--font-display); font-size: 1.6rem; color: var(--dim); letter-spacing: 0.05em;
}
.podium-1 .podium-pos { color: var(--hazard); font-size: 2rem; }
.podium-name {
    font-family: var(--font-mono);
    color: var(--bone); margin: 0.8rem 0 0.4rem;
    font-size: 1rem;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.podium-value {
    font-family: var(--font-display);
    color: var(--moss);
    font-size: 1.3rem;
}
.podium-1 .podium-value { color: var(--hazard); font-size: 1.5rem; }

.ranking-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-1);
    border: 1px solid var(--border);
    font-size: 0.9rem;
}
.ranking-table th {
    background: var(--bg-2);
    color: var(--dim);
    text-align: left;
    padding: 0.85rem 1rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border-bottom: 2px solid var(--rust);
}
.ranking-table td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--bone);
}
.ranking-table tr:hover td { background: rgba(255,255,255,0.02); }
.rank-num {
    font-family: var(--font-display);
    color: var(--rust);
    font-size: 1.1rem;
}
.rank-steamid {
    font-family: var(--font-mono);
    font-size: 0.7rem;
    color: var(--dim);
    margin-top: 0.2rem;
}
.rank-spent {
    color: var(--hazard);
    font-family: var(--font-display);
    font-size: 1.05rem;
}
.rank-time { color: var(--dim); font-size: 0.8rem; }

@media (max-width: 700px) {
    .podium { grid-template-columns: 1fr; }
    .podium-1 { transform: none; order: -1; }
    .hide-mobile { display: none; }
}
</style>

<?php \App\View::endSection(); ?>
