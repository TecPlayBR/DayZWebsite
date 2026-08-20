<?php
/** @var array $config */
/** @var bool  $ativo        entrega in-game ativa agora (o bot espelhou nos ultimos 30min) */
/** @var int   $vistoEm      unix time da ultima confirmacao do bot (0 = nunca) */
/** @var int   $idadeSeg     segundos desde a ultima confirmacao */
/** @var string $botUrl      endereco do bot configurado no site ('' = nao configurado) */
/** @var bool  $botTokenSet  ha token do bot salvo */
?>
<?php $title = 'Integração FlameHost'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>🔥 Integração FlameHost (moeda dentro do jogo)</h1>
        <p>
            Quando o seu servidor usa a <strong>loja da FlameHost</strong>, a moeda do jogador
            mora <strong>dentro do jogo</strong>. O que ele compra aqui no site é entregue lá, e o
            número que aparece no site passa a ser um espelho do que existe no servidor.
        </p>
    </div>
</div>

<?php
// ESTA PAGINA E SO-LEITURA, E DE PROPOSITO.
//
// A credencial de FTP do servidor de jogo fica no painel do BOT, nao aqui, porque e o bot
// que escreve nos arquivos do servidor -- o site nunca toca neles. Guardar a senha do FTP
// tambem no site seria uma segunda copia de um segredo que o site nao usa pra nada.
//
// Mas a pagina precisa EXISTIR: o dono do servidor abre as integracoes do site, ve Agent,
// Sparda e Discord, e nao acha justamente a que entrega a moeda dele. Foi exatamente o que
// aconteceu -- "e pq nao tem o Integracao com Flame?". Uma pagina que aponta pro lugar certo
// e diz o estado atual custa pouco e resolve a procura.
?>

<div class="stat-card" style="margin-bottom: 1rem;">
    <div class="label">Estado da entrega in-game</div>
    <div style="margin-top: 0.8rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
        <?php if ($ativo): ?>
            <span style="padding:0.4rem 0.9rem;border-radius:6px;background:var(--moss);color:#fff;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.1em;">
                ativa
            </span>
            <span style="color:var(--dim); font-size:0.85rem;">
                O bot confirmou o saldo do jogo
                <?php if ($idadeSeg < 90): ?>
                    agora mesmo.
                <?php else: ?>
                    há <?= (int) round($idadeSeg / 60) ?> min.
                <?php endif; ?>
            </span>
        <?php else: ?>
            <span style="padding:0.4rem 0.9rem;border-radius:6px;background:var(--rust-2);color:#fff;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.1em;">
                inativa
            </span>
            <span style="color:var(--dim); font-size:0.85rem;">
                <?php if ($vistoEm === 0): ?>
                    O bot nunca confirmou um saldo do jogo neste site.
                <?php else: ?>
                    A última confirmação foi há <?= (int) round($idadeSeg / 60) ?> min — passou do prazo,
                    então o site voltou a tratar o saldo como sendo dele.
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </div>
    <p style="margin-top:0.8rem; color:var(--dim); font-size:0.82rem; line-height:1.5;">
        Este estado <strong>não é um interruptor</strong>: é um sinal que expira. Enquanto o bot
        está espelhando, o site sabe que a moeda mora no jogo. Se você desligar a entrega no
        painel do bot, o site volta ao normal sozinho, sem precisar mexer em nada aqui.
    </p>
</div>

<div class="stat-card" style="margin-bottom: 1rem;">
    <div class="label">Onde se configura</div>
    <p style="margin-top:0.6rem; line-height:1.6;">
        No <strong>painel do bot</strong>, aba <strong>Integração</strong>, seção
        <strong>🔥 Entrega in-game na FlameHost</strong>. Lá você preenche endereço, porta,
        usuário e senha do FTP do seu servidor de jogo e clica em <em>Testar e ligar</em> — o
        bot descobre a pasta sozinho.
    </p>
    <p style="margin-top:0.6rem; color:var(--dim); font-size:0.82rem; line-height:1.5;">
        Fica lá, e não aqui, porque é o <strong>bot</strong> que escreve nos arquivos do seu
        servidor. O site nunca toca neles, então não faz sentido ele guardar uma segunda cópia
        da sua senha de FTP.
        <?php if ($botUrl !== ''): ?>
            <br>O bot deste site está em <code><?= e($botUrl) ?></code>.
        <?php endif; ?>
    </p>
    <?php if ($botUrl === '' || !$botTokenSet): ?>
        <div style="margin-top:0.8rem; background:var(--danger-overlay); border-left:3px solid var(--rust-2); padding:0.8rem 1rem;">
            <strong>Antes disso, falta o link com o bot.</strong> Sem o endereço e o token em
            <a href="/admin/discord-integration" style="color:var(--hazard);">Integração Discord</a>,
            a compra feita no site <strong>não chega no jogo</strong>: o site não tem como avisar
            o bot que o pagamento foi aprovado.
        </div>
    <?php endif; ?>
</div>

<div class="stat-card">
    <div class="label">O que muda no site quando está ativa</div>
    <ul style="margin:0.6rem 0 0; padding-left:1.2rem; line-height:1.8;">
        <li><strong>Gastar moeda no site sai do ar.</strong> Caixas e compra de VIP com moeda
            deixam de aparecer, porque o gasto tem que acontecer onde a moeda está. Se a sua loja
            só vende pacote de moeda, nada muda pra você.</li>
        <li><strong>O saldo mostrado aqui é um espelho, e espelho atrasa.</strong> O mod desconta
            no arquivo do servidor e não avisa ninguém, então o bot relê a cada poucos minutos.
            Gasto feito dentro do jogo aparece no site com um atraso de minutos — isso é esperado,
            e a tela do jogador avisa.</li>
        <li><strong>O que vale é sempre o número do jogo.</strong> Ajuste de moedas feito aqui no
            painel é aplicado no servidor primeiro; se não der, nada é gravado aqui, pra a tela
            nunca mostrar um valor que não existe lá dentro.</li>
    </ul>
</div>

<?php \App\View::endSection(); ?>
