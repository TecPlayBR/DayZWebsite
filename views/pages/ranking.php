<?php /** @var array $config, $top */ ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background5.png'); // LCP preload sync ?>
<?php \App\View::section('content'); ?>

<section class="hero" style="min-height: 40vh; padding-bottom: 2rem;">
    <div class="hero-bg" style="background-image: linear-gradient(180deg, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.95) 100%), url('<?= asset('img/background5.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// HALL OF FAME</span>
        <h1 class="hero-title">Os <span class="accent">Sobreviventes</span><br>Mais Resistentes.</h1>
        <p class="hero-subtitle">Ranking dos jogadores que mais investiram no servidor. Cada centavo trocado por mais chance de continuar inteiro.</p>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container">

        <?php if (empty($top)): ?>
            <div style="text-align: center; padding: 4rem 1rem; color: var(--dim);">
                <p>Ranking ainda vazio. <a href="/shop" style="color: var(--rust-2);">Seja o primeiro a entrar pra história.</a></p>
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
                            <div class="podium-name"><?= e($p['display_name'] ?? 'Anônimo') ?></div>
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
                                    <strong><?= e($p['display_name'] ?? 'Anônimo') ?></strong>
                                    <div class="rank-steamid"><?= e(substr($p['steam_id'], 0, 8) . '...' . substr($p['steam_id'], -4)) ?></div>
                                </td>
                                <td class="rank-spent">R$ <?= number_format((float)$p['total_spent_brl'], 2, ',', '.') ?></td>
                                <td class="hide-mobile"><?= number_format((int)$p['coins'], 0, ',', '.') ?> moedas</td>
                                <td class="hide-mobile rank-time"><?= e($p['last_seen_at'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p style="text-align: center; margin-top: 2rem; color: var(--dim); font-size: 0.85rem;">
                Total no ranking: <?= count($top) ?> jogadores · Atualizado em tempo real
            </p>

        <?php endif; ?>
    </div>
</section>

<style>
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
