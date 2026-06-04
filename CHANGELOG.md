# Changelog — DayZ Website Template (Tecplay)

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento [SemVer](https://semver.org/lang/pt-BR/).

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
