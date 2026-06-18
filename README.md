<div align="center">

# 🏴 Tecplay — DayZ Website Template

**Site completo, gratuito e profissional para servidores DayZ brasileiros.**
Tema apocalipse · Painel admin completo · Mercado Pago · Login Steam · Multi-idioma · Multi-server.

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com)
[![License](https://img.shields.io/badge/License-Tecplay--NC-a855f7?style=flat-square)](LICENSE.txt)
[![Status](https://img.shields.io/badge/Status-Produção-16a34a?style=flat-square)]()
[![Versão](https://img.shields.io/badge/Versão-2.6.0-facc15?style=flat-square)](RELEASE_NOTES.md)

*Sobreviva. Construa. Domine. Agora também na web.*

[**Documentação**](#-instalação) · [**Demo**](https://tecplay.inf.br) · [**Suporte**](https://tecplay.inf.br/suporte/) · [**Customização**](https://tecplay.inf.br/servicos/#web)

</div>

---

## ⚠️ LICENÇA — GRATUITO MAS NÃO COMERCIAL

Este template é **GRATUITO** para qualquer dono de servidor DayZ instalar e usar.

🚫 **PROIBIDO VENDER, REVENDER ou COBRAR** por este template (modificado ou não).
A venda não autorizada é **crime** previsto no **Art. 184 §2º do Código Penal** (reclusão 2 a 4 anos + multa) e na **Lei 9.609/98 Art. 12** (Lei do Software).

🛠️ **Modificações:** permitidas para uso próprio, mas perdem o direito a suporte oficial.

📜 Leia o **[LICENSE.txt](LICENSE.txt)** completo antes de instalar — você concorda com ele ao instalar.

---

## 🧩 IMPORTANTE — o que é grátis × o que entrega no jogo

O **site é grátis** e faz a loja, o painel, a carteira de moedas e o leaderboard. Mas **o site sozinho NÃO entrega as moedas/itens dentro do jogo** — ele registra a compra e credita o saldo. Quem **entrega no DayZ automaticamente** é uma destas peças (separadas do site):

| Peça | O que faz | Custo |
|---|---|---|
| **Site (este template)** | Loja, checkout, painel admin, carteira de moedas, leaderboard, perfis | **Grátis** |
| **Tecplay Agent** (`.exe`) | Lê as compras do site e **entrega as moedas/itens no servidor** (roda na máquina do servidor de jogo) | **Pago** — R$49,90/mês ou R$700 vitalício |
| **Bot Discord Tecplay** | Integra o site + entrega in-game (alternativa pra quem **não tem servidor dedicado**) | Pago (planos) |

> 👉 **Sem o Agent ou o Bot, o jogador paga e a moeda NÃO chega no jogo sozinha** (a liberação seria manual). Por isso o site **não promete "liberação automática"** enquanto não detecta um entregador, e o **painel admin avisa** quando a entrega in-game não está ativa.
> Detalhes do Agent: **https://tecplay.inf.br/produtos/detalhe/?slug=tecplay-agent**

---

## ✨ Features

### 🌐 Lado público

- **Landing page apocalipse** com hero animado, contador de wipe e status do servidor ao vivo (BattleMetrics)
- **Loja** com 6 pacotes seedados (R$ 9,99 a R$ 149,90), bônus, combos e cupons
- **Checkout transparente — PIX + Cartão** (QR/copia-e-cola **e** cartão de crédito **dentro do site**, sem sair pro Mercado Pago; o cartão é tokenizado no navegador, o número **não toca o servidor** — PCI SAQ-A). Cupom compartilhado, parcelamento com mínimo configurável. **Webhook auto-credit** de moedas
- **🎁 Caixas / Lootboxes** (`/caixas`): abre com moedas ou diária grátis, **carrossel "sorteando prêmio"**, **raridade que define a chance**, **countdown ao vivo** da diária, e o item **cai no jogo via CFTools** (fila pendente + blindagem de restart)
- **📦 Histórico de Caixas** no perfil do jogador (recebidas + pendentes, com horário) — transparência do que caiu
- **🗓 Eventos & Sorteios** (`/eventos`): ativos / em breve / encerrados + teaser na home
- **Login Steam OpenID 2.0** com pré-fill automático no checkout
- **Multi-idioma** PT-BR + EN-US (dropdown elegante no header)
- **Multi-server**: 1 site atendendo N servidores DayZ
- **Mural de vendas ao vivo** opcional (com anonimização LGPD)
- **Galeria** de screenshots com lightbox e setas
- **Hall da Fama** dos jogadores (top coins + top apoiadores)
- **Sistema de avaliações** dos jogadores
- **12 conquistas automáticas** (Primeiro Sangue, Veterano, Lendário, Madrugador, Insone, Tubarão, Colecionador, Persistência, Generoso, Tiro Rápido, Veterano de Guerra) — totalmente i18n
- **Reviews públicas** em `/depoimentos` (qualquer visitante envia, admin modera) + `AggregateRating` Schema.org pro Google
- **Wishlist** de pacotes pros jogadores logados
- **Páginas dinâmicas** (Termos, Privacidade LGPD, Regras, Reembolso) editáveis no admin
- **SEO completo**: Open Graph, Twitter Card, JSON-LD, sitemap.xml, robots.txt

### 🛡 Painel admin

- **Dashboard** com gráfico de vendas 30 dias (Chart.js)
- **CRUD completo** de Jogadores, Pacotes, Combos, Cupons, Páginas, Anúncios, Reviews, Servidores, Galeria
- **Loja in-game** (🛒) — cadastre itens que o jogador compra com moeda no Discord (`/loja`): SKU, custo e o que é entregue in-game (classnames). O bot debita e o servidor dropa o item
- **🎁 Caixas** — cria caixas (custo/diária + imagem, **ordem na vitrine**, cooldown opcional) e o pool de itens com **raridade que define a chance** (peso auto-preenchido + % ao vivo). Drop in-game via CFTools GameLabs. **📜 Log de aberturas** pesquisável por SteamID com o horário exato do drop (resolve disputa anti-golpista)
- **🗓 Eventos** — cria eventos/sorteios (datas, prêmio, vencedor); status calculado pelas datas
- **🏆 Recompensas com agendamento** — premia o top do ranking em moedas: cadência Manual/Semanal/Mensal, **auto-creditar** (cron) ou botão **"Premiar agora"** (idempotente) + histórico
- **🎮 Entrega Sparda nativa** — gera as URLs pro mod entregar moeda in-game sem o Agent pago
- **Audit log** de toda ação administrativa
- **Histórico granular de saldo** por jogador
- **Console de logs PHP** integrado ao painel
- **Multi-admin com RBAC**: 4 papéis (super_admin / finance / support / editor). URL não-autorizada cai em 403 dedicado. Suporte só vê jogadores, sem valor financeiro
- **Modo manutenção** com mensagem customizável
- **Promo sazonal** com cupom auto-aplicado em todos os pacotes
- **Webhook Discord** para notificações de venda
- **Configurações editáveis** sem mexer em código

### 🔌 Integrações nativas

- **`tecplay-agent.exe`** — sincronização de moedas in-game ↔ site (`/api/sync-players.php`)
- **Bot Discord Tecplay** — endpoint `/notify/purchase` notifica bot quando venda aprovada; `/api/bot-integration.php` expõe saldo, compra de coins (`/comprar`) e **loja in-game** (`/loja`: `shop_items` + `spend`)
- **BattleMetrics API** — status servidor live com cache 60s
- **Steam Web API** — avatar e display name dos jogadores

### 🔐 Segurança hardened

- **CSRF tokens em 100% das forms POST** (audit v1.2.0 corrigiu 26 rotas admin que estavam sem)
- **RBAC granular**: 4 papéis com matriz de permissões + página 403 dedicada com info do violador
- **Security headers** no `.htaccess`: HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy
- **HTTPS forçado** via 301 redirect (precisa descomentar no `.htaccess` raiz)
- Rate limit no checkout, login admin e reviews públicas (dois eixos: IP + IP+user)
- Sanitização HTML em páginas dinâmicas (allowlist + remove `on*`/javascript:/data:)
- Claim atômico no webhook MP (prevê dupla entrega de coins em race condition)
- Validação obrigatória de assinatura MP em produção
- Open redirect guard em parâmetros `_back`
- Cookies de sessão com `HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS
- Senhas bcrypt (PASSWORD_BCRYPT)
- Cap de 64KB no payload de webhooks
- **Rate-limit anti-bruteforce** nos endpoints `/api/*` — conta **só falhas de auth** por IP, então o mod/agent legítimo (token válido) nunca é limitado
- **Abertura de caixa serializada** (`GET_LOCK`) contra race de duplo-clique na diária
- `session_regenerate_id` no login Steam (anti session-fixation); health-check sem métricas de negócio; JSON-LD com `JSON_HEX_TAG`
- **Pagamento com cartão PCI SAQ-A**: tokenização client-side (Secure Fields do MP), o PAN nunca passa pelo servidor

### ⚡ Performance otimizada

- **PageSpeed Insights**: home/shop mobile **95-97**, desktop **100/100** (medido em produção)
- **WebP-on-the-fly** via Apache content negotiation (sem alteração no markup)
- **Self-hosted fonts** (sem round-trip pro Google Fonts CDN)
- **Cache estático** 1 ano + gzip + preload do hero por página
- **Mobile-first**: drawer pattern admin, scroll horizontal em tabelas, grids inline responsivos
- **Chart Dashboard** com legenda visível, escondido em mobile pra UX limpa

---

## 🚀 Instalação

**Tempo total:** 30 a 45 minutos · **Conhecimento necessário:** zero. Se sabe usar cPanel e FileZilla, consegue.

📖 **Veja o guia completo em [INSTALACAO.md](INSTALACAO.md)** (passo a passo com prints).

### TL;DR pra quem é experiente

```bash
# 1. Sobe via FTP — public/ vai pro public_html, resto fica um nível acima
# 2. Cria database MySQL no cPanel
# 3. Acessa https://seudominio.com/install.php
# 4. Wizard cria config.php + admin + AGENT_TOKEN + opcional MP
# 5. Site no ar
```

### Requisitos no servidor

- PHP **8.0+** (8.2+ recomendado) com extensões: `pdo`, `pdo_mysql`, `mbstring`, `json`, `curl`, `openssl`
- MySQL **5.7+** ou MariaDB **10.3+**
- Apache com `mod_rewrite` e `AllowOverride All` (ou Nginx equivalente)

---

## 🔄 Atualizar (GARANTIDO sem perder seus dados)

Atualizar pra uma versão nova **nunca apaga seus dados nem sua personalização** — desde que você siga a regra de ouro abaixo. Banco, config, skin, logos e uploads ficam intactos.

### 🛑 Regra de ouro: NUNCA sobrescreva estes (são SEUS, não do template)

Ao subir os arquivos novos, **pule / não sobrescreva**:

| Item | O que é | Vem no ZIP? |
|---|---|---|
| `config/config.php` | suas credenciais de banco, tokens, Mercado Pago | ❌ não (gitignored) |
| `public/assets/css/theme.override.css` | suas cores (painel Personalização) | ❌ não |
| `public/assets/img/custom/` | logo/favicon/backgrounds enviados pelo **painel** | ❌ não |
| `public/assets/img/gallery/` | suas screenshots da galeria | ❌ não |
| `storage/` | cache, logs, backups, rate-limit | ❌ não |
| `.htaccess` | **se você editou à mão** (HTTPS, regras próprias) | ⚠️ vem um padrão |
| `lang/pt-br.php` · `lang/en-us.php` | **só se editou textos à mão** (melhor usar o painel) | ⚠️ vem |
| **`public/assets/img/logo*.png`, `background*.png`, `favicon.ico`** | **logos/fundos trocados DIRETO no arquivo (método antigo)** | ⚠️ **VÊM e sobrescrevem!** |

> ✅ Os 5 primeiros são **gitignored** — nem estão no ZIP do GitHub, então um upload normal **não os toca**.
> ⚠️ Os 3 últimos **vêm no pacote**. Em especial: **se você trocou seu logo/fundo substituindo o arquivo direto** em `assets/img/` (método antigo), faça **backup deles antes** ou eles voltam pro padrão do template.
> 💡 **A prova de update de vez:** mande seu logo/favicon/fundos e cores pelo painel **Admin → Personalização** — eles passam a ficar em `custom/` (gitignored) e **nunca mais** são sobrescritos por nenhum update.

### Passo a passo

1. **Backup primeiro** (sempre). Duas formas:
   - Banco: `php cli/backup.php` (gera um `.sql` em `storage/backups/`), ou exporte pelo phpMyAdmin.
   - Arquivos: baixe por FTP uma cópia de `config/`, `lang/`, `public/assets/css/theme.override.css` e `public/assets/img/custom/`.
2. **Suba os arquivos novos por FTP.** Conteúdo de `public/` → raiz pública (`public_html`); o resto (`src/ views/ lang/ config/ migrations/ cli/ schema.sql`) → **um nível acima**, ao lado de `src/`. Suba **todas** as pastas — se faltar uma, o site quebra silenciosamente (ex.: sem `lang/`, o menu vira **NAV.RULES**). **Respeite a regra de ouro acima.**
3. **Atualize o banco** — rode UMA vez:
   ```
   php cli/migrate.php
   ```
   Roda só as migrations que faltam, é **idempotente** e **nunca apaga dados** (só adiciona tabela/coluna que falta). Sem SSH? Use **Cron Jobs** do painel: agende um cron "uma vez" com `php /home/SEU_USER/public_html/cli/migrate.php`, rode e remova.
4. **Confira o deploy:** abra a **home**, a **loja** e faça **login no `/admin`**. Se o menu aparecer como `NAV.RULES`/`SHOP.TITLE`, a pasta `lang/` não subiu — reenvie ela. O resto está no banco e segue intacto.

> 🚨 **NUNCA use o `install.php` pra atualizar, e NUNCA apague o `config.php` pra "reinstalar".** O `install.php` é **só pra instalação do ZERO**. Num site já instalado ele se recusa a rodar (`Já instalado`) — mas se você apagar o `config.php` pra forçar, ele roda do zero e **pode apagar tudo o que você tem** (páginas, pacotes, jogadores, configs). Pra atualizar é **sempre**: subir arquivos (respeitando a Regra de Ouro) **+ `php cli/migrate.php`**. Nunca o install.

## 🔑 Esqueci a senha do admin (recuperação)

Travou fora do painel? Três caminhos, do mais simples ao mais robusto:

1. **Outro admin:** se existe outra conta admin, ela entra em `/admin/team` e reseta a sua.
2. **"Esqueci minha senha"** no `/admin/login` → link por email. *(Precisa de email configurado e funcionando — em hospedagem compartilhada o `mail()` às vezes não sai ou cai no spam.)*
3. **À prova de tudo — reset por linha de comando (NÃO depende de email):**
   ```
   php cli/reset-password.php <usuario> <nova_senha>
   ```
   - **Com SSH:** roda direto na pasta do site.
   - **Sem SSH (Hostinger/cPanel):** Painel → **Cron Jobs** → adiciona um cron "uma vez" com esse comando (o caminho completo do `cli/reset-password.php` você vê no File Manager) → roda → remove o cron.
   Só roda por CLI (o navegador não executa) → seguro.

## 🎨 Personalizar a aparência (sem FTP, sem código)

Tudo pelo painel, em **Admin → Personalização Visual** (`/admin/customize`). O que você muda aqui **sobrevive a updates** (fica isolado do template):

- **Logo, logo pequeno e favicon** — botão "Enviar", escolhe a imagem, pronto. "Voltar ao padrão" desfaz.
- **Backgrounds** (hero, login, loja, 404, páginas) — mesmo esquema. Otimize imagens grandes no [TinyPNG](https://tinypng.com) antes (background pesado deixa o site lento).
- **Cores do site** — color picker com 10 cores (principal, acento, fundos, texto…). Clica em "Salvar cores" e aplica na hora, no site e no painel.

Textos do site (nome, tagline, links sociais, regras, termos, anúncios) ficam em **Admin → Configurações** e **Páginas** — também à prova de update (ficam no banco).

> Se você der "Enviar" e a imagem não mudar na hora, dê **Ctrl+F5** (cache do navegador). É normal.

---

## 🆘 Problemas comuns (resolva você mesmo)

| Sintoma | Causa provável | Solução |
|---|---|---|
| Menu/textos aparecem como **`NAV.RULES`**, `SHOP.TITLE` etc. | A pasta `lang/` não subiu (ou subiu incompleta) | Reenvie a pasta `lang/` (fica ao lado de `src/`). |
| **Internal Server Error / 500** em tudo | PHP abaixo de 8.0, ou `mod_rewrite`/`AllowOverride` desligado | Confirme PHP 8.0+ no painel e que o `.htaccess` está ativo. Veja `storage/logs/php-errors.log`. |
| **"Banco indisponível"** | Credenciais erradas em `config/config.php` ou banco fora do ar | Confira host/usuário/senha do banco no `config/config.php`. |
| **500 só em `/loja` ou `/perfil`** | Faltou rodar as migrations (tabela nova ausente) | Rode `php cli/migrate.php` (veja a seção Atualizar). |
| **Email de recuperação não chega** | `mail()` do PHP é instável em hospedagem compartilhada (cai em spam ou não sai) | Use o reset por CLI: `php cli/reset-password.php <usuario> <senha>` (não depende de email). |
| **Logo/cores sumiram após atualizar** | Sobrescreveu os arquivos do cliente no upload | Reenvie `public/assets/img/custom/` e `theme.override.css` do seu backup. Veja a "Regra de ouro" em Atualizar. |
| **Travei fora do admin** | Perdeu a senha e não tem outro admin | `php cli/reset-password.php <usuario> <nova_senha>` (via SSH ou Cron Jobs). |
| Compra fica **"pendente" pra sempre** | Webhook do Mercado Pago não chegou (token/URL errados) | Confira o webhook no painel do Mercado Pago e o `access_token` no `config/config.php`. |

---

## 📊 Ativar leaderboard + estatísticas (CFTools — opcional)

Quer mostrar **kills, K/D, tempo online e armas** do seu servidor no `/ranking` e no perfil de cada jogador (`/player/SEU_STEAMID`)? O site puxa isso direto do **CFTools Cloud** — você usa o **SEU próprio app** (o segredo fica só com você, controlando só o seu servidor).

> ⚠️ **Atenção (causa de 90% da confusão):** as **Aplicações** ficam no **PORTAL DE DESENVOLVEDOR**, que é **um site separado** do painel normal de servidores. Muita gente procura em `app.cftools.cloud` (o painel dos servidores) e não acha — o lugar certo é **`developer.cftools.cloud`**.

**Passo a passo:**
1. Tenha o **CFTools** ativo no seu servidor DayZ (o agente CFTools rodando — é o que coleta os stats).
2. **Criar a Aplicação (pega o `app_id` + `secret`):**
   - Abra **https://developer.cftools.cloud** e faça login com a sua conta CFTools (a MESMA que é dona do servidor).
   - No menu, vá em **Applications** → **Create application** (dê um nome, ex: "Meu Site").
   - Copie o **Application ID** e o **Secret** (o Secret só aparece uma vez — guarde).
3. **Autorizar a Aplicação a ver seu servidor (Grant):**
   - Ainda na página da sua Application no `developer.cftools.cloud`, vai ter uma **Grant URL** (botão/link tipo "Authorize" ou "Grant access").
   - Abra essa Grant URL **logado na conta dona do servidor** e confirme. Sem isso, a API responde `no-grant`.
4. **Pegar o `server_api_id` (UUID do servidor):**
   - Vá no painel dos servidores: **https://app.cftools.cloud** → abra o seu servidor.
   - O **Server API ID** é o identificador (UUID, tipo `ad221e8f-8c63-...`) que fica em **Settings/Manage do servidor** (ou no endereço da página de gerenciamento dele). É esse valor que vai em `server_api_id`.
5. Preencha as 3 credenciais (`app_id`, `secret`, `server_api_id`). Tem **dois jeitos** (escolha um):
   - **Pelo painel (mais fácil, recomendado):** entre em **Admin → Configurações → 🎮 Integração CFTools** e cole os 3 valores. Salvou, ativou. Sem mexer em arquivo.
   - **Pelo `config/config.php`** (se preferir fixar no arquivo — tem prioridade sobre o painel):
     ```php
     'cftools' => [
         'app_id'        => 'SEU_APPLICATION_ID',
         'secret'        => 'SEU_SECRET',
         'server_api_id' => 'SEU_SERVER_API_ID',
     ],
     ```
6. Pronto. O site cacheia as respostas (respeita os limites do CFTools) e as stats aparecem sozinhas no ranking e nos perfis. **O CFTools também é o que faz os itens das CAIXAS caírem no jogo** — sem ele preenchido, as caixas abrem mas ficam pendentes. **Sem CFTools, o site funciona normal — só não mostra stats de gameplay nem entrega caixas in-game.**

## 🎮 Programa de afiliado / streamer ("Apoie seu Streamer") — opcional

Contratou um streamer pra divulgar o servidor? Dá pra **pagar cachê por venda** que veio dele, automaticamente rastreado.

1. Ative em **Admin → Configurações → 🎮 Programa de afiliado**. (Lá também: "permitir o cliente trocar de streamer".)
2. Em **Admin → Cupons**, crie o cupom do streamer e abra a seção **"Programa de afiliado"**: preencha o **nome do streamer** e o **% de cachê** por recorrência do cliente — **1ª compra / 2ª compra / 3ª+ compra** (ex: `5 / 10 / 0` = ganha 5% na 1ª, 10% na 2ª e nada da 3ª em diante, quando o cliente "já consolidou").
3. O streamer divulga o código. O cliente digita uma vez (no checkout ou no painel **"Apoie seu Streamer"**) e fica **atrelado a ele** — 1 streamer por vez.
4. O **cachê** é calculado **sobre o valor cheio** (antes do desconto), só em **compra paga**. O **benefício pro cliente** (definido no cupom: % off, R$ off ou **🪙 moedas bônus**) vale **1x** — não repete na recorrência, então não corrói sua margem.
5. Veja quanto deve pra cada um em **Admin → Streamers**: faturamento gerado e **cachê a pagar** (total e por mês), além das vendas individuais. O site só mostra o valor — você paga o streamer por fora.

> 🔒 O `secret` é seu e fica só no seu `config/config.php` (que é gitignored e nunca vai pro repositório).

---

## 🎁 Ativar Caixas + Recompensas automáticas (opcional)

As **Caixas** dropam o item no jogo usando a **API GameLabs do CFTools** — então precisa do **mod GameLabs** instalado no seu servidor (Workshop `2464526692`) + o CFTools configurado (acima). Sem GameLabs, a caixa registra a abertura como **pendente** (não dropa). Player precisa estar **online** pra receber; se estiver offline ou perto do restart, fica pendente e cai depois.

- **Caixas:** Admin → **🎁 Caixas** → cria a caixa + adiciona itens (classname DayZ, quantidade, peso). Ative quando estiver pronta.
- **Restart (blindagem do drop):** Admin → **Configurações** → ligue e informe os horários de restart (ex: `00:00, 04:00, ...`). Perto do restart o drop vira pendente pra não cair no limbo.

### ⏱ Crons opcionais (Painel → Cron Jobs)
Pra automatizar entregas pendentes e premiação do ranking:
```
# entrega pendências de caixa (a cada 2 min)
curl -s "https://SEUSITE/api/deliver-boxes.php?token=SEU_AGENT_TOKEN"

# premiação automática do leaderboard (de hora em hora)
curl -s "https://SEUSITE/api/award-rewards.php?token=SEU_AGENT_TOKEN"
```
> `SEU_AGENT_TOKEN` = o `agent_token` do `config.php` (o mesmo que aparece em Admin → 🎮 Integração Sparda). Sem cron, use o botão **"Premiar agora"** e a entrega pendente cai sozinha quando alguém abre `/caixas`.

---

## 🛒 Loja in-game — o `/loja` do Discord (Bot Pro)

A seção **Admin → 🛒 Loja in-game** é o **catálogo do comando `/loja` do Bot Tecplay (Pro)** — não é do site/Sparda. A divisão é:

- **Site** = fonte da verdade: guarda o catálogo de itens e o **saldo de moedas** do jogador.
- **Bot Pro** = o rosto no Discord: mostra o `/loja`, processa a compra (chama o site via `bot-integration.php?action=spend` pra debitar) e manda o servidor **entregar o item in-game**.

**Pré-requisito:** o **Bot Pro** rodando e integrado (Admin → 🤖 Integração Discord). Sem o bot, os itens daqui não fazem nada — pra dropar item **pelo site sem bot**, use as **🎁 Caixas**.

**Como cadastrar um item:** Admin → 🛒 Loja in-game → Novo item. Preencha nome, SKU (id único), custo em moedas, e monte a **entrega** no formulário (o JSON é gerado sozinho):

| Campo | O que é |
|---|---|
| **Classname** | o item DayZ que será dado (ex: `M4A1`, `BarrelLong`) |
| **Qtd** | quantas unidades |
| **Anexos** | peças da arma, separadas por vírgula (ex: `M4_Suppressor, M4_RISHndgrd`) |
| **Cargo (avançado)** | itens DENTRO do item — **só roupa/mochila** (arma com cargo dá erro no jogo) |
| **Vida** | 0 a 1 (1 = novo) |

Um item ("kit") pode entregar vários classnames — é só adicionar mais linhas.

---

## 🏠 Onde hospedar

Você pode rodar este template em qualquer hospedagem PHP. Recomendações **Tecplay-tested**:

### 🟢 Easy Mode — AWS Lightsail $10/mês

Blueprint **LAMP_PHP_8** vem com Apache + PHP 8.1 + MariaDB + phpMyAdmin pré-instalado. Você só:

1. Cria a instância no console Lightsail (3 cliques)
2. Conecta via SFTP, sobe o `public_html`
3. Importa o `schema.sql` via phpMyAdmin (`https://seu-ip/phpmyadmin`)
4. Aponta DNS do domínio pro IP estático da Lightsail
5. Roda `sudo certbot --apache -d seudominio.com` (HTTPS grátis)

Specs: **2 GB RAM · 2 vCPU burst · 60 GB SSD · 3 TB transfer**. Aguenta 100-500 visitas/dia + polling do bot Discord sem reclamar. Aguenta o pico de wipe day.

🔗 https://aws.amazon.com/lightsail/pricing/

### 🔵 Power Mode — Hetzner Cloud CPX21 ~$8/mês

Melhor custo-benefício do mercado se você topa rodar `apt install lamp-server^`. Você precisa:

1. Cria VPS Ubuntu 22.04 no Hetzner (Falkenstein DE ou Ashburn US)
2. `apt install lamp-server^ certbot python3-certbot-apache`
3. Sobe arquivos via SCP/rsync
4. Cria DB MySQL local, importa `schema.sql`
5. Configura virtualhost Apache, roda Certbot

Specs: **3 GB RAM · 3 vCPU AMD · 80 GB NVMe**. Latência BR ~110ms (Ashburn) — coloca Cloudflare na frente como CDN pra esconder isso.

🔗 https://www.hetzner.com/cloud/

### 🚫 NÃO funcionam (não rodam PHP nativo)

- Vercel · Netlify · Cloudflare Pages → só JS/TS/Jamstack
- Render · Railway → PHP só via Dockerfile custom (não vale a dor)

### 💡 Migrando do Hostinger compartilhado?

Tempo total: 2-4h trabalho + ~30min downtime real. Pontos de atenção:
- Charset DB: Hostinger às vezes usa `utf8mb3`, força `utf8mb4_unicode_ci` no dump
- Paths absolutos: refatorar `/home/u123456/public_html/` → `/var/www/html/`
- `.htaccess`: remover blocos `# BEGIN Hostinger`, manter rewrite original
- DNS TTL: baixar pra 300s **24h antes** da migração

- **PHP 8.0+** vanilla (sem framework, sem Composer runtime)
- **MySQL 5.7+** / MariaDB com UTF-8 (utf8mb4)
- Router próprio + View engine com sections/layouts (estilo Blade simplificado)
- Steam OpenID 2.0 + Mercado Pago REST API + BattleMetrics API
- Chart.js via CDN (zero build step)
- **Sem npm, sem composer, sem Docker** — sobe direto

```
DayZWebsite/
├── public/          # Document root (vai no public_html)
│   ├── index.php    # Front controller
│   ├── install.php  # Wizard de setup
│   ├── api/         # mp-webhook.php, sync-players.php, health.php
│   └── assets/
├── src/             # Classes (Database, Router, View, Auth, MercadoPago, ...)
├── views/           # Templates PHP
│   ├── layouts/main.php
│   ├── partials/
│   ├── pages/
│   └── admin/
├── lang/            # PT-BR e EN-US
├── config/          # config.example.php (config.php é gerado pelo install)
├── storage/         # Cache, logs, ratelimit (ignorados pelo git)
├── cli/             # Scripts auxiliares (backup, seed-demo, build-release)
└── schema.sql       # Schema completo
```

---

## 🛠 Suporte oficial

A Tecplay oferece **suporte técnico exclusivo** para a versão **NÃO MODIFICADA** com plano contratado:

👉 **[tecplay.inf.br/suporte/](https://tecplay.inf.br/suporte/)**

Inclui:
- Diagnóstico e correção de problemas
- Atualizações regulares (correções, melhorias, features novas)
- Direito a sugerir features no roadmap via ticket

### 🎨 Modificações sob demanda

Quer algo específico só para o seu servidor (integração, mod, sistema próprio)?

👉 **[tecplay.inf.br/servicos/#web](https://tecplay.inf.br/servicos/#web)**

---

## 📞 Contato

| Canal | Onde |
|---|---|
| 🌐 Site | [tecplay.inf.br](https://tecplay.inf.br) |
| 📧 E-mail | suporte@tecplay.inf.br |
| 💬 Discord | [discord.gg/uwSE3WSjNH](https://discord.gg/uwSE3WSjNH) |

---

## 🤝 Contribuição

Este é um repositório **privado** e **proprietário**. Não aceitamos PRs ou issues de terceiros.

Se você é parceiro Tecplay e precisa relatar bug ou sugerir feature, **abra um ticket** pelo Discord oficial ou e-mail acima — assim entra no roadmap formal.

---

<div align="center">

🏴 *Sobreviva. Construa. Domine.*

**Tecplay © 2026** · Todos os direitos reservados

</div>
