# Changelog — DayZ Website Template (Tecplay)

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento [SemVer](https://semver.org/lang/pt-BR/).

---

## [2.13.0] — 2026-06-26

> **Tem migration** (`v2.13.0_clans.sql` — tabelas `clans`, `clan_members`, `clan_requests`). Rode `php cli/migrate.php`.

### 🛡 Clãs (Fase 1)
- **Nova aba `/clans`:** jogadores **registram o clã** (nome, TAG, logo, Discord, descrição), a lista é pública e cada clã tem sua página com membros.
- **Entrada só com aceite (LGPD/consentimento):** o jogador **pede pra entrar** (o dono aceita) **ou** o dono **convida por SteamID** (o jogador aceita no perfil dele). Ninguém é adicionado à força. **1 jogador = 1 clã.** Membro pode **sair** quando quiser.
- **Dono gerencia:** aceita/recusa pedidos, convida, remove membro, edita (descrição/Discord/logo), dissolve. Teto de membros (padrão 20).
- **No perfil** (`/player`) aparece o clã do jogador + os convites pendentes pra aceitar.
- **Admin → 🛡 Clãs:** modera (remove clã com conteúdo impróprio — dissolve e libera os membros). Regras de conteúdo no formulário (sem pornô/ódio/política/marca de terceiro) — proteção via Marco Civil (takedown).
- **Roadmap (Fase 2):** eventos de clã premiados (moeda dividida/por membro, ou item por membro).

## [2.12.3] — 2026-06-26

> Sem migration. Consistência interna (pós-auditoria).

- **🧹 Settings consistente:** `achievement_rewards` + `achievement_rewards_enabled` entraram no `Settings::SCHEMA` (estavam fora; o /admin/achievements salvava por INSERT direto driblando o whitelist). Agora salva via `Settings::set` (atualiza cache + normaliza), igual aos outros configs JSON. Sem mudança de comportamento pro usuário.
- Auditoria read-only do PHP confirmou o resto do codebase limpo (sem rota/view/função morta nova; CSRF e auth ok).

## [2.12.2] — 2026-06-26

> Sem migration. Limpeza de código morto + docs.

- **🧹 Código morto removido:** a query de `reviewed_ids` rodava em **todo** load de perfil sem ser usada (sobra de quando o perfil tinha "Avaliar"); e a rota `POST /reviews/submit` ficou sem caller (o modal de avaliação saiu na v2.12.0). Ambos removidos. Avaliação pública segue em `/depoimentos` (`/reviews/public-submit`).
- **📚 Docs:** README atualizado (15 conquistas incl. as de gameplay, aba `/vip`, editor da Seção da Home).

## [2.12.1] — 2026-06-26

> Sem migration.

- Conquista **🎯 Franco-Atirador**: distância da kill ajustada de 150m → **500m** (no PvP a galera acerta tiros longos; 500m é digno do título).

## [2.12.0] — 2026-06-25

> Sem migration.

### 🏅 +3 conquistas de gameplay
- Novas conquistas baseadas no que o jogador faz **no jogo** (via `player_stats`/CFTools), não só em compras: **🎯 Franco-Atirador** (kill a 150m+), **☣ Exterminador** (500 zumbis), **⏳ Veterano de Chernarus** (100h online). Total agora: 15 conquistas. Degradam limpo se o servidor não tiver CFTools.

### 🎁 Cards de caixa alinhados
- O footer (custo + botão **Abrir**) agora ancora no **fundo** de todos os cards — a caixa diária (com poucos itens) não fica mais "pra cima" que as outras. Layout uniforme.

### 👤 Perfil do jogador mais enxuto (dropdowns)
- Os históricos (compras, caixas, loja in-game, premiações do ranking, bônus de conquista) viraram **dropdowns fechados por padrão** — a página não estica mais. Cada um mostra até **25** registros.
- **Caixas com item a receber abrem sozinhas** e ganham destaque (borda + "⏳ X a receber") — só fica aberto o que precisa de ação.
- **Sem forçar avaliação:** removida a coluna "Avaliar" e o pop-up no histórico de compras. Quem quiser avaliar usa a página **/depoimentos** (avaliar é espontâneo, não obrigação por ter comprado).

## [2.11.1] — 2026-06-25

> Sem migration.

- Seção da Home: limite de **8 → 12 cards** (servidores com muitas mecânicas cabem todas). Editor mostra 12 linhas.

## [2.11.0] — 2026-06-25

> Sem migration (config em `settings`). 

### 🏠 Seção "O Que Você Vai Encontrar" agora é editável no painel
- A vitrine de cards da home (abaixo dos depoimentos) saiu do código fixo e virou **editável em Admin → 🏠 Seção da Home**: título, subtítulo, liga/desliga e **até 8 cards** (emoji + título + texto). Card sem título é ignorado (menos cards = deixar vazio).
- **Instalação nova não muda:** sem nada salvo, a seção cai nos **4 cards genéricos** do idioma (igual antes). Cada servidor escreve os próprios diferenciais sem mexer em código — e o template não carrega conteúdo de cliente nenhum.

## [2.10.4] — 2026-06-25

> Sem migration. Integração opcional com Financeiro/matriz central (config-driven).

### 🔁 Encaminhar vendas pra um Financeiro central (opcional)
- O Mercado Pago só notifica **uma** URL (a da loja). Quem roda um **painel financeiro central** que também precisa saber das vendas agora pode configurar o webhook pra **encaminhar** a notificação pra lá **depois de entregar a moeda** — **fire-and-forget** (timeout curto, erro ignorado: se o Financeiro cair, a loja **não** para de entregar).
- **Config-driven** (`config.php` → `matriz.forward_url` + `matriz.server_slug`); **vazio = não encaminha pra ninguém** (padrão do template — nada vaza pra terceiros).
- A criação do pagamento agora manda **descrição clara** (`"{site} — {pacote} ({N} moedas)"`) e **`metadata.server_slug` + `metadata.kind="loja"`** — resolve vendas que chegavam sem descrição e atribui ao servidor certo no painel central.

## [2.10.3] — 2026-06-23

> Sem migration. Fix de código no webhook + (no DanoninhoZ) reconciliação de dados.

### 👤 Comprador sem nome virava "Anônimo" no ranking
- A loja pega o SteamID por **input** (sem login Steam), então quem compra só digitando o ID nascia **sem nome** e aparecia como **"Anônimo"** no ranking de investimento. Agora, ao aprovar o pagamento, o site **busca o nick na Steam** (Web API se houver key, senão XML público) e grava no jogador. (No Dano, fiz o backfill dos nomes que faltavam.)

### 🏆 Ranking de investimento mostrava valor errado
- O ranking lia um **cache** (`players.total_spent_brl`) que podia ficar defasado (ex: após reset de teste), mostrando menos do que o jogador gastou de verdade. Reconciliado pela soma real das compras aprovadas. (As conquistas já usavam a soma real, então estavam certas.)

### 💬 Conquista "Generoso" não liberava
- Conquista de avaliação só conta review **com SteamID vinculado**. Uma review antiga sem SteamID não creditava o jogador — corrigido o vínculo no caso reportado.

## [2.10.2] — 2026-06-23

> Sem migration.

### 🔔 Aviso do "Receber" virou toast flutuante
- O aviso ao resgatar caixa (ex: "precisa estar no jogo") agora é um **toast flutuante** no canto superior direito (acima da navbar, `z-index` alto), com slide-in e **some sozinho** depois de alguns segundos (erro/aviso fica ~9s; sucesso ~6s) — além do botão de fechar. Antes ele entrava no fluxo da página e ficava escondido atrás do header fixo.

## [2.10.1] — 2026-06-23

> **Tem migration** (`v2.10.1_reviews_avatar.sql` — `reviews.avatar`). Rode `php cli/migrate.php`.

### 🏆 FIX importante — premiação do ranking creditava ZERO
- O leaderboard do CFTools devolve `cftools_id` + `latest_name`, mas **nunca o `steam_id`** — e a premiação lia `row['steam_id']`, então **pulava todo mundo e não creditava ninguém** (o admin via "0 creditadas" e o jogador não tinha histórico). Agora resolve o `steam_id` mapeando o `cftools_id` pelo `player_stats`. O vencedor que ainda não vinculou a conta (nunca abriu o perfil logado) é **reportado** ("X não recebeu — precisa abrir o site 1x") em vez de sumir em silêncio.

### 🪙 Tela "Venda de VIP" (admin) redesenhada
- Layout limpo e alinhado (estava com campos desalinhados): um cartão por plano com toggle, nome, descrição e os 3 preços (30/60/90) em grade.

### 👤 Perfil — aviso do "Receber" legível
- O aviso ao resgatar caixa offline ("precisa estar no jogo") agora é um **banner de alto contraste com botão de fechar** no topo (antes o vermelho era ilegível e ficava fixo na tela).

### ⭐ Depoimentos — foto da Steam + alinhamento
- Os depoimentos (home e `/depoimentos`) agora mostram a **foto do perfil Steam** do autor (capturada no envio; quem não tem cai na inicial do nome).
- Cartões com **texto longo não quebram mais** o layout: rodapé/avatar fixos embaixo, nome e corpo com quebra correta.

## [2.10.0] — 2026-06-23

> Sem migration (config em `settings`, compra usa `player_grants` que já existe). Nada a rodar ao atualizar.

### 🪙 Loja de VIP / BattlePass paga com MOEDAS (aba `/vip`)
- Nova **aba "VIP"** no site: o jogador compra **VIP (PanelVip1–4)** ou **BattlePass** por **30/60/90 dias** gastando as **moedas** que já tem — sem dinheiro real. A aba só aparece quando o admin liga a venda.
- **Checkout em moedas, atômico:** debita o saldo sem nunca deixar negativo (mesma trava do gasto in-game), grava o benefício e o **agent aplica no Sparda** no próximo ciclo — exatamente o fluxo de entitlements da concessão manual.
- **Renovação soma dias:** comprar de novo antes de expirar **empilha** os dias no que ainda falta (o jogador nunca perde o que pagou). A aba mostra o que está ativo e até quando.
- **Admin → 🪙 Venda de VIP:** liga/desliga geral + por plano, nome/descrição exibidos e a **tabela de preço (tier × 30/60/90 dias)** em moedas. Preço 0/vazio = aquela opção não é vendida.
- Por que aba própria (e não dentro da Loja): a Loja é **entrada** de dinheiro (compra moeda com PIX/cartão); VIP é **gasto** de moeda — famílias diferentes. Separar deixa o ciclo "compro moeda → gasto no VIP" claro e não mistura duas formas de pagamento na mesma vitrine.

## [2.9.1] — 2026-06-23

> **Tem migration** (`v2.9.1_coupon_per_user_limit.sql` — adiciona `coupons.per_user_limit`). Rode `php cli/migrate.php` ao atualizar.

### 🎟 Cupons: editar + limite por jogador
- **Editar cupom** (✎): antes só dava pra criar, ativar/desativar ou apagar. Agora a tela **Editar** ajusta desconto, limites, pacotes, janela de validade e dados de afiliado. O **código não muda** (compras e vínculos apontam pra ele).
- **Limite por jogador** (`per_user_limit`): além do limite **TOTAL** (`max_uses`, somando todo mundo), dá pra dizer **quantas vezes cada jogador** pode usar. Ex: `1` = cupom de aniversário, um por pessoa. Conta as compras pagas do jogador com aquele código. Vazio = sem limite por jogador.
  - Esclarecida a semântica na tela: **Máx. TOTAL** (todos somados) × **Máx. por jogador**.

### 🗑 Excluir pacote de moeda
- Novo botão **Excluir** em Admin → Pacotes (faltava — só dava pra desativar). **Proteção:** pacote que já tem compras **não é apagado** (preserva o histórico/recibo do jogador) — a tela avisa e sugere desativar.

### 👤 Perfil do jogador: transparência das recompensas
- **Bônus de conquista** agora aparece no perfil do dono ("🏅 Bônus de conquistas") — antes só o admin via o log; o jogador recebia as moedas sem ver o registro.
- **Resgate de caixa ("Receber"):** a mensagem (ex: "precisa estar no jogo") agora aparece num **banner no topo** do perfil, visível na hora — antes ficava enterrada lá embaixo na seção de caixas e parecia que "nada acontecia".
- **Premiação do ranking:** a mensagem do admin ao premiar ficou clara — distingue "creditou X colocações" de "nada novo (período já premiado / sem vencedor elegível)", já que a premiação **não paga 2x no mesmo período**.

## [2.9.0] — 2026-06-22

> Sem migration (config em settings).

### 👁 Esconder abas do ranking (visibilidade por categoria)
- Novo em **Admin → Recompensas → "Abas visíveis no /ranking"**: desmarque pra **esconder a aba inteira** de uma categoria do ranking público — **independente de premiação**. Ex: servidor sem sistema de zumbi esconde "Zumbis"; quem não quer expor "quem mais gastou" esconde "Investimento"; ou esconde "Tempo online".
- Cobre **Investimento + as 5 de gameplay** (Kills, Zumbis, K/D, Tempo online, Kill mais longa). **Default: todas visíveis** (instalações existentes não mudam). Aba oculta **nunca renderiza** — nem por link direto (`?stat=` cai numa aba visível).

## [2.8.7] — 2026-06-22

> Sem migration nova. Revisão do TEXTO-SEMENTE das páginas legais (afeta instalações NOVAS do template).

### 🧹 Seed das páginas legais revisado (gramática + vestígios)
Corrigido em `schema.sql` + `migrations/v2.2.0_seed_legal_pages.sql` (o que um cliente NOVO recebe ao instalar):
- **`discord.gg/SEU-CONVITE-CONVITE` → `SEU-CONVITE`** (placeholder tinha "CONVITE" duplicado — 11 ocorrências).
- FAQ: "Atendemos **do o** mais rápido" → "o mais rápido". Privacidade: "365 dias **rolling**" → "corridos".
- **Removido vestígio do DanoninhoZ no template:** a página "Como Conectar" trazia o BattleMetrics real (`/dayz/38863389`) e a porta `2402` (do Dano) hardcoded → agora `SEU-ID-BATTLEMETRICS` e porta padrão `2302`.

## [2.8.6] — 2026-06-22

> Sem migration. Limpeza de código (sem mudança de comportamento).

### 🧹 Código morto removido (pente-fino)
- Auditoria read-only de todo o codebase (119 arquivos): **nenhuma** view/classe órfã, **nenhum** bloco de código comentado/inalcançável, **zero** debug leftover. Codebase já estava limpo.
- Removidas **6 funções/métodos sem nenhum call site** (confirmado por grep reverso): `view()` (helpers — wrapper não usado de `View::display`), `Csrf::require()`, `Boxes::findById()`, `Events::hasAny()`, `Lang::available()`, `Settings::isAllowed()`. Smoke test (home/shop/ranking/eventos/caixas) 200 após remoção.

## [2.8.5] — 2026-06-22

> Sem migration nova. **Correção importante de instalação do zero** + ajustes.

### 🐛 Install do zero estava incompleto (cliente novo ficava sem tabelas)
- O `install.php` importava o `schema.sql` e **só marcava** as migrations como aplicadas — mas o `schema.sql` estava **atrás** das migrations novas (`player_grants`, `achievement_rewards_log`, `login_log` não estavam nele). Resultado: uma instalação do zero nascia **sem** essas tabelas → VIP/Passe, Bônus por conquista e Log de login quebravam (500) no cliente novo.
- **Fix duplo:** (1) `schema.sql` sincronizado com as 3 tabelas; (2) o `install.php` agora **RODA as migrations** em cima do schema (idempotentes, ignora "já existe") em vez de só marcá-las — assim qualquer defasagem futura do schema **nunca** deixa o cliente sem tabela. **Validado** num install do zero no staging (30 tabelas, 17 migrations, tudo OK).

### ✨ Ajustes (2.8.1–2.8.4)
- **Filtro de tabela respeita `data-nofilter`** — não duplica onde já há busca server-side (ex: Jogadores só ordena). **Ordenar** continua em todas.
- **Telas de Log** (Logins, Logs de caixa) agora **ordenam** por coluna (via `data-enhance`, sem mudar o visual).
- **🐛 Editar item de caixa** voltou a funcionar (o handler do ✎ não prendia porque o script rodava antes dos botões; agora usa *event delegation*, robusto e compatível com o PJAX).
- **🏆 Premiação do ranking aparece no perfil** do jogador (seção "Premiações do ranking" — data, categoria, lugar, moedas). Antes caía no saldo em silêncio.

## [2.8.0] — 2026-06-22

> Sem migration — só subir os arquivos.

### 📊 Tabelas do admin: ordenar (clique na coluna) + filtrar
- Componente **reutilizável** no painel: **clique no cabeçalho** de qualquer tabela pra ordenar (asc/desc, detecta número/texto/data com `localeCompare` pt-BR), e **campo de filtro** multi-termo (espaço = E) que aparece em listas com **6+ linhas**. Auto-aplica em **todas** as `.admin-table` (Jogadores, Compras, Pacotes, Cupons, Combos…) — uma vez só, sem mexer em cada tela. Progressive enhancement (se o JS falhar, a tabela funciona normal) e compatível com a navegação PJAX (re-aplica via MutationObserver).

### ✏️ Editar item de caixa (sem deletar e refazer)
- O pool de itens da caixa ganhou **botão ✎ Editar** por item: preenche o form com os dados atuais (tipo, classname, nome, qtd, raridade, ativo) e salva por cima — a **imagem é mantida** se você não trocar. Antes só dava pra remover e recadastrar. (O backend já suportava `UPDATE`; faltava a interface.)

## [2.7.2] — 2026-06-22

> Sem migration — só subir os arquivos.

### ➕ Criar pacote de moeda no admin (faltava!)
- O admin de **Pacotes** agora tem **"➕ Novo pacote"** — antes só dava pra editar/ligar/desligar os seedados. Form em `/admin/packages/new` (define o **ID/slug** único, nome, moedas, bônus, preço, imagem, perks) → `POST /admin/packages/create` (valida slug `[a-z0-9_-]`, **rejeita ID duplicado**, INSERT). O pacote novo já entra automaticamente na loja/checkout (mesmas rotas validadas, preço server-side — sem exposição). O `package_edit.php` virou null-safe (serve criar **e** editar).

## [2.7.1] — 2026-06-21

> Sem migration — só subir `public/api/mp-webhook.php`.

### 🔒 Anti-underpay no webhook do Mercado Pago (defesa em profundidade)
- O webhook agora confere que o **valor REALMENTE pago** (consultado na API do MP, não no payload da notificação) **cobre o preço da compra** antes de creditar. Se vier menor (tentativa de "pagar R$1 numa coisa cara"), marca a compra como `underpaid` e **não credita** — registra no log do servidor pro admin revisar. É redundância forte: o `unit_price` da preference já é server-side (vem do banco, nunca do cliente), mas isto fecha qualquer brecha de manipulação de valor.
- Recapitulando as proteções de pagamento já existentes (confirmadas em auditoria): **replay/duplicação** barrado por claim atômico (`delivered_at IS NULL` + rowCount); **notificação forjada** barrada por **assinatura HMAC obrigatória em produção** + **re-consulta do pagamento na API do MP**; **preço** sempre server-side (lookup do pacote no banco).

## [2.7.0] — 2026-06-20

> **TEM migration** (`v2.7.0_achievement_rewards_loginlog.sql`) — suba os arquivos e rode `php cli/migrate.php`.

### 👤 Perfil unificado (fim da duplicidade `my-purchases` × `/player`)
- O **`/player/{steamid}` virou o perfil ÚNICO**. Visitante vê só o público (stats de combate + conquistas); o **dono logado** vê também o bloco privado **no próprio perfil** (saldo, compras + avaliar, histórico de caixas, loja in-game, "Apoie seu Streamer"). O **`/my-purchases` agora redireciona** pro perfil (links/bookmarks/forms seguem funcionando, com os flashes preservados na query).
- Financeiro **nunca** aparece pra visitante — quando não é o dono, os campos de saldo/investido **nem saem do banco** (LGPD). As **conquistas passaram a ser públicas** no perfil.

### 🏅 Recompensa por conquista (configurável) — `/admin/achievements`
- O admin define **+X moedas por conquista** (0 = nenhuma) + um liga/desliga global. Quando o jogador desbloqueia, ganha o bônus **por conta da casa** — pago **1x por conquista por jogador** (idempotente via claim atômico em `achievement_rewards_log`), creditado automaticamente quando ele vê o próprio perfil/loga.
- **Não aparece na loja**: só credita e fica registrado no log do painel (+ auditoria em `balance_log`). Teto de 100k por recompensa (anti-abuso).

### 🔑 Log de login no site — `/admin/logins`
- Registro de **quem entrou via Steam** (SteamID, nick, IP, navegador) pra auditoria/privacidade, com busca por SteamID. **Sem trancar o ranking** (que segue público pro SEO/vitrine) — o log é desacoplado.

## [2.6.1] — 2026-06-19

> Sem migration — só subir os arquivos. Hardening de segurança (pós-pentest defensivo).

### 🔒 Segurança
- **`install.php` em produção agora retorna 404 puro** quando já instalado (antes vazava `<h1>Ja instalado</h1>` + instrução pra forçar reinstalação — reconhecimento gratuito pra atacante). Recomendado também **remover o `install.php`** do servidor após instalar.
- **CSP em modo `Content-Security-Policy-Report-Only`** no `.htaccess` (FASE 1: não bloqueia nada, só reporta violações). Allowlist pra Mercado Pago + Steam + Google Fonts + jsDelivr. Quando os reports confirmarem que nada legítimo viola, trocar pra enforce (`Content-Security-Policy`). **Não enforce sem testar o checkout no navegador.**
- Novo endpoint **`/api/csp-report.php`** coleta as violações de CSP em `storage/cache/csp-reports.log` (204 sempre, sem corpo, cap de tamanho, sem PII).
- (Confirmados na auditoria, já existentes: rate-limit no login admin 5/user+20/IP→15min, webhook MP exige HMAC + re-consulta o pagamento em produção, perfil público `/player` não vaza saldo/PII, nicks escapados com `e()`, cookies Secure+HttpOnly+SameSite, queries parametrizadas.)

## [2.6.0] — 2026-06-18

> **TEM migration** (`v2.6.0_entitlements.sql`) — suba os arquivos e rode `php cli/migrate.php`.

### 🎟️ Entitlements (VIP / BattlePass) gerenciados pelo SITE
- Novo admin **`/admin/entitlements`**: concede/revoga **VIP** (tiers PanelVip1..4 / CUSTOM) e **BattlePass** por SteamID + dias, escopado por servidor.
- Endpoint **`/api/entitlements.php`** (auth `X-Agent-Token` / `?token=`, mesmo padrão do `sync-players`): o `tecplay-agent` puxa os pendentes/revogados e escreve os JSON do mod Sparda (`VipPanel/PlayersVIP.json` + `BattlePass/premiums/<steamid>.json`), depois confirma (ACK). Fluxo: pending→applied / revoked→removed.
- Tabela `player_grants` (migration `v2.6.0_entitlements.sql`).
- O **site é a fonte da verdade** (quem manda no servidor de jogo). O agent (v2.3.0+) aplica com verify/retry anti-clobber (os mods Sparda escrevem os mesmos arquivos).

## [2.5.3] — 2026-06-17

> Sem migration — só subir os arquivos. Robustez (cache) + privacidade do ranking.

### 🐛 Trocar as credenciais do CFTools não "pegava" sem limpar cache na mão
- O `CFTools` cacheia o token de auth (23h) e lookups (até 7 dias) em `storage/cache`. Ao **trocar o app CFTools** (App ID / Secret / Server API ID), o token **antigo** continuava valendo contra o app novo → a integração (ranking de gameplay + entrega das caixas) parecia "morta" até apagar `storage/cache` na mão.
- Fix: salvar as Configurações agora **invalida o cache do CFTools automaticamente** (`CFTools::clearCache()`). Também adicionei um botão **🧹 Limpar cache** em Configurações como reforço (limpa CFTools + status do servidor + perfis Steam). Reportado por um usuário do template.

### 🔒 Opção de ocultar os nomes dos players online no ranking
- Novo toggle em **Configurações → Privacidade do ranking** (`hide_online_players`). Quando ligado, o `/ranking` mostra só a **contagem** ("X online agora") e **oculta a lista de nomes** — evita expor quem está jogando.

## [2.5.2] — 2026-06-16

> Sem migration — só subir os arquivos. Correção de instalação nova.

### 🐛 Instalação nova mostrava "migrations pendentes" falso
- O `install.php` importa o `schema.sql` (que já tem o efeito de TODAS as migrations), mas **não marcava** o `schema_migrations` → o painel do admin de um site **recém-instalado** mostrava o banner "⚠ Banco de dados desatualizado — X migrations pendentes" mesmo estando 100% em dia (o `pending_migrations()` compara a pasta `migrations/` com a tabela `schema_migrations`, que nascia vazia).
- Fix: o `install.php` agora **carimba todas as migrations como aplicadas** logo após importar o schema. Instalação do zero nasce com 0 pendências.
- (Sites já no ar que mostravam o aviso por terem migrations antigas não-rastreadas: rodar `php cli/migrate.php` uma vez resolve — ele aplica/pula benigno e marca tudo.)

---

## [2.5.1] — 2026-06-16

> Sem migration — só subir os arquivos. Visual do preview de chances das caixas.

### 🎨 Preview de odds das caixas — design moderno
- O "Ver itens possíveis" agora abre **por padrão** (chances **à vista**, sem precisar clicar) com scroll fininho moderno (cabe até caixa com 40+ itens sem esticar o card).
- Cada item: **ícone** + **nome completo** (não corta mais — "Caixa de Munição 7.62" aparece inteiro, com tooltip), **chip de raridade colorido**, **% de chance** e uma **barrinha visual** proporcional (cor da raridade, com glow). **Prêmios raros no topo** (lendário primeiro).
- 100% CSS/markup (sem migration). Cores por raridade via `--rc`.

---

## [2.5.0] — 2026-06-16

> **TEM migration** — suba os arquivos e rode `php cli/migrate.php` (`v2.5.0_box_weight_from_rarity.sql`). Normaliza os pesos das caixas pela raridade.

### 🎁 Caixas: a raridade define a chance (campo "peso" removido)
- O campo **"Peso"** saiu do editor de caixa (form + tabela do pool). Agora a **raridade define a chance sozinha** (comum cai muito, lendário cai pouco) — o peso é derivado server-side (`Boxes::RARITY_WEIGHT`: comum 100 / incomum 40 / raro 15 / épico 5 / lendário 2). Menos confusão pro admin, que só escolhe a raridade.
- Migration normaliza o peso de todos os itens existentes pela raridade (idempotente).

### 🔍 Preview de chances na `/caixas` (transparência — anti "ninguém me avisou")
- O **"Ver itens possíveis"** agora mostra, por item: **ícone** (se houver, estilo Free Fire/PUBG), nome + qtd, **raridade colorida** e a **chance % real** — ordenado do mais provável pro mais raro, com nota "chances reais de cada item". Transparência de loot box pra evitar disputa. Inclui as recompensas em moedas. i18n PT/EN.

---

## [2.4.3] — 2026-06-15

> Sem migration — só subir os arquivos. Fechamento da reauditoria Hostinger (itens de código que ainda faltavam).

### 💸 Conversão / UX
- **`/eventos` com CTA**: cada evento ativo/futuro agora mostra "como participar" + botões **Comprar moedas** e **Discord** (antes não tinha chamada pra ação nenhuma).
- **`/depoimentos` com incentivo de review**: aviso abaixo do login Steam — "reviews aprovados aparecem aqui e na loja em até 24h; só jogadores verificados avaliam" — pra derrubar a barreira de "será que vale escrever?".
- Chaves i18n PT/EN pros dois (paridade 295/295).

> Resto da reauditoria (galeria lazy/dims/lightbox a11y, caixas cross-sell/itens/aria-label, shop ♡/buy aria-label, canonical limpando `?stat`, sitemap connect 0.7, etc.) **já estava implementado desde a v2.3.0** — o scraping pegou um estado antigo. Itens de ops (DNS, Search Console, banner OG, e-mail DPO) seguem com o dono do servidor.

---

## [2.4.2] — 2026-06-15

> Sem migration — só subir os arquivos. Resgate manual de itens das caixas.

### 📥 Resgate manual das caixas ("Receber" no painel)
- Novo modo de entrega (Configurações → **Entrega das caixas**): **resgate manual**. Em vez de o item cair sozinho assim que o jogador fica online, ele fica **pendente** e o jogador clica **"Receber"** no painel (`/my-purchases`) quando estiver num lugar/momento **seguro** — evita o item cair em hora ruim (inimigo perto, recém-logado).
- Botão **"Receber"** nas caixas pendentes do painel → `POST /claim-box/{id}`, **server-authoritative** (só resgata abertura que é do próprio jogador e está pendente; valida online + janela de restart + rate-limit). Anti-burla: o cliente não força item que não é dele.
- No modo manual, o poller automático (`Boxes::deliverPending`) **não entrega sozinho** — espera o resgate. Modo automático (padrão) inalterado, e mesmo nele o botão "Receber" permite forçar.
- Base pro resgate in-game (menu tecla I, escolher item/todos) que o mod vai implementar — contrato e plano em `_handoff-claudes/PLANO_resgate_itens_claim.md`.

---

## [2.4.1] — 2026-06-15

> Sem migration — só subir os arquivos. Picuinhas de UX.

### ✨ Acabamento de UX
- **Asterisco de campo obrigatório**: os labels dos campos obrigatórios marcam `*` (avaliação no `/depoimentos`, SteamID no `/shop`) pra deixar claro o que é exigido antes de enviar.
- **Tabelas responsivas no tablet**: as tabelas de dados públicas (ranking, "Minhas compras" + histórico de caixas/loja in-game) agora **rolam na horizontal** em telas estreitas, em vez de estourar/esmagar o layout.

---

## [2.4.0] — 2026-06-15

> **TEM migration** — depois de subir os arquivos, rode `php cli/migrate.php` (aplica `v2.4.0_streamer_affiliate.sql`). Só adiciona campos; dados intactos.

### 🎮 Programa de afiliado / streamer ("Apoie seu Streamer")
- Qualquer cupom pode virar de um streamer: na tela **Cupons**, defina o **nome do streamer** e o **% de cachê escalonado** pela recorrência do cliente — **1ª compra**, **2ª compra**, **3ª+ compra** (ex: 5% / 10% / 0%, o modelo "cliente já consolidou").
- O cliente se **atrela a um streamer** digitando o código (no checkout ou no painel **"Apoie seu Streamer"**) — **1 streamer por vez**, travado pra sempre; toggle no painel pra **permitir troca** (last-wins).
- O **cachê é calculado sobre o valor cheio** (antes do desconto), só em **compra paga**, e a atribuição é pelo **vínculo do perfil** (não pelo reuso do cupom). O benefício pro cliente vale **1x** (1ª compra paga) — não repete, então não sangra a margem na recorrência.
- Cada compra **carimba** o streamer atribuído no momento → histórico estável mesmo se o cliente trocar de streamer depois.
- Novo tipo de benefício de cupom: **🪙 moedas bônus** (além de % e R$ de desconto).
- Nova tela admin **Streamers** (`/admin/streamers`): por streamer, mostra faturamento gerado, **cachê a pagar** (total + por mês) e as vendas individuais. O site só mostra o valor — o pagamento ao streamer é por fora. Liga/desliga geral + "permitir troca" em **Configurações**.

### 🗄️ Migration
- `v2.4.0_streamer_affiliate.sql`: `coupons` ganha `affiliate_name` + `commission_pct_1/2/3plus` e o tipo `coins` no `discount_type`; `players` ganha `affiliate_coupon_code` + `affiliate_bound_at`; `purchases` ganha `affiliate_coupon_code` (carimbo); seeds `affiliate_enabled` + `affiliate_allow_switch`. Idempotente via `migrate.php`.

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
