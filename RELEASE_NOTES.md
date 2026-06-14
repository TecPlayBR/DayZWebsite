# 🏴 DayZ Website Template — Notas da versão (v2.1.0)

> Features + hardening. **Atualizar é seguro e não apaga nada.** **NÃO tem migration nova** nesta versão — é só subir os arquivos. Passo a passo no **[ATUALIZAR.md](ATUALIZAR.md)**.

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
