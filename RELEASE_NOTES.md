# 🏴 DayZ Website Template — Notas da versão (v2.9.0)

> **Atualizar é seguro e não apaga nada.** Suba os arquivos e rode `php cli/migrate.php` (idempotente). O log completo fica no **[CHANGELOG.md](CHANGELOG.md)**; aqui vão os destaques.

---

## 🆕 v2.7 – v2.8 — Perfil unificado, recompensas, UX do admin, segurança e install robusto

- **👤 Perfil unificado** (`/player`): visitante vê combate + conquistas; o **dono logado** vê também saldo, compras, caixas e loja no mesmo lugar (fim da página `/my-purchases` separada). Financeiro nunca aparece pra visitante (LGPD).
- **🏅 Recompensa por conquista** (admin dá moedas por conquista, 1x por jogador) + **🏆 premiação do ranking aparece no perfil** do jogador (antes caía no saldo em silêncio).
- **🛠 Admin mais produtivo:** tabelas **ordenáveis + filtráveis**, **criar pacote de moeda**, **editar item de caixa**, **log de logins**, **premiação liga/desliga por categoria** e **abas do ranking configuráveis** (esconde categoria que não faz sentido pro servidor).
- **🔒 Segurança (pós-pentest):** anti-underpay no checkout (valor pago ≥ preço), CSP em Report-Only + coletor, `install.php` retorna 404 quando já instalado, validação Steam OpenID e upload (MIME real) confirmadas.
- **🛟 Install/Update à prova de defasagem:** a instalação do zero **roda as migrations** sobre o `schema.sql` → nasce completa (nenhuma tabela faltando pro cliente). Update = subir arquivos + `php cli/migrate.php`.

> ⚠️ Ao atualizar de uma versão < 2.7.0: **rode `php cli/migrate.php`** (cria `player_grants`, `achievement_rewards_log`, `login_log`).

---

## 🆕 v2.6.0 — VIP & BattlePass pelo painel (entitlements)

- **Conceda VIP/Passe no admin** (`/admin/entitlements`): escolhe o SteamID, o tier (PanelVip1..4) e os dias — o site vira a fonte da verdade.
- O **tecplay-agent** puxa do site (`/api/entitlements.php`) e escreve no servidor (mod Sparda), com verify/retry. Conceder → **Ativo** no jogo; revogar → **removido**. Sem comando, tudo no painel.

---

## 🆕 v2.5.3 — Cache do CFTools auto-limpa + privacidade do ranking

- **🐛 Trocar o app CFTools agora "pega" na hora.** Antes, o token antigo ficava em cache (23h) e a integração parecia morta até limpar `storage/cache` na mão. Agora salvar as Configurações **limpa o cache do CFTools automaticamente** — e tem um botão **🧹 Limpar cache** de reforço.
- **🔒 Ocultar nomes dos players online.** Novo toggle em *Configurações → Privacidade do ranking*: mostra só a contagem "X online" e esconde a lista de nomes.

---

## 🆕 v2.5.0 — Caixas: raridade define a chance + preview de odds

- **Campo "peso" removido** do editor de caixa: a **raridade** define a chance sozinha (comum cai muito, lendário pouco). Menos confusão; o admin só escolhe a raridade.
- **Preview de chances na `/caixas`**: o "Ver itens possíveis" mostra ícone + nome + raridade colorida + a **chance % real** de cada item (transparência de loot box, anti "ninguém me avisou", estilo Free Fire/PUBG).
- **Tem migration** (`v2.5.0_box_weight_from_rarity.sql`) que normaliza os pesos pela raridade.

---

## 🆕 v2.4.0 — Programa de afiliado / streamer

- **🎮 "Apoie seu Streamer":** transforme cupons em códigos de streamer que pagam **cachê por venda**. O cliente se atrela a um streamer (1 por vez), e o streamer ganha um **% escalonado** pela recorrência do cliente (1ª/2ª/3ª+ compra), sobre o **valor cheio**, só em compra paga.
- **Sem sangrar margem:** o desconto pro cliente vale 1x; a atribuição segue pelo vínculo do perfil, não pelo reuso do cupom.
- **Tela admin "Streamers":** faturamento gerado e **cachê a pagar** por streamer (total + por mês) + as vendas individuais.
- **Novo benefício de cupom:** 🪙 **moedas bônus** (além de % e R$).
- **Liga/desliga** + "permitir troca de streamer" em Configurações. **Tem migration** (`v2.4.0_streamer_affiliate.sql`).

