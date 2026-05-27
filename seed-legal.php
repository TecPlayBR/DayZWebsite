<?php
// ============================================================
// Seed das paginas legais (Termos, Privacidade, Reembolso, FAQ).
// Rode UMA VEZ pela linha de comando (ou via web protegido):
//   php seed-legal.php
// Os textos sao TEMPLATE INICIAL. Recomenda-se revisao por advogado
// antes de colocar em producao com publico real.
// ============================================================

declare(strict_types=1);
$ROOT = __DIR__;
require $ROOT . '/src/Database.php';

$cfg = require $ROOT . '/config/config.php';
\App\Database::init($cfg['db']);

$pages = [
    // ===== TERMOS DE USO =====
    [
        'slug' => 'terms',
        'title_ptbr' => 'Termos de Uso',
        'title_enus' => 'Terms of Use',
        'sort_order' => 100,
        'body_ptbr' => <<<HTML
<p><em>Última atualização: __DATE__</em></p>

<h2>1. Aceitação dos Termos</h2>
<p>Ao acessar e usar este site, você concorda com os termos e condições aqui descritos. Se você não concorda com algum termo, não utilize o serviço.</p>

<h2>2. Sobre o Serviço</h2>
<p>Este site oferece a venda de moedas virtuais para uso exclusivo dentro do servidor de DayZ vinculado. As moedas:</p>
<ul>
    <li>São itens virtuais, sem valor monetário fora do servidor</li>
    <li>Não podem ser convertidas em dinheiro real</li>
    <li>Não são transferíveis para outros jogadores ou servidores</li>
    <li>Podem ser perdidas em caso de wipe, restart, banimento ou descontinuação do servidor</li>
</ul>

<h2>3. Conta e Identificação</h2>
<p>A entrega das moedas é vinculada ao <strong>SteamID</strong> informado no momento da compra. É responsabilidade do comprador fornecer o SteamID correto. Pedidos com SteamID errado não são reembolsáveis após a entrega.</p>

<h2>4. Conduta do Jogador</h2>
<p>O uso do servidor está sujeito às regras publicadas em <a href="/page/rules">Regras do Servidor</a>. Quebrar regras pode resultar em advertência, banimento temporário ou permanente — <strong>sem reembolso de moedas adquiridas previamente</strong>.</p>

<h2>5. Pagamentos</h2>
<p>Os pagamentos são processados via Mercado Pago. Após confirmação do pagamento, as moedas são entregues em até 15 segundos. Em caso de falha técnica, contate o suporte via Discord.</p>

<h2>6. Modificações</h2>
<p>Reservamo-nos o direito de modificar estes termos, regras, preços e funcionamento do servidor a qualquer momento, com aviso prévio quando possível.</p>

<h2>7. Limitação de Responsabilidade</h2>
<p>Não nos responsabilizamos por perda de progresso, moedas ou itens decorrentes de bugs do jogo, falhas do mod, ataques cibernéticos, decisões da Bohemia Interactive (desenvolvedora do DayZ) ou outras causas alheias ao nosso controle.</p>

<h2>8. Contato</h2>
<p>Dúvidas sobre estes termos? Entre em contato pelo Discord oficial ou e-mail.</p>

<blockquote><em>Este é um documento padrão e pode não cobrir todas as situações específicas do seu negócio. Recomendamos revisão por advogado antes do uso em produção.</em></blockquote>
HTML,
        'body_enus' => <<<HTML
<p><em>Last updated: __DATE__</em></p>

<h2>1. Acceptance of Terms</h2>
<p>By accessing and using this site, you agree to the terms and conditions described herein. If you do not agree with any term, do not use the service.</p>

<h2>2. About the Service</h2>
<p>This site offers the sale of virtual coins for exclusive use within the linked DayZ server. The coins:</p>
<ul>
    <li>Are virtual items with no monetary value outside the server</li>
    <li>Cannot be converted into real money</li>
    <li>Are non-transferable to other players or servers</li>
    <li>May be lost in case of wipe, restart, ban or server discontinuation</li>
</ul>

<h2>3. Account and Identification</h2>
<p>Coin delivery is tied to the <strong>SteamID</strong> provided at purchase. It is the buyer's responsibility to provide the correct SteamID. Orders with incorrect SteamID are non-refundable after delivery.</p>

<h2>4. Player Conduct</h2>
<p>Use of the server is subject to the published <a href="/page/rules">Server Rules</a>. Rule violations may result in warnings, temporary or permanent bans — <strong>without refund of previously purchased coins</strong>.</p>

<h2>5. Payments</h2>
<p>Payments are processed via Mercado Pago. After payment confirmation, coins are delivered within 15 seconds. In case of technical failure, contact support via Discord.</p>

<h2>6. Modifications</h2>
<p>We reserve the right to modify these terms, rules, prices and server operation at any time, with prior notice when possible.</p>

<h2>7. Limitation of Liability</h2>
<p>We are not responsible for loss of progress, coins or items resulting from game bugs, mod failures, cyber attacks, decisions by Bohemia Interactive (developer of DayZ) or other causes beyond our control.</p>

<h2>8. Contact</h2>
<p>Questions about these terms? Contact us via official Discord or email.</p>

<blockquote><em>This is a standard document and may not cover all specific business situations. We recommend legal review before production use.</em></blockquote>
HTML,
    ],

    // ===== POLÍTICA DE PRIVACIDADE =====
    [
        'slug' => 'privacy',
        'title_ptbr' => 'Política de Privacidade',
        'title_enus' => 'Privacy Policy',
        'sort_order' => 110,
        'body_ptbr' => <<<HTML
<p><em>Última atualização: __DATE__</em></p>

<h2>1. Quem somos</h2>
<p>Este site é operado pela equipe administradora do servidor (doravante "nós"). Esta política descreve como coletamos, usamos e protegemos suas informações pessoais, em conformidade com a <strong>Lei Geral de Proteção de Dados (LGPD - Lei 13.709/2018)</strong>.</p>

<h2>2. Dados que coletamos</h2>
<ul>
    <li><strong>SteamID:</strong> identificador da sua conta Steam, necessário para entregar moedas no servidor</li>
    <li><strong>E-mail (opcional):</strong> apenas se você informar para contato de suporte</li>
    <li><strong>Dados de pagamento:</strong> processados exclusivamente pelo Mercado Pago — nós NÃO armazenamos número de cartão, CVV ou senha bancária</li>
    <li><strong>Endereço IP e logs:</strong> registrados temporariamente para fins de segurança e prevenção a fraude</li>
    <li><strong>Cookies essenciais:</strong> sessão de login e preferência de idioma (não usamos cookies de rastreamento de terceiros)</li>
</ul>

<h2>3. Como usamos seus dados</h2>
<ul>
    <li>Entregar moedas compradas no servidor vinculado ao seu SteamID</li>
    <li>Confirmar pagamentos junto ao Mercado Pago</li>
    <li>Prestar suporte quando você nos contata</li>
    <li>Detectar e prevenir fraudes e chargebacks indevidos</li>
</ul>

<h2>4. Compartilhamento</h2>
<p>Não vendemos nem alugamos seus dados. Compartilhamos somente:</p>
<ul>
    <li><strong>Mercado Pago:</strong> dados necessários ao processamento do pagamento</li>
    <li><strong>Autoridades:</strong> mediante ordem judicial ou requisição legal válida</li>
</ul>

<h2>5. Seus direitos (LGPD)</h2>
<p>Você pode, a qualquer momento, solicitar:</p>
<ul>
    <li>Confirmação de existência de tratamento dos seus dados</li>
    <li>Acesso aos dados que mantemos sobre você</li>
    <li>Correção de dados incompletos, inexatos ou desatualizados</li>
    <li>Anonimização, bloqueio ou eliminação de dados desnecessários</li>
    <li>Portabilidade ou eliminação dos dados</li>
</ul>
<p>Para exercer qualquer direito, entre em contato pelo Discord oficial.</p>

<h2>6. Retenção</h2>
<p>Mantemos registros de compras por <strong>5 anos</strong> (exigência fiscal). SteamID e dados de saldo no jogo persistem enquanto sua conta estiver ativa no servidor.</p>

<h2>7. Segurança</h2>
<p>Usamos HTTPS, senhas hash bcrypt, proteção CSRF e rate limiting. Mesmo assim, nenhum sistema é 100% imune — você é responsável por proteger suas próprias credenciais Steam e e-mail.</p>

<h2>8. Menores de idade</h2>
<p>O serviço é destinado a maiores de 13 anos (idade mínima de uso do Steam). Menores entre 13 e 18 anos devem ter consentimento dos responsáveis para realizar compras.</p>

<blockquote><em>Este é um template inicial. Adapte às práticas reais do seu servidor e revise com advogado especializado em LGPD.</em></blockquote>
HTML,
        'body_enus' => <<<HTML
<p><em>Last updated: __DATE__</em></p>

<h2>1. Who we are</h2>
<p>This site is operated by the server's administrative team ("we"). This policy describes how we collect, use and protect your personal information, in compliance with the Brazilian <strong>General Data Protection Law (LGPD - Law 13.709/2018)</strong>.</p>

<h2>2. Data we collect</h2>
<ul>
    <li><strong>SteamID:</strong> your Steam account identifier, needed to deliver coins on the server</li>
    <li><strong>Email (optional):</strong> only if you provide it for support contact</li>
    <li><strong>Payment data:</strong> processed exclusively by Mercado Pago — we do NOT store card number, CVV or banking password</li>
    <li><strong>IP address and logs:</strong> temporarily logged for security and fraud prevention</li>
    <li><strong>Essential cookies:</strong> login session and language preference (no third-party tracking cookies)</li>
</ul>

<h2>3. How we use your data</h2>
<ul>
    <li>Deliver purchased coins to your SteamID on the linked server</li>
    <li>Confirm payments with Mercado Pago</li>
    <li>Provide support when you contact us</li>
    <li>Detect and prevent fraud and improper chargebacks</li>
</ul>

<h2>4. Sharing</h2>
<p>We do not sell or rent your data. We share only:</p>
<ul>
    <li><strong>Mercado Pago:</strong> data necessary for payment processing</li>
    <li><strong>Authorities:</strong> upon valid court order or legal request</li>
</ul>

<h2>5. Your rights (LGPD)</h2>
<p>You can, at any time, request:</p>
<ul>
    <li>Confirmation of processing of your data</li>
    <li>Access to the data we hold about you</li>
    <li>Correction of incomplete, inaccurate or outdated data</li>
    <li>Anonymization, blocking or deletion of unnecessary data</li>
    <li>Portability or deletion</li>
</ul>
<p>To exercise any right, contact us via official Discord.</p>

<h2>6. Retention</h2>
<p>We keep purchase records for <strong>5 years</strong> (tax requirement). SteamID and in-game balance data persist while your account is active on the server.</p>

<h2>7. Security</h2>
<p>We use HTTPS, bcrypt password hashing, CSRF protection and rate limiting. Still, no system is 100% immune — you are responsible for protecting your own Steam credentials and email.</p>

<h2>8. Minors</h2>
<p>The service is intended for users aged 13+ (Steam's minimum age). Users aged 13–18 must have parental consent to make purchases.</p>

<blockquote><em>This is a starter template. Adapt to your server's actual practices and review with an LGPD specialist.</em></blockquote>
HTML,
    ],

    // ===== POLÍTICA DE REEMBOLSO =====
    [
        'slug' => 'refund',
        'title_ptbr' => 'Política de Reembolso',
        'title_enus' => 'Refund Policy',
        'sort_order' => 120,
        'body_ptbr' => <<<HTML
<p><em>Última atualização: __DATE__</em></p>

<h2>1. Natureza do produto</h2>
<p>As moedas comercializadas neste site são <strong>bens virtuais de entrega imediata e consumo no ambiente do jogo</strong>. Após a entrega no SteamID informado, o produto é considerado entregue e consumido.</p>

<h2>2. Direito de arrependimento (CDC)</h2>
<p>Conforme o Art. 49 do Código de Defesa do Consumidor, você pode desistir da compra em até <strong>7 dias corridos</strong> a partir do pagamento, <strong>desde que as moedas ainda NÃO tenham sido utilizadas no jogo</strong>. Se já foram gastas, o direito de arrependimento não se aplica (bem consumido).</p>

<h2>3. Falha técnica na entrega</h2>
<p>Se você pagou e as moedas não chegaram ao seu SteamID em até <strong>30 minutos</strong>, abra um chamado no Discord oficial com:</p>
<ul>
    <li>Comprovante do pagamento (Mercado Pago)</li>
    <li>SteamID informado na compra</li>
    <li>Horário aproximado da compra</li>
</ul>
<p>Após verificação, processamos a entrega manual em até 24h úteis, ou reembolso integral se a entrega for inviável.</p>

<h2>4. SteamID errado</h2>
<p>Se você informou um SteamID errado e as moedas foram entregues a outra conta, <strong>não há reembolso</strong> — é responsabilidade do comprador conferir antes de pagar. Em casos comprovados de erro de digitação evidente (ex: diferença de 1 dígito), avaliamos caso a caso.</p>

<h2>5. Banimento ou perda no jogo</h2>
<p>Banimentos por quebra de regras, perda de itens por morte/raid/bug do jogo <strong>não geram reembolso</strong>. As regras do servidor estão em <a href="/page/rules">/page/rules</a>.</p>

<h2>6. Chargebacks indevidos</h2>
<p>Abertura de chargeback ou disputa sem contato prévio com o suporte é considerada má-fé. Nesses casos, banimos a conta vinculada e bloqueamos compras futuras.</p>

<h2>7. Como solicitar reembolso</h2>
<ol>
    <li>Abra um ticket no Discord oficial</li>
    <li>Envie comprovante de pagamento + SteamID</li>
    <li>Aguarde análise (até 48h úteis)</li>
    <li>Reembolso aprovado é processado via Mercado Pago em até 7 dias úteis</li>
</ol>

<blockquote><em>Template inicial. Revisar com advogado e adaptar conforme CNPJ/MEI do operador.</em></blockquote>
HTML,
        'body_enus' => <<<HTML
<p><em>Last updated: __DATE__</em></p>

<h2>1. Product nature</h2>
<p>The coins sold on this site are <strong>virtual goods with immediate delivery and consumption in the game environment</strong>. Once delivered to the provided SteamID, the product is considered delivered and consumed.</p>

<h2>2. Right of withdrawal</h2>
<p>You may cancel the purchase within <strong>7 calendar days</strong> from payment, <strong>provided the coins have NOT yet been used in-game</strong>. If they have been spent, the right of withdrawal does not apply (consumed good).</p>

<h2>3. Technical delivery failure</h2>
<p>If you paid and coins did not arrive at your SteamID within <strong>30 minutes</strong>, open a ticket on the official Discord with:</p>
<ul>
    <li>Mercado Pago payment receipt</li>
    <li>SteamID provided at purchase</li>
    <li>Approximate purchase time</li>
</ul>
<p>After verification, we process manual delivery within 24 business hours, or full refund if delivery is unfeasible.</p>

<h2>4. Wrong SteamID</h2>
<p>If you entered a wrong SteamID and coins were delivered to another account, <strong>no refund</strong> — it is the buyer's responsibility to verify before paying. Cases of evident typo (e.g. 1-digit difference) are reviewed individually.</p>

<h2>5. In-game ban or loss</h2>
<p>Bans for rule violations, loss of items via death/raid/game bug <strong>do not generate refund</strong>. Server rules at <a href="/page/rules">/page/rules</a>.</p>

<h2>6. Improper chargebacks</h2>
<p>Opening a chargeback or dispute without prior contact with support is considered bad faith. In these cases, we ban the linked account and block future purchases.</p>

<h2>7. How to request a refund</h2>
<ol>
    <li>Open a ticket on the official Discord</li>
    <li>Send payment proof + SteamID</li>
    <li>Wait for review (up to 48 business hours)</li>
    <li>Approved refund is processed via Mercado Pago within 7 business days</li>
</ol>

<blockquote><em>Starter template. Review with a lawyer and adapt according to operator's tax setup.</em></blockquote>
HTML,
    ],
];

$today = date('d/m/Y');
foreach ($pages as $p) {
    $pt = str_replace('__DATE__', $today, $p['body_ptbr']);
    $en = str_replace('__DATE__', date('Y-m-d'), $p['body_enus']);

    \App\Database::query(
        "INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
         VALUES (?, ?, ?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE
            title_ptbr = VALUES(title_ptbr),
            title_enus = VALUES(title_enus),
            body_ptbr  = VALUES(body_ptbr),
            body_enus  = VALUES(body_enus),
            sort_order = VALUES(sort_order)",
        [$p['slug'], $p['title_ptbr'], $p['title_enus'], $pt, $en, $p['sort_order']]
    );
    echo "[OK] {$p['slug']} - {$p['title_ptbr']}\n";
}

echo "\nTodas as paginas legais foram seedadas. Acesse /page/terms, /page/privacy, /page/refund.\n";
