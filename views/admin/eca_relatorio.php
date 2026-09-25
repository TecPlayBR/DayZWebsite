<?php /** @var array $config; @var string $site, $modo, $fornecedor, $terms_version, $gerado_em; @var bool $diaria_exige; @var ?string $ultima_ok; @var int $n_verificados */ ?>
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>Relatório de conformidade ECA Digital - <?= e($site) ?></title>
<style>body{font:14px/1.5 Georgia,serif;max-width:800px;margin:2rem auto;color:#111} h1{font-size:1.5rem} h2{font-size:1.1rem;margin-top:1.6rem} table{border-collapse:collapse;width:100%} td,th{border:1px solid #999;padding:.4rem;text-align:left;vertical-align:top} @media print{a{color:#111;text-decoration:none}}</style></head>
<body>
<h1>Relatório de conformidade: proteção de menores (ECA Digital)</h1>
<p><strong>Site:</strong> <?= e($site) ?> · <strong>Gerado em:</strong> <?= e($gerado_em) ?></p>

<h2>1. Base legal</h2>
<p>Lei 15.211/2025 (Estatuto Digital da Criança e do Adolescente), art. 9º (mecanismos confiáveis de verificação de idade, vedada a autodeclaração) e art. 20 (vedação de caixas de recompensa a crianças e adolescentes). Decreto 12.880/2026, art. 23 (verificação de idade para caixas de recompensa; caixa restrita por padrão) e art. 24 (requisitos do mecanismo). Orientações preliminares da ANPD sobre mecanismos confiáveis de aferição de idade (março de 2026).</p>

<h2>2. Mecanismo adotado</h2>
<table>
<tr><th>Modo em operação</th><td><?= e($modo) ?></td></tr>
<tr><th>Fornecedor da verificação</th><td><?= e($fornecedor) ?> (conferência do CPF em base oficial)</td></tr>
<tr><th>Caixa diária gratuita</th><td><?= $diaria_exige ? 'também exige verificação' : 'liberada para adulto declarado' ?></td></tr>
<tr><th>Versão dos Termos com consentimento registrado</th><td><?= e($terms_version) ?></td></tr>
<tr><th>Adultos verificados (ativos)</th><td><?= (int) $n_verificados ?></td></tr>
<tr><th>Última verificação bem-sucedida</th><td><?= e((string) ($ultima_ok ?? 'nenhuma')) ?></td></tr>
</table>

<h2>3. Como funciona</h2>
<ol>
<li>Caixas de recompensa ficam fechadas por padrão. Só abrem para conta com verificação de idade concluída por fonte externa.</li>
<li>Compra de moeda exige declaração de data de nascimento (18+) e aceite registrado dos Termos de Uso e da Política de Privacidade, com data, hora, IP e versão do texto.</li>
<li>A verificação usa o CPF apenas durante a consulta ao fornecedor. O CPF não é armazenado, registrado em log ou mantido em sessão. Fica gravado: resultado, método, data, referência de auditoria do fornecedor, situação cadastral e um hash irreversível com sal próprio do site, usado só para impedir que um CPF verifique mais de uma conta.</li>
<li>Menores declarados ou detectados ficam com compras e caixas bloqueadas, mantendo o acesso ao jogo, ranking e clãs.</li>
<li>O jogador pode contestar e retificar refazendo a verificação; o administrador pode revogar uma verificação com motivo registrado.</li>
</ol>

<h2>4. Requisitos do art. 24 do Decreto 12.880/2026</h2>
<table>
<tr><th>I. Proporcionalidade</th><td>Verificação exigida só para a funcionalidade vedada (caixas); declaração para compra de moeda.</td></tr>
<tr><th>II. Acurácia e confiabilidade</th><td>Fonte oficial (Receita Federal) via fornecedor especializado; autodeclaração não libera caixas.</td></tr>
<tr><th>III. Finalidade única</th><td>Dados usados só para aferir idade; sem perfil comportamental.</td></tr>
<tr><th>IV. Minimização</th><td>Guardados só resultado, método, data e ano de nascimento; CPF descartado.</td></tr>
<tr><th>V. Privacidade</th><td>Sem biometria, sem imagem de documento.</td></tr>
<tr><th>VI. Sem compartilhamento contínuo</th><td>Uma consulta por verificação; nenhum envio recorrente.</td></tr>
<tr><th>VII. Segurança</th><td>Hash com sal por site; chaves do fornecedor gravadas no servidor, nunca exibidas.</td></tr>
<tr><th>VIII. Sem rastreabilidade de histórico</th><td>Não há registro de acessos do jogador ligado à verificação.</td></tr>
<tr><th>IX. Interoperabilidade</th><td>Fornecedor substituível (FlagCheck, Serpro, CPFHub) sem alterar o registro.</td></tr>
<tr><th>X. Inclusão</th><td>Sem exigência de documento com foto ou dispositivo específico; CPF é universal.</td></tr>
<tr><th>XI. Transparência e auditabilidade</th><td>Este relatório, a página pública /idade explicando o mecanismo, exportação CSV e trilha de auditoria do administrador.</td></tr>
</table>
<p style="margin-top:2rem;font-size:.85rem;color:#555;">Gerado automaticamente pelo painel do site. Reflete a configuração no momento da emissão.</p>
</body></html>