---

## 🆕 Destaques da linha v2.3.x (até a v2.3.9)

- **🎮 Integração CFTools pelo painel** — agora dá pra ativar o **ranking de gameplay** (kills, zumbis, K/D, tempo) **e a entrega das caixas no jogo** direto em **Admin → Configurações → 🎮 Integração CFTools** (ou no `install.php`), sem editar `config.php`. Aviso no `/admin/caixas` quando não está configurado.
- **🛒 Aceite dos Termos no checkout** — saiu o checkbox obrigatório da vitrine `/shop` (fricção); o aceite virou um aviso por ação na tela de pagamento.
- **🎮 Histórico da loja in-game (`/loja`)** no painel do jogador (Minhas compras).
- **🌱 Dados de exemplo no `install.php`** — checkbox pra o site não nascer vazio (removível com `php cli/seed-demo.php --clean`).
- **🔴 Removido o "R$ X / moeda"** dos cards da loja (era enganoso — gerava disputa de valor).
- **🛡 Robustez de host** — fix do `finfo` em hospedagem sem `fileinfo`, logo responsivo (logo-com-nome), páginas legais de exemplo, e degradação limpa em instalações antigas.

> Detalhe completo (todas as 2.3.0 → 2.3.9): **[CHANGELOG.md](CHANGELOG.md)**.

---

## 🆕 O que chegou na v2.2.0

### 🐛 Páginas legais de exemplo (correção importante)
Sites instalados antes nasciam com as **páginas legais em branco** — Termos, Privacidade (LGPD), Reembolso, Regras, FAQ e Como Conectar. Quem abria `/page/terms` via página vazia.

- **Sites novos** já nascem com as 6 páginas preenchidas (exemplo pronto em PT e EN).
- **Sites já em produção:** rode `php cli/migrate.php` — a migration `v2.2.0_seed_legal_pages.sql` preenche **só o que está vazio**. Página que você **já editou fica intacta** (não sobrescreve), e rodar de novo não duplica.
- Depois, em **Admin → Páginas**, troque os placeholders (`[NOME DO SERVIDOR]`, `[SEU CNPJ]`, `discord.gg/SEU-CONVITE`, `[IP:PORTA do seu servidor]`) pelos seus dados.

> ✅ Testado em banco: instalação nova = 6 páginas; cliente que editou = preservado; página vazia = preenchida; rodar 2x = sem duplicar.

---

## 🆕 O que chegou na v2.1.0

### 💳 Cartão de crédito TRANSPARENTE (dentro do site) — **destaque**
- O jogador paga com cartão **sem sair do site** (igual ao PIX). O cartão é tokenizado **no navegador** pelo SDK do Mercado Pago (CardForm/Secure Fields) — o número do cartão **nunca passa pelo nosso servidor** (PCI SAQ-A). A gente só recebe o token de uso único.
- **Campos nativos** (o autofill do navegador funciona), documento **fixo em CPF**, recusa com mensagem amigável.
- **Parcelamento configurável**: Admin → Configurações define o valor mínimo pra liberar parcelas (padrão **R$30**); abaixo trava em **1x à vista**. Quem define juros é a conta MP do cliente.
- Cupom **compartilhado** entre PIX e Cartão. A aba "Cartão" liga sozinha quando a **Public Key do MP** está no config (sem ela, só o PIX aparece — nada quebra).

### 🎁 Caixas — upgrades
- **Ordem na vitrine** configurável (qual caixa aparece primeiro); caixa nova vai pro fim automaticamente.
- **Raridade define a chance**: escolher a raridade preenche o peso sozinho (comum cai muito, lendário raríssimo) + preview de **% ao vivo** no admin. Acabou o lendário com a mesma chance da comum.
- **Cooldown opcional** (0 = sem espera) na diária; **caixa nova nasce ativa**.
- **Countdown ao vivo** (HH:MM:SS) da diária no site — ao zerar, **libera o botão na hora, sem recarregar**.
- Recompensa pode ser **item OU moedas**; upload de imagem (capa + itens).

