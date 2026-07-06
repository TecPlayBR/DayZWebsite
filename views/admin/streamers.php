<?php /** @var array $config, $streamers; @var bool $affiliate_on, $allow_switch */ ?>
<?php $title = 'Streamers'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<?php
$money = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$pct   = fn($v) => rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ',') . '%';
$mes   = function ($ym) {
    $m = ['01'=>'jan','02'=>'fev','03'=>'mar','04'=>'abr','05'=>'mai','06'=>'jun','07'=>'jul','08'=>'ago','09'=>'set','10'=>'out','11'=>'nov','12'=>'dez'];
    [$y, $mm] = array_pad(explode('-', (string)$ym), 2, '');
    return ($m[$mm] ?? $mm) . '/' . $y;
};
$totGross = array_sum(array_map(fn($s) => $s['gross'], $streamers));
$totComm  = array_sum(array_map(fn($s) => $s['commission'], $streamers));
?>

<div class="admin-page-head">
    <div>
        <h1>🎮 Streamers / Afiliados</h1>
        <p>Quanto cada streamer gerou de venda e o cachê a pagar (sobre o valor cheio, só compra paga). O valor é só pra você pagar por fora - o site não paga automático.</p>
    </div>
</div>

<?php if (!$affiliate_on): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--rust);">
        ⚠️ <strong>Programa de afiliado desligado.</strong> Os cupons de streamer funcionam como desconto normal e <strong>não</strong> atrelam clientes nem geram cachê. Ative em <a href="/admin/settings" style="color:var(--hazard);">Configurações → Programa de afiliado</a>.
    </div>
<?php endif; ?>

<?php if (empty($streamers)): ?>
    <div class="stat-card" style="text-align:center; padding:3rem 1rem; color:var(--dim);">
        Nenhuma venda atribuída a streamer ainda.<br>
        Crie um cupom de afiliado em <a href="/admin/coupons" style="color:var(--hazard);">Cupons</a> (seção "Programa de afiliado") e divulgue o código pro seu streamer.
    </div>
<?php else: ?>

    <div class="stat-card" style="margin-bottom:1.5rem; display:flex; gap:2.5rem; flex-wrap:wrap;">
        <div><div class="label">Faturamento atribuído</div><div style="font-size:1.4rem; font-family:var(--font-display); color:var(--bone);"><?= $money($totGross) ?></div></div>
        <div><div class="label">Cachê total a pagar</div><div style="font-size:1.4rem; font-family:var(--font-display); color:var(--hazard);"><?= $money($totComm) ?></div></div>
        <div><div class="label">Streamers ativos</div><div style="font-size:1.4rem; font-family:var(--font-display); color:var(--bone);"><?= count($streamers) ?></div></div>
    </div>

    <?php foreach ($streamers as $s): ?>
        <div class="stat-card" style="margin-bottom:1.5rem; padding:1.4rem;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                <div>
                    <div style="font-size:1.2rem; font-family:var(--font-display); color:var(--bone);">🎮 <?= e($s['affiliate_name']) ?></div>
                    <div class="dim" style="font-size:0.8rem;">cupom <strong class="mono" style="color:var(--hazard);"><?= e($s['code']) ?></strong> · cachê <?= $pct($s['pct1']) ?> / <?= $pct($s['pct2']) ?> / <?= $pct($s['pct3plus']) ?> (1ª/2ª/3ª+)</div>
                </div>
                <div style="text-align:right;">
                    <div class="label">Cachê a pagar</div>
                    <div style="font-size:1.5rem; font-family:var(--font-display); color:var(--hazard);"><?= $money($s['commission']) ?></div>
                </div>
            </div>

            <div style="display:flex; gap:2rem; flex-wrap:wrap; margin-bottom:1rem; font-size:0.9rem;">
                <span class="dim">Vendas: <strong style="color:var(--bone);"><?= (int)$s['sales'] ?></strong></span>
                <span class="dim">Compradores: <strong style="color:var(--bone);"><?= (int)$s['buyers'] ?></strong></span>
                <span class="dim">Faturamento (valor cheio): <strong style="color:var(--bone);"><?= $money($s['gross']) ?></strong></span>
            </div>

            <?php if (!empty($s['by_month'])): ?>
            <table class="admin-table" style="margin-bottom:0.8rem;">
                <thead><tr><th>Mês</th><th>Vendas</th><th>Faturamento</th><th>Cachê</th></tr></thead>
                <tbody>
                    <?php foreach ($s['by_month'] as $ym => $m): ?>
                        <tr>
                            <td><?= e($mes($ym)) ?></td>
                            <td class="mono"><?= (int)$m['sales'] ?></td>
                            <td class="mono"><?= $money($m['gross']) ?></td>
                            <td class="mono" style="color:var(--hazard);"><?= $money($m['commission']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <details>
                <summary style="cursor:pointer; font-size:0.82rem; color:var(--dim);">Ver as <?= (int)$s['sales'] ?> venda(s) individual(is)</summary>
                <table class="admin-table" style="margin-top:0.6rem;">
                    <thead><tr><th>Data</th><th>SteamID</th><th>Compra do cliente</th><th>Valor cheio</th><th>%</th><th>Cachê</th></tr></thead>
                    <tbody>
                        <?php foreach ($s['rows'] as $r):
                            $nthLabel = $r['nth'] >= 3 ? '3ª+' : ($r['nth'] === 2 ? '2ª' : '1ª'); ?>
                            <tr>
                                <td class="dim"><?= e(fmt_dt($r['created_at'])) ?></td>
                                <td class="mono" style="font-size:0.78rem;"><?= e(substr($r['steam_id'], 0, 8) . '…' . substr($r['steam_id'], -4)) ?></td>
                                <td><?= $nthLabel ?></td>
                                <td class="mono"><?= $money($r['gross']) ?></td>
                                <td class="mono"><?= $pct($r['pct']) ?></td>
                                <td class="mono" style="color:var(--hazard);"><?= $money($r['commission']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php \App\View::endSection(); ?>
