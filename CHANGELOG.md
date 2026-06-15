# Changelog — DayZ Website Template (Tecplay)

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento [SemVer](https://semver.org/lang/pt-BR/).

---

## [2.3.10] — 2026-06-15

> Sem migration — só subir os arquivos. Acabamento do v2.3.9 + documentação atualizada.

### 🌐 i18n da nota de termos no checkout
- A nota "Ao concluir o pagamento, você aceita os Termos…" (adicionada na v2.3.9) estava **hardcoded em PT**. Virou chave de idioma (`pix.terms_note` + `pix.terms_terms` + `pix.terms_refund`) com tradução PT/EN — paridade de idiomas mantida (292/292 chaves).

### 🧹 Botão "Ver no admin" no checkout de modo-dev
- A tela de checkout **modo dev** (só aparece quando o Mercado Pago **não** está configurado) tinha um botão "Ver no admin" visível pra qualquer visitante. Agora só aparece pra **admin logado** (atalho de teste) — visitante comum não vê. Em produção com MP configurado essa tela nunca aparece.

### 📚 Documentação atualizada (estava defasada)
- **INSTALACAO.md:** o passo a passo do `install.php` documentava 5 cards — agora cobre os **7** (incluindo **CFTools** e **Dados de exemplo**), com aviso de limpar o demo antes do go-live.
- **README.md:** a seção CFTools agora mostra que dá pra configurar **pelo painel** (Admin → Configurações), não só pelo `config.php`, e deixa claro que o CFTools também faz as **caixas caírem no jogo**.
- **RELEASE_NOTES.md:** estava parado na v2.2.0 — atualizado pra v2.3.9 com os destaques da linha 2.3.x (badge do README aponta pra ele).

---

## [2.3.9] — 2026-06-15

> Sem migration — só subir os arquivos. UX da loja + histórico in-game no painel + dados de exemplo no instalador.

### 🛒 Aceite dos Termos saiu da vitrine `/shop` → virou aviso no checkout
- Removido o **checkbox obrigatório** "Li e aceito os Termos" da página da loja (era fricção: o jogador tinha que marcar antes de escolher o pacote). O aceite agora é **por ação** — ao concluir o pagamento na tela de checkout, com um aviso claro "Ao concluir o pagamento, você aceita os Termos de Uso e a Política de Reembolso" linkando ambos. O registro de aceite (`terms_accepted_at`) continua sendo gravado.

### 🎮 Histórico da loja in-game (`/loja` do Discord) no painel do jogador
- A página **Minhas compras** agora mostra a seção **"Loja in-game (/loja)"** com os gastos de moeda feitos pelo comando `/loja` e entregues no jogo (item, moedas gastas, saldo após, data). Degrada limpo em instalações antigas sem a tabela `shop_spends`.

### 🌱 Instalador (`install.php`) com opção de dados de exemplo
- Novo checkbox **"Popular com dados fictícios"** (marcado por padrão): cria jogadores, compras, avaliações e anúncios de exemplo pra o site não nascer vazio. Tudo marcado como demo (steam `76561197000*`, anúncios `[demo]`) e removível com `php cli/seed-demo.php --clean` antes do go-live. Lógica de seed extraída pra `cli/seed-demo-lib.php` (compartilhada entre o CLI e o instalador).

---

## [2.3.8] — 2026-06-15

> Sem migration — só subir os arquivos. **Correção importante (afetou cliente real).**

### 🔴 Removido o "R$ X / moeda" (custo por moeda) dos cards da loja
- O rótulo arredondava pra **"R$ 0,01 / moeda"** em quase todos os pacotes (2 casas decimais), ficando idêntico e inútil. **Pior:** criava uma falsa "tabela de preço por moeda" que jogadores usavam pra **exigir uma quantidade de moedas calculada** (preço ÷ 0,01), recusando a quantia real do pacote — gerou disputa com cliente real.
- A quantidade de moedas é definida **por pacote** (não por real), então esse "preço por moeda" não faz sentido e foi **removido** dos cards da loja. O preço do pacote e a quantidade de moedas continuam visíveis normalmente.

---

## [2.3.7] — 2026-06-15

> Sem migration — só subir os arquivos. Fim do pente-fino do admin.

### ✏️ Anúncios — editar de verdade
- O backend já fazia UPDATE, mas a UI só tinha "apagar e criar de novo" (com um texto "em breve"). Agora cada anúncio tem botão **✎ Editar** → abre o form **pré-preenchido** (título, texto, tipo, datas, botão, publicado), e o submit vira "Salvar alterações". Removido o texto de "chegará em update futuro".

### 🧹 Limpeza
- **players:** a mensagem de "nenhum jogador encontrado com esses filtros / limpar busca" era inalcançável (o empty-state genérico capturava antes). Agora a busca filtrada sem resultado mostra a mensagem certa.
- **gallery / servers:** removidos inputs `csrf_token` mortos (o CSRF real é o `_csrf` do `Csrf::field()`, que continua presente) — eram markup confuso sem efeito.
- Auditoria confirmou: **todas as rotas `/admin/*` têm guard de permissão**; CSRF em todos os POSTs.

---

## [2.3.6] — 2026-06-15

> Sem migration — só subir os arquivos. Pente-fino do admin + logo responsiva.

### 🖼️ Logo responsiva (logo "com nome" embutido)
- Quem usa uma **logo com o nome embutido** (imagem larga) em vez de uma logo curta tinha o logo **esmagado/minúsculo** no rodapé (o `.footer-brand img` forçava 60×60). Agora header e rodapé usam **`width:auto` + `max-width` + `object-fit:contain`** — serve tanto logo quadradinha quanto logo larga, sem distorcer.

### 🐛 Admin
- **Discord "split-brain":** o painel só tem o campo `social_discord`, mas o botão Discord do **header** (+ página de manutenção, erro 500 e rodapé de e-mail) liam `discord_invite` — que não tem campo no admin. Resultado: cliente novo preenchia "Discord" e o botão do header ficava vazio. Agora esses pontos leem `social_discord` (com fallback pro `discord_invite` legado). Texto de ajuda corrigido.
- **Caixas — criar rápido:** removido o checkbox "Ativa" que era ignorado (caixa nova nasce sempre ativa por design — agora há uma nota explicando, em vez do controle que não fazia nada). Corrigido também um warning de `slug` (chave indefinida) no criar rápido.

---

## [2.3.5] — 2026-06-15

> Sem migration — só subir os arquivos.

### 🐛 Upload de imagem quebrava em host sem a extensão `fileinfo`
- Em hospedagens que **não têm a extensão PHP `fileinfo`**, subir imagem (logo, galeria, pacote, caixas) dava fatal: **`Call to undefined function finfo_open()`**. Reportado por cliente em produção.
- Novo helper `detect_image_mime()` resiliente: tenta `finfo`; se a extensão não existe, cai pra **`getimagesize()`** (parte do GD, quase sempre presente — e ainda confirma que é imagem REAL) e por fim `mime_content_type()`. Aplicado nos **4 pontos de upload** (imagem de pacote, galeria, marca/customize, e o `upload_image` de caixas/itens).
- A validação por allowlist de MIME continua igual (arquivo não-imagem segue rejeitado). Testado: PNG real → `image/png`; texto → rejeitado; fallback sem finfo funciona.

---

## [2.3.4] — 2026-06-15

> Sem migration — só subir os arquivos.

### 💚 Login Steam não joga mais o jogador na loja
- Depois do login Steam, o jogador era sempre redirecionado pra `/shop` — ficava evasivo, parecia "compra moeda agora". Agora ele **volta pra página onde estava** quando clicou em login (ranking, perfil, etc., com query preservada). Sem referer disponível, cai na **home** (neutro), não na loja.
- Captura segura: só path interno same-site; ignora `/auth` e `/admin` (sem loop) e referer externo (anti open-redirect). Os fluxos que precisam de destino fixo (avaliar em `/depoimentos`, ver `/my-purchases`) seguem indo pro lugar certo.

---

## [2.3.3] — 2026-06-15

> Sem migration — só subir os arquivos. Pente-fino das páginas principais.

### 🐛 Correções
- **Contador de players não inventa mais "/60"**: home e `/server-status` mostravam `X/60` quando o máximo do servidor vinha desconhecido (chumbado em 60). Agora mostra só o número de online quando o máximo não é conhecido — nada de denominador inventado pra servidor que não é de 60 slots.
- **Link morto no `/eventos`**: o vencedor de um evento encerrado virava link `/player/` (vazio) se tivesse nome mas não SteamID. Agora só vira link quando há SteamID; senão é texto normal.

### 🧹 Limpeza (sobras de feature removida)
- `player_public.php`: removido o CSS órfão `.pp-tx*` (sobra de uma seção de transações que não existe mais — o perfil público não mostra dado financeiro).
- `depoimentos.php`: removido o mapeamento de erro `invalid_name` (o handler nunca emite — o nick vem da Steam, não de input).

---

## [2.3.2] — 2026-06-15

> Sem migration — só subir os arquivos.

### 🔌 Alias do webhook do Mercado Pago
- Novo `public/api/webhook.php` que encaminha pro `mp-webhook.php`. Alguns painéis do MP ficam apontados pra `/api/webhook.php` (URL antiga/padrão) enquanto o handler real é o `mp-webhook.php` — sem o alias, essa URL do painel dava **404 (webhook morto)**. Agora **as duas URLs funcionam**, então não tem como o painel do MP ficar com webhook quebrado por causa do nome do arquivo. (O ideal segue sendo apontar o painel pra `/api/mp-webhook.php`; o alias é a rede de segurança.)

---

## [2.3.1] — 2026-06-15

> Sem migration — só subir os arquivos. Limpeza da loja.

### 🧹 Loja (/shop) — tira ruído
- **Removido o campo de cupom da `/shop`**: era redundante — o cupom já é digitado na tela de **checkout**. A promo sazonal continua aplicando automática no "Comprar" (preço riscado segue válido); cupom manual só no checkout.
- **Removido o depoimento (social proof) de dentro da `/shop`**: review de player não faz sentido no meio da loja — fica em `/depoimentos`. (O estilo `.pack-percoin`, que estava escondido nesse bloco, foi movido pro CSS principal — preço por moeda segue estilizado.)
- Testado: `/shop` renderiza 200 sem erro; cupom/depoimento fora; botão Comprar, preço-por-moeda e cross-sell intactos.

---

## [2.3.0] — 2026-06-14

> Sem migration — só subir os arquivos. Onda de **performance, acessibilidade, SEO e conversão** (reauditoria externa).

### ⚡ Performance / Core Web Vitals
- **Galeria**: `width`/`height` + `decoding=async` em todas as imagens (mata CLS), 1ª imagem com `fetchpriority=high` (LCP), demais com `loading=lazy`. Alt descritivo (nome + servidor + Chernarus).
- **Ranking**: avatares "online agora" com `width`/`height` + `loading=lazy` + `decoding=async`.
- (Hero preload com `fetchpriority` e canonical sem querystring já existiam desde a v2.1 — confirmados.)

### ♿ Acessibilidade
- **Lightbox da galeria** agora é `role=dialog aria-modal`, com **alt dinâmico** por imagem, **foco movido pro botão fechar** ao abrir, **restaurado** ao fechar, e **focus-trap** no Tab (Esc/setas já existiam).
- **Wishlist** (♡) na loja: `aria-pressed` + `aria-label` com o nome do pacote; o ícone vira `aria-hidden`.
- (aria-label nos botões Comprar/Abrir e seletor de idioma com `role=listbox`/`aria-selected` já existiam — confirmados.)

### 💸 Conversão / UX
- **Cross-sell**: `/shop` linka pras **Caixas** e `/caixas` linka pra **Loja** ("sem moedas?") — as duas páginas de conversão agora se conversam.
- **Caixas** mostram os **itens possíveis** (lista expansível `details/summary`, com cor de raridade) — responde a objeção de "compra às cegas".

### 🔍 SEO on-page
- **Galeria** e **Ranking** ganharam **texto introdutório** (anti-thin-content) com nome do servidor + palavras-chave do nicho.
- **Sitemap**: `/page/connect` promovido a prioridade **0.7**; `/` e `/shop` ganham `lastmod` real (última atualização de pacote) pra sinalizar re-crawl.
- Novas chaves i18n (PT/EN, paridade 290=290) pros textos acima.

---

## [2.2.3] — 2026-06-14

> Sem migration — só subir os arquivos.

### 🟢 Aviso no painel quando o banco está desatualizado
- Fecha o item de "validação de versão de schema" da auditoria. Agora o **Dashboard do admin mostra um banner** quando há migration pendente (cliente subiu os arquivos novos mas esqueceu de rodar `php cli/migrate.php`), listando o que falta e o comando exato.
- Detecção segura: só acusa quando `schema_migrations` **existe e tem lacuna**. Instalação nova (via `schema.sql`, que já traz tudo) **não dá alarme falso**. Qualquer erro na checagem não trava o painel. Testado nos 3 cenários (install novo = sem aviso; cliente atrasado = avisa e nomeia; em dia = sem aviso).

---

## [2.2.2] — 2026-06-14

> Sem migration — só subir os arquivos. Correções de uma auditoria de segurança/robustez.

### 🔴 Recuperação de senha do admin estava MORTA (corrigido)
- Um guard global de CSRF aplicava `Auth::requireAdmin()` em **todo** POST `/admin/*` (menos `/admin/login`). Como `/admin/forgot` e `/admin/reset` são usados por quem está **deslogado**, o `requireAdmin()` jogava o usuário pro login e os handlers **nunca rodavam** — ou seja, "esqueci a senha" não funcionava (lockout sem saída pelo site; só restava o `cli/reset-password.php`).
- Fix: o guard agora ignora as rotas públicas do admin (`/admin/login`, `/admin/forgot`, `/admin/reset`), que já fazem **CSRF + rate-limit próprios** nos handlers.

### 🟡 Home não cai mais com 500 em banco sem migration
- Cliente que sobe os arquivos novos mas **esquece de rodar `php cli/migrate.php`** podia derrubar o **site inteiro**: o header chama `Servers::isMulti()` em toda página e a home chama `Events::featured()` — sem as tabelas (`servers`/`events`), a query lançava e a home dava 500 pra todo mundo.
- Fix: `Servers::*`, `Events::*` e `Boxes::all()` agora **degradam graciosamente** (retornam vazio/single-server) se a tabela não existir, em vez de derrubar a página. Testado em banco com as tabelas removidas.

---

## [2.2.1] — 2026-06-14

> Sem migration — só subir os arquivos.

### 🐛 Upload de logo/galeria/tema "se resolve sozinho" em qualquer host
- Em hosts que rodam o PHP num **usuário diferente do dono dos arquivos** (enviados por FTP), as pastas de upload nasciam **sem permissão de escrita pro PHP** → o painel dava "precisa de chmod 755" ao subir logo, e o cliente acabava **editando o `index.php` na mão** (que some no próximo update).
- Novo helper `ensure_writable_dir()`: cria a pasta recursivo e **escala a permissão sozinho (0775 → 0777)** até conseguir gravar. Aplicado em **todos** os pontos de escrita do painel: marca (`assets/img/custom`), galeria (`assets/img/gallery`), imagem de pacote (`assets/img/packages`), itens/caixas (`upload_image`) e tema (`assets/css/theme.override.css`). Cada upload ainda faz **retry** após forçar a permissão.
- Mensagens de erro do painel agora explicam 755 **ou** 775 conforme o host. Resultado: o cliente **não precisa mais mexer em FTP nem editar código** pra trocar a marca.

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
- **Newsletter REMOVIDA por completo:** o form de captura de e-mail do rodapé aparecia por padrão e não tinha utilidade clara. Removido tudo — bloco do rodapé (HTML/JS/CSS), rota `POST /api/newsletter-subscribe`, tabela `newsletter_emails` do `schema.sql`, chaves de tradução (`newsletter.*` em PT/EN) e chaves de `Settings`. (Sites existentes mantêm a tabela `newsletter_emails` parada, sem uso — não removo dado deles; quem quiser dropar faz manualmente.)
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
