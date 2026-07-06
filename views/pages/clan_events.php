<?php /** @var array $config, $my_clan, $events; @var bool $is_leader; @var ?array $steam_user */ ?>
<?php $ceSite = $config['settings']['site_name'] ?? $config['site_name'] ?? 'Servidor'; ?>
<?php \App\View::with('title', 'Eventos de Clã - ' . $ceSite); ?>
<?php \App\View::with('description', 'Competições entre clãs do ' . $ceSite . ' - placar ao vivo dos eventos de clã no DayZ.'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background5.png'); ?>
<?php \App\View::section('content'); ?>
<?php
$okMsg = match($_GET['ok'] ?? '') {
    'registered'   => 'Clã inscrito no evento! Agora é esperar começar.',
    'unregistered' => 'Inscrição cancelada.',
    default => '',
};
$errMsg = match($_GET['err'] ?? '') {
    'not_owner' => 'Só o líder do clã pode inscrever no evento.',
    'already'   => 'Seu clã já está inscrito nesse evento.',
    'started'   => 'O evento já começou - não dá mais pra mexer na inscrição.',
    'not_found' => 'Evento não encontrado.',
    'csrf'      => 'Sessão expirada, tente de novo.',
    default => '',
};
// Formata o valor do placar conforme a métrica.
$ceFmt = static function (int $v, string $metric): string {
    if ($metric === 'playtime_seconds') {
        $h = intdiv($v, 3600); $m = intdiv($v % 3600, 60);
        return $h > 0 ? ($h . 'h ' . $m . 'm') : ($m . 'm');
    }
    return number_format($v, 0, ',', '.');
};
$my_clan = $my_clan ?? null;
$myId = $my_clan ? (int)$my_clan['id'] : 0;
?>

<section class="hero" style="min-height:34vh;padding-bottom:1.5rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.5) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background5.png') ?>');"></div>
    <div class="container hero-content">
        <span class="hero-kicker">// GUERRA DE FACÇÕES</span>
        <h1 class="hero-title">Eventos de <span class="accent">Clã</span></h1>
        <?php if ($my_clan): ?>
            <p class="hero-subtitle">Seu clã: <strong style="color:var(--hazard);">[<?= e($my_clan['tag']) ?>] <?= e($my_clan['name']) ?></strong>. Os contadores só somam o que rolar <strong>durante</strong> cada evento.</p>
        <?php else: ?>
            <p class="hero-subtitle">Competições entre facções. <strong>Entre num clã</strong> pra disputar e ver o placar.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width:900px;">
        <div class="rank-tabs" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:2rem;justify-content:center;">
            <a class="rank-tab" href="/ranking" style="padding:.5rem 1.1rem;border:1px solid var(--border);border-radius:2px;color:var(--dim);text-decoration:none;font-family:var(--font-mono);font-size:.8rem;">← Ranking geral</a>
            <span class="rank-tab active" style="padding:.5rem 1.1rem;border:1px solid var(--rust);border-radius:2px;background:var(--rust);color:var(--bone);font-family:var(--font-mono);font-size:.8rem;">🛡 Clãs</span>
        </div>

        <?php if ($okMsg): ?><div class="ce-flash ok"><?= e($okMsg) ?></div><?php endif; ?>
        <?php if ($errMsg): ?><div class="ce-flash err"><?= e($errMsg) ?></div><?php endif; ?>

        <?php if (!$my_clan): ?>
            <div style="text-align:center;color:var(--bone);padding:3rem 1.2rem;background:var(--bg-1);border:1px solid var(--hazard);border-radius:8px;">
                <div style="font-size:2.2rem;margin-bottom:.6rem;">🛡</div>
                <h2 style="font-family:var(--font-display);color:var(--bone);margin:0 0 .6rem;">Eventos são só pra clãs</h2>
                <p style="color:var(--dim);max-width:480px;margin:0 auto 1.2rem;">As competições de clã (placar, prêmios) só aparecem pra quem faz parte de um clã. Entre ou crie o seu pra disputar.</p>
                <a href="/clans" class="btn">Ver clãs / criar o meu →</a>
            </div>
        <?php elseif (empty($events)): ?>
            <div style="text-align:center;color:var(--dim);padding:3rem 1rem;background:var(--bg-1);border:1px dashed var(--border);border-radius:6px;">
                Nenhum evento de clã no momento. Fica de olho - quando rolar, aparece aqui e o <strong>líder</strong> inscreve a facção. 🛡
            </div>
        <?php else: ?>
            <?php foreach ($events as $row): $ev = $row['ev']; $phase = $row['phase']; $scores = $row['scores']; $metric = $ev['metric']; ?>
                <article class="ce-card">
                    <div class="ce-head">
                        <div style="min-width:0;">
                            <span class="ce-badge ce-<?= $phase ?>"><?= e(\App\ClanEvent::phaseLabel($phase)) ?></span>
                            <h2 class="ce-title"><?= e($ev['title']) ?></h2>
                            <p class="ce-meta">🎯 <?= e(\App\ClanEvent::metricLabel($metric)) ?> · 🗓 <?= date('d/m H:i', strtotime($ev['starts_at'])) ?> → <?= date('d/m H:i', strtotime($ev['ends_at'])) ?></p>
                        </div>
                    </div>

                    <?php if (!empty($ev['description'])): ?><p class="ce-desc"><?= nl2br(e($ev['description'])) ?></p><?php endif; ?>
                    <?php if (!empty($ev['prize'])): ?><p class="ce-prize">🏆 <strong>Prêmio:</strong> <?= e($ev['prize']) ?></p><?php endif; ?>

                    <!-- Inscrição do clã do jogador -->
                    <div class="ce-reg">
                        <?php if ($row['registered']): ?>
                            <span class="ce-reg-on">✓ Seu clã está inscrito</span>
                            <?php if ($phase === 'scheduled' && $is_leader): ?>
                                <form method="POST" action="/clan-events/<?= (int)$ev['id'] ?>/unregister" onsubmit="return confirm('Cancelar a inscrição do clã nesse evento?');" style="margin:0;">
                                    <?= \App\Csrf::field() ?><button class="btn-mini outline">Cancelar inscrição</button>
                                </form>
                            <?php endif; ?>
                        <?php elseif ($phase === 'scheduled'): ?>
                            <?php if ($is_leader): ?>
                                <form method="POST" action="/clan-events/<?= (int)$ev['id'] ?>/register" style="margin:0;">
                                    <?= \App\Csrf::field() ?><button class="btn">Inscrever meu clã</button>
                                </form>
                            <?php else: ?>
                                <span class="ce-reg-off">Seu clã ainda não está inscrito - <strong>avise o líder</strong> pra inscrever.</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="ce-reg-off">Seu clã não participou deste evento.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Placar -->
                    <?php if (!empty($scores)): ?>
                        <table class="ce-board">
                            <thead><tr><th>#</th><th>Clã</th><th style="text-align:right;"><?= e(\App\ClanEvent::metricLabel($metric)) ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($scores as $i => $s): $mine = (int)$s['clan_id'] === $myId; ?>
                                <tr class="<?= $mine ? 'ce-mine' : '' ?>">
                                    <td class="ce-rank"><?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ($i + 1) . 'º')) ?></td>
                                    <td><a href="/clan/<?= (int)$s['clan_id'] ?>" class="ce-clan">[<?= e($s['tag']) ?>] <?= e($s['name']) ?></a></td>
                                    <td class="ce-score"><?= $ceFmt((int)$s['score'], $metric) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($phase === 'active'): ?><p class="ce-live">● Placar ao vivo - atualiza conforme o jogo é jogado.</p><?php endif; ?>
                        <?php if ($phase === 'ended'): ?><p class="ce-live" style="color:var(--dim);">Resultado final (congelado).</p><?php endif; ?>
                    <?php elseif ($phase === 'scheduled'): ?>
                        <p class="ce-empty">Clãs inscritos vão aparecer aqui. O placar começa a contar quando o evento iniciar.</p>
                    <?php else: ?>
                        <p class="ce-empty">Nenhum clã inscrito participou.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <p style="text-align:center;margin-top:2rem;"><a href="<?= $my_clan ? '/clan/'.$myId : '/clans' ?>" class="btn btn-outline"><?= $my_clan ? '← Meu clã' : '← Todos os clãs' ?></a></p>
    </div>
