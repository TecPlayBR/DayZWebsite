<?php /** @var array $config, $ultimas; @var string $modo, $fornecedor; @var bool $tem_chave, $ok; @var int $n_verificados, $n_declarados, $n_menores, $n_falhas_30d; @var ?array $teste */ ?>
<?php $title = 'Conformidade ECA Digital'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<div class="admin-page-head">
    <div>
        <h1>🛡 Proteção de menores (ECA Digital)</h1>
        <p>Lei 15.211/2025 e Decreto 12.880/2026. Caixa de recompensa só para adulto verificado. Esta tela mostra só metadados: nunca CPF, nunca data de nascimento.</p>
    </div>
</div>

<?php if ($ok): ?><div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--moss);">✓ Feito.</div><?php endif; ?>
<?php if ($teste): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid <?= !empty($teste['ok']) ? 'var(--moss)' : 'var(--danger-border)' ?>;">
        Teste da chave: <?= e((string) ($teste['msg'] ?? '')) ?>
    </div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.2rem; border-left:3px solid <?= $modo === 'verificado' ? 'var(--moss)' : ($modo === 'declaracao' ? 'var(--hazard)' : 'var(--danger-border)') ?>;">
    <strong>Modo atual: <?= e($modo) ?></strong>
    <?php if ($modo === 'desligado'): ?> · <span style="color:var(--rust-2);">verificação desligada: caixas indisponíveis para todos</span>
    <?php elseif ($modo === 'declaracao'): ?> · transição: caixas fechadas até colar a chave e mudar para Verificado
    <?php else: ?> · conforme<?php endif; ?>
    · fornecedor <strong><?= e($fornecedor) ?></strong> <?= $tem_chave ? '(chave salva)' : '<span style="color:var(--rust-2);">(sem chave)</span>' ?>
    <form method="POST" action="/admin/eca/testar-chave" style="display:inline; margin-left:.8rem;"><?= \App\Csrf::field() ?><button class="btn btn-sm" type="submit">Testar chave</button></form>
    <a class="btn btn-sm" href="/admin/settings#eca" style="margin-left:.4rem;">Configurar</a>
    <a class="btn btn-sm" href="/admin/eca/relatorio" target="_blank" style="margin-left:.4rem;">Relatório de conformidade</a>
    <a class="btn btn-sm" href="/admin/eca/export.csv" style="margin-left:.4rem;">Exportar CSV</a>
</div>

<div style="display:grid; grid-template-columns:repeat(5,1fr); gap:1rem; margin-bottom:1.2rem;">
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_verificados ?></div><div style="color:var(--dim);">adultos verificados</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_declarados ?></div><div style="color:var(--dim);">adultos só declarados</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_menores ?></div><div style="color:var(--dim);">menores (bloqueados)</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_bloqueios ?></div><div style="color:var(--dim);">aberturas de caixa barradas</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_falhas_30d ?></div><div style="color:var(--dim);">falhas do fornecedor (30 dias)</div></div>
</div>

<div class="stat-card" style="margin-bottom:1.2rem;">
    <h3 style="margin-top:0;">Prazos de guarda: limpeza automática</h3>
    <p style="margin:.2rem 0 .8rem; color:var(--dim);">
        O site guarda IP, navegador, data e hora de cada login com a Steam. O Marco Civil da Internet (art. 15) pede 6 meses,
        e a Política de Privacidade modelo promete eliminar depois disso. A mesma limpeza apaga o registro de ações dos administradores com mais de 12 meses e os arquivos temporários do limite de tentativas, que levam o IP no nome, com mais de 24 horas.
        Hoje: <strong style="color:var(--bone);"><?= (int) $login_total ?></strong> registros, <strong style="color:var(--bone);"><?= (int) $login_antigos ?></strong> com mais de 6 meses.
    </p>
    <?php if (!empty($login_retencao)): ?>
        <form method="POST" action="/admin/eca/retencao-login" data-confirm="Desligar a limpeza? O registro de login volta a ser guardado sem prazo, e a Política de Privacidade deixa de corresponder ao site." style="display:flex; gap:.8rem; align-items:center; flex-wrap:wrap;">
            <?= \App\Csrf::field() ?><input type="hidden" name="acao" value="desligar">
            <span style="color:var(--moss);">Limpeza ligada: o site apaga sozinho o que passa do prazo.</span>
            <button class="btn btn-sm" type="submit">Desligar</button>
        </form>
    <?php else: ?>
        <form method="POST" action="/admin/eca/retencao-login" data-confirm="Ligar a limpeza apaga agora os <?= (int) $login_antigos ?> registros de login com mais de 6 meses, o registro dos administradores com mais de 12 meses e os arquivos temporários com mais de 24 horas, e daí em diante o site apaga sozinho o que passar do prazo. Isso não pode ser desfeito. Continuar?" style="display:flex; gap:.8rem; align-items:center; flex-wrap:wrap;">
            <?= \App\Csrf::field() ?><input type="hidden" name="acao" value="ligar">
            <span style="color:var(--hazard);">Limpeza desligada: o registro de login fica guardado sem prazo.</span>
            <button class="btn btn-sm" type="submit">Ligar a limpeza</button>
        </form>
    <?php endif; ?>
