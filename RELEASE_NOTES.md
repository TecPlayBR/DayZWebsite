# 🏴 DayZ Website Template — Notas da versão (v2.0.0)

> Atualização grande. **Atualizar é seguro e não apaga nada** — suba os arquivos novos e rode `php cli/migrate.php`. Passo a passo mastigado no **[ATUALIZAR.md](ATUALIZAR.md)**.

---

## 🆕 O que chegou na v2.0.0

### 🎁 Caixas / Lootboxes — **destaque desta versão**
- O jogador **abre caixas** (gastando moedas ou uma **diária grátis**) com **animação de carrossel** ("sorteando prêmio"), estilo CS:GO.
- O item sorteado **cai no personagem dentro do jogo** via CFTools (drop real, validado ao vivo).
- **Sorteio por peso** (raridade) — você define a chance de cada item; o painel calcula o % automático.
- **Blindagem de restart:** perto de um restart o item não é dropado ao vivo (iria pro limbo) — fica **pendente** e cai quando o servidor volta / o jogador conecta.
- Admin → **🎁 Caixas**: cria caixa (nome, imagem, custo/diária) e o pool de itens (classname, quantidade, peso, raridade).

### 🗓 Eventos & Sorteios
- Página `/eventos` (no menu): **Disponíveis agora / Em breve / Encerrados**, com prêmio e vencedor do sorteio.
- **Teaser na home** destacando o próximo evento (ou o que está acontecendo agora).
- Admin → **🗓 Eventos**: cria evento/sorteio (datas, prêmio, imagem, vencedor). Status calculado pelas datas.

### 🏆 Recompensas do leaderboard — agora **com agendamento**
- Define **quando credita**: Manual, **Semanal** ou **Mensal**, com **auto-creditar** na virada do período (via cron).
- Botão **"Premiar agora"** credita o top atual na hora — **idempotente** (clicar 2x não paga dobrado).
- **Histórico** das premiações no painel. As moedas caem no saldo do jogador (e no jogo, como qualquer compra).

### 💳 Checkout PIX transparente
- O jogador paga **dentro do site** (QR + copia-e-cola), **sem ser jogado pro Mercado Pago**. Cupom pode ser aplicado na própria tela.
- Assim que o PIX aprova, a página **leva sozinha pro perfil** com o saldo novo e as **últimas transações**.
- Cartão/boleto continuam disponíveis (botão de fallback).

### 🔌 Entrega in-game NATIVA (sem Agent pago)
- Integração direta com o **mod Sparda** (`getcoins`/`postcoins`): o site vende a moeda e o mod entrega no jogo. Painel **🎮 Entrega Sparda** gera as URLs prontas. (Agent e Bot continuam funcionando como alternativas.)

### 🟢 Status do servidor em tempo real + restart
- **Jogadores online agora** via CFTools (em tempo real, não depende só do BattleMetrics).
- **Próximo restart** discreto na página de status, configurável (horários) em Admin → Configurações — e usado pra blindar o drop das caixas.

### 🖼 Perfil & Steam
- **Foto + nick da Steam** no topo e no perfil (preenche sozinho mesmo em sessão antiga).
- Perfil mostra **últimas transações** + estatísticas de combate.

### 🛡 Segurança & i18n
- Auditoria de segurança: **XSS** (caixas) e **IDOR** (status/pagamento de compra) corrigidos; débito de moeda atômico; webhook MP com HMAC + reconsulta.
- **Fuso horário do Brasil** nas datas. Páginas novas **traduzidas** (PT/EN).
- **Caminho de update garantido**: `schema.sql` completo pra instalação nova + migrations idempotentes pra quem atualiza.

---

## 🔜 Em desenvolvimento (próximas versões)
- **Discord do player** (identificar/pingar) — sob demanda.
- **Ações de servidor pelo painel** via CFTools GameLabs (heal / teleport / mensagem in-game).
- Gerenciar a loja Sparda pelo painel.

> Sugestões de features? Fala com a gente — o roadmap é guiado por quem usa.

---

## ⚠️ Importante ao atualizar
- **NUNCA** use o `install.php` pra atualizar, e **NUNCA** apague o `config/config.php`.
- Atualizar = subir os arquivos novos (respeitando a "Regra de Ouro" do **[README](README.md#-atualizar-garantido-sem-perder-seus-dados)**) + rodar `php cli/migrate.php`.
- **Crons opcionais** (Painel → Cron Jobs) pras automações novas:
  - Entregas pendentes de caixa (a cada 2 min): `curl -s "https://SEUSITE/api/deliver-boxes.php?token=SEU_AGENT_TOKEN"`
  - Premiação automática (de hora em hora): `curl -s "https://SEUSITE/api/award-rewards.php?token=SEU_AGENT_TOKEN"`
- Em caso de dúvida, o **[ATUALIZAR.md](ATUALIZAR.md)** tem o passo a passo completo.

---

## 📜 Histórico — v1.6.0 (desde a 1.2.0)
- **Recuperação de senha** (link por e-mail + `cli/reset-password.php`).
- **Personalização visual pelo painel** (logo, favicon, backgrounds, cores) — à prova de update.
- **Leaderboard + perfil de jogador** via CFTools (ranking de gameplay, perfil público, foto Steam).
- **Painel de Recompensas** (prêmios por colocação no ranking).
- **Loja in-game** (catálogo de itens gastáveis) e **Cupons completos** (% ou fixo, limite, validade, por pacote).
- **Hardening**: XSS, headers de segurança, rate-limit, anti-SSRF, timeout de sessão, retry no MP, `cli/migrate.php` + `install.php` anti-destruição, i18n completo.

— Tecplay · https://tecplay.inf.br