</section>

<style>
.ce-flash { padding:.8rem 1.1rem; border-radius:4px; margin-bottom:1.2rem; }
.ce-flash.ok { background:rgba(90,108,78,0.18); border-left:3px solid var(--moss); color:var(--text-success); }
.ce-flash.err { background:rgba(231,76,60,0.18); border-left:3px solid var(--rust-2); color:var(--text-danger); }
.ce-card { background:var(--bg-1); border:1px solid var(--border); border-radius:8px; padding:1.3rem 1.5rem; margin-bottom:1.5rem; }
.ce-head { display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
.ce-badge { display:inline-block; font-family:var(--font-mono); font-size:.7rem; padding:.2rem .6rem; border-radius:2px; letter-spacing:.05em; text-transform:uppercase; margin-bottom:.5rem; }
.ce-scheduled { background:var(--hazard-border); color:var(--hazard); }
.ce-active { background:rgba(90,108,78,0.25); color:var(--text-success); }
.ce-ended { background:rgba(160,160,160,0.18); color:var(--dim); }
.ce-title { font-family:var(--font-display); color:var(--bone); font-size:1.25rem; margin:0 0 .3rem; }
.ce-meta { color:var(--dim); font-size:.82rem; font-family:var(--font-mono); margin:0; }
.ce-desc { color:var(--bone); line-height:1.6; margin:1rem 0 .5rem; }
.ce-prize { color:var(--hazard); font-size:.9rem; margin:.3rem 0 0; }
.ce-reg { display:flex; align-items:center; gap:.8rem; flex-wrap:wrap; margin:1.1rem 0; padding:.8rem 1rem; background:var(--bg-0); border:1px solid var(--border); border-radius:5px; }
.ce-reg-on { color:var(--text-success); font-weight:600; font-size:.9rem; }
.ce-reg-off { color:var(--dim); font-size:.88rem; }
.ce-board { width:100%; border-collapse:collapse; font-size:.92rem; }
.ce-board th { text-align:left; color:var(--dim); font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; padding:.5rem .6rem; border-bottom:2px solid var(--rust); }
.ce-board td { padding:.55rem .6rem; border-bottom:1px solid var(--border); color:var(--bone); }
.ce-board .ce-rank { width:42px; font-family:var(--font-mono); }
.ce-clan { color:var(--bone); text-decoration:none; }
.ce-clan:hover { color:var(--hazard); }
.ce-score { text-align:right; font-family:var(--font-mono); color:var(--hazard); font-weight:700; }
.ce-mine { background:var(--hazard-overlay); }
.ce-mine .ce-clan { color:var(--hazard); }
.ce-live { font-size:.78rem; color:var(--text-success); margin:.6rem 0 0; }
.ce-empty { color:var(--dim); font-size:.85rem; font-style:italic; margin:.8rem 0 0; }
@media (max-width:600px){ .ce-board { display:block; overflow-x:auto; } }
</style>
<?php \App\View::endSection(); ?>
