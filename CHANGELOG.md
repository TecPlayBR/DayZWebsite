# Changelog — DayZ Website Template (Tecplay)

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento [SemVer](https://semver.org/lang/pt-BR/).

---

## [2.2.0] — 2026-06-14

> **Tem migration:** `v2.2.0_seed_legal_pages.sql`. Rode `php cli/migrate.php` depois de subir os arquivos.

### 🐛 Correção crítica — páginas legais nasciam vazias
- Instalações nunca semeavam a tabela `pages`, então **Termos, Privacidade (LGPD), Reembolso, Regras, FAQ e Como Conectar vinham em branco** em sites novos (`/page/terms` etc. vazios). Reportado em produção.
- **`schema.sql`**: instalações **novas** agora já nascem com as 6 páginas legais preenchidas (conteúdo de exemplo PT+EN, genericizado).
- **`migrations/v2.2.0_seed_legal_pages.sql`**: para sites **já em produção** — `INSERT … ON DUPLICATE KEY UPDATE` com `IF(vazio)`, ou seja **só preenche o que está vazio/ausente e NUNCA sobrescreve página já editada**; idempotente. Validado em banco (fresh=6 páginas; preserva editado; refila vazio; re-run não duplica).
- Conteúdo de exemplo com placeholders (`[NOME DO SERVIDOR]`, `[SEU CNPJ]`, `discord.gg/SEU-CONVITE`, `[IP:PORTA do seu servidor]`) — sem dados de nenhum cliente. Passo de upgrade documentado em **ATUALIZAR.md**.

### 🐛 Auditoria "instalação nova" — outros furos da mesma classe (corrigidos)
- **Tabela `invoices` faltava no `schema.sql`** (só existia na migration v1.5.0). Como `api/bot-integration.php` e `api/mp-webhook.php` **usam** essa tabela, uma **instalação nova** que usasse a cobrança por Pix de valor livre dava erro "table doesn't exist". Adicionada ao `schema.sql`. (Sites existentes já tinham via `migrate.php` — sem ação.)
- **Newsletter:** o rodapé mostrava o form de captura de e-mail **por padrão** e o admin **não tinha como desligar** (a chave `newsletter_enabled` não estava na whitelist do `Settings` nem era semeada). Agora **vem DESLIGADA por padrão** (`newsletter_enabled='0'` no seed; default 0 no rodapé) e é uma chave válida/controlável (`newsletter_enabled`, `newsletter_forward_url` no `Settings::SCHEMA`).
- **Settings sociais** `social_tiktok/twitch/kick/x` agora semeados (já estavam na whitelist; eram só campos vazios sem linha — cosmético, normalizado).
- ✅ Auditoria das demais dimensões **limpa**: links de menu/rodapé → todas as páginas semeadas; rotas das views todas definidas; paridade de chaves PT/EN; nenhuma outra tabela cuja vazio quebre página pública.

---

## [2.1.0] — 2026-06-14

> Features + hardening. **Sem migration** — só subir os arquivos. Pra ligar o cartão: adicionar `mercado_pago.public_key` no `config.php`.

### 💳 Cartão de crédito transparente (in-site)
- Pagamento com cartão **dentro do site** (sem redirect pro MP). Tokenização client-side via SDK do MP (CardForm/Secure Fields) — PAN **não toca o servidor** (PCI SAQ-A).
- `MercadoPago::createCardPayment()` + `cardRejectMessage()`; rota `POST /shop/card-pay/{id}` (anti-IDOR via `$_SESSION['checkout_pids']`, CSRF, rate-limit); entrega pelo webhook (claim atômico).
- Campos nativos (autofill ok), documento fixo em CPF, parcelamento com mínimo configurável (`card_installments_min`, default R$30), cupom compartilhado PIX+Cartão. Gated em `mercado_pago.public_key` (vazio = só PIX).

### 🎁 Caixas / 📦 Inventário
- **Ordem na vitrine** configurável (auto max+1 em caixa nova); **cooldown opcional** (0 = sem espera); caixa nova nasce ativa.
- **Raridade dirige o peso/chance** (auto-fill no admin + % ao vivo).
- **Countdown ao vivo** da diária (libera o botão ao zerar, sem reload).
- **Histórico de Caixas** no painel do jogador (`/my-purchases`) + **log admin** de aberturas pesquisável por SteamID com horário do drop (`/admin/caixas/logs`).

### 🔍 SEO
- Títulos/descrições **únicos por página**; **hreflang** pt-BR/en-US/x-default; **sitemap** corrigido (`/caixas` in, `/server-status` out, dedup `/rules`).
- **Schema** Product/Offer no `/shop` + **FAQPage** no `/faq`; JSON-LD com `JSON_HEX_TAG`.
- Checkbox de termos **não pré-marcado** (CDC/LGPD).

### 🛡 Segurança
- **Rate-limit** anti-bruteforce em `public/api/*` (só falhas de auth — não afeta o mod, que vem de 1 IP).
- `GET_LOCK` serializa `Boxes::open()` (race da diária); `session_regenerate_id` no login Steam; guard anti open-redirect; `health.php` sem métricas de negócio; customizador de tema com backup `.bak` + audit antes→depois.

### 🐛 Correções
- Playtime do perfil (CFTools `omega.playtime`); faixas status+restart empilhadas (flex); faixa de anúncio como card com ícone; **PT 100% acentuado** nas Regras + páginas legais; webhook MP não rebaixa compra entregue.

---

## [2.0.0] — 2026-06-13

> Consolida 1.6.0 → 2.0.3. Migrations idempotentes: `v1.6.0_player_stats`, `v1.8.0_caixas`, `v1.9.0_reward_payouts`, `v2.0.0_eventos`, `v2.0.1_player_origin_reward`, `v2.0.2_package_image`, `v2.0.3_box_item_coins`.

- **🎁 Caixas / Lootboxes**: carrossel estilo CS:GO, drop real via CFTools, sorteio por peso, blindagem de restart, recompensa item ou moedas, imagens.
- **🗓 Eventos & Sorteios** (`/eventos` + teaser na home).
- **🏆 Recompensas do leaderboard** agendadas (semanal/mensal, auto-creditar, idempotente, histórico).
- **💳 Checkout PIX transparente** (QR no site).
- **🔌 Entrega in-game nativa** via mod Sparda (`getcoins`/`postcoins`).
- **🟢 Status + próximo restart** em tempo real (CFTools); **🖼 foto/nick Steam** no perfil; **imagens nos pacotes**.
- **🛡 Hardening**: XSS (caixas), IDOR (status/pagamento), débito atômico, webhook MP HMAC + reconsulta, fuso BR, i18n PT/EN.

---

## [1.5.0] — 2026-06-10

### 💸 Cobrança Pix de valor livre (helpdesk + pagamentos)

O admin gera, pelo bot Discord (`/cobrar`), uma cobrança Pix de **valor arbitrário**
com dados do cliente (nome, CPF, email, telefone, descrição) e manda o QR. Serve pra
cobrar **serviços/atendimento**, não só loja de coins. Cobra no **MP do site**.

- **Nova tabela `invoices`** — cobranças avulsas: `invoice_ref` UNIQUE (idempotência),
  dados do cliente, `amount_brl`, `status` (pending/paid/expired/cancelled), `qr_code`
  (copia-e-cola guardado pro retry idempotente), audit (`created_by`, `guild_id`).
- **`/api/bot-integration.php`** ganhou duas actions:
  - **`POST ?action=create_invoice`** `{name, cpf?, email?, phone?, description, amount_brl, invoice_ref, created_by?, guild_id?}`
    → cria o Pix (MP do site) e devolve `{qr_code, qr_code_base64, ticket_url, invoice_id, expires_at}`.
    Idempotente por `invoice_ref` (não cobra 2x — devolve o QR guardado).
  - **`GET ?action=invoice_status&invoice_ref=X`** → `{status, amount_brl, paid_at, expires_at}` (bot faz polling).
- **`mp-webhook.php`** roteia `external_reference` com prefixo `inv-` → marca a `invoice`
  como `paid` (idempotente, `paid_at` via COALESCE). NÃO toca o fluxo de purchases.
- ⚠️ MVP **sem emissão de NF** (decisão do Financeiro — ME/Simples Nacional). CPF fica só no site (LGPD).

**Migration:** `migrations/v1.5.0_invoices.sql` (idempotente). **AÇÃO por cliente:** rodar no DB do cliente.

---

## [1.4.0] — 2026-06-10

### 🛒 Loja in-game — catálogo gastável + entrega (Fase 2)

Os jogadores agora **gastam moeda** (no Discord, via `/loja` do bot) pra receber
**itens dentro do DayZ**. O site é a fonte da verdade do saldo e do catálogo; o
bot debita e o mod (PBO) entrega in-game.

- **Nova tabela `shop_items`** — itens gastáveis: `sku`, `name`, `icon`,
  `coins_cost`, `enabled`, `sort_order` e `deliver_json` (o que o servidor
  entrega: `[{classname, quantity, attachments[], cargo[], health}]`).
- **Nova tabela `shop_spends`** — registro de cada gasto com `spend_ref` UNIQUE
  (idempotência: retry de rede não debita 2x) + snapshot do `deliver`.
- **`/api/bot-integration.php`** ganhou duas actions:
  - **`GET ?action=shop_items`** — catálogo habilitado pro bot listar no `/loja`.
  - **`POST ?action=spend`** `{steam_id, sku, server_id?, spend_ref}` — debita
    `players.coins` de forma **atômica** (`UPDATE ... WHERE coins >= custo` +
    transação, nunca fica negativo), idempotente por `spend_ref`, devolve
    `{ ok, new_balance, deliver:[...] }`. Erros: `402 insufficient_coins`,
    `404 item_not_found`/`player_not_found`.
- **Admin `/admin/shop`** — cadastro dos itens (criar/editar/ativar/excluir),
  com o `deliver[]` editável (permissão `packages`).

> **Update:** rode `migrations/v1.4.0_shop_catalog.sql` no banco. Aditivo, não
> mexe em nada existente. Cadastre os itens em **Admin → 🛒 Loja in-game**.

---

## [1.3.0] — 2026-06-08

### ✨ Loja via Discord (bot-integration) — multi-canal

- **`/api/bot-integration.php`** ganhou duas actions pra loja funcionar 100% pelo
  Discord (cliente que não usa o site web):
  - **`GET ?action=packages`** — lista os pacotes de coins habilitados
    (`{ bonus_enabled, packages:[{id,name,icon,coins,bonus_coins,price_brl,badge,ribbon,featured}] }`).
  - **`POST ?action=create_checkout`** `{steam_id, package_id, server_id?, coupon_code?}`
    — **reusa o mesmo fluxo do checkout web**: cria `purchases` pending + preference
    do Mercado Pago e devolve `{ ok, purchase_id, init_point, price_brl, coins_total }`.
    O cliente paga pelo link e o **`mp-webhook.php` credita os coins** — mesma entrega
    do site, sem duplicar pagamento.
  - **`POST ?action=create_pix`** (mesmo body) — **QR Pix DIRETO** pro `/comprar` do
    Discord: cria um pagamento Pix no Mercado Pago do site e devolve
    `{ qr_code (copia-e-cola), qr_code_base64 (PNG), ticket_url, purchase_id, expires_at }`.
    Coins creditados pelo mesmo `mp-webhook.php` na aprovação. Novo método
    `MercadoPago::createPixPayment()` (com idempotência) suporta isso.
  - A validação/criação de `purchases` foi fatorada em `_prepare_purchase()` —
    `create_checkout` e `create_pix` compartilham (sem duplicar regra de cupom/bônus/server).
- **Por quê:** a loja é a mesma (este site/DB), então comprar pelo Discord ou pelo
  site bate no mesmo saldo/compras. Auth e log inalterados (Bearer + `discord_integration_log`).
- **Pendente (próxima versão):** `?action=spend` (gastar coins em kit/VIP pelo Discord)
  — depende do catálogo de itens in-game (em desenvolvimento).

---

## [1.2.1] — 2026-06-06

### 🐛 Bugfix CRÍTICO — sync-players compat com Tecplay Agent

- **`/api/sync-players.php`** agora devolve `steamid` como **alias** de `steam_id`
  no GET, e aceita `steamid` como fallback no POST.
- **Por quê (grave):** o `tecplay-agent.exe` usa o campo `steamid` (sem underline).
  Como o template renomeou para `steam_id`, o agent não enxergava nenhum player do
  site → **compras pagas não chegavam no jogo** (sincronização DB→JSON quebrada).
  Afeta TODO cliente que roda o Agent.
- **Ação pro cliente:** atualizar `public/api/sync-players.php` (pull/redeploy do
  template). Não precisa mexer no Agent — o alias resolve do lado do site.

---

## [1.2.0] — 2026-06-05

Release com foco em **segurança, RBAC, performance mobile e experiência
multi-idioma**. Atualização recomendada pra todos os clientes em produção.

### 🔒 Segurança

- **RBAC (Role-Based Access Control)** no painel admin. 4 papéis:
  - `super_admin` — tudo (gerencia equipe, settings, integrações).
  - `finance` — Dashboard, Pacotes, Combos, Compras, Cupons.
  - `support` — somente Jogadores e Avaliações (sem acesso a valor financeiro).
  - `editor` — Páginas, Galeria, Anúncios, Personalizar visual.
  - URL direta sem permissão cai em página **403 Acesso negado** com user/role/path/timestamp registrados.
  - Helpers novos: `Auth::can($area)`, `Auth::requireCan($area)`, `Auth::homePath()`.
  - UI nova em `/admin/team` com select de role + matriz de permissões visível.
  - Guards: não dá pra auto-rebaixar nem deixar sistema sem super_admin.
- **CSRF em 100% das rotas POST admin**. Audit anterior descobriu 26 rotas sem
  proteção. Todas patcheadas + valid_token check obrigatório.
- **Security headers** no `.htaccess`: HSTS, X-Content-Type-Options,
  X-Frame-Options, Referrer-Policy, Permissions-Policy.
- **HTTPS forçado** via 301 (descomentado no `.htaccess` raiz).

### ⚡ Performance

- **WebP-on-the-fly** via Apache rewrite no `.htaccess` raiz. Imagens `.png`/`.jpg`
  retornam `image/webp` quando o browser suporta. Sem alteração no markup.
  Esperado: −70% a −92% no payload de imagens hero.
- **Self-hosted fonts** (`public/assets/fonts/`). Elimina round-trip pro Google
  Fonts CDN. Inter (variable font, weights 400-700), Black Ops One, VT323.
  Esperado: −780ms render-blocking + −1300ms cadeia de dependência.
- **Cache estático** via `mod_expires` + `mod_headers` (1 ano pra imagens/woff2,
  1 mês pra css/js).
- **Compressão gzip** via `mod_deflate` (html/css/js/json/xml).
- **`<link rel="preload">` dinâmico** por página — cada page setou seu hero
  background via `View::with('hero_image', ...)`. Antes era `background.png`
  hardcoded e o preload virava cache miss.
- **PageSpeed Insights pós-deploy** (referência): home mobile **97** / desktop
  **100**; shop mobile **95** / desktop **100** (testado num servidor real).

### 📱 Mobile

- **Drawer pattern** no admin (hambúrguer top-right, sidebar slides off-canvas).
  Antes a sidebar 260px fixa estourava em viewport pequeno.
- **Auto-wrap** de tabelas admin em `<div class="admin-table-wrap">` com scroll
  horizontal. Funciona via MutationObserver (cobre PJAX automaticamente).
- **Grids inline 2-3 cols** em forms admin viram 1 coluna em mobile via
  `[style*="grid-template-columns: 1fr 1fr"]` + `!important`.
- **Hero status BattleMetrics** centralizado + embaixo dos CTAs em mobile
  (antes ficava lado a lado com o conteúdo, espremendo o texto pra esquerda).
- **Body scroll lock** quando drawer aberto (com restauração de posição).
- **Hide-on-scroll** do hambúrguer admin (some ao rolar pra baixo, volta ao rolar pra cima).
- **Bandeiras lang-flag** maiores no drawer mobile (era 32×22px, agora 56×38px,
  opacity 0.85 mínima pra todos serem visíveis).

### 💬 Conteúdo / Engajamento

- **Reviews públicas**: form em `/depoimentos` permite qualquer visitante enviar
  avaliação (rate-limit 3/h por IP). Admin modera em `/admin/reviews`. Schema:
  `reviews.source` (`purchase` | `public`) + `purchase_id`/`steam_id` NULL.
- **Testimonials na home** ler reviews aprovadas reais (rating ≥4) via DB,
  substituindo o setting `testimonials_json` manual.
- **AggregateRating Schema.org** dormente — ativa quando count(reviews approved) > 0.
  Google pode mostrar estrelas no SERP.
- **Newsletter capture** no footer com card moderno + email de confirmação
  automático via `Mailer`. Endpoint `POST /api/newsletter-subscribe` com
  rate-limit 10/h por IP. Setting `newsletter_forward_url` permite forward
  pra Reach 100/Mailchimp/etc sem deploy.
- **12 conquistas** (era 6): adicionadas Whale, Insomniac, Streak, Generous,
  Rapid Fire, Anniversary. Totalmente i18n (`lang/*.php` → `achievements.<slug>`).

### 🌐 i18n (EN-US)

- Sweep completo de strings hardcoded em PT → `__()` em:
  - Footer (links, copyright, newsletter, social proof)
  - Hero status BattleMetrics (online/offline/voltando/avise no Discord)
  - Hero stats (jogadores cadastrados/jogando agora/compras semana)
  - Live purchases label
  - Testimonials home
  - Shop notes (coupon, terms, SteamID warning, auto delivery)
  - Profile/My Purchases (todos os campos, flash messages, ações)
  - Checkout return (success/pending/fail)
  - Depoimentos page (hero, empty state, form completo, flash messages)
  - Gallery
  - Status badges (`✓ Entregue` → `✓ Delivered`, etc)
  - Conquistas (12 nomes + descrições)
- **Tagline multilang** via setting per-locale: `site_tagline_ptbr` /
  `site_tagline_enus`. Footer escolhe automaticamente.
- **SEO per-page** via `View::with('title', ...)` + `'description'` —
  qualquer view filha pode sobrescrever sem mexer em controller.
- Pacotes (`packages.name/description`) **mantidos em PT** propositalmente —
  são nomes de produtos/brand (decisão de UX, não bug).

### 🎨 UX/UI

- **Chart dashboard**: legend visível com `pointStyle: rectRounded` + boxWidth
  14×14, labels `💰 Receita (R$)` / `📦 Compras (quantidade)`, tooltip rico
  com formatação BRL, height fixo 320px. Cores hardcoded (`#4ade80` verde,
  `#fde047` amarelo) — funcionam em qualquer skin custom escura.
- **Chart escondido em mobile** (≤760px) — UX mobile prioriza stat cards +
  últimas compras. JS pula `fetch()` pra economizar request.
- **Stat cards mobile** em 2 colunas (≤640px) ou 1 (≤480px).
- **Footer drawer mobile** com links rápidos (Termos · Privacidade · FAQ) +
  copyright. Substitui o lang-select redundante (dropdown já existe no header).
- **Padronização emojis** na nav admin (📊 👥 📦 🛰 🤖 🎁 💰 📄 📢 🎟 ⭐ 🖼 🧑‍💼 📋 🐛 🎨 ⚙️ 🛟).
- **CTA Discord** quando server offline (alt-cta em vez de "Offline" cru).
- **Hide rank BM ruim**: setting `bm_rank_threshold` (default 500) — rank
  só aparece se for top N.

### 🔧 Schema

- **`admin_users.role`** — VARCHAR(20) DEFAULT 'super_admin'.
- **`reviews.source`** + `purchase_id`/`steam_id` NULL.
- **`newsletter_emails`** — tabela nova.
- Migration: `migrations/v1.2.0_rbac_reviews_newsletter.sql` (idempotente, usa
  information_schema pra checks).

### 🐛 Bugfixes

- **Loop infinito** em `/admin?err=csrf` causado por CSRF check inserido em
  GET por engano (regex de batch). Removido.
- **Logo footer** pesava 65KB pra render 60×60 — versão dedicada
  `logo_semfundo_small.png` (120×120 source) = 8KB WebP servido (−87%).
- **Preload mismatch** hero — preload apontava `.webp` mas CSS pedia `.png`,
  causando double download. Padronizado: preload usa mesma URL do CSS, Apache
  faz content negotiation transparente.
- **Cores chart** invisíveis em skins escuras (var(--rust) bordô em bg bordô).
  Trocadas por hex high-contrast (`#4ade80`, `#fde047`).
- **CSS @media legacy** em `admin.css` linhas 610-644 (responsivo antigo do
  v1.0) sobrescrevia drawer pattern novo. Removido.
- **Lang-flag** EN com `opacity: 0.55` ficava invisível no drawer mobile.
  Override pra 0.85 + 56×38px.
- **CSRF field** ausente em 4 forms (gallery x2, servers x2). Adicionados.

### 📚 Documentação

- `views/pages/admin_forbidden.php` — página 403 dedicada com info do violador.
- `INSTALACAO.md` atualizado com seção RBAC + migration v1.2.0.

### Como atualizar

1. Pull do repo (ou substitui arquivos preservando `config/config.php` e `theme.override.css`).
2. Aplica migration:
   ```bash
   mysql -u USER -p DB < migrations/v1.2.0_rbac_reviews_newsletter.sql
   ```
3. Em `/admin/team`: atribui roles aos membros (todos viram `super_admin` por
   default — restringe quem precisa).
4. **Opcional**: descomenta `RewriteCond %{HTTPS} off` no `.htaccess` raiz pra
   forçar HTTPS 301 (recomendado).

---

## [1.1.1] — 2026-06-04

### Alterado — Template 100% skinável via override

Refactor de cores hardcoded → CSS custom properties em todo o template.
Override de paleta (`public/assets/css/theme.override.css`) agora cobre
~98% do visual do site público + painel admin. Antes, paleta antiga
(`rust/bone apocalipse`) e cores Tailwind ficavam hard-codadas em ~50
arquivos PHP/CSS e ignoravam o override.

**Wave 1** — 32 arquivos, 125 substituições:
- Cores Tecplay duplicadas em vars (`#a855f7`, `#ede9fe`, etc) → `var(--*)`
- Paleta antiga `rust/bone` (`#c1440e`, `#d4c5a9`, `#0d1014`, etc) → vars novas
- Tailwind colors (`#fca5a5` text-danger, `#86efac` text-success) → vars novas
- Overlays translúcidos `rgba(231,76,60,0.12)` etc → vars novas

**Wave 2** — 12 arquivos, 37 substituições:
- Paleta apocalipse remanescente (`#d4a017`, `#6b7280`, `#e74c3c`, `#5a6c4e`)
- Variações de alpha em overlays vermelho/dourado

### Adicionado — 7 vars CSS novas no `:root`

```css
--text-danger:    #fca5a5;
--text-success:   #86efac;
--danger-overlay: rgba(231,76,60,0.12);
--danger-border:  rgba(231,76,60,0.4);
--hazard-overlay: rgba(212,160,23,0.08);
--hazard-border:  rgba(212,160,23,0.2);
--dark-overlay:   rgba(0,0,0,0.6);
```

`theme.override.example.css` ganhou doc detalhada de todas as vars disponíveis.

### Compatibilidade

- Sem breaking change. Override existente continua funcionando.
- Sites em produção sem `theme.override.css` continuam idênticos (paleta Tecplay default).
- Pra aproveitar 100% do refactor, override deve setar as 7 vars novas além das antigas.

---

## [1.1.0] — 2026-06-02

### Adicionado

- **Integração Discord** com Tecplay Bot (Pro/Free). 🤖
  - Endpoint novo `/api/bot-integration.php` — autenticado por Bearer token,
    isolado do `/api/health.php` público.
  - 3 actions disponíveis:
    - `GET /api/bot-integration.php` → teste de conexão
    - `GET /api/bot-integration.php?action=player&steam_id=...` → coins, total_spent_brl, last_seen_at, display_name
    - `GET /api/bot-integration.php?action=stats` → players_total, sales_today, revenue_month_brl, vip_active
  - Aba **"🤖 Integração Discord"** no admin: gerar token (`tcp_` + 48 hex chars
    com 192 bits de entropia), copiar com 1 clique, status visual
    (verde<5min · amarelo<1h · vermelho>1h ou nunca), log das últimas 10
    chamadas (timestamp, IP, action, HTTP code).
  - Tabela nova `discord_integration_log` (capada em 200 registros, GC
    automático em 5% das chamadas).
  - Settings novos: `discord_integration_token`, `discord_integration_last_ok`.
  - **Distribuível:** repo NÃO vem com token embutido — cada cliente gera o
    seu no admin. Endpoint exige HTTPS em produção (header `X-Content-Type-Options`,
    sem CORS).
  - Migration: `migrations/v1.1.0_discord_integration.sql` (idempotente, INSERT IGNORE).

### Como o cliente final usa

1. Aplica a migration no DB (uma vez):
   ```bash
   mysql -u USER -p DB < migrations/v1.1.0_discord_integration.sql
   ```
   (ou via phpMyAdmin → Importar)
2. Acessa `/admin/discord-integration` → clica "Gerar novo token" → copia.
3. Cola URL do site + token na aba "Integração Site" do painel do Tecplay Bot.
4. Clica "Testar conexão" no painel do bot → status verde.

### Notas técnicas

- Endpoint `/api/health.php` continua **público** e intocado (uptime monitor
  não quebra).
- Token salvo em `settings`, nunca em `config.php` (cada cliente isolado).
- Log usa `INSERT IGNORE` na migration pra ser idempotente — pode rodar de novo.

### Alterado — Identidade visual Tecplay

- **Paleta default** do template migrada de "rust/bone/moss" (apocalipse genérico)
  para a identidade oficial **Tecplay** (roxo brand `#a855f7` + lilás `#c084fc`
  + accent dourado `#facc15` + fundo violeta noite). Espelha
  https://tecplay.inf.br.
- **Sistema de skin customizada**: novo arquivo
  `public/assets/css/theme.override.css` (gitignored) sobrescreve a paleta
  default sem tocar no template. Carregado automaticamente se existir.
- **Helper PHP** `theme_override_tag()` em `src/helpers.php` insere o `<link>`
  do override condicionalmente em todos os layouts.
- **Exemplo de override**: `theme.override.example.css` traz 4 paletas prontas
  (apocalipse rust, sangue+esmeralda, cyan tactical, toxic green). Cliente copia
  pra `theme.override.css` e edita.
- **Aba Admin → Personalizar** atualizada: orienta usar override em vez de
  editar `theme.css` direto. Atualizações futuras do template não sobrescrevem
  as cores customizadas.
- Nomes das CSS vars (`--rust`, `--bone`, `--moss`…) preservados — 258
  referências espalhadas no CSS continuam funcionando. Só os valores mudaram.

---

## [1.0.0] — 2026-05-XX

Versão inicial do template.

### Funcionalidades base

- Sistema de loja com Mercado Pago (PIX/boleto/cartão)
- Steam OpenID 2.0
- BattleMetrics integration (server status)
- Multi-server support (1 site, N servidores DayZ)
- Painel admin completo (players, packages, purchases, settings, gallery, etc)
- Sistema de cupons + combos
- Achievements calculados on-the-fly
- Páginas dinâmicas editáveis
- Multi-idioma (pt-br / en-us)
- Sincronização com Tecplay Agent via `/api/sync-players.php`
- Health check em `/api/health.php`

---

🏴 *Tecplay — Sobreviva. Construa. Domine.*