</div>

<div class="stat-card" id="paginas-legais" style="margin-bottom:1.2rem;">
    <h3 style="margin-top:0;">Páginas legais: Termos, Privacidade e Reembolso</h3>
    <?php if (($legal_msg ?? '') === 'aplicado'): ?><p style="color:var(--moss); margin:.2rem 0 .8rem;">Texto novo aplicado. O texto anterior ficou guardado no histórico, e os jogadores aceitam os Termos novos na próxima compra.</p>
    <?php elseif (($legal_msg ?? '') === 'faltando'): ?><p style="color:var(--rust-2); margin:.2rem 0 .8rem;">Não foi aplicado: preencha os dados abaixo.</p>
    <?php elseif (($legal_msg ?? '') === 'salvo'): ?><p style="color:var(--moss); margin:.2rem 0 .8rem;">Dados salvos.</p><?php endif; ?>
    <p style="margin:.2rem 0 .8rem; color:var(--dim);">
        <?php if (!empty($legal_precisa)): ?>
            <strong style="color:var(--hazard);">Há um texto novo das páginas legais, revisado por advogada</strong> (ECA Digital, LGPD, Marco Civil e CDC).
            Preencha os dados da sua empresa e clique em Aplicar: o site troca os Termos, a Privacidade, o Reembolso e a resposta do FAQ sobre menores.
        <?php else: ?>
            Suas páginas legais estão no texto modelo atual. Se mudar os dados, aplique de novo para atualizar as páginas.
        <?php endif; ?>
    </p>
    <form method="POST" action="/admin/eca/paginas-legais">
        <?= \App\Csrf::field() ?>
        <div class="adm-grid-2">
            <?= admin_campo(['label' => 'Razão social', 'name' => 'legal_razao_social', 'value' => $legal['razao'] ?? '', 'required' => true, 'attrs' => 'maxlength="160"', 'hint' => 'De quem vende as Moedas e recebe os pagamentos.']) ?>
            <?= admin_campo(['label' => 'CNPJ', 'name' => 'legal_cnpj', 'value' => $legal['cnpj'] ?? '', 'required' => true, 'attrs' => 'maxlength="20" inputmode="numeric"']) ?>
            <?= admin_campo(['label' => 'E-mail de atendimento', 'name' => 'legal_email', 'type' => 'email', 'value' => $legal['email'] ?? '', 'required' => true, 'attrs' => 'maxlength="160"', 'hint' => 'Canal fora do Discord para suporte, reembolso e pedidos de privacidade.']) ?>
            <?= admin_campo(['label' => 'Provedor de hospedagem do site', 'name' => 'legal_hospedagem', 'value' => $legal['hospedagem'] ?? '', 'required' => true, 'attrs' => 'maxlength="80" placeholder="Hostinger"']) ?>
            <?= admin_campo(['label' => 'País do datacenter', 'name' => 'legal_pais', 'value' => ($legal['pais'] ?? '') !== '' ? $legal['pais'] : 'Brasil', 'attrs' => 'maxlength="60"', 'hint' => 'No hPanel da Hostinger aparece em Detalhes do plano.']) ?>
            <?= admin_campo(['label' => 'Fundamento da transferência (só se o país não for o Brasil)', 'name' => 'legal_mecanismo_hospedagem', 'value' => $legal['mecanismo'] ?? '', 'attrs' => 'maxlength="160" placeholder="cláusulas-padrão da ANPD"']) ?>
        </div>
        <p style="margin:.6rem 0; font-size:.85rem; color:var(--dim);">Nome do servidor, domínio, convite do Discord e fornecedor de verificação vêm das Configurações.</p>
        <div style="display:flex; gap:.6rem; flex-wrap:wrap;">
            <button class="btn btn-sm" type="submit" name="acao" value="salvar">Salvar dados</button>
            <button class="btn btn-sm" type="submit" name="acao" value="aplicar" data-confirm="Aplicar o texto novo nas páginas de Termos, Privacidade e Reembolso e na resposta do FAQ sobre menores? O texto anterior fica guardado no histórico do site, e os jogadores vão aceitar os Termos novos na próxima compra.">Aplicar o texto novo</button>
        </div>
    </form>
