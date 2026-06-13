# 🏴 DayZ Website Template — Notas da versão (v1.6.0)

> Atualização grande: se você está vindo da **1.2.0** (a última pública), chegou MUITA coisa. Tudo abaixo já está no template.
> **Atualizar é seguro e não apaga nada** — siga o **[ATUALIZAR.md](ATUALIZAR.md)** (passo a passo mastigado, serve pra qualquer versão).

---

## 🆕 O que chegou desde a 1.2.0

### 🔐 Recuperação de senha (nunca mais ficar trancado)
- **"Esqueci minha senha"** no `/admin/login` (link por e-mail).
- **Reset por linha de comando** (`cli/reset-password.php`) — funciona mesmo sem e-mail, via SSH ou **Cron Job** do painel. Acabou o "perdi a senha, vou apagar tudo e reinstalar".

### 🎨 Personalização visual pelo painel (sem FTP, sem código)
- **Admin → Personalização:** upload de **logo, favicon e backgrounds** + **seletor de cores** (10 cores do tema).
- Tudo o que você muda aqui é **à prova de update** — fica isolado e nenhuma atualização futura sobrescreve.

### 📊 Leaderboard + perfil de jogador (via CFTools) — **destaque desta versão**
- **Ranking de gameplay** em `/ranking`: Kills, Zumbis mortos, K/D, Tempo online, Kill mais longa — dados reais do seu servidor.
- **Perfil público** `/player/SEU_STEAMID`: avatar da Steam + saldo, compras, e as estatísticas de combate.
- **Foto da Steam no login** funcionando out-of-the-box (não precisa mais de API key).
- Liga em minutos com o **seu próprio app CFTools** (passo a passo no README → "Ativar leaderboard").

### 🏆 Painel de Recompensas
- Premie os melhores do ranking em **moedas**. Você liga/desliga por categoria (ex: só Kills), define o prêmio do **1º/2º/3º** lugar (ou só o 1º), e os prêmios aparecem **destacados** no ranking.

### 🛒 Loja in-game
- Catálogo de itens gastáveis (jogador troca moedas por itens entregues no jogo), gerenciado no painel.

### 🎟 Cupons completos
- Desconto por % ou valor fixo, limite de usos, janela de validade e **restrição por pacote** (cupom só vale pra pacotes X/Y).

### 🛡 Segurança e robustez (hardening)
- Correção de XSS, headers de segurança (anti-clickjacking/MIME), rate-limit à prova de spoof, proteção anti-SSRF, timeout de sessão admin, retry no Mercado Pago.
- **Update seguro de verdade:** `cli/migrate.php` (atualiza o banco sem apagar dados, vindo de qualquer versão) + o `install.php` agora **se recusa a apagar um banco que já tem dados**.
- `verificar.php`: diagnóstico de deploy (checklist do que subiu).
- i18n completo (corrigido o caso de instalação nova mostrar `NAV.RULES` cru).

### ✨ Polimento de experiência
- Datas amigáveis ("há 2h", "ontem"), estados vazios com chamada pra ação, modais fecham no ESC, validação de SteamID na hora da compra, e mais.

---

## 🔜 Em desenvolvimento (próximas versões)
Estamos melhorando o template continuamente. No forno:
- **Entrega automática das recompensas** do ranking (creditar o prêmio dos vencedores sozinho, por período/mês).
- **Ações de servidor pelo painel** via CFTools (kick / ban / mensagem in-game).
- **Mais opções de personalização** e relatórios.

> Sugestões de features? Fala com a gente — o roadmap é guiado por quem usa.

---

## ⚠️ Importante ao atualizar
- **NUNCA** use o `install.php` pra atualizar, e **NUNCA** apague o `config/config.php`.
- Atualizar = subir os arquivos novos (respeitando a "Regra de Ouro" do **[README](README.md#-atualizar-garantido-sem-perder-seus-dados)**) + rodar `php cli/migrate.php`.
- Em caso de dúvida, o **[ATUALIZAR.md](ATUALIZAR.md)** tem o passo a passo completo.

— Tecplay · https://tecplay.inf.br
