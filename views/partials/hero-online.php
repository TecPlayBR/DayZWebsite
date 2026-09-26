<?php
/**
 * Chip de status do servidor no topo da home.
 * @var array  $ss          status_servidor(): configured, online, players, max, rank
 * @var string $discordUrl  convite do Discord (mostrado quando o servidor esta fora)
 * @var bool   $showRank    ranking BattleMetrics bom o bastante pra exibir
 * @var bool   $mostrar     interruptor do dono (Configuracoes > hero_online_enabled)
 *
 * Com o servidor online e VAZIO a contagem fica escondida (so "Online"): "0 jogadores" no topo
 * espanta quem acabou de chegar. Ela volta sozinha quando alguem entra, pela atualizacao abaixo.
 */
if (empty($mostrar) || empty($ss['configured'])) return;
$online = !empty($ss['online']);
$n   = (int) ($ss['players'] ?? 0);
$max = (int) ($ss['max'] ?? 0);
?>
<div class="hero-status hero-status-<?= $online ? 'online' : 'offline' ?>" aria-live="polite"<?= $online ? ' data-status-url="/status-servidor.json"' : '' ?>>
    <span class="dot"></span>
    <?php if ($online): ?>
        <span><?= e(__('hero.status_online')) ?></span>
        <span class="hs-contagem"<?= $n > 0 ? '' : ' hidden' ?>>
            <span style="color: var(--dim);" aria-hidden="true">&middot;</span>
            <span><strong class="hs-n"><?= $n ?></strong><span class="hs-max"><?= $max > 0 ? '/' . $max : '' ?></span> <?= e(__('hero.status_players')) ?></span>
        </span>
        <?php if (!empty($showRank)): ?>
            <span style="color: var(--dim);" aria-hidden="true">&middot;</span>
            <span title="Ranking BattleMetrics" style="color: var(--hazard);">#<?= (int) $ss['rank'] ?></span>
        <?php endif; ?>
    <?php else: ?>
        <span><?= e(__('hero.status_voltando')) ?></span>
        <?php if (!empty($discordUrl)): ?>
            <span style="color: var(--dim);" aria-hidden="true">&middot;</span>
            <a href="<?= e($discordUrl) ?>" target="_blank" rel="noopener" style="color: var(--moss); font-weight: 600;"><?= e(__('hero.status_avise_discord')) ?></a>
        <?php endif; ?>
    <?php endif; ?>
    <a href="/server-status" class="hs-detalhes"><?= e(__('hero.status_detalhes')) ?></a>
</div>
<?php if ($online): ?>
<script nonce="<?= csp_nonce() ?>">
(function () { // escopo proprio: a home nao tem PJAX, mas o padrao do template e nunca poluir o global
    var el = document.querySelector('.hero-status[data-status-url]');
    if (!el || !window.fetch) return;
    var cont = el.querySelector('.hs-contagem'), num = el.querySelector('.hs-n'), max = el.querySelector('.hs-max');
    function atualiza() {
        // Aba escondida nao consulta nada: quem deixou a home aberta num canto nao gera trafego.
        if (document.visibilityState !== 'visible') return;
        fetch(el.getAttribute('data-status-url'), { credentials: 'omit' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.online) return;   // caiu ou foi desligado: fica como esta ate recarregar
                var qtd = parseInt(d.players, 10) || 0;
                var txtMax = d.max > 0 ? '/' + d.max : '';
                // So escreve quando muda, pra o aria-live nao repetir o mesmo numero a cada minuto.
                if (num.textContent !== String(qtd)) num.textContent = String(qtd);
                if (max.textContent !== txtMax) max.textContent = txtMax;
                cont.hidden = qtd <= 0;
            })
            .catch(function () { /* sem rede: mantem o ultimo numero */ });
    }
    setInterval(atualiza, 60000);
    document.addEventListener('visibilitychange', atualiza);
})();
</script>
<?php endif; ?>