</div>

<div class="stat-card" style="margin-bottom:1.2rem;">
    <h3 style="margin-top:0;">Consentimentos de um jogador</h3>
    <form method="GET" action="/admin/eca" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
        <input class="field mono" name="steam_id" value="<?= e($consent_steam ?? '') ?>" placeholder="SteamID64 (7656119...)" maxlength="17" style="min-width:260px;">
        <button class="btn btn-sm" type="submit">Buscar</button>
        <small style="color:var(--dim);">Só metadados: tipo, versão, data, IP.</small>
    </form>
    <?php if (!empty($consent_steam)): ?>
        <?php if (empty($consentimentos)): ?>
            <p style="color:var(--dim); margin:.8rem 0 0;">Nenhum consentimento registrado para <code><?= e($consent_steam) ?></code>.</p>
        <?php else: ?>
        <table class="admin-table" style="width:100%; margin-top:.8rem;">
            <thead><tr><th>Tipo</th><th>Versão</th><th>Quando</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($consentimentos as $c): ?>
                <tr><td><?= e($c['kind']) ?></td><td><?= e($c['version']) ?></td><td><?= e($c['created_at']) ?></td><td style="font-family:var(--font-mono); font-size:.8rem;"><?= e((string) $c['ip']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="stat-card">
    <h3 style="margin-top:0;">Últimas 200 verificações</h3>
    <table class="admin-table" style="width:100%;">
        <thead><tr><th>#</th><th>SteamID</th><th>Método</th><th>Resultado</th><th>Ref. do fornecedor</th><th>Situação</th><th>Quando</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($ultimas as $r): ?>
            <tr style="<?= $r['revoked_at'] ? 'opacity:.55;' : '' ?>">
                <td><?= (int) $r['id'] ?></td>
                <td><a href="/player/<?= e($r['steam_id']) ?>" style="color:var(--hazard);"><?= e($r['steam_id']) ?></a></td>
                <td><?= e($r['method']) ?></td>
                <td><?= e($r['result']) ?><?= $r['revoked_at'] ? ' (revogada: ' . e((string) $r['revoked_reason']) . ')' : '' ?></td>
                <td style="font-family:var(--font-mono); font-size:.8rem;"><?= e((string) ($r['provider_ref'] ?? '')) ?></td>
                <td><?= e((string) ($r['provider_status'] ?? '')) ?></td>
                <td><?= e($r['created_at']) ?></td>
                <td>
                    <?php if (!$r['revoked_at'] && $r['result'] === 'adulto' && $r['method'] !== 'declaracao'): ?>
                    <form method="POST" action="/admin/eca/revogar" data-confirm="Revogar esta verificação?" style="display:flex; gap:.3rem;">
                        <?= \App\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input name="motivo" placeholder="motivo" required maxlength="160" style="padding:.3rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                        <button class="btn btn-sm" type="submit">Revogar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php \App\View::endSection(); ?>
