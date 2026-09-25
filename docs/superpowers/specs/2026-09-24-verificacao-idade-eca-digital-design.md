# Verificação de idade e consentimento (ECA Digital) - desenho da versão 3.3.0

Data: 24/09/2026. Estado: **desenho aprovado pelo Bryan, aguardando revisão jurídica** antes de
virar plano de implementação. Escrito para dois leitores: o advogado da Tecplay, que precisa
dizer se a leitura da lei está certa e responder as perguntas do fim, e quem for implementar.

---

## 1. O problema

O DayZWebsite vende moeda virtual e **caixas de recompensa** (loot boxes: o jogador gasta moeda e
recebe um item aleatório). Hoje o template:

- não tem nenhum gate de idade: login é pela Steam, que não informa idade, e a caixa abre na hora;
- registra um "aceite de termos" que o jogador nunca marcou (`shop.php:183` e `checkout_pix.php:32`
  mandam `terms_accepted=1` num campo oculto, e o servidor grava `terms_accepted_at = NOW()`);
- coleta CPF só no cartão, e só valida dígito verificador.

Os Termos de Uso dizem que a plataforma é "destinada a maiores de 18 anos", mas nada impede um
menor de comprar e abrir caixa. Com a Lei 15.211/2025 em vigor, isso é exposição direta de cada
cliente que usa o template, e por tabela da Tecplay.

## 2. Base legal (o que a norma diz, literalmente)

**Lei 15.211/2025 (ECA Digital), em vigor desde 17/03/2026**

- Art. 2º, IV: caixa de recompensa é "funcionalidade disponível em certos jogos eletrônicos que
  permite a aquisição, mediante pagamento, pelo jogador, de itens virtuais consumíveis ou de
  vantagens aleatórias".
- Art. 2º, parágrafo único: "acesso provável" por criança ou adolescente considera probabilidade
  de uso, facilidade de acesso e grau de risco.
- Art. 9º: "deverão ser adotados mecanismos confiáveis de verificação de idade [...], **vedada a
  autodeclaração**".
- Art. 20: "São vedadas as caixas de recompensa (loot boxes) oferecidas em jogos eletrônicos
  direcionados a crianças e a adolescentes **ou de acesso provável por eles**, nos termos da
  respectiva classificação indicativa."
- Art. 35: advertência com prazo de até 30 dias; multa de até 10% do faturamento do grupo no
  Brasil ou de R$ 10 a R$ 1.000 por usuário cadastrado, limitada a R$ 50 milhões por infração;
  suspensão temporária; proibição de atividade. Advertência e multa pela autoridade
  administrativa (ANPD); suspensão e proibição pelo Judiciário.

**Decreto 12.880/2026 (regulamento), de 18/03/2026**

- Art. 23, caput: "Fornecedores de jogos eletrônicos com caixas de recompensa deverão realizar a
  verificação de idade dos usuários [...] de modo a impedir o acesso a essa funcionalidade por
  crianças e adolescentes."
- Art. 23, § 1º: os jogos "poderão [...] **restringir totalmente por padrão o acesso à
  funcionalidade de caixas de recompensa, hipótese em que será dispensada a verificação de
  idade**".
- Art. 24: o mecanismo de aferição observará (I) proporcionalidade ao risco; (II) acurácia,
  robustez e confiabilidade; (III) vedação de uso dos dados para outra finalidade; (IV)
  minimização; (V) privacidade; (VI) vedação a compartilhamento contínuo; (VII) segurança;
  (VIII) **vedação à rastreabilidade da identidade e do histórico de acessos**; (IX)
  interoperabilidade; (X) inclusão e não discriminação; (XI) transparência e auditabilidade.
- Art. 24, § 3º: dado de documento limita-se à idade ou faixa etária, "vedado o armazenamento,
  a retenção ou qualquer forma de conservação da imagem, da cópia do documento ou da
  informação, que deverá ser eliminada de modo imediato e irreversível".

**ANPD, "Mecanismos confiáveis de aferição de idade: orientações preliminares" (março/2026)**