### 📦 Inventário do jogador + Log anti-golpista
- **"Histórico de Caixas"** no painel do jogador (Minhas Compras): tudo que abriu, com status **✓ Entregue / ⏳ Pendente** e horário.
- **Admin → Caixas → 📜 Logs**: log de aberturas **pesquisável por SteamID** com o **horário exato do drop** — prova na mão pra resolver disputa quando o jogador reclamar.

### 🔍 SEO completo
- **Títulos e descrições únicos** por página (home, loja, ranking, depoimentos, galeria, regras, páginas dinâmicas).
- **hreflang** (pt-BR / en-US / x-default) + **sitemap** corrigido (`/caixas` incluído, `/server-status` removido, duplicata `/rules` resolvida).
- **Schema** Product/Offer na loja (preço dos pacotes no Google) + **FAQPage** no `/faq` (accordion direto no Google).
- Checkbox de aceite de termos **NÃO vem mais pré-marcado** (conformidade CDC/LGPD — consentimento ativo).

### 🛡 Hardening de segurança
- **Rate-limit anti-bruteforce** nos endpoints `/api/*` — conta **só falhas de auth** por IP, então o mod/agent legítimo (token válido) **nunca** é limitado.
- Abertura de caixa **serializada** (`GET_LOCK`) — fecha a race de duplo-clique na diária.
- **Anti session-fixation** no login Steam; guard **anti open-redirect** no retorno; **health-check** parou de vazar métricas de negócio; hardening de XSS nos dados estruturados (JSON-LD).
- Customizador de cores agora **faz backup** antes de resetar (a paleta não se perde mais) + registra o **antes→depois** no audit log.

### 🐛 Correções
- **Playtime** ("tempo online") no perfil passou a contabilizar (vinha de `omega.playtime` do CFTools, não de `game.dayz`).
- Faixas de **status + próximo restart** na home **empilhadas em flex** — não se sobrepõem mais.
- **Faixa de anúncio** virou card centralizado com ícone por tipo (◆/✓/⚠/⚡), sombra e blur.
- **Português** das Regras e páginas legais **100% acentuado**.
- Webhook MP **não rebaixa** mais compra já entregue (PIX expirado tardio não vira "cancelada").

---

## ⚠️ Ao atualizar pra v2.1.0
- **Sem migration nova.** Só suba os arquivos novos (respeitando a Regra de Ouro do README).
- Pra **ativar o cartão transparente**: adicione `'public_key' => 'APP_USR-...'` no bloco `mercado_pago` do `config/config.php` (a Public Key fica no **mesmo painel MP** do access_token). Sem ela, só o PIX aparece.
- Opcional: Admin → Configurações → **"Parcelamento no cartão — valor mínimo (R$)"**.
- **NUNCA** use o `install.php` pra atualizar, e **NUNCA** apague o `config/config.php`.

---

## 📜 Histórico — v2.0.0
- **🎁 Caixas / Lootboxes** com carrossel estilo CS:GO, drop real via CFTools, sorteio por peso, blindagem de restart.
- **🗓 Eventos & Sorteios** (`/eventos` + teaser na home).
- **🏆 Recompensas do leaderboard** com agendamento (semanal/mensal, auto-creditar, idempotente).
- **💳 Checkout PIX transparente** (QR no site, sem ir pro MP).
- **🔌 Entrega in-game nativa** via mod Sparda (`getcoins`/`postcoins`), sem Agent pago.
- **🟢 Status do servidor + próximo restart** em tempo real (CFTools).
- **🖼 Foto + nick da Steam** no perfil; **últimas transações** + stats de combate.

## 📜 Histórico — v1.6.0 (desde a 1.2.0)
- **Recuperação de senha** (e-mail + `cli/reset-password.php`); **personalização visual** pelo painel (à prova de update); **leaderboard + perfil** via CFTools; **cupons completos**; **hardening** (XSS, headers, rate-limit, anti-SSRF, sessão, retry MP, `migrate.php`/`install.php` anti-destruição, i18n completo).

— Tecplay · https://tecplay.inf.br
