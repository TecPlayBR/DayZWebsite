<div align="center">

# 🏴 Tecplay — DayZ Website Template

**Site completo, gratuito e profissional para servidores DayZ brasileiros.**
Tema apocalipse · Painel admin completo · Mercado Pago · Login Steam · Multi-idioma · Multi-server.

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com)
[![License](https://img.shields.io/badge/License-Tecplay--NC-a855f7?style=flat-square)](LICENSE.txt)
[![Status](https://img.shields.io/badge/Status-Produção-16a34a?style=flat-square)]()
[![Versão](https://img.shields.io/badge/Versão-1.1.0-facc15?style=flat-square)](CHANGELOG.md)

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

## ✨ Features

### 🌐 Lado público

- **Landing page apocalipse** com hero animado, contador de wipe e status do servidor ao vivo (BattleMetrics)
- **Loja** com 6 pacotes seedados (R$ 9,99 a R$ 149,90), bônus, combos e cupons
- **Checkout Mercado Pago** (PIX, boleto, cartão) com **webhook auto-credit** de moedas
- **Login Steam OpenID 2.0** com pré-fill automático no checkout
- **Multi-idioma** PT-BR + EN-US (dropdown elegante no header)
- **Multi-server**: 1 site atendendo N servidores DayZ
- **Mural de vendas ao vivo** opcional (com anonimização LGPD)
- **Galeria** de screenshots com lightbox e setas
- **Hall da Fama** dos jogadores (top coins + top apoiadores)
- **Sistema de avaliações** dos jogadores
- **Conquistas automáticas** (Primeiro Sangue, Veterano, Lendário, Madrugador, etc)
- **Wishlist** de pacotes pros jogadores logados
- **Páginas dinâmicas** (Termos, Privacidade LGPD, Regras, Reembolso) editáveis no admin
- **SEO completo**: Open Graph, Twitter Card, JSON-LD, sitemap.xml, robots.txt

### 🛡 Painel admin

- **Dashboard** com gráfico de vendas 30 dias (Chart.js)
- **CRUD completo** de Jogadores, Pacotes, Combos, Cupons, Páginas, Anúncios, Reviews, Servidores, Galeria
- **Audit log** de toda ação administrativa
- **Histórico granular de saldo** por jogador
- **Console de logs PHP** integrado ao painel
- **Multi-admin** com equipe e roles
- **Modo manutenção** com mensagem customizável
- **Promo sazonal** com cupom auto-aplicado em todos os pacotes
- **Webhook Discord** para notificações de venda
- **Configurações editáveis** sem mexer em código

### 🔌 Integrações nativas

- **`tecplay-agent.exe`** — sincronização de moedas in-game ↔ site (`/api/sync-players.php`)
- **Bot Discord Tecplay** — endpoint `/notify/purchase` notifica bot quando venda aprovada
- **BattleMetrics API** — status servidor live com cache 60s
- **Steam Web API** — avatar e display name dos jogadores

### 🔐 Segurança hardened

- CSRF tokens em **todas** as forms POST
- Rate limit no checkout e login admin (dois eixos: IP + IP+user)
- Sanitização HTML em páginas dinâmicas (allowlist + remove `on*`/javascript:/data:)
- Claim atômico no webhook MP (prevê dupla entrega de coins em race condition)
- Validação obrigatória de assinatura MP em produção
- Open redirect guard em parâmetros `_back`
- Cookies de sessão com `HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS
- Senhas bcrypt (PASSWORD_BCRYPT)
- Cap de 64KB no payload de webhooks

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