- Autodeclaração tem "baixo grau de confiabilidade" por depender "de informações facilmente
  manipuláveis" e carecer "de fontes de dados íntegras e independentes".
- Método só por data de nascimento "favorece a criação de múltiplos cadastros com informações
  inverídicas" (Radar Tecnológico nº 5).
- Registro de auditoria: "apenas metadados funcionais [...], como o resultado da aferição, o
  momento do acesso e o método empregado, sem reproduzir os dados pessoais que serviram de
  insumo"; evitar guardar biometria, imagem ou dado extraído de documento.
- Cronograma: orientações definitivas em agosto/2026, adaptação de agosto a novembro/2026,
  **fiscalização efetiva a partir de janeiro/2027**.

**Contexto do produto:** o DayZ é ClassInd 18. Isso ajuda a argumentar que o jogo não é
"direcionado" a menor, mas o site é aberto e o login é Steam (13+ nos termos da Steam), o que
cai em "acesso provável". O desenho parte do pior caso.

**gov.br:** o Login Único é negado a sistemas privados em área de concorrência. Não é opção.

## 3. Regra de produto

Quatro estados por jogador (`players.age_status`):

| Estado | Como chega | O que pode |
|---|---|---|
| `desconhecido` | padrão de toda conta | jogar, ranking, clã, ver loja |
| `menor` | declarou nascimento < 18 | o mesmo; loja e caixas bloqueadas com explicação |
| `adulto_declarado` | declarou nascimento ≥ 18 e marcou os Termos | **comprar moeda e pacotes** |
| `adulto_verificado` | CPF confirmado por fornecedor externo | **abrir caixas** (e tudo acima) |

Regras:

1. **Caixa fechada por padrão** (Decreto, art. 23 § 1º). Só abre para `adulto_verificado`.
   Inclui a caixa diária grátis, por decisão de produto: ela existe para levar à caixa paga.
   *(Pergunta jurídica 1.)*
2. **Loja exige `adulto_declarado`.** Os Termos já dizem 18+, e contrato com menor de 16 é
   nulo. Declarar não é "verificar" no sentido da lei, mas é a base mínima para vender moeda,
   que não é caixa de recompensa. *(Pergunta jurídica 2.)*
3. **Coleta só quando precisa** (art. 24 IV). Nada é pedido no login. A data de nascimento é
   pedida na primeira tentativa de compra; o CPF, na primeira tentativa de abrir caixa.
4. **Um CPF verifica uma única conta Steam por site.** O hash do CPF é único na tabela. Fecha
   o cenário "CPF do responsável verifica várias contas".
5. **O gate vale da próxima ação em diante.** Quem já comprou ou abriu caixa não é reprocessado;
   na próxima compra ou abertura cai no fluxo.
6. **O bot obedece à mesma regra.** `bot-integration.php` (criação de PIX, cartão e link) recusa
   quem não é `adulto_declarado` com erro `age_required`, e o bot responde com o link do site.
   O bot não abre caixa (só resgata entrega de caixa já aberta), então não precisa de gate ali.
7. **Três modos no painel** (`age_gate_mode`): `desligado` (aviso vermelho: "não conforme"),
   `declaracao` (aviso amarelo: transição, caixas seguem fechadas), `verificado` (verde).
   A migration entra em `declaracao`. O cliente sobe para `verificado` ao colar a chave.

## 4. Dados

### 4.1 O que fica guardado

Só metadados, como a ANPD recomenda. **O CPF nunca é gravado**, nem em log, nem em sessão: ele
existe na memória da requisição que chama o fornecedor e morre com ela.

