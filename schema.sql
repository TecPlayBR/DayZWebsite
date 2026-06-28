-- ============================================================
-- (c) 2026 Tecplay - DayZ Website Template
-- Schema completo: 5 tabelas + seed dos 6 pacotes
-- ============================================================
-- Importado pelo install.php quando o cliente roda o wizard.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- TABELA: admin_users
-- Login do painel admin do site. Senha em bcrypt.
-- ============================================================
DROP TABLE IF EXISTS admin_users;
CREATE TABLE admin_users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    role            VARCHAR(20)  NOT NULL DEFAULT 'super_admin',
    password_hash    VARCHAR(255) NOT NULL,
    email            VARCHAR(255) NULL,
    reset_token_hash VARCHAR(64)  NULL,
    reset_expires    DATETIME     NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at    DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Role values: super_admin | finance | support | editor (matriz em src/Auth.php::PERMISSIONS)

-- ============================================================
-- TABELA: players
-- Cada jogador conhecido (vem do agent sync ou de compra no site).
-- ============================================================
DROP TABLE IF EXISTS players;
CREATE TABLE players (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    steam_id        VARCHAR(20)  NOT NULL UNIQUE,
    display_name    VARCHAR(100) NULL,
    server_id       INT          NULL DEFAULT 1,
    coins           INT          NOT NULL DEFAULT 0,
    total_spent_brl DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    last_seen_at    DATETIME NULL,
    origin          ENUM('agent','panel','payment','manual','bot','reward','box') NOT NULL DEFAULT 'agent',
    notes           TEXT NULL,
    -- Afiliado/streamer ao qual o cliente esta atrelado (programa "Apoie seu Streamer").
    -- Setado 1x quando ele usa um cupom de afiliado; so troca se o admin permitir.
    affiliate_coupon_code VARCHAR(40) NULL,
    affiliate_bound_at    DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_coins (coins DESC),
    INDEX idx_total_spent (total_spent_brl DESC),
    INDEX idx_last_seen (last_seen_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: packages
-- Pacotes de moedas vendidos no site. Editavel pelo admin.
-- ============================================================
DROP TABLE IF EXISTS packages;
CREATE TABLE packages (
    id              VARCHAR(40)  NOT NULL PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    icon            VARCHAR(10)  NULL,
    image           VARCHAR(255) NULL,
    coins           INT          NOT NULL,
    bonus_coins     INT          NOT NULL DEFAULT 0,
    price_brl       DECIMAL(8,2) NOT NULL,
    badge           VARCHAR(40)  NULL,
    bonus_badge     VARCHAR(40)  NULL,
    perks_json      JSON         NULL,
    bonus_perks_json JSON        NULL,
    featured        TINYINT(1)   NOT NULL DEFAULT 0,
    ribbon          VARCHAR(40)  NULL,
    sort_order      INT          NOT NULL DEFAULT 0,
    enabled         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_enabled_order (enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: purchases
-- Toda compra (pendente, paga, falhada). Vinda do Mercado Pago.
-- ============================================================
DROP TABLE IF EXISTS purchases;
CREATE TABLE purchases (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    steam_id        VARCHAR(20)  NOT NULL,
    package_id      VARCHAR(40)  NOT NULL,
    server_id       INT          NULL DEFAULT 1,
    coins_base      INT          NOT NULL,
    coins_bonus     INT          NOT NULL DEFAULT 0,
    coins_total     INT          NOT NULL,
    price_brl       DECIMAL(8,2) NOT NULL,
    mp_payment_id     VARCHAR(64)  NULL,
    coupon_code       VARCHAR(40)  NULL,    -- código do cupom usado (se houver)
    discount_brl      DECIMAL(8,2) NOT NULL DEFAULT 0, -- valor descontado do preço total
    affiliate_coupon_code VARCHAR(40) NULL, -- streamer atribuido NO MOMENTO da compra (historico estavel)
    terms_accepted_at DATETIME     NULL,    -- timestamp de quando aceitou termos
    terms_version     VARCHAR(20)  NULL,    -- versao dos termos (ex: '2026-05-27')
    mp_status         VARCHAR(32)  NULL,    -- pending, approved, rejected, cancelled, refunded
    payment_method  VARCHAR(32)  NULL,    -- pix, boleto, credit_card, debit_card
    delivered_at    DATETIME NULL,        -- quando os coins foram creditados ao player
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_steam (steam_id, created_at DESC),
    INDEX idx_status (mp_status, created_at DESC),
    INDEX idx_mp_payment (mp_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: pages
-- Paginas estaticas editaveis pelo admin (regras, sobre, etc).
-- Slug eh a URL: /<slug>. Conteudo em PT-BR e EN-US.
-- ============================================================
DROP TABLE IF EXISTS pages;
CREATE TABLE pages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(80)  NOT NULL UNIQUE,
    title_ptbr      VARCHAR(200) NOT NULL,
    title_enus      VARCHAR(200) NULL,
    body_ptbr       MEDIUMTEXT   NULL,
    body_enus       MEDIUMTEXT   NULL,
    published       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      INT          NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pub_sort (published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: announcements
-- Banners/avisos exibidos no topo da home. Admin agenda janela.
-- ============================================================
DROP TABLE IF EXISTS announcements;
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NULL,
    kind ENUM('info','warning','danger','success') NOT NULL DEFAULT 'info',
    cta_label VARCHAR(50) NULL,
    cta_url VARCHAR(500) NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (published, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: reviews
-- Avaliações públicas após compra aprovada. Admin modera.
-- ============================================================
DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NULL,                 -- NULL pra reviews públicas (source='public')
    steam_id VARCHAR(20) NULL,            -- NULL pra reviews públicas
    display_name VARCHAR(100) NULL,
    avatar VARCHAR(255) NULL,             -- foto Steam (capturada do login no envio); fallback = inicial do nome
    rating TINYINT NOT NULL,
    body TEXT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'purchase',  -- 'purchase' (compra real) | 'public' (form em /depoimentos)
    approved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_approved (approved, created_at DESC),
    INDEX idx_steam (steam_id),
    INDEX idx_source (source),
    CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: coupons
-- Cupons de desconto aplicáveis no checkout.
-- discount_type=percent: discount_value é 0-100 (% off)
-- discount_type=fixed:   discount_value é R$ off (descontado do total)
-- package_ids NULL = válido pra qualquer pacote
-- ============================================================
DROP TABLE IF EXISTS coupons;
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    discount_type ENUM('percent','fixed','coins') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(8,2) NOT NULL,
    max_uses INT NULL,                       -- limite GLOBAL de usos (todos os jogadores somados); NULL = ilimitado
    per_user_limit INT NULL,                 -- limite POR jogador (ex: 1 = cada um usa uma vez só); NULL = ilimitado
    used_count INT NOT NULL DEFAULT 0,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    package_ids JSON NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    -- Programa de afiliado/streamer: se affiliate_name preenchido, o cupom rende cachê.
    -- Comissao escalonada pela recorrencia do cliente (1a/2a/3a+ compra), sobre o valor cheio.
    affiliate_name VARCHAR(120) NULL,
    commission_pct_1 DECIMAL(5,2) NOT NULL DEFAULT 0,
    commission_pct_2 DECIMAL(5,2) NOT NULL DEFAULT 0,
    commission_pct_3plus DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code_active (code, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: settings
-- Configuracoes editaveis pelo admin sem mexer em config.php.
-- ============================================================
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
    `key`           VARCHAR(64)  NOT NULL PRIMARY KEY,
    `value`         TEXT         NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: servers — registry de servidores DayZ (multi-server)
-- Single-server: 1 linha (id=1). Multi-server: 2+ linhas ativas.
-- ============================================================
DROP TABLE IF EXISTS servers;
CREATE TABLE servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(80)  NOT NULL,
    slug             VARCHAR(40)  NOT NULL UNIQUE,
    description      VARCHAR(255) NULL,
    ip               VARCHAR(45)  NULL,
    port             INT          NULL,
    battlemetrics_id VARCHAR(40)  NULL,
    agent_token      VARCHAR(80)  NOT NULL,
    map              VARCHAR(40)  DEFAULT 'Chernarus',
    max_players      INT          DEFAULT 60,
    active           TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order       INT          NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO servers (id, name, slug, description, agent_token, sort_order) VALUES
(1, 'Servidor Principal', 'principal', 'Servidor padrão do site.', SHA2(CONCAT(RAND(), NOW()), 256), 0);

-- ============================================================
-- TABELA: audit_log — trilha de ações administrativas
-- ============================================================
DROP TABLE IF EXISTS audit_log;
CREATE TABLE audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NULL,
    admin_username VARCHAR(50) NULL,
    action       VARCHAR(80) NOT NULL,
    target_type  VARCHAR(40) NULL,
    target_id    VARCHAR(64) NULL,
    payload      JSON NULL,
    ip           VARCHAR(45) NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_time (action, created_at),
    INDEX idx_admin (admin_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: balance_log — histórico granular de saldo do player
-- ============================================================
DROP TABLE IF EXISTS balance_log;
CREATE TABLE balance_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    player_id      INT NOT NULL,
    steam_id       VARCHAR(20) NOT NULL,
    delta          INT NOT NULL DEFAULT 0,
    balance_before INT NOT NULL,
    balance_after  INT NOT NULL,
    source         VARCHAR(40) NOT NULL,
    ref_type       VARCHAR(40) NULL,
    ref_id         VARCHAR(64) NULL,
    notes          TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_player_time (player_id, created_at),
    INDEX idx_steam_time (steam_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: combos — pacotes promocionais agregando vários packages
-- ============================================================
DROP TABLE IF EXISTS combos;
CREATE TABLE combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug         VARCHAR(40)  NOT NULL UNIQUE,
    name         VARCHAR(100) NOT NULL,
    description  VARCHAR(255) NULL,
    package_ids  JSON         NOT NULL,
    custom_price DECIMAL(8,2) NOT NULL,
    enabled      TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order   INT          NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_enabled_order (enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: wishlist — pacotes salvos como desejo pelo player
-- ============================================================
DROP TABLE IF EXISTS wishlist;
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    steam_id    VARCHAR(20) NOT NULL,
    package_id  VARCHAR(40) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_steam_pkg (steam_id, package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: gallery — screenshots públicas do servidor
-- ============================================================
DROP TABLE IF EXISTS gallery;
CREATE TABLE gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename    VARCHAR(120) NOT NULL,
    caption     VARCHAR(200) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    published   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pub_sort (published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: invoices — cobranças Pix de valor livre (/cobrar do bot)
-- (migration v1.5.0 — usada por api/bot-integration.php + api/mp-webhook.php)
-- ============================================================
DROP TABLE IF EXISTS invoices;
CREATE TABLE invoices (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    invoice_ref   VARCHAR(80)   NOT NULL UNIQUE,   -- id unico do bot (idempotencia)
    name          VARCHAR(120)  NOT NULL,
    cpf           VARCHAR(14)   NULL,
    email         VARCHAR(160)  NULL,
    phone         VARCHAR(20)   NULL,
    description   VARCHAR(255)  NOT NULL,
    amount_brl    DECIMAL(10,2) NOT NULL,
    status        ENUM('pending','paid','expired','cancelled') NOT NULL DEFAULT 'pending',
    mp_payment_id VARCHAR(40)   NULL,
    qr_code       TEXT          NULL,               -- payload Pix EMV p/ retry idempotente
    created_by    VARCHAR(40)   NULL,               -- discord id do admin (audit)
    guild_id      VARCHAR(32)   NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at       DATETIME      NULL,
    expires_at    DATETIME      NULL,
    INDEX idx_inv_status (status, created_at),
    INDEX idx_inv_payment (mp_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED: 6 pacotes (base do Bryan, ajustaveis no admin)
-- ============================================================
INSERT INTO packages (id, name, icon, coins, bonus_coins, price_brl, bonus_badge, perks_json, bonus_perks_json, featured, ribbon, sort_order) VALUES
('simples',  'PACOTE SIMPLES',  '🪙',  10,  0,   9.99,  NULL,
    '["10 moedas no jogo","Entrega instantanea","Sem expiracao"]', NULL, 0, NULL, 10),
('astuto',   'PACOTE ASTUTO',   '🪙',  25,  5,   24.99, 'BONUS +5',
    '["25 moedas no jogo","Entrega instantanea","Sem expiracao"]', '["+5 moedas de bonus"]', 0, NULL, 20),
('valioso',  'PACOTE VALIOSO',  '🪙',  50,  10,  49.99, 'BONUS +10',
    '["50 moedas no jogo","Entrega instantanea","Sem expiracao"]', '["+10 moedas de bonus"]', 0, NULL, 30),
('veterano', 'PACOTE VETERANO', '🪙',  75,  15,  74.99, 'BONUS +15',
    '["75 moedas no jogo","Entrega instantanea","Sem expiracao"]', '["+15 moedas de bonus"]', 1, 'MAIS POPULAR', 40),
('premium',  'PACOTE PREMIUM',  '🪙',  100, 25,  99.99, 'BONUS +25',
    '["100 moedas no jogo","Entrega instantanea","Sem expiracao"]', '["+25 moedas de bonus"]', 0, NULL, 50),
('master',   'PACOTE MASTER',   '🪙',  200, 50,  149.90,'BONUS +50',
    '["200 moedas no jogo","Entrega instantanea","Sem expiracao","Maior pacote disponivel"]', '["+50 moedas de bonus"]', 0, NULL, 60);

-- ============================================================
-- TABELA: discord_integration_log  (migration v1.1.0)
-- Auditoria das chamadas ao /api/bot-integration.php (mantida curta, ~200).
-- ============================================================
DROP TABLE IF EXISTS discord_integration_log;
CREATE TABLE discord_integration_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    called_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip          VARCHAR(45) NOT NULL DEFAULT '',
    action      VARCHAR(64) NOT NULL DEFAULT '',
    status_code SMALLINT    NOT NULL DEFAULT 0,
    INDEX idx_called (called_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELAS: shop_items + shop_spends  (migration v1.4.0 — loja in-game)
-- Catalogo gastavel (/admin/shop, ?action=shop_items) + debito (?action=spend).
-- ============================================================
DROP TABLE IF EXISTS shop_items;
CREATE TABLE shop_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sku          VARCHAR(64)  NOT NULL UNIQUE,
    name         VARCHAR(120) NOT NULL,
    icon         VARCHAR(16)  NULL,
    coins_cost   INT          NOT NULL,
    enabled      TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order   INT          NOT NULL DEFAULT 0,
    deliver_json JSON         NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_enabled_order (enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS shop_spends;
CREATE TABLE shop_spends (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    spend_ref    VARCHAR(80)  NOT NULL UNIQUE,
    steam_id     VARCHAR(20)  NOT NULL,
    sku          VARCHAR(64)  NOT NULL,
    coins_spent  INT          NOT NULL,
    new_balance  INT          NOT NULL,
    deliver_json JSON         NOT NULL,
    server_id    INT          NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_steam (steam_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: player_stats  (migration v1.6.0 — leaderboard/perfil)
-- Stats de gameplay vindos do CFTools via Bot. Core ordenável + extra_json.
-- ============================================================
DROP TABLE IF EXISTS player_stats;
CREATE TABLE player_stats (
    steam_id          VARCHAR(20)  NOT NULL PRIMARY KEY,
    cftools_id        VARCHAR(40)  NULL,
    kills             INT          NOT NULL DEFAULT 0,
    deaths            INT          NOT NULL DEFAULT 0,
    kdratio           DECIMAL(8,2) NOT NULL DEFAULT 0,
    playtime_seconds  INT          NOT NULL DEFAULT 0,
    longest_kill_m    INT          NOT NULL DEFAULT 0,
    longest_shot_m    INT          NOT NULL DEFAULT 0,
    suicides          INT          NOT NULL DEFAULT 0,
    kills_infected    INT          NOT NULL DEFAULT 0,
    extra_json        JSON         NULL,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kills    (kills DESC),
    INDEX idx_kd       (kdratio DESC),
    INDEX idx_playtime (playtime_seconds DESC),
    INDEX idx_longest  (longest_kill_m DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Caixas / Lootboxes (v1.8.0)
-- ============================================================
DROP TABLE IF EXISTS boxes;
CREATE TABLE boxes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80)  NOT NULL,
    slug            VARCHAR(80)  NOT NULL,
    image           VARCHAR(255) DEFAULT NULL,
    description     TEXT         DEFAULT NULL,
    cost_coins      INT          NOT NULL DEFAULT 0,
    is_daily        TINYINT(1)   NOT NULL DEFAULT 0,
    cooldown_hours  INT          NOT NULL DEFAULT 24,
    enabled         TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      INT          NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS box_items;
CREATE TABLE box_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    box_id      INT          NOT NULL,
    type        ENUM('item','coins') NOT NULL DEFAULT 'item',
    classname   VARCHAR(120) NOT NULL,
    name        VARCHAR(120) NOT NULL,
    image       VARCHAR(255) DEFAULT NULL,
    quantity    INT          NOT NULL DEFAULT 1,
    weight      INT          NOT NULL DEFAULT 1,
    rarity      VARCHAR(20)  NOT NULL DEFAULT 'common',
    enabled     TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0,
    KEY idx_box (box_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS box_openings;
CREATE TABLE box_openings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    box_id       INT          NOT NULL,
    steam_id     VARCHAR(20)  NOT NULL,
    item_id      INT          DEFAULT NULL,
    classname    VARCHAR(120) NOT NULL,
    item_name    VARCHAR(120) NOT NULL,
    quantity     INT          NOT NULL DEFAULT 1,
    rarity       VARCHAR(20)  NOT NULL DEFAULT 'common',
    cost_paid    INT          NOT NULL DEFAULT 0,
    status       ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP    NULL DEFAULT NULL,
    KEY idx_steam (steam_id),
    KEY idx_status (status),
    KEY idx_box (box_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Premiações do leaderboard (v1.9.0)
-- ============================================================
DROP TABLE IF EXISTS reward_payouts;
CREATE TABLE reward_payouts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    period_label VARCHAR(20)  NOT NULL,
    category     VARCHAR(30)  NOT NULL,
    place        TINYINT      NOT NULL,
    steam_id     VARCHAR(20)  NOT NULL,
    player_name  VARCHAR(120) DEFAULT NULL,
    coins        INT          NOT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_period_cat_place (period_label, category, place),
    KEY idx_steam (steam_id),
    KEY idx_period (period_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Eventos & Sorteios (v2.0.0)
-- ============================================================
DROP TABLE IF EXISTS events;
CREATE TABLE events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(120) NOT NULL,
    slug            VARCHAR(120) NOT NULL,
    type            ENUM('event','raffle') NOT NULL DEFAULT 'event',
    image           VARCHAR(255) DEFAULT NULL,
    description     TEXT         DEFAULT NULL,
    prize           VARCHAR(200) DEFAULT NULL,
    starts_at       DATETIME     DEFAULT NULL,
    ends_at         DATETIME     DEFAULT NULL,
    winner_steam_id VARCHAR(20)  DEFAULT NULL,
    winner_name     VARCHAR(120) DEFAULT NULL,
    enabled         TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      INT          NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slug (slug),
    KEY idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Entitlements VIP/BattlePass (v2.6.0) — geridos pelo site, aplicados pelo agent
-- ============================================================
DROP TABLE IF EXISTS player_grants;
CREATE TABLE player_grants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    server_id       INT          NOT NULL,
    steam_id        VARCHAR(20)  NOT NULL,
    nickname        VARCHAR(120) NULL,
    type            VARCHAR(20)  NOT NULL,
    tier            VARCHAR(20)  NULL,
    days            INT          NOT NULL DEFAULT 0,
    expiration_date DATE         NULL,
    status          VARCHAR(20)  NOT NULL DEFAULT 'pending',
    notes           VARCHAR(255) NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_at      DATETIME     NULL,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_grants_server_status (server_id, status),
    INDEX idx_grants_steam (steam_id),
    INDEX idx_grants_exp (expiration_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Recompensa por conquista + Log de login (v2.7.0)
-- ============================================================
DROP TABLE IF EXISTS achievement_rewards_log;
CREATE TABLE achievement_rewards_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    steam_id   VARCHAR(20)  NOT NULL,
    slug       VARCHAR(40)  NOT NULL,
    coins      INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ach_steam_slug (steam_id, slug),
    KEY idx_ach_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS login_log;
CREATE TABLE login_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    steam_id     VARCHAR(20)  NOT NULL,
    display_name VARCHAR(190) NULL,
    ip           VARCHAR(45)  NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_steam (steam_id),
    KEY idx_login_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings padrao
INSERT INTO settings (`key`, `value`) VALUES
('bonus_enabled', '1'),
('site_name', 'MEU SERVIDOR'),
('site_tagline', 'Sobreviva. Construa. Domine. A apocalipse nao espera.'),
('server_ip', ''),
('server_port', '2302'),
('battlemetrics_id', ''),
('next_wipe_at', ''),
('wipe_label', 'Próximo wipe'),
('discord_invite', ''),
('social_discord', ''),
('social_instagram', ''),
('social_whatsapp', ''),
('social_facebook', ''),
('social_youtube', ''),
('social_tiktok', ''),
('social_twitch', ''),
('social_kick', ''),
('social_x', ''),
('maintenance_enabled', '0'),
('maintenance_message', ''),
('maintenance_eta', ''),
('promo_coupon_code', ''),
('promo_label', ''),
('discord_sales_webhook', ''),
('live_purchases_enabled', '0'),
('live_purchases_anonymize', '1'),
('live_purchases_show_price', '0'),
('terms_version', '2026-05-27'),
('discord_integration_token', ''),
('discord_integration_last_ok', '0'),
('affiliate_enabled', '0'),
('affiliate_allow_switch', '0'),
('box_claim_enabled', '0');

-- ==== SEED: paginas legais (v2.2.0) ====
-- Conteudo de EXEMPLO para os clientes nao nascerem com paginas legais vazias.
-- Ajuste nome do servidor, CNPJ, Discord e IP no painel admin (Paginas).
-- rules
INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
VALUES ('rules', 'Regras do Servidor', 'Server Rules', '<h2>Regras do Servidor</h2>

<p class="legal-meta">Última atualização: 2026-06-04 — Aplica-se a todos os jogadores do servidor [NOME DO SERVIDOR] e da comunidade no Discord oficial.</p>

<div class="legal-callout">
<p><strong>Importante:</strong> ao conectar no servidor ou entrar no Discord, você concorda com TODAS as regras abaixo. Ignorância não é desculpa — leia até o fim.</p>
</div>

<h3>1. Regras Gerais</h3>
<ul>
<li>Duplicar, hackear, explorar falhas, usar glitches e trapacear resulta em <strong>banimento permanente</strong> — você e todo o seu grupo.</li>
<li>Contas alternativas <strong>não são permitidas</strong>.</li>
<li>Evitar banimento (combat log, alt account) resulta em banimento permanente.</li>
<li>Não há reembolso para equipamentos perdidos devido a bugs sem evidência em vídeo.</li>
<li>Veículos por sua conta e risco. Não reembolsamos — isto é DayZ.</li>
<li>A equipe não tolera desrespeito. Se você sofrer com isso, denuncie em ticket no Discord.</li>
<li>Se qualquer membro da staff te ofender ou desrespeitar, reporte imediatamente. Você tem direitos como consumidor e cidadão.</li>
</ul>

<h3>2. Comunicação (Chat & Voz)</h3>
<ul>
<li>Chat e voz preferencialmente em <strong>português brasileiro</strong> (servidor BR).</li>
<li>Proibido CAPS LOCK excessivo, spam de mensagens, flood de emojis.</li>
<li>Proibido discurso de ódio, racismo, xenofobia, homofobia, transfobia, capacitismo, misoginia ou qualquer discriminação. Banimento permanente.</li>
<li>Stream-sniping (perseguir jogador por live) é proibido. Banimento.</li>
<li>Doxxing (divulgar dados pessoais reais de outro jogador) é crime — denunciamos à polícia.</li>
</ul>

<h3>3. Base Building</h3>
<ul>
<li>Para construir uma base é necessário primeiro colocar um marcador de terreno com bandeira.</li>
<li>Cada grupo pode ter no máximo <strong>2 marcadores</strong> (com ou sem bandeira). Qualquer marcador além disso será deletado.</li>
<li>Bases podem ser construídas em qualquer região que não esteja demarcada em vermelho no mapa.</li>
<li>Você é livre para construir do jeito que quiser, mas lembre-se: RAID é permitido aos finais de semana.</li>
<li>Bases sem bandeira (ou com bandeira expirada) podem ser raidadas <strong>a qualquer momento</strong>.</li>
</ul>

<h3>4. Regras de Raid</h3>
<ul>
<li>Clãs devem obrigatoriamente estar usando a <strong>TAG do clã</strong>. Todo membro deve usar a TAG, sem exceções.</li>
<li>Somente clãs com TAG podem efetuar RAID. Jogadores sem TAG <strong>não podem raidar</strong>.</li>
<li>É <strong>proibido</strong> raidar estruturas que não sejam portões/janelas/baús/armários/manequins. Identificado no log → banimento.</li>
<li>RAID liberado em: portões, janelas, baús, armários, manequins e itens similares. Qualquer ferramenta vale (explosivos, lança-granada, granada de gás, etc).</li>
<li><strong>RAID PROIBIDO em dias de semana.</strong> Sem exceção.</li>
<li>Horário de RAID:
  <ul>
    <li><strong>Sábado:</strong> 16:00 às 23:59</li>
    <li><strong>Domingo:</strong> 14:00 às 20:00</li>
  </ul>
</li>
<li>Tomou RAID fora do horário? Denuncie — investigamos. Tomou no horário porque não logou para defender? Paciência, isso é DayZ.</li>
<li>Quebra das regras de RAID → banimento individual ou do clã inteiro, conforme envolvimento.</li>
<li>Obstruir passagem nos dias de RAID (armadilhas, carros, barricadas táticas) é permitido.</li>
<li>Provocar dano em estruturas/proteções fora do horário de RAID → banimento.</li>
<li>Bases já raidadas e abertas podem ser saqueadas sem restrição.</li>
<li>Bases sem bandeira → raidáveis a qualquer momento. Bandeira baixa não conta como sem bandeira.</li>
<li>Anti-logout: você não pode deslogar instantaneamente em combate. O sistema pune automaticamente.</li>
<li>Alianças entre clãs são permitidas. Forme a sua.</li>
<li>A staff <strong>não interfere</strong> em guerras de clã. Estamos aqui para dar suporte, não para jogar por você.</li>
</ul>

<h3>5. Anti-Cheat e Limites Técnicos</h3>
<ul>
<li>Proibido o uso do depurador gráfico da NVIDIA (Nvidia Inspector) ou similares para anular vegetação, alterar gráficos para ganhar vantagem visual. Banimento.</li>
<li>Usar scripts/macros que tirem grama, neblina, sombras, etc → banimento.</li>
<li>Bugs do mapa: se for "exploit" (atravessar parede, ficar invulnerável, etc) → banimento. Se for "feature" (subir num pixel que dá para subir, atalho que não quebra o mapa) → permitido.</li>
<li>Caso identifiquemos uso de cheats, o clã inteiro pode ser banido, e a informação será divulgada a outros servidores BR.</li>
<li>Você pode ser convidado a instalar um <strong>validador anti-cheat</strong> sob supervisão da equipe se denunciado. Recusar = banimento.</li>
</ul>

<h3>6. Discord (Comunidade)</h3>
<ul>
<li>Idade mínima: <strong>13 anos</strong> (TOS Discord). Menores de 18 requerem consentimento dos responsáveis.</li>
<li>Proibido spam em canais, DM ads (mensagens privadas com propaganda), divulgação de outros servidores DayZ.</li>
<li>NSFW só em canais marcados como tal (se existirem). Em outros = banimento.</li>
<li>Use os canais para finalidade correta: tickets em <code>#suporte</code>, denúncias em <code>#reportar</code>, dúvidas em <code>#geral</code>.</li>
<li>Respeite o trabalho da moderação. Discussão com mod via ticket privado, não em público.</li>
</ul>

<h3>7. Loja e Moedas (Termo de Aceite)</h3>
<ul>
<li>Ao comprar moedas na <a href="/shop">Loja</a>, você concorda com os <a href="/page/terms">Termos de Uso</a> e <a href="/page/refund">Política de Reembolso</a>.</li>
<li>Moedas compradas só podem ser usadas dentro do servidor — não há reembolso após uso parcial ou total.</li>
<li>Não realizamos <strong>trocas</strong> de itens da loja. Pense bem antes de comprar.</li>
<li>Não realizamos <strong>descontos</strong> sob demanda. Promoções são gerais e anunciadas.</li>
<li>Se um item sumir do seu inventário, a staff <strong>não repõe</strong> — DayZ é DayZ. Cuidado onde guarda suas coisas.</li>
<li>Banido = não pode mais jogar. Banimento é irrevogável. Reembolso só nos casos previstos na <a href="/page/refund">Política de Reembolso</a>.</li>
<li>A staff <strong>nunca</strong> faz transferência de bens entre players ou interfere em gameplay. Se alguém da staff se ofereceu a fazer isso, denuncie aos donos do servidor imediatamente.</li>
</ul>

<h3>8. Wipes (Reset Periódico)</h3>
<ul>
<li>O servidor pode ter wipes programados. A data será anunciada no <a href="https://discord.gg/SEU-CONVITE">Discord oficial</a> e no banner do site.</li>
<li>No wipe, sua progressão (base, inventário) é perdida — como no DayZ vanilla. Suas <strong>moedas compradas na loja são preservadas</strong>.</li>
<li>Moedas obtidas via loja in-game (Trader) podem ser wipadas — verifique antes.</li>
<li>Wipe forçado (sem aviso, por falha técnica nossa) → você tem direito a reembolso ou reposição.</li>
<li>Wipe causado pela Bohemia (patch da dona do jogo) → reposição via loja in-game; sem reembolso por motivo externo a nós.</li>
<li>A equipe é obrigada a comunicar wipes com antecedência. Se você não foi avisado, exija seus direitos.</li>
</ul>

<h3>9. Penalidades</h3>
<p>As penalidades variam conforme gravidade e reincidência:</p>
<ul>
<li><strong>Advertência</strong> verbal/escrita — primeira infração leve.</li>
<li><strong>Kick</strong> — desconexão forçada do servidor/Discord, sem ban.</li>
<li><strong>Ban temporário</strong> — 24h, 7 dias ou 30 dias.</li>
<li><strong>Ban permanente</strong> — sem retorno. Apenas em casos graves ou reincidência.</li>
</ul>
<p>A staff registra todas as ações em audit log. Decisões podem ser questionadas via ticket — não em chat público.</p>

<h3>10. Aceite e Atualizações</h3>
<p>Ao conectar no servidor ou entrar no Discord, você aceita estas regras integralmente. Atualizações desta página serão anunciadas com até 7 dias de antecedência, exceto em casos urgentes (segurança, exploit grave).</p>

<p style="font-size:0.85rem;color:#8aa0b5;margin-top:1.5rem;">
<strong>Responsável legal:</strong> [RAZAO SOCIAL DA SUA EMPRESA], CNPJ [00.000.000/0000-00]. <strong>[NOME DO SERVIDOR]</strong> é marca registrada.<br>
Dúvidas: <a href="https://discord.gg/SEU-CONVITE">Discord oficial [NOME DO SERVIDOR]</a>.
</p>
', '<h2>Server Rules</h2>
<p class=''legal-meta''>Last update: 2026-06-04 - Applies to all players on the [NOME DO SERVIDOR] server and the official Discord community.</p>
<div class=''legal-callout''>
<p><strong>Important:</strong> by connecting to the server or joining Discord, you agree to ALL rules below. Ignorance is no excuse - read until the end.</p>
</div>
<h3>1. General Rules</h3>
<ul>
<li>Duplicating, hacking, exploiting glitches, or cheating results in <strong>permanent ban</strong> - you and your entire group.</li>
<li>Alt accounts <strong>are not allowed</strong>.</li>
<li>Avoiding bans (combat log, alt account) results in permanent ban.</li>
<li>No refund for equipment lost to bugs without video evidence.</li>
<li>Vehicles at your own risk. We do not refund - this is DayZ.</li>
<li>The team does not tolerate disrespect. If you suffer from it, report via ticket on Discord.</li>
<li>If any staff member offends or disrespects you, report immediately. You have rights as consumer and citizen.</li>
</ul>
<h3>2. Communication (Chat & Voice)</h3>
<ul>
<li>Chat and voice preferably in <strong>Brazilian Portuguese</strong> (BR server).</li>
<li>No excessive CAPS LOCK, message spam, or emoji flooding.</li>
<li>No hate speech, racism, xenophobia, homophobia, transphobia, ableism, misogyny, or any discrimination. Permanent ban.</li>
<li>Stream-sniping (pursuing player via livestream) is forbidden. Ban.</li>
<li>Doxxing (disclosing another player''s real personal data) is a crime - we report to police.</li>
</ul>
<h3>3. Base Building</h3>
<ul>
<li>To build a base you must first place a territory marker with flag.</li>
<li>Each group may have at most <strong>2 markers</strong> (with or without flag). Any extra marker will be deleted.</li>
<li>Bases may be built in any region not marked red on the map.</li>
<li>You are free to build however you want, but remember: RAIDs are allowed on weekends.</li>
<li>Bases without flag (or with expired flag) can be raided <strong>at any time</strong>.</li>
</ul>
<h3>4. Raid Rules</h3>
<ul>
<li>Clans must be wearing the <strong>clan TAG</strong>. Every member must wear the TAG, no exceptions.</li>
<li>Only clans with TAGs can RAID. Players without TAG <strong>cannot raid</strong>.</li>
<li>It is <strong>forbidden</strong> to raid structures other than gates/windows/chests/cabinets/mannequins. Detected in logs -> ban.</li>
<li>RAID allowed on: gates, windows, chests, cabinets, mannequins, and similar items. Any tool works (explosives, grenade launchers, gas grenades, etc).</li>
<li><strong>RAID FORBIDDEN on weekdays.</strong> No exception.</li>
<li>RAID schedule:
  <ul>
    <li><strong>Saturday:</strong> 16:00 - 23:59</li>
    <li><strong>Sunday:</strong> 14:00 - 20:00</li>
  </ul>
</li>
<li>Got raided outside hours? Report - we investigate. Got raided in hours because you didn''t log in to defend? Tough, this is DayZ.</li>
<li>Breaking RAID rules -> individual or whole-clan ban depending on involvement.</li>
<li>Obstructing passage on RAID days (traps, cars, tactical barricades) is allowed.</li>
<li>Damaging structures/protections outside RAID hours -> ban.</li>
<li>Already-raided open bases can be looted without restriction.</li>
<li>Bases without flag -> raidable anytime. Low flag does NOT count as no flag.</li>
<li>Anti-logout: you cannot disconnect instantly in combat. System auto-punishes.</li>
<li>Alliances between clans are allowed. Form yours.</li>
<li>Staff <strong>does not interfere</strong> in clan wars. We''re here for support, not to play for you.</li>
</ul>
<h3>5. Anti-Cheat and Technical Limits</h3>
<ul>
<li>Forbidden: NVIDIA graphic debugger (Nvidia Inspector) or similar to nullify vegetation, alter graphics for visual advantage. Ban.</li>
<li>Using scripts/macros to remove grass, fog, shadows, etc -> ban.</li>
<li>Map bugs: if it''s ''exploit'' (going through walls, invulnerability) -> ban. If it''s ''feature'' (climbing a pixel that can be climbed, shortcut that doesn''t break the map) -> allowed.</li>
<li>If we detect cheat usage, the whole clan can be banned, and info shared with other BR servers.</li>
<li>You may be asked to install an <strong>anti-cheat validator</strong> under team supervision if reported. Refusing = ban.</li>
</ul>
<h3>6. Discord (Community)</h3>
<ul>
<li>Minimum age: <strong>13 years</strong> (Discord TOS). Under 18 requires guardian consent.</li>
<li>No channel spam, DM ads (private messages with ads), or promotion of other DayZ servers.</li>
<li>NSFW only in marked channels (if any). Elsewhere = ban.</li>
<li>Use channels for proper purpose: tickets at <code>#support</code>, reports at <code>#report</code>, questions at <code>#general</code>.</li>
<li>Respect moderation work. Discussion with mod via private ticket, not in public.</li>
</ul>
<h3>7. Shop and Coins (Terms of Acceptance)</h3>
<ul>
<li>By purchasing coins at the <a href=''/shop''>Shop</a>, you agree with <a href=''/page/terms''>Terms of Use</a> and <a href=''/page/refund''>Refund Policy</a>.</li>
<li>Purchased coins can only be used within the server - no refund after partial or total use.</li>
<li>We do not <strong>exchange</strong> shop items. Think carefully before buying.</li>
<li>We do not give <strong>discounts</strong> on demand. Promotions are general and announced.</li>
<li>If an item vanishes from your inventory, staff <strong>does not replace</strong> - DayZ is DayZ. Be careful where you store things.</li>
<li>Banned = cannot play anymore. Bans are irrevocable. Refund only in cases provided in <a href=''/page/refund''>Refund Policy</a>.</li>
<li>Staff <strong>never</strong> transfers goods between players or interferes in gameplay. If any staff member offered to do so, report to server owners immediately.</li>
</ul>
<h3>8. Wipes (Periodic Reset)</h3>
<ul>
<li>The server may have scheduled wipes. The date will be announced on the <a href=''https://discord.gg/SEU-CONVITE''>official Discord</a> and on the site banner.</li>
<li>On wipe, your progression (base, inventory) is lost - like vanilla DayZ. Your <strong>coins purchased in the shop are preserved</strong>.</li>
<li>Coins from in-game shop (Trader) may be wiped - check before.</li>
<li>Forced wipe (no notice, due to our technical failure) -> you have right to refund or replacement.</li>
<li>Wipe caused by Bohemia (game patch) -> replacement via in-game shop; no refund for external reason.</li>
<li>The team must announce wipes in advance. If you weren''t notified, claim your rights.</li>
</ul>
<h3>9. Penalties</h3>
<p>Penalties vary by severity and recurrence:</p>
<ul>
<li><strong>Warning</strong> verbal/written - first minor infraction.</li>
<li><strong>Kick</strong> - forced disconnection from server/Discord, no ban.</li>
<li><strong>Temporary ban</strong> - 24h, 7 days, or 30 days.</li>
<li><strong>Permanent ban</strong> - no return. Only in serious or repeated cases.</li>
</ul>
<p>Staff logs all actions in audit log. Decisions may be questioned via ticket - not in public chat.</p>
<h3>10. Acceptance and Updates</h3>
<p>By connecting to the server or joining Discord, you accept these rules in full. Updates to this page will be announced with up to 7 days advance notice, except in urgent cases (security, serious exploit).</p>
<p style=''font-size:0.85rem;color:#8aa0b5;margin-top:1.5rem;''>
<strong>Legal entity:</strong> [RAZAO SOCIAL DA SUA EMPRESA], CNPJ [00.000.000/0000-00]. <strong>[NOME DO SERVIDOR]</strong> is a registered trademark.<br>
Questions: <a href=''https://discord.gg/SEU-CONVITE''>[NOME DO SERVIDOR] official Discord</a>.
</p>', 1, 1)
ON DUPLICATE KEY UPDATE
  title_ptbr = IF(title_ptbr IS NULL OR title_ptbr = '', VALUES(title_ptbr), title_ptbr),
  body_ptbr  = IF(body_ptbr  IS NULL OR body_ptbr  = '', VALUES(body_ptbr),  body_ptbr),
  body_enus  = IF(body_enus  IS NULL OR body_enus  = '', VALUES(body_enus),  body_enus);

-- terms
INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
VALUES ('terms', 'Termos de Uso', 'Terms of Use', '<h2>Termos de Uso</h2>

<p class="legal-meta">Última atualização: 2026-05-20</p>
<h3>1. Aceitação</h3>
<p>Ao acessar e utilizar a <strong>[NOME DO SERVIDOR] Store</strong> (este site, doravante "Plataforma"), você concorda integralmente com estes Termos de Uso, com a Política de Reembolso e com a Política de Privacidade. Se não concordar, NÃO utilize a Plataforma.</p>
<h3>2. Definições</h3>
<ul>
<li><strong>Plataforma:</strong> o site <code>seuservidor.com</code> e seus serviços associados.</li>
<li><strong>Servidor:</strong> servidor de jogo DayZ administrado pela [NOME DO SERVIDOR] Store.</li>
<li><strong>Moedas:</strong> bens virtuais consumíveis utilizáveis exclusivamente dentro do Servidor, sem valor econômico fora dele.</li>
<li><strong>Jogador:</strong> pessoa física maior de 18 anos (ou autorizado pelos responsáveis) que adquire e/ou utiliza Moedas.</li>
<li><strong>SteamID:</strong> identificador único da plataforma Steam usado para vincular a conta do Jogador.</li>
</ul>
<h3>3. Cadastro e Conta Steam</h3>
<p>Para utilizar os serviços, o Jogador deve possuir uma conta Steam ativa. A Plataforma utiliza o <strong>SteamID público</strong> (não a senha) para vincular as compras. O Jogador é responsável pela segurança da própria conta Steam.</p>
<h3>4. Aquisição de Moedas</h3>
<ul>
<li>Os pagamentos são processados pelo <strong>Mercado Pago</strong> (PIX, cartão de crédito, boleto), instituição de pagamento autorizada pelo Banco Central.</li>
<li>O crédito das Moedas na conta do Jogador é <strong>automático</strong> após confirmação do pagamento pelo Mercado Pago (em geral, instantâneo no PIX e em até 48h para boleto).</li>
<li>Os preços são fixados em <strong>Reais (BRL)</strong> e podem ser alterados sem aviso prévio; o preço aplicável é o exibido no momento da compra.</li>
<li>A Plataforma poderá oferecer pacotes promocionais com <strong>bônus de Moedas</strong>, sujeitos a disponibilidade e ao período da promoção.</li>
</ul>
<h3>5. Uso das Moedas</h3>
<ul>
<li>As Moedas são vinculadas <strong>exclusivamente</strong> ao SteamID do Jogador e somente podem ser utilizadas dentro do Servidor.</li>
<li>As Moedas <strong>não possuem valor monetário</strong>, não podem ser convertidas em dinheiro, transferidas para outro jogador ou trocadas por outros bens.</li>
<li>A Plataforma pode, a qualquer tempo, alterar a economia interna do jogo (preços de itens in-game, mecânicas, etc).</li>
</ul>
<h3>6. Conduta do Jogador</h3>
<p>É vedado ao Jogador:</p>
<ul>
<li>Utilizar <strong>cheats, hacks, exploits</strong> ou qualquer software/método que dê vantagem indevida no jogo.</li>
<li>Praticar comportamento <strong>tóxico, racista, discriminatório</strong> ou que constitua ilícito (assédio, ameaças, etc).</li>
<li>Tentar <strong>fraudar</strong> o sistema de pagamento (chargeback abusivo, cartões clonados, etc).</li>
<li>Realizar <strong>engenharia reversa</strong>, automatização ou exploração de bugs na Plataforma.</li>
<li>Revender Moedas, contas ou itens do servidor para terceiros.</li>
</ul>
<div class="legal-callout">
<p><strong>Banimento:</strong> infringir qualquer das proibições acima implica em banimento (temporário ou permanente, a critério da administração) <strong>sem direito a reembolso</strong> das Moedas não utilizadas ou em uso.</p>
</div>
<h3>7. Disponibilidade</h3>
<p>A Plataforma e o Servidor são oferecidos no regime de <strong>melhor esforço</strong>. Não garantimos disponibilidade ininterrupta. Manutenções programadas ou emergenciais podem suspender o serviço temporariamente, sem ensejar reembolso, salvo nos casos previstos na Política de Reembolso.</p>
<h3>8. Modificações dos Termos</h3>
<p>Estes Termos podem ser atualizados a qualquer momento. A versão vigente será sempre a publicada nesta página. Mudanças relevantes serão comunicadas via Discord oficial e/ou banner no site.</p>
<h3>9. Limitação de Responsabilidade</h3>
<p>A Plataforma não responde por:</p>
<ul>
<li>Perda de Moedas decorrente de comprometimento da conta Steam do Jogador (responsabilidade do Jogador).</li>
<li>Indisponibilidade do Mercado Pago, Steam ou serviços de terceiros.</li>
<li>Atos de outros jogadores no Servidor.</li>
<li>Danos indiretos, lucros cessantes ou consequências meramente psicológicas decorrentes do uso da Plataforma.</li>
</ul>
<h3>10. Foro</h3>
<p>Estes Termos são regidos pelas leis da República Federativa do Brasil. Fica eleito o foro do domicílio do consumidor, em conformidade com o art. 101, inciso I, do Código de Defesa do Consumidor.</p>
<p style="font-size:0.85rem;color:#8aa0b5;margin-top:0.8rem;">
<strong>Responsável legal:</strong> [RAZAO SOCIAL DA SUA EMPRESA], CNPJ [00.000.000/0000-00]. Marca <strong>[NOME DO SERVIDOR]</strong> e domínio <code>seuservidor.com</code> são propriedade desta empresa.
</p>
</section>', '<h2>Terms of Use</h2>
<p class=''legal-meta''>Last update: 2026-05-20</p>
<h3>1. Acceptance</h3>
<p>By accessing and using <strong>[NOME DO SERVIDOR] Store</strong> (this website, hereinafter ''Platform''), you fully agree to these Terms of Use, the Refund Policy, and the Privacy Policy. If you do not agree, DO NOT use the Platform.</p>
<h3>2. Definitions</h3>
<ul>
<li><strong>Platform:</strong> the website <code>seuservidor.com</code> and its associated services.</li>
<li><strong>Server:</strong> the DayZ game server administered by [NOME DO SERVIDOR] Store.</li>
<li><strong>Coins:</strong> consumable virtual goods usable exclusively inside the Server, with no economic value outside it.</li>
<li><strong>Player:</strong> a natural person aged 18+ (or authorized by guardians) who acquires and/or uses Coins.</li>
<li><strong>SteamID:</strong> unique Steam platform identifier used to link the Player account.</li>
</ul>
<h3>3. Registration and Steam Account</h3>
<p>To use the services, the Player must have an active Steam account. The Platform uses the <strong>public SteamID</strong> (not the password) to link purchases. The Player is responsible for the security of their own Steam account.</p>
<h3>4. Coin Acquisition</h3>
<ul>
<li>Payments are processed by <strong>Mercado Pago</strong> (PIX, credit card, boleto), a payment institution authorized by the Brazilian Central Bank.</li>
<li>Coin credit to the Player account is <strong>automatic</strong> after payment confirmation by Mercado Pago (generally instant for PIX and up to 48h for boleto).</li>
<li>Prices are fixed in <strong>Brazilian Reais (BRL)</strong> and may be changed without prior notice; the applicable price is the one shown at the time of purchase.</li>
<li>The Platform may offer promotional packages with <strong>bonus Coins</strong>, subject to availability and promotion period.</li>
</ul>
<h3>5. Use of Coins</h3>
<ul>
<li>Coins are linked <strong>exclusively</strong> to the Player''s SteamID and may only be used inside the Server.</li>
<li>Coins <strong>have no monetary value</strong>, cannot be converted to money, transferred to another player, or exchanged for other goods.</li>
<li>The Platform may, at any time, change the in-game economy (item prices, mechanics, etc).</li>
</ul>
<h3>6. Player Conduct</h3>
<p>The Player is forbidden from:</p>
<ul>
<li>Using <strong>cheats, hacks, exploits</strong>, or any software/method that gives undue advantage in the game.</li>
<li>Engaging in <strong>toxic, racist, discriminatory</strong> behavior or any illegal conduct (harassment, threats, etc).</li>
<li>Attempting to <strong>defraud</strong> the payment system (abusive chargebacks, cloned cards, etc).</li>
<li>Performing <strong>reverse engineering</strong>, automation, or bug exploitation on the Platform.</li>
<li>Reselling Coins, accounts, or server items to third parties.</li>
</ul>
<div class=''legal-callout''>
<p><strong>Ban:</strong> infringing any of the above prohibitions results in a ban (temporary or permanent, at administration discretion) <strong>without right to refund</strong> of unused or in-use Coins.</p>
</div>
<h3>7. Availability</h3>
<p>The Platform and the Server are offered on a <strong>best-effort</strong> basis. We do not guarantee uninterrupted availability. Scheduled or emergency maintenance may temporarily suspend the service, without giving rise to refund, except in cases provided in the Refund Policy.</p>
<h3>8. Modifications to Terms</h3>
<p>These Terms may be updated at any time. The version in effect will always be the one published on this page. Relevant changes will be communicated via official Discord and/or banner on the site.</p>
<h3>9. Liability Limitation</h3>
<p>The Platform is not liable for:</p>
<ul>
<li>Loss of Coins resulting from compromise of the Player''s Steam account (Player''s responsibility).</li>
<li>Unavailability of Mercado Pago, Steam, or third-party services.</li>
<li>Actions of other players on the Server.</li>
<li>Indirect damages, loss of profits, or purely psychological consequences arising from use of the Platform.</li>
</ul>
<h3>10. Jurisdiction</h3>
<p>These Terms are governed by the laws of the Federative Republic of Brazil. The forum of the consumer''s domicile is elected, in accordance with art. 101, item I, of the Brazilian Consumer Defense Code.</p>
<p style=''font-size:0.85rem;color:#8aa0b5;margin-top:0.8rem;''>
<strong>Legal entity:</strong> [RAZAO SOCIAL DA SUA EMPRESA], CNPJ [00.000.000/0000-00]. <strong>[NOME DO SERVIDOR]</strong> brand and domain <code>seuservidor.com</code> are property of this company.
</p>', 1, 2)
ON DUPLICATE KEY UPDATE
  title_ptbr = IF(title_ptbr IS NULL OR title_ptbr = '', VALUES(title_ptbr), title_ptbr),
  body_ptbr  = IF(body_ptbr  IS NULL OR body_ptbr  = '', VALUES(body_ptbr),  body_ptbr),
  body_enus  = IF(body_enus  IS NULL OR body_enus  = '', VALUES(body_enus),  body_enus);

-- privacy
INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
VALUES ('privacy', 'Política de Privacidade (LGPD)', 'Privacy Policy (LGPD)', '<h2>Política de Privacidade (LGPD)</h2>

<p class="legal-meta">Em conformidade com a Lei 13.709/18 (LGPD) · Última atualização: 2026-05-20</p>
<h3>1. Quem somos (Controlador dos Dados — LGPD art. 5º, VI)</h3>
<p>A <strong>[NOME DO SERVIDOR] Store</strong> é uma loja virtual de bens digitais para o servidor DayZ [NOME DO SERVIDOR], operada pela empresa:</p>
<ul>
<li><strong>Razão Social:</strong> [SUA EMPRESA]</li>
<li><strong>CNPJ:</strong> [SEU CNPJ]</li>
<li><strong>Marca:</strong> [NOME DO SERVIDOR]</li>
<li><strong>Domínio:</strong> <code>seuservidor.com</code></li>
<li><strong>País de operação:</strong> Brasil</li>
</ul>
<p>A [SUA EMPRESA] é a <strong>controladora dos dados pessoais</strong> tratados nesta Plataforma, conforme definição da LGPD (Lei 13.709/18). Toda comunicação relativa a esta política deve ser endereçada à empresa identificada acima.</p>
<h3>2. Dados que coletamos</h3>
<p>Para operar a Plataforma, coletamos e tratamos os seguintes dados:</p>
<ul>
<li><strong>SteamID64</strong> (público) — vincula a compra ao Jogador.</li>
<li><strong>Nome de exibição e avatar</strong> da Steam — exibidos no carrinho/perfil (dados públicos da Valve).</li>
<li><strong>Endereço IP</strong> de acesso — registrado em logs por 12 meses (Marco Civil, art. 15).</li>
<li><strong>Identificadores de transação</strong> do Mercado Pago — payment_id, valor, status, método de pagamento. <strong>Não armazenamos número de cartão.</strong></li>
<li><strong>Histórico de Moedas</strong> — saldo, créditos, débitos, com timestamps.</li>
<li><strong>Logs técnicos</strong> — sucessos e falhas de acesso à API, para fins de segurança.</li>
</ul>
<h3>3. Finalidade do tratamento (LGPD art. 7)</h3>
<ul>
<li><strong>Execução de contrato</strong> (art. 7, V): entregar as Moedas compradas e manter o saldo.</li>
<li><strong>Cumprimento de obrigação legal</strong> (art. 7, II): emissão de notas fiscais (quando aplicável) e retenção de logs.</li>
<li><strong>Legítimo interesse</strong> (art. 7, IX): prevenção de fraude, segurança da plataforma.</li>
<li><strong>Consentimento</strong> (art. 7, I): qualquer outro uso será precedido de consentimento explícito.</li>
</ul>
<h3>4. Com quem compartilhamos</h3>
<ul>
<li><strong>Mercado Pago</strong> — processador de pagamentos. Compartilha conosco status do pagamento. Não temos acesso ao seu cartão.</li>
<li><strong>Valve / Steam</strong> — utilizamos a API pública do Steam para buscar nome de exibição e avatar. Não enviamos dados pessoais à Valve.</li>
<li><strong>Hostinger</strong> — provedor de hospedagem do site, com sede e DPO próprios em conformidade com LGPD.</li>
<li><strong>Não vendemos, alugamos ou compartilhamos seus dados com terceiros para fins de marketing.</strong></li>
</ul>
<h3>5. Seus direitos (LGPD art. 18)</h3>
<p>Você pode, a qualquer momento, exercer os direitos:</p>
<ul>
<li><strong>Confirmação</strong> da existência de tratamento;</li>
<li><strong>Acesso</strong> aos seus dados;</li>
<li><strong>Correção</strong> de dados incompletos, inexatos ou desatualizados;</li>
<li><strong>Anonimização, bloqueio ou eliminação</strong> de dados desnecessários ou excessivos;</li>
<li><strong>Portabilidade</strong> dos dados;</li>
<li><strong>Eliminação</strong> dos dados tratados com base no seu consentimento;</li>
<li><strong>Revogação do consentimento</strong> a qualquer momento.</li>
</ul>
<p>Para exercer qualquer direito, abra um ticket no <strong>Discord oficial</strong> identificando seu SteamID64. Respondemos em até <strong>15 dias</strong> (LGPD art. 19, §1º, II).</p>
<div class="legal-callout warn">
<p>A eliminação de dados pode ser limitada pelo cumprimento de obrigações legais (ex: histórico financeiro retido por 5 anos por exigência fiscal e do Marco Civil).</p>
</div>
<h3>6. Retenção de dados</h3>
<ul>
<li><strong>Logs de acesso</strong>: 12 meses (Marco Civil art. 15).</li>
<li><strong>Logs de moedas (coin_history)</strong>: 365 dias corridos.</li>
<li><strong>Histórico de pagamentos</strong>: 5 anos (obrigação fiscal).</li>
<li><strong>Saldo de Moedas e SteamID</strong>: enquanto a conta estiver ativa, com prazo de 24 meses sem login para inativação automática.</li>
</ul>
<h3>7. Segurança</h3>
<p>Implementamos medidas técnicas e organizacionais razoáveis para proteção dos dados:</p>
<ul>
<li>Conexão criptografada (HTTPS/TLS) obrigatória;</li>
<li>Senhas administrativas protegidas com hash (HMAC-SHA256 para tokens);</li>
<li>Rate-limiting contra força bruta;</li>
<li>Logs auditáveis de acesso e operações destrutivas;</li>
<li>Validação de assinatura HMAC do Mercado Pago para evitar webhooks forjados.</li>
</ul>
<h3>8. Cookies</h3>
<p>A Plataforma utiliza armazenamento local do navegador (<code>localStorage</code>, <code>sessionStorage</code>) para manter sua sessão Steam ativa durante a navegação. Não utilizamos cookies de rastreamento de terceiros, anúncios ou analytics externos.</p>
<h3>9. Menores de idade</h3>
<p>A Plataforma é destinada a maiores de 18 anos. Menores de idade só podem utilizar com assistência expressa dos responsáveis legais, que assumem responsabilidade pelos pagamentos efetuados.</p>
<h3>10. Atualizações</h3>
<p>Esta Política pode ser atualizada. Mudanças relevantes serão sinalizadas no site e/ou via Discord oficial. Recomendamos revisão periódica.</p>
<h3>11. Contato</h3>
<p>Para dúvidas, solicitações ou denúncias relativas a esta política, abra um ticket no Discord oficial linkado no rodapé.</p>
</section>', '<h2>Privacy Policy (Brazilian LGPD)</h2>
<h3>1. Data Controller</h3>
<p>[NOME DO SERVIDOR] Store, operated in partnership with [SUA EMPRESA] (CNPJ [SEU CNPJ]). Contact: <a href=''https://discord.gg/SEU-CONVITE''>official Discord</a>.</p>
<h3>2. Data we collect</h3>
<p>Public Steam ID, Steam name, coin balance, purchase history, last connection IP. We do NOT collect CPF, address, or phone.</p>
<h3>3. Processing purpose (LGPD art. 7)</h3>
<p>Operate the service: identify player, credit purchases, fraud prevention.</p>
<h3>4. With whom we share</h3>
<p>Mercado Pago (payment processing). Hostinger (infrastructure). Steam (OpenID identification). No data sold to third parties.</p>
<h3>5. Your rights (LGPD art. 18)</h3>
<p>Access, correction, deletion, portability, consent revocation. Request via Discord.</p>
<h3>6. Data retention</h3>
<p>Purchase data kept for 5 years (fiscal obligation). Player data kept while account is active + 1 year after inactivity.</p>
<h3>7. Security</h3>
<p>HTTPS on all pages. Passwords with bcrypt hash. Restricted administrative access.</p>
<h3>8. Cookies</h3>
<p>Only essential: login session and language. No third-party tracking.</p>
<h3>9. Minors</h3>
<p>Service not intended for users under 13. Minors under 18 require parental consent.</p>
<h3>10. Updates</h3>
<p>This policy may change. We will notify on site/Discord 30 days in advance.</p>
<h3>11. Contact</h3>
<p>For LGPD matters: official [NOME DO SERVIDOR] Discord.</p>', 1, 3)
ON DUPLICATE KEY UPDATE
  title_ptbr = IF(title_ptbr IS NULL OR title_ptbr = '', VALUES(title_ptbr), title_ptbr),
  body_ptbr  = IF(body_ptbr  IS NULL OR body_ptbr  = '', VALUES(body_ptbr),  body_ptbr),
  body_enus  = IF(body_enus  IS NULL OR body_enus  = '', VALUES(body_enus),  body_enus);

-- refund
INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
VALUES ('refund', 'Política de Reembolso', 'Refund Policy', '<h2>Política de Reembolso</h2>

<p class="legal-meta">Última atualização: 2026-05-20 · Baseada no CDC (Lei 8.078/90), arts. 49 e 18</p>
<div class="legal-callout info">
<p><strong>Direito de Arrependimento (CDC art. 49):</strong> compras feitas pela internet podem ser canceladas em até <strong>7 (sete) dias corridos</strong> a partir da confirmação do pagamento, <strong>desde que o produto digital ainda não tenha sido consumido</strong>.</p>
</div>
<h3>1. Quando o reembolso É concedido</h3>
<ul>
<li><strong>Pagamento duplicado</strong>: o sistema cobrou duas vezes a mesma compra. Reembolso integral da segunda cobrança.</li>
<li><strong>Falha técnica no crédito</strong>: pagamento aprovado mas as Moedas não foram entregues e o suporte não conseguiu solucionar em até 7 dias.</li>
<li><strong>Arrependimento em até 7 dias</strong>: o Jogador pediu reembolso em até 7 dias da compra E as Moedas adquiridas estão <strong>integralmente intactas</strong> (saldo atual ≥ Moedas compradas).</li>
<li><strong>Cobrança não reconhecida</strong> (suspeita de fraude no cartão): o caso é investigado e, se confirmada a fraude, reembolso integral mais bloqueio da conta envolvida.</li>
</ul>
<h3>2. Quando o reembolso NÃO é concedido</h3>
<ul>
<li><strong>Moedas já gastas</strong> (parcial ou totalmente) dentro do Servidor. Conforme entendimento consolidado para bens digitais consumíveis, uma vez utilizada, a Moeda é considerada produto entregue e consumido.</li>
<li><strong>Banimento por trapaça, hack ou comportamento tóxico</strong>: ao infringir o item 6 dos Termos de Uso, o Jogador perde direito a reembolso das Moedas não utilizadas (item 6).</li>
<li><strong>Solicitação após 7 (sete) dias</strong> da confirmação do pagamento.</li>
<li><strong>Comprovada má-fé</strong>: padrão de uso intensivo seguido de pedido de reembolso (ex: comprar, usar 80% do pacote em 1 hora, pedir devolução). A análise de logs comprova o consumo.</li>
<li><strong>Chargeback indevido</strong>: contestação no cartão depois de a Moeda ter sido entregue e consumida configura tentativa de fraude e enseja banimento permanente sem direito a recompra.</li>
</ul>
<div class="legal-callout warn">
<p><strong>Análise de boa-fé:</strong> a Plataforma mantém histórico detalhado de todas as transações, créditos e gastos no Servidor (logs imutáveis com timestamp). Solicitações são avaliadas com base nesses registros.</p>
</div>
<h3>3. Como solicitar reembolso</h3>
<ol>
<li>Abra um <strong>ticket no nosso Discord</strong> (link no rodapé do site).</li>
<li>Informe:
<ul>
<li>Seu <strong>SteamID64</strong> (17 dígitos).</li>
<li>O <strong>Payment ID</strong> do Mercado Pago (recebido no e-mail de confirmação).</li>
<li><strong>Motivo</strong> detalhado do pedido.</li>
<li>Comprovantes adicionais (printscreen, conversa) se aplicável.</li>
</ul>
</li>
<li>A equipe analisa em até <strong>5 (cinco) dias úteis</strong>.</li>
<li>Se aprovado, o estorno é processado via Mercado Pago em até <strong>14 dias úteis</strong> (prazo bancário, fora do nosso controle).</li>
<li>Caso a compra envolva pacote com bônus, o reembolso considera o valor pago — não o valor comercial das Moedas bônus.</li>
</ol>
<h3>4. Estorno parcial</h3>
<p>Não realizamos estorno parcial. Ou a totalidade do pedido se enquadra e é devolvida, ou o pedido é negado integralmente.</p>
<h3>5. Reincidência</h3>
<p>Jogadores que solicitarem reembolso de boa-fé por motivos diversos serão analisados. Padrão repetido (3+ pedidos em 30 dias) pode resultar em bloqueio de novas compras com aquele SteamID/cartão por até 90 dias.</p>
</section>', '<h2>Refund Policy</h2>
<h3>1. When refund IS granted</h3>
<p>Technical failure on our end (system down, coins not credited after 24h). Request via Discord with proof.</p>
<h3>2. When refund is NOT granted</h3>
<p>Coins already spent in-game. Ban for misconduct. Claim after 7 days of purchase. Purchase above R$200 with more than 30 days.</p>
<h3>3. How to request a refund</h3>
<p>Open a ticket on our official Discord: <a href=''https://discord.gg/SEU-CONVITE''>discord.gg/SEU-CONVITE</a>. Include: Steam ID, payment proof (Mercado Pago), and reason.</p>
<h3>4. Partial refund</h3>
<p>Evaluated case by case. If you used part of the coins, refund will be proportional to the unused balance.</p>
<h3>5. Recurrence</h3>
<p>Repeated refund requests without valid justification may result in account ban.</p>', 1, 4)
ON DUPLICATE KEY UPDATE
  title_ptbr = IF(title_ptbr IS NULL OR title_ptbr = '', VALUES(title_ptbr), title_ptbr),
  body_ptbr  = IF(body_ptbr  IS NULL OR body_ptbr  = '', VALUES(body_ptbr),  body_ptbr),
  body_enus  = IF(body_enus  IS NULL OR body_enus  = '', VALUES(body_enus),  body_enus);

-- faq
INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
VALUES ('faq', 'Perguntas Frequentes', 'Frequently Asked Questions', '<h2>Perguntas Frequentes</h2>

<p class="legal-meta">Dúvidas comuns sobre compras, moedas e suporte</p>
<details class="faq-item">
<summary class="faq-q">Como compro Moedas?</summary>
<div class="faq-a">
<ol>
<li>Clique em "Entrar com Steam" no topo do site e informe seu SteamID64.</li>
<li>Escolha o pacote desejado e clique em "Comprar Agora".</li>
<li>Você será redirecionado ao Mercado Pago. Pague com PIX, cartão ou boleto.</li>
<li>Após confirmação do pagamento, as Moedas são creditadas <strong>automaticamente</strong>.</li>
</ol>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Quanto tempo demora pra Moeda chegar?</summary>
<div class="faq-a">
<ul>
<li><strong>PIX:</strong> instantâneo após aprovação (geralmente &lt; 30 segundos).</li>
<li><strong>Cartão de crédito:</strong> instantâneo se aprovado, ou até 24h se for análise antifraude do MP.</li>
<li><strong>Boleto:</strong> de 1 a 3 dias úteis após o pagamento no banco.</li>
</ul>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Paguei mas não recebi as Moedas. E agora?</summary>
<div class="faq-a">
<p>Espere até 10 minutos (pode ser análise antifraude do MP). Se não chegar:</p>
<ol>
<li>Verifique no seu e-mail do Mercado Pago se o pagamento foi aprovado.</li>
<li>Abra um ticket no Discord com seu SteamID64 e o Payment ID do MP.</li>
<li>Resolvemos em até 24h. Pagamentos confirmados sempre viram Moedas.</li>
</ol>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Posso transferir Moedas pra outro jogador?</summary>
<div class="faq-a">
<p><strong>Não.</strong> Moedas são vinculadas ao SteamID e não são transferíveis. Tentar burlar essa regra resulta em bloqueio da conta envolvida.</p>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Posso usar Moedas em outros servidores?</summary>
<div class="faq-a">
<p>Não. As Moedas têm valor exclusivamente dentro do servidor DayZ [NOME DO SERVIDOR].</p>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Sou menor de 18, posso comprar?</summary>
<div class="faq-a">
<p>A Plataforma é destinada a maiores de 18 anos. Menores devem ter assistência expressa dos responsáveis legais, que serão considerados financeiramente responsáveis. Em caso de compras feitas por menor sem autorização, o reembolso será processado mediante comprovação documental dos responsáveis.</p>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Quando posso pedir reembolso?</summary>
<div class="faq-a">
<p>Em até <strong>7 dias da compra</strong>, desde que as Moedas <strong>não tenham sido gastas</strong>. Para casos de falha técnica (pagamento aprovado mas não creditado), abra ticket que resolvemos. Para mais detalhes, veja a <a href="#reembolso">Política de Reembolso</a>.</p>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Fui banido. Posso pedir reembolso?</summary>
<div class="faq-a">
<p><strong>Não.</strong> Banimento por uso de cheats, hacks, exploits ou conduta tóxica resulta em perda de todas as Moedas não utilizadas, conforme item 6 dos <a href="#termos">Termos de Uso</a>. A revisão de banimento pode ser solicitada via Discord, mas o reembolso não está disponível.</p>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">O servidor caiu — perco minhas Moedas?</summary>
<div class="faq-a">
<p>Não. As Moedas ficam armazenadas no banco de dados do site (espelhado no servidor de jogo). Quando o servidor voltar, seu saldo continua intacto. Manutenções programadas são anunciadas no Discord.</p>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Perdi acesso à minha conta Steam. E as Moedas?</summary>
<div class="faq-a">
<p>As Moedas são vinculadas ao SteamID. Se você perder acesso à Steam, primeiro recupere com o suporte Valve. Após recuperar, suas Moedas continuam disponíveis. Não transferimos Moedas de um SteamID pra outro, mesmo que sejam do mesmo dono.</p>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Posso obter Moedas grátis (cupom, sorteio)?</summary>
<div class="faq-a">
<p>Eventualmente realizamos promoções com bônus de Moedas em pacotes ou cupons. Acompanhe o Discord oficial. <strong>Nunca</strong> entregamos Moedas via mensagem privada — qualquer "promoção" fora dos canais oficiais é golpe.</p>
</div>
</details>
<details class="faq-item">
<summary class="faq-q">Como falo com a equipe?</summary>
<div class="faq-a">
<p>Pelo <strong>Discord oficial</strong> linkado no rodapé. Atendemos o mais rápido possível (média de 2-12 horas, exceto madrugada e feriados).</p>
</div>
</details>', '<h2>FAQ - Frequently Asked Questions</h2>
<h3>How do I buy coins?</h3>
<p>Go to the <a href=''/shop''>Shop</a>, choose a package, login with Steam, and pay via PIX, boleto, or credit card.</p>
<h3>How do I connect to the DayZ server?</h3>
<p>IP: <code>[IP:PORTA do seu servidor]</code>. Add it directly in the DayZ client or via favorites.</p>
<h3>How long until I receive the coins?</h3>
<p>PIX/credit card: instant after confirmation (up to 2 min). Boleto: up to 3 business days.</p>
<h3>Can I play without buying coins?</h3>
<p>Yes. Coins are optional - they give access to items, kits, and extra benefits in the game.</p>
<h3>I forgot to log in with Steam, can I recover the purchase?</h3>
<p>Yes. Open a ticket on Discord with the Mercado Pago receipt and your Steam ID.</p>
<h3>Is the server under maintenance?</h3>
<p>Current status shown on the homepage. Advance notices on the official Discord.</p>
<h3>Can I receive bonus coins?</h3>
<p>Yes. Packages from Astuto+ deliver extra coins (+5/+10/+15/+25/+50). Loyalty bonus by volume spent (R$ 250 / 500 / 750 / 1000+).</p>
<h3>How do I report a bug or problem?</h3>
<p>Discord: <a href=''https://discord.gg/SEU-CONVITE''>discord.gg/SEU-CONVITE</a> - support channel.</p>', 1, 5)
ON DUPLICATE KEY UPDATE
  title_ptbr = IF(title_ptbr IS NULL OR title_ptbr = '', VALUES(title_ptbr), title_ptbr),
  body_ptbr  = IF(body_ptbr  IS NULL OR body_ptbr  = '', VALUES(body_ptbr),  body_ptbr),
  body_enus  = IF(body_enus  IS NULL OR body_enus  = '', VALUES(body_enus),  body_enus);

-- connect
INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
VALUES ('connect', 'Como Conectar', 'How to Connect', '<h2>Como Conectar no Servidor</h2>
<p class=''legal-meta''>Guia passo a passo pra entrar no servidor [NOME DO SERVIDOR] pela primeira vez.</p>

<h3>Requisitos</h3>
<ul>
<li><strong>DayZ</strong> instalado e atualizado (Steam, pago, ~30 GB)</li>
<li>Conta Steam ativa</li>
<li>Conexão estável (>10 Mbps download recomendado)</li>
<li>(Opcional) <strong>DZSA Launcher</strong> — facilita gerenciar mods. Download grátis: <a href=''https://dayzsalauncher.com/'' target=''_blank'' rel=''noopener''>dayzsalauncher.com</a></li>
</ul>

<h3>Método 1 - Via DZSA Launcher (recomendado)</h3>
<ol>
<li>Baixa e instala o <strong>DZSA Launcher</strong></li>
<li>Abre o launcher e clica em <strong>Servers</strong></li>
<li>Na barra de busca digita: <code>[NOME DO SERVIDOR]</code></li>
<li>Clica no servidor <strong>[BR] [NOME DO SERVIDOR] | PVP | RAID-FIND | VANILLA+</strong></li>
<li>Clica <strong>JOIN</strong> — o launcher baixa os mods automaticamente</li>
<li>Aguarda download dos mods (primeira vez pode demorar 5-30 min)</li>
<li>Quando entrar, faz login com Steam normalmente</li>
</ol>

<h3>Método 2 - Direto pelo cliente DayZ</h3>
<ol>
<li>Abre o DayZ pelo Steam</li>
<li>Menu principal → <strong>SERVIDORES</strong></li>
<li>Aba <strong>FAVORITOS</strong> ou <strong>COMUNIDADE</strong></li>
<li>Clica <strong>ADICIONAR SERVIDOR</strong> e cola: <code>[IP:PORTA do seu servidor]</code></li>
<li>Marca como favorito + clica <strong>JOGAR</strong></li>
<li>Se aparecer ''Bad Version'' = você precisa instalar os mods (use Método 1)</li>
</ol>

<h3>IP e porta do servidor</h3>
<div class=''legal-callout''>
<p><strong>IP:</strong> <code>[IP do seu servidor]</code><br>
<strong>Porta:</strong> <code>2302</code><br>
<strong>Mapa:</strong> Chernarus<br>
<strong>Slots:</strong> 60 jogadores</p>
</div>

<h3>BattleMetrics (status ao vivo)</h3>
<p>Acompanhe o status ao vivo do servidor, players online e histórico:<br>
<a href=''https://www.battlemetrics.com/servers/dayz/SEU-ID-BATTLEMETRICS'' target=''_blank'' rel=''noopener''>battlemetrics.com/servers/dayz/SEU-ID-BATTLEMETRICS</a></p>

<h3>Primeira vez? Veja o que fazer:</h3>
<ol>
<li>Conecte no servidor e ache um lugar seguro</li>
<li>Abre o site, vai na <a href=''/shop''>Loja</a> pra comprar suas primeiras moedas</li>
<li>Loga com Steam — moedas são vinculadas ao seu Steam ID</li>
<li>No jogo, vai num <strong>Trader</strong> (NPC vendedor) e troca moedas por itens/comida/armas</li>
<li>Lê as <a href=''/rules''>Regras do servidor</a> antes de raidar ou jogar PvP</li>
</ol>

<h3>Problemas comuns</h3>
<ul>
<li><strong>''Bad Version''</strong> ao conectar → falta instalar os mods. Use o DZSA Launcher (Método 1).</li>
<li><strong>''Wrong Signature''</strong> → mods desatualizados. Atualize via Steam Workshop.</li>
<li><strong>''Connection Failed''</strong> → servidor pode estar reiniciando. Espera 2-5 min e tenta de novo.</li>
<li><strong>''Kicked: BattlEye''</strong> → BattlEye corrompido. Botão direito no DayZ → Propriedades → Verificar Integridade.</li>
<li><strong>Outras dúvidas</strong> → entre no nosso <a href=''https://discord.gg/SEU-CONVITE'' target=''_blank'' rel=''noopener''>Discord oficial</a> e abra ticket.</li>
</ul>

<h3>Mods do servidor</h3>
<p>O servidor tem mods exclusivos da nossa comunidade + mods da comunidade DayZ. A lista completa atualizada está no <strong>Steam Workshop Collection</strong> linkada no DZSA Launcher quando você clica em JOIN — basta aceitar download.</p>

<h3>Discord oficial</h3>
<p>Pra avisos de wipe, eventos, suporte e comunidade:<br>
<a href=''https://discord.gg/SEU-CONVITE'' target=''_blank'' rel=''noopener''>discord.gg/SEU-CONVITE</a></p>
', '<h2>How to Connect to the Server</h2>
<p class=''legal-meta''>Step-by-step guide to join [NOME DO SERVIDOR] for the first time.</p>

<h3>Requirements</h3>
<ul>
<li><strong>DayZ</strong> installed and updated (Steam, paid, ~30 GB)</li>
<li>Active Steam account</li>
<li>Stable connection (>10 Mbps download recommended)</li>
<li>(Optional) <strong>DZSA Launcher</strong> - makes mod management easier. Free download: <a href=''https://dayzsalauncher.com/'' target=''_blank'' rel=''noopener''>dayzsalauncher.com</a></li>
</ul>

<h3>Method 1 - Via DZSA Launcher (recommended)</h3>
<ol>
<li>Download and install <strong>DZSA Launcher</strong></li>
<li>Open the launcher and click <strong>Servers</strong></li>
<li>Type in search: <code>[NOME DO SERVIDOR]</code></li>
<li>Click on <strong>[BR] [NOME DO SERVIDOR] | PVP | RAID-FIND | VANILLA+</strong></li>
<li>Click <strong>JOIN</strong> - launcher auto-downloads mods</li>
<li>Wait for mods download (first time may take 5-30 min)</li>
<li>Once in, login with Steam normally</li>
</ol>

<h3>Method 2 - Direct via DayZ client</h3>
<ol>
<li>Open DayZ via Steam</li>
<li>Main menu → <strong>SERVERS</strong></li>
<li><strong>FAVORITES</strong> or <strong>COMMUNITY</strong> tab</li>
<li>Click <strong>ADD SERVER</strong> and paste: <code>[IP:PORTA do seu servidor]</code></li>
<li>Mark as favorite + click <strong>JOIN</strong></li>
<li>If ''Bad Version'' appears = you need to install mods (use Method 1)</li>
</ol>

<h3>Server IP and Port</h3>
<div class=''legal-callout''>
<p><strong>IP:</strong> <code>[IP do seu servidor]</code><br>
<strong>Port:</strong> <code>2302</code><br>
<strong>Map:</strong> Chernarus<br>
<strong>Slots:</strong> 60 players</p>
</div>

<h3>BattleMetrics (live status)</h3>
<p>Follow server live status, online players, and history:<br>
<a href=''https://www.battlemetrics.com/servers/dayz/SEU-ID-BATTLEMETRICS'' target=''_blank'' rel=''noopener''>battlemetrics.com/servers/dayz/SEU-ID-BATTLEMETRICS</a></p>

<h3>First time? What to do:</h3>
<ol>
<li>Connect to the server and find a safe place</li>
<li>Open the site, go to <a href=''/shop''>Shop</a> to buy your first coins</li>
<li>Login with Steam - coins are linked to your Steam ID</li>
<li>In-game, go to a <strong>Trader</strong> (NPC vendor) and exchange coins for items/food/weapons</li>
<li>Read the <a href=''/rules''>Server Rules</a> before raiding or PvP</li>
</ol>

<h3>Common problems</h3>
<ul>
<li><strong>''Bad Version''</strong> when connecting → mods missing. Use DZSA Launcher (Method 1).</li>
<li><strong>''Wrong Signature''</strong> → outdated mods. Update via Steam Workshop.</li>
<li><strong>''Connection Failed''</strong> → server may be restarting. Wait 2-5 min and try again.</li>
<li><strong>''Kicked: BattlEye''</strong> → BattlEye corrupted. Right-click DayZ → Properties → Verify Integrity.</li>
<li><strong>Other questions</strong> → join our <a href=''https://discord.gg/SEU-CONVITE'' target=''_blank'' rel=''noopener''>official Discord</a> and open a ticket.</li>
</ul>

<h3>Server mods</h3>
<p>The server has exclusive mods from our community + popular DayZ mods. Complete updated list is in the <strong>Steam Workshop Collection</strong> linked in DZSA Launcher when you click JOIN - just accept download.</p>

<h3>Official Discord</h3>
<p>For wipe announcements, events, support, and community:<br>
<a href=''https://discord.gg/SEU-CONVITE'' target=''_blank'' rel=''noopener''>discord.gg/SEU-CONVITE</a></p>
', 1, 6)
ON DUPLICATE KEY UPDATE
  title_ptbr = IF(title_ptbr IS NULL OR title_ptbr = '', VALUES(title_ptbr), title_ptbr),
  body_ptbr  = IF(body_ptbr  IS NULL OR body_ptbr  = '', VALUES(body_ptbr),  body_ptbr),
  body_enus  = IF(body_enus  IS NULL OR body_enus  = '', VALUES(body_enus),  body_enus);
-- ==== /SEED: paginas legais ====

-- ============================================================
-- TABELAS: clans / clan_members / clan_requests (v2.13.0)
-- Clãs registrados no site. 1 jogador = 1 clã. Entrada só com aceite.
-- ============================================================
DROP TABLE IF EXISTS clans;
CREATE TABLE clans (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(60)  NOT NULL,
    tag            VARCHAR(8)   NOT NULL,
    owner_steam_id VARCHAR(20)  NOT NULL,
    description    VARCHAR(500) NULL,
    discord_url    VARCHAR(255) NULL,
    logo           VARCHAR(255) NULL,
    member_cap     INT          NOT NULL DEFAULT 20,
    status         VARCHAR(20)  NOT NULL DEFAULT 'active',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clan_tag (tag),
    UNIQUE KEY uq_clan_name (name),
    KEY idx_clan_owner (owner_steam_id),
    KEY idx_clan_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS clan_members;
CREATE TABLE clan_members (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    clan_id   INT         NOT NULL,
    steam_id  VARCHAR(20) NOT NULL,
    role      VARCHAR(10) NOT NULL DEFAULT 'member',
    joined_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_steam (steam_id),
    KEY idx_member_clan (clan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS clan_requests;
CREATE TABLE clan_requests (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    clan_id    INT         NOT NULL,
    steam_id   VARCHAR(20) NOT NULL,
    kind       VARCHAR(10) NOT NULL DEFAULT 'request',
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_req (clan_id, steam_id),
    KEY idx_req_steam (steam_id),
    KEY idx_req_clan (clan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELA: help_articles (v2.14.0) — Central de Ajuda / Guia
-- ============================================================
DROP TABLE IF EXISTS help_articles;
CREATE TABLE help_articles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(30)  NOT NULL,
    slug        VARCHAR(120) NOT NULL,
    title       VARCHAR(160) NOT NULL,
    summary     VARCHAR(300) NULL,
    body        MEDIUMTEXT   NULL,
    video_url   VARCHAR(255) NULL,
    image       VARCHAR(255) NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    published   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_help_slug (slug),
    KEY idx_help_cat (category, published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELAS: eventos de clã (migration v2.15.0) — placar por delta
-- ============================================================
CREATE TABLE clan_events (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(120) NOT NULL,
    slug           VARCHAR(140) NOT NULL,
    description    TEXT         NULL,
    metric         VARCHAR(30)  NOT NULL DEFAULT 'kills_infected',
    prize          VARCHAR(255) NULL,
    prize_coins    INT          NOT NULL DEFAULT 0,
    starts_at      DATETIME     NOT NULL,
    ends_at        DATETIME     NOT NULL,
    baseline_taken TINYINT(1)   NOT NULL DEFAULT 0,
    frozen_at      DATETIME     NULL,
    winner_clan_id INT          NULL,
    winner_name    VARCHAR(120) NULL,
    rewarded_at    DATETIME     NULL,
    enabled        TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order     INT          NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cevt_slug (slug),
    KEY idx_cevt_dates (starts_at, ends_at),
    KEY idx_cevt_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clan_event_entries (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_id      INT          NOT NULL,
    clan_id       INT          NOT NULL,
    registered_by VARCHAR(20)  NULL,
    final_score   BIGINT       NULL,
    final_rank    INT          NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_centry (event_id, clan_id),
    KEY idx_centry_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clan_event_members (
    id             INT          AUTO_INCREMENT PRIMARY KEY,
    event_id       INT          NOT NULL,
    clan_id        INT          NOT NULL,
    steam_id       VARCHAR(20)  NOT NULL,
    baseline       BIGINT       NOT NULL DEFAULT 0,
    active         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deactivated_at DATETIME     NULL,
    UNIQUE KEY uq_cmem (event_id, steam_id),
    KEY idx_cmem_clan (event_id, clan_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