```sql
CREATE TABLE IF NOT EXISTS age_verifications (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    steam_id         VARCHAR(20)  NOT NULL,
    method           ENUM('declaracao','flagcheck','serpro','cpfhub') NOT NULL,
    result           ENUM('adulto','menor','falhou') NOT NULL,
    birth_year       SMALLINT     NULL,            -- só o ano, para a faixa etária
    cpf_hash         CHAR(64)     NULL,            -- sha256(cpf + sal do site); NULL na declaração
    cpf_hash_revoked CHAR(64)     NULL,            -- recebe o hash quando a linha é revogada
    provider_ref     VARCHAR(120) NULL,            -- audit_token / id da consulta no fornecedor
    provider_status  VARCHAR(40)  NULL,            -- ex.: situação cadastral 'regular'
    ip               VARCHAR(45)  NULL,
    user_agent       VARCHAR(255) NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at       DATETIME     NULL,
    revoked_reason   VARCHAR(160) NULL,
    UNIQUE KEY uq_age_cpf (cpf_hash),              -- um CPF, uma conta (entre não revogadas)
    KEY idx_age_player (steam_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE players
    ADD COLUMN IF NOT EXISTS age_status ENUM('desconhecido','menor','adulto_declarado','adulto_verificado')
        NOT NULL DEFAULT 'desconhecido',
    ADD COLUMN IF NOT EXISTS age_verified_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS consents (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    steam_id    VARCHAR(20)  NOT NULL,
    kind        ENUM('termos','privacidade','idade') NOT NULL,
    version     VARCHAR(20)  NOT NULL,             -- versão do texto aceito
    text_hash   CHAR(64)     NOT NULL,             -- sha256 do texto exibido
    ip          VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_consent_player (steam_id, kind, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Sobre o `UNIQUE (cpf_hash)`: revogação não apaga a linha; para permitir reverificação após
revogação, o hash da linha revogada é movido para `cpf_hash_revoked` (coluna sem unique) no ato
da revogação. Detalhe de implementação, registrado aqui para não cair no `UNIQUE` sem querer.

Nota sobre o `birth_year`: guarda-se só o ano, não a data completa. É o mínimo que dá a faixa
etária e evita compor um dado identificador a mais. *(Pergunta jurídica 3 fala de retenção.)*

### 4.2 Settings novas

| chave | tipo | padrão | uso |
|---|---|---|---|
| `age_gate_mode` | `desligado`/`declaracao`/`verificado` | `declaracao` | regra 7 |
| `age_provider` | `flagcheck`/`serpro`/`cpfhub` | `flagcheck` | driver |
| `age_provider_key` | string (guardada como as chaves do MP) | vazio | credencial |
| `age_provider_secret` | string | vazio | só Serpro (OAuth2) |
| `age_daily_box_gated` | bool | `true` | regra 1 |
| `age_hash_salt` | string gerada na migration | aleatória | sal do `cpf_hash` |
| `terms_version` | string | `1` | versão atual dos Termos, carimba consentimento |

## 5. Fluxos

### 5.1 Comprar (loja, checkout PIX/cartão, bot)

1. Jogador clica em comprar. Se `age_status` ≥ `adulto_declarado`, segue como hoje.
2. Senão, tela **"Confirme sua idade"**: data de nascimento, checkbox real "Li e aceito os
   Termos de Uso e a Política de Privacidade", botão continuar. Texto curto explicando o porquê
   (Lei 15.211/2025) e o que é guardado (só o ano e o aceite).
3. Servidor: valida data; se < 18, `age_status = menor`, grava `age_verifications`
   (`declaracao`, `menor`) e mostra a página "Este site vende produtos para maiores de 18 anos",
   sem tom acusatório, com o que o jogador ainda pode fazer. Se ≥ 18, `adulto_declarado`, grava
   `age_verifications` (`declaracao`, `adulto`), grava `consents` (`termos`, `privacidade`,
   `idade`), volta ao passo 1.
4. O `<input type="hidden" name="terms_accepted" value="1">` some das duas views. O checkout
   passa a exigir `consents` do jogador para a `terms_version` atual; a compra segue carimbando
   `terms_accepted_at` e `terms_version` como hoje, agora com valor verdadeiro.

### 5.2 Abrir caixa

1. Jogador clica em abrir. Se `adulto_verificado`, segue como hoje.
2. Se `desconhecido` ou `adulto_declarado`, tela **"Verifique sua identidade"**: CPF (e a data
   de nascimento, se ainda não declarou), explicação de que o CPF é conferido numa base oficial
   e **não é guardado**, e de que um CPF só pode verificar uma conta. Botão verificar.
3. Servidor (`POST /idade/verificar`): valida dígitos; calcula `cpf_hash`; se o hash já existe
   ativo em outra conta, recusa ("este CPF já verificou outra conta") sem chamar o fornecedor;
   chama `AgeVerifier::verify(cpf, nascimento)`; grava `age_verifications` com o resultado, o
   `provider_ref` e o `provider_status`; se adulto, `age_status = adulto_verificado`,
   `age_verified_at = NOW()`; **descarta o CPF**. Se menor, `age_status = menor`. Se o
   fornecedor falhou (timeout, chave inválida, CPF irregular), `result = falhou`, mensagem clara
   ao jogador e alerta ao admin.
4. `POST /caixas/{slug}/open` passa a devolver `403 {"error":"age","url":"/idade"}` quando o
   jogador não é `adulto_verificado`, independente do que a tela mostrou (autoridade no servidor).
5. `age_status = menor` também esconde a listagem de caixas e mostra a explicação.

### 5.3 Retificação e revogação

- Jogador em `menor` por engano pode pedir retificação em `/idade`: refaz o passo 5.2 com
  fornecedor (declaração não reabre). A ANPD cobra "meios de contestação e retificação".
- Admin pode revogar uma verificação (motivo obrigatório); o jogador volta a `adulto_declarado`
  e precisa verificar de novo.

### 5.4 Modo `desligado`

Tudo funciona como hoje, e o painel mostra faixa vermelha fixa: "Caixas de recompensa sem
verificação de idade: não conforme com a Lei 15.211/2025. Ative em Configurações." Existe para o
cliente que quer assumir o risco por escrito, não como padrão.

## 6. Verificador plugável

```php
interface AgeVerifier {
    /** @return array{result:'adulto'|'menor'|'falhou', ref:?string, status:?string, error:?string} */
    public function verify(string $cpf, ?string $birthDate): array;
    public function test(): array;   // botão "testar chave" no painel
}
```

| Driver | Fonte | Manda | Recebe | Custo e contratação (24/09/2026) |
|---|---|---|---|---|
| `FlagCheckVerifier` | Receita Federal em tempo real | CPF | `maior_de_18`, `status_cpf`, `audit_token` | R$ 3,33 a 5,00 por consulta em créditos que não vencem; self-service, PIX, sandbox grátis |
| `SerproVerifier` | Receita Federal (Consulta CPF v3) | CPF + nascimento (DDMMAAAA) | nascimento, nome, situação | faixas de ~R$ 0,36 a 0,66; contrato PJ na Loja Serpro; OAuth2 |
| `CpfHubVerifier` | base própria "revalidada regularmente" | CPF | nome, sexo, nascimento | grátis 50/mês; R$ 149/mês com 1.000 |

Padrão do template: **FlagCheck**, porque devolve o mínimo (só "é maior" e um token de
auditoria), é tempo real na Receita e o cliente ativa sozinho. Como cada jogador verifica uma vez
só, 100 jogadores custam de R$ 333 a R$ 500 ao cliente, uma vez. Serpro fica para cliente com
CNPJ e volume. CPFHub fica documentado como opção barata com ressalva de fonte não em tempo real.

O driver **nunca** grava o payload bruto; guarda só `ref` e `status`. Timeout de 8 s; falha não
bloqueia o jogador para sempre, só aquela tentativa. As chaves ficam onde as do Mercado Pago
ficam hoje (settings, com o mesmo tratamento).

## 7. Painel do admin

**Configurações › Proteção de menores (ECA Digital)**: modo, fornecedor, chave/segredo, botão
"testar chave" (chama `test()`, mostra saldo ou erro), toggle da diária, link para esta spec em
linguagem de cliente.

**Relatórios › Conformidade (ECA Digital)**:
- contadores: verificados, declarados, menores, falhas nos últimos 30 dias, bloqueios de caixa;
- tabela de `age_verifications` **sem PII**: SteamID, método, resultado, fornecedor, `ref`, data,
  revogado?; exportação CSV;
- **Relatório de conformidade** imprimível: descreve o mecanismo (art. 24 XI), versão dos Termos,
  fornecedor em uso, data da última verificação bem-sucedida, e a lista de medidas (esta seção
  vira o texto). É o documento que se mostra se a ANPD bater.
- ações: revogar verificação (motivo), ver histórico de consentimentos de um jogador.

Tudo isso entra no `AuditLog` já existente (`age.verified`, `age.revoked`, `age.settings`).

## 8. Textos que aparecem para o jogador

Curtos, sem juridiquês, e cada um com versão em `stringtable` como o resto do site. Os quatro:
"Confirme sua idade", "Verifique sua identidade", "Este site vende para maiores de 18 anos",
"Este CPF já verificou outra conta". Cada um diz o que é guardado e por quê. Sem travessão.

## 9. O que fica fora desta versão

- Biometria facial, foto de documento (art. 24 § 3º torna isso um passivo), gov.br (inacessível).
- Verificação central na Tecplay (decisão de negócio: cada cliente é controlador do próprio dado).
- Rastrear histórico de acessos do jogador (art. 24 VIII proíbe).
- Reprocessar compras antigas.
- Estimar idade por comportamento.

## 10. Entrega

- Versão **3.3.0**. Migration `v3.3.0_eca_digital.sql` idempotente, aplicada pelo `/update.php`.
- Ordem: staging da Hostinger com chave sandbox do FlagCheck, depois NomadeZ (cliente que pediu),
  depois Danoninho-Z.
- O 3.2.8 dos formulários do admin passa a ser **3.3.1**, porque esta versão já mexe nas telas.
- Comunicado aos clientes: um parágrafo no CHANGELOG. Ao jogador, a própria página `/idade`
  explica o que mudou e o que é guardado (cada site pode complementar na Central de Ajuda).

## 11. Testes

- Unitários (PHP, sem rede): gate por estado em cada rota (`/caixas/*/open`, checkout PIX,
  cartão, `bot-integration`), cálculo de idade em bordas (faz 18 hoje, ano bissexto), hash único,
  revogação reabre a verificação, `menor` nunca abre caixa mesmo com `age_gate_mode=desligado`
  depois de declarado.
- Drivers com HTTP falso: resposta adulto, menor, CPF irregular, timeout, chave inválida.
  Nenhum teste chama fornecedor real.
- Migration: rodar duas vezes no mesmo banco sem erro; rodar em banco 3.2.6 e 3.2.7.
- Guarda de regressão: teste que falha se `terms_accepted` voltar a ser campo oculto.
- Staging: fluxo completo com sandbox, prints para o relatório.

## 12. Perguntas para o advogado

1. **Caixa diária grátis.** O art. 2º, IV fala em "mediante pagamento". A diária não custa
   moeda. O desenho a fecha junto por prudência (ela leva à paga). Manter fechada, ou pode
   ficar aberta para `adulto_declarado`?
2. **Venda de moeda para `adulto_declarado`.** Moeda não é caixa de recompensa; o art. 9º veda
   autodeclaração para "conteúdo, produto ou serviço impróprio para menores". Vender moeda com
   declaração + Termos 18+ é sustentável, ou a loja inteira precisa de verificação?
3. **Retenção.** Por quanto tempo guardar `age_verifications` e `consents` depois que a conta
   some ou o jogador pede exclusão (LGPD art. 16 vs. prova de conformidade e prazo do CDC)?
4. **Texto dos Termos.** As versões seedadas falam em "assistência dos responsáveis" para
   menores. Com a regra nova (menor não compra), esse trecho precisa mudar?
5. **Fornecedor como operador.** O cliente contrata FlagCheck/Serpro direto. Precisa de cláusula
   na Política de Privacidade do site nomeando o operador e a finalidade?

Respondidas essas cinco, o desenho vira plano de implementação.
