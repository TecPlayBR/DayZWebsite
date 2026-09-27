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
-- TABELA: servers - registry de servidores DayZ (multi-server)
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
-- TABELA: audit_log - trilha de ações administrativas
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
-- TABELA: balance_log - histórico granular de saldo do player
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
-- TABELA: combos - pacotes promocionais agregando vários packages
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
-- TABELA: wishlist - pacotes salvos como desejo pelo player
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
-- TABELA: gallery - screenshots públicas do servidor
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
-- TABELA: invoices - cobranças Pix de valor livre (/cobrar do bot)
-- (migration v1.5.0 - usada por api/bot-integration.php + api/mp-webhook.php)
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
-- TABELAS: shop_items + shop_spends  (migration v1.4.0 - loja in-game)
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
-- TABELA: player_stats  (migration v1.6.0 - leaderboard/perfil)
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
-- Entitlements VIP/BattlePass (v2.6.0) - geridos pelo site, aplicados pelo agent
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

DROP TABLE IF EXISTS page_versions;
CREATE TABLE page_versions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(80)  NOT NULL,
    body_ptbr  MEDIUMTEXT   NULL,
    body_enus  MEDIUMTEXT   NULL,
    motivo     VARCHAR(160) NULL,
    saved_by   VARCHAR(60)  NULL,
    saved_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pv_slug (slug, saved_at)
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
('login_log_retencao', '1'),
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

<p class="legal-meta">Última atualização: 2026-06-04 - Aplica-se a todos os jogadores do servidor [NOME DO SERVIDOR] e da comunidade no Discord oficial.</p>

<div class="legal-callout">
<p><strong>Importante:</strong> ao conectar no servidor ou entrar no Discord, você concorda com TODAS as regras abaixo. Ignorância não é desculpa - leia até o fim.</p>
</div>

<h3>1. Regras Gerais</h3>
<ul>
<li>Duplicar, hackear, explorar falhas, usar glitches e trapacear resulta em <strong>banimento permanente</strong> - você e todo o seu grupo.</li>
<li>Contas alternativas <strong>não são permitidas</strong>.</li>
<li>Evitar banimento (combat log, alt account) resulta em banimento permanente.</li>
<li>Não há reembolso para equipamentos perdidos devido a bugs sem evidência em vídeo.</li>
<li>Veículos por sua conta e risco. Não reembolsamos - isto é DayZ.</li>
<li>A equipe não tolera desrespeito. Se você sofrer com isso, denuncie em ticket no Discord.</li>
<li>Se qualquer membro da staff te ofender ou desrespeitar, reporte imediatamente. Você tem direitos como consumidor e cidadão.</li>
</ul>

<h3>2. Comunicação (Chat & Voz)</h3>
<ul>
<li>Chat e voz preferencialmente em <strong>português brasileiro</strong> (servidor BR).</li>
<li>Proibido CAPS LOCK excessivo, spam de mensagens, flood de emojis.</li>
<li>Proibido discurso de ódio, racismo, xenofobia, homofobia, transfobia, capacitismo, misoginia ou qualquer discriminação. Banimento permanente.</li>
<li>Stream-sniping (perseguir jogador por live) é proibido. Banimento.</li>
<li>Doxxing (divulgar dados pessoais reais de outro jogador) é crime - denunciamos à polícia.</li>
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
<li>Tomou RAID fora do horário? Denuncie - investigamos. Tomou no horário porque não logou para defender? Paciência, isso é DayZ.</li>
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

<h3>6. Recompensas, Conquistas e Economia (Anti-Burla)</h3>
<p>Conquistas, eventos, ranking, caixas e recompensas existem pra premiar quem joga de verdade. Burlar esses sistemas é levado a sério - algumas recompensas têm valor real:</p>
<ul>
<li><strong>Proibido combinar mortes ("kill-trading")</strong> - matar (ou morrer de propósito pra) um parceiro pra farmar abates, distância de tiro ou qualquer métrica de conquista, evento ou ranking.</li>
<li><strong>Proibido conluio</strong> entre jogadores ou contas pra inflar resultado de evento (inclusive evento de clã), ranking ou recompensa.</li>
<li><strong>Proibido usar conta alternativa</strong> pra farmar conquistas, recompensas, indicações ou vantagens. Alt account já é proibida (§1); usar pra farmar é agravante.</li>
<li><strong>Proibido automatizar</strong> (macro, script, bot) ações que geram recompensa - farm de tempo online, abates, abertura de caixa, etc.</li>
<li><strong>Proibido explorar bug</strong> de um sistema de recompensa (duplicar prêmio, repetir resgate, brecha de evento). Achou a brecha? <strong>Reporte</strong> - não abuse.</li>
</ul>
<div class="legal-callout warn">
<p><strong>Consequência:</strong> identificado o abuso (por logs, denúncia ou investigação), a staff pode <strong>anular as recompensas e Moedas ganhas de forma indevida</strong>, aplicar <strong>banimento</strong> (individual ou do clã/grupo envolvido) e tratar o caso como tentativa de fraude. A análise considera o conjunto de evidências e o padrão de comportamento. <strong>Moedas que você comprou na Loja não são afetadas por esta regra</strong> - só as recompensas obtidas burlando o sistema.</p>
</div>

<h3>7. Discord (Comunidade)</h3>
<ul>
<li>Idade mínima: <strong>13 anos</strong> (TOS Discord). Menores de 18 requerem consentimento dos responsáveis.</li>
<li>Proibido spam em canais, DM ads (mensagens privadas com propaganda), divulgação de outros servidores DayZ.</li>
<li>NSFW só em canais marcados como tal (se existirem). Em outros = banimento.</li>
<li>Use os canais para finalidade correta: tickets em <code>#suporte</code>, denúncias em <code>#reportar</code>, dúvidas em <code>#geral</code>.</li>
<li>Respeite o trabalho da moderação. Discussão com mod via ticket privado, não em público.</li>
</ul>

<h3>8. Loja e Moedas (Termo de Aceite)</h3>
<ul>
<li>Ao comprar moedas na <a href="/shop">Loja</a>, você concorda com os <a href="/page/terms">Termos de Uso</a> e <a href="/page/refund">Política de Reembolso</a>.</li>
<li>Moedas compradas só podem ser usadas dentro do servidor - não há reembolso após uso parcial ou total.</li>
<li>Não realizamos <strong>trocas</strong> de itens da loja. Pense bem antes de comprar.</li>
<li>Não realizamos <strong>descontos</strong> sob demanda. Promoções são gerais e anunciadas.</li>
<li>Se um item sumir do seu inventário, a staff <strong>não repõe</strong> - DayZ é DayZ. Cuidado onde guarda suas coisas.</li>
<li>Banido = não pode mais jogar. Banimento é irrevogável. Reembolso só nos casos previstos na <a href="/page/refund">Política de Reembolso</a>.</li>
<li>A staff <strong>nunca</strong> faz transferência de bens entre players ou interfere em gameplay. Se alguém da staff se ofereceu a fazer isso, denuncie aos donos do servidor imediatamente.</li>
</ul>

<h3>9. Wipes (Reset Periódico)</h3>
<ul>
<li>O servidor pode ter wipes programados. A data será anunciada no <a href="https://discord.gg/SEU-CONVITE">Discord oficial</a> e no banner do site.</li>
<li>No wipe, sua progressão (base, inventário) é perdida - como no DayZ vanilla. Suas <strong>moedas compradas na loja são preservadas</strong>.</li>
<li>Moedas obtidas via loja in-game (Trader) podem ser wipadas - verifique antes.</li>
<li>Wipe forçado (sem aviso, por falha técnica nossa) → você tem direito a reembolso ou reposição.</li>
<li>Wipe causado pela Bohemia (patch da dona do jogo) → reposição via loja in-game; sem reembolso por motivo externo a nós.</li>
<li>A equipe é obrigada a comunicar wipes com antecedência. Se você não foi avisado, exija seus direitos.</li>
</ul>

<h3>10. Penalidades</h3>
<p>As penalidades variam conforme gravidade e reincidência:</p>
<ul>
<li><strong>Advertência</strong> verbal/escrita - primeira infração leve.</li>
<li><strong>Kick</strong> - desconexão forçada do servidor/Discord, sem ban.</li>
<li><strong>Ban temporário</strong> - 24h, 7 dias ou 30 dias.</li>
<li><strong>Ban permanente</strong> - sem retorno. Apenas em casos graves ou reincidência.</li>
</ul>
<p>A staff registra todas as ações em audit log. Decisões podem ser questionadas via ticket - não em chat público.</p>

<h3>11. Perda de Item, Bug e Reposição (Prova Obrigatória)</h3>
<p>DayZ é DayZ: bugs acontecem, e nem sempre o log guarda o que rolou. A gente faz o possível pra investigar, mas o critério de reposição é o mesmo pra todos, sem exceção.</p>
<ul>
<li><strong>Sem foto, sem vídeo, sem prova, não há reposição.</strong> "Sumiu meu item" ou "eu tinha 50 disso e agora não tenho nada", sem registro, não gera ação da staff.</li>
<li><strong>Gravar é obrigação do jogador.</strong> Use OBS, o Gravador do Windows (Win+G), Medal ou qualquer gravador, e tire prints do inventário e da base por segurança.</li>
<li><strong>Só há reposição com prova E registro no log.</strong> Se não encontrarmos o registro no log, infelizmente não tem como repor, mesmo com vídeo, porque não dá pra confirmar o que aconteceu do lado do servidor.</li>
<li>Item perdido por morte, raid, decay do servidor ou descuido (guardar no lugar errado) não é reposto.</li>
<li>Quando cabível, a reposição é a critério da staff e limitada ao que o log confirmar. A decisão é pela análise das evidências, não por insistência.</li>
</ul>
<div class="legal-callout warn">
<p><strong>Resumindo:</strong> sem prova, é a sua palavra contra a nossa, e a gente não repõe no achismo. Jogue gravando e você se protege.</p>
</div>

<h3>12. Aceite e Atualizações</h3>
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
<h3>6. Rewards, Achievements, and Economy (Anti-Abuse)</h3>
<p>Achievements, events, rankings, crates, and rewards exist to reward players who actually play. Gaming these systems is taken seriously - some rewards have real value:</p>
<ul>
<li><strong>No kill-trading</strong> - killing (or deliberately dying to) a partner to farm kills, shot distance, or any achievement, event, or ranking metric.</li>
<li><strong>No collusion</strong> between players or accounts to inflate event results (including clan events), rankings, or rewards.</li>
<li><strong>No alternate accounts</strong> to farm achievements, rewards, referrals, or advantages. Alt accounts are already banned (§1); using them to farm is an aggravating factor.</li>
<li><strong>No automation</strong> (macros, scripts, bots) of actions that generate rewards - playtime farming, kills, crate opening, etc.</li>
<li><strong>No exploiting bugs</strong> in a reward system (duplicating a prize, repeating a claim, event loopholes). Found a loophole? <strong>Report it</strong> - don''t abuse it.</li>
</ul>
<div class="legal-callout warn">
<p><strong>Consequence:</strong> once abuse is identified (through logs, reports, or investigation), the staff may <strong>void the rewards and Coins obtained improperly</strong>, apply a <strong>ban</strong> (individual or the entire clan/group involved), and treat the case as attempted fraud. The analysis considers the overall evidence and behavior pattern. <strong>Coins you purchased in the Shop are not affected by this rule</strong> - only rewards obtained by gaming the system.</p>
</div>

<h3>7. Discord (Community)</h3>
<ul>
<li>Minimum age: <strong>13 years</strong> (Discord TOS). Under 18 requires guardian consent.</li>
<li>No channel spam, DM ads (private messages with ads), or promotion of other DayZ servers.</li>
<li>NSFW only in marked channels (if any). Elsewhere = ban.</li>
<li>Use channels for proper purpose: tickets at <code>#support</code>, reports at <code>#report</code>, questions at <code>#general</code>.</li>
<li>Respect moderation work. Discussion with mod via private ticket, not in public.</li>
</ul>
<h3>8. Shop and Coins (Terms of Acceptance)</h3>
<ul>
<li>By purchasing coins at the <a href=''/shop''>Shop</a>, you agree with <a href=''/page/terms''>Terms of Use</a> and <a href=''/page/refund''>Refund Policy</a>.</li>
<li>Purchased coins can only be used within the server - no refund after partial or total use.</li>
<li>We do not <strong>exchange</strong> shop items. Think carefully before buying.</li>
<li>We do not give <strong>discounts</strong> on demand. Promotions are general and announced.</li>
<li>If an item vanishes from your inventory, staff <strong>does not replace</strong> - DayZ is DayZ. Be careful where you store things.</li>
<li>Banned = cannot play anymore. Bans are irrevocable. Refund only in cases provided in <a href=''/page/refund''>Refund Policy</a>.</li>
<li>Staff <strong>never</strong> transfers goods between players or interferes in gameplay. If any staff member offered to do so, report to server owners immediately.</li>
</ul>
<h3>9. Wipes (Periodic Reset)</h3>
<ul>
<li>The server may have scheduled wipes. The date will be announced on the <a href=''https://discord.gg/SEU-CONVITE''>official Discord</a> and on the site banner.</li>
<li>On wipe, your progression (base, inventory) is lost - like vanilla DayZ. Your <strong>coins purchased in the shop are preserved</strong>.</li>
<li>Coins from in-game shop (Trader) may be wiped - check before.</li>
<li>Forced wipe (no notice, due to our technical failure) -> you have right to refund or replacement.</li>
<li>Wipe caused by Bohemia (game patch) -> replacement via in-game shop; no refund for external reason.</li>
<li>The team must announce wipes in advance. If you weren''t notified, claim your rights.</li>
</ul>
<h3>10. Penalties</h3>
<p>Penalties vary by severity and recurrence:</p>
<ul>
<li><strong>Warning</strong> verbal/written - first minor infraction.</li>
<li><strong>Kick</strong> - forced disconnection from server/Discord, no ban.</li>
<li><strong>Temporary ban</strong> - 24h, 7 days, or 30 days.</li>
<li><strong>Permanent ban</strong> - no return. Only in serious or repeated cases.</li>
</ul>
<p>Staff logs all actions in audit log. Decisions may be questioned via ticket - not in public chat.</p>
<h3>11. Item Loss, Bugs, and Replacement (Proof Required)</h3>
<p>DayZ is DayZ: bugs happen, and the log does not always capture what occurred. We do our best to investigate, but the replacement rule is the same for everyone, no exceptions.</p>
<ul>
<li><strong>No photo, no video, no proof, no replacement.</strong> "My item vanished" or "I had 50 of these and now I have none", with no record, gets no action from staff.</li>
<li><strong>Recording is the player''s responsibility.</strong> Use OBS, the Windows Game Recorder (Win+G), Medal, or any recorder, and take screenshots of your inventory and base for safety.</li>
<li><strong>Replacement only happens with proof AND a matching log record.</strong> If we cannot find the record in the log, we unfortunately cannot replace it, even with video, because we cannot confirm what happened on the server side.</li>
<li>Items lost to death, raid, server decay, or carelessness (storing in the wrong place) are not replaced.</li>
<li>When applicable, replacement is at the staff''s discretion and limited to what the log confirms. The decision is based on evidence, not persistence.</li>
</ul>
<div class="legal-callout warn">
<p><strong>In short:</strong> without proof, it is your word against ours, and we do not replace based on guesses. Play while recording and you protect yourself.</p>
</div>

<h3>12. Acceptance and Updates</h3>
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

<p class="legal-meta">Versão [VERSÃO] · Vigente desde [DATA DE VIGÊNCIA]</p>
<h3>1. Quem somos e aceitação</h3>
<p>Este site ([DOMÍNIO], doravante "Plataforma") e o servidor de jogo DayZ [NOME DO SERVIDOR] (doravante "Servidor") são operados por <strong>[RAZÃO SOCIAL]</strong>, [DOCUMENTO], com endereço em [ENDEREÇO], que vende as Moedas, recebe os pagamentos e responde perante o consumidor.</p>
<p>A Plataforma usa software fornecido pela Tecplay. A Tecplay não vende as Moedas e não recebe os pagamentos.</p>
<p>Ao utilizar a Plataforma, você concorda com estes Termos, com a Política de Reembolso e com a Política de Privacidade.</p>
<h3>2. Definições</h3>
<ul>
<li><strong>Usuário:</strong> pessoa que acessa ou utiliza a Plataforma ou o Servidor.</li>
<li><strong>Comprador:</strong> Usuário maior de 18 anos que adquire Moedas.</li>
<li><strong>Usuário verificado:</strong> Usuário cuja idade foi confirmada por verificação de CPF, exigida para funcionalidade sujeita a restrição de idade.</li>
<li><strong>Moedas:</strong> créditos virtuais de uso limitado, utilizáveis exclusivamente dentro do Servidor. As Moedas não transferem propriedade de nenhum bem e não podem ser convertidas em dinheiro.</li>
<li><strong>Caixas de recompensa:</strong> funcionalidade que entrega itens de jogo por sorteio.</li>
<li><strong>SteamID:</strong> identificador público da conta Steam, usado para vincular as compras ao Usuário.</li>
</ul>
<h3>3. Idade</h3>
<ul>
<li><strong>Público:</strong> o DayZ tem classificação indicativa de 18 anos no Brasil, por violência extrema, temas sensíveis e drogas lícitas. O Servidor e a Plataforma são destinados a maiores de 18 anos e não são divulgados para crianças ou adolescentes.</li>
<li><strong>Regra legal:</strong> caixas de recompensa, inclusive a caixa diária gratuita, só abrem para Usuário verificado maior de 18 anos, conforme a legislação de proteção de crianças e adolescentes em ambientes digitais (Lei 15.211/2025).</li>
<li><strong>Política deste Servidor:</strong> por medida de proteção, menores de 18 anos também não podem comprar Moedas.</li>
<li>Para comprar Moedas, o Usuário entra com a conta Steam, informa a data de nascimento e aceita estes Termos. [SE O DONO LIGAR A VERIFICAÇÃO NA COMPRA: a idade também é verificada por CPF.]</li>
<li>Para abrir caixas de recompensa, a idade é verificada por CPF junto a fornecedor especializado, inclusive quando as Moedas usadas na caixa foram compradas. A declaração de idade não substitui essa verificação.</li>
<li>Quem declarar ser menor de 18 anos, ou tiver a menoridade constatada na verificação, fica impedido de comprar e de abrir caixas. Uma nova declaração não desfaz o bloqueio. Somente uma verificação por CPF que comprove a maioridade o libera.</li>
<li>Se a administração identificar que um Usuário é menor de 18 anos, bloqueia as compras e as caixas dessa conta. O responsável pode pedir a eliminação dos dados pelo [E-MAIL DE ATENDIMENTO].</li>
<li>Se o serviço de verificação estiver indisponível, a funcionalidade que depende dele fica suspensa até o serviço voltar. A indisponibilidade nunca libera o acesso sem verificação. O Usuário pode tentar novamente depois, sem custo, e o site não repete a consulta automaticamente.</li>
</ul>
<h3>4. Conta Steam</h3>
<p>A Plataforma usa o SteamID público, nunca a senha. O Usuário é responsável pela segurança da própria conta Steam.</p>
<h3>5. Compra de Moedas</h3>
<ul>
<li>Antes do pagamento, a Plataforma informa a quantidade de Moedas, o bônus quando houver, o preço total, a validade da promoção, as regras de uso, a restrição de idade e o canal de suporte.</li>
<li>Os pagamentos são processados pelo Mercado Pago (Pix, cartão de crédito e boleto).</li>
<li>O crédito das Moedas é automático após a confirmação do pagamento, em geral imediato no Pix e em até 48 horas no boleto. As Moedas ficam disponíveis para uso imediato após o crédito.</li>
<li>Os preços são em Reais (BRL). Vale o preço exibido antes da confirmação da compra. Alterações de preço valem apenas para compras futuras e não modificam compras já concluídas.</li>
</ul>
<h3>6. Uso das Moedas e mudanças no jogo</h3>
<ul>
<li>As Moedas ficam vinculadas ao SteamID do Comprador e só podem ser usadas dentro do Servidor. Não podem ser transferidas a outro Usuário nem trocadas por dinheiro.</li>
<li>A administração pode modificar funcionalidades, preços de itens futuros, balanceamento e regras do Servidor por razões técnicas, de segurança, de atualização ou de equilíbrio do jogo.</li>
<li>Essas mudanças não reduzem retroativamente o saldo de Moedas já adquirido sem comunicação prévia. Quando houver impacto relevante sobre direito já contratado, será oferecida solução razoável ao consumidor, inclusive reembolso quando a lei o assegurar.</li>
</ul>
<h3>7. Conduta do Usuário</h3>
<p>É vedado:</p>
<ul>
<li>usar cheats, hacks, exploits ou qualquer método que dê vantagem indevida no jogo;</li>
<li>praticar assédio, ameaça, discriminação ou qualquer conduta ilícita;</li>
<li>fraudar o pagamento, por exemplo com cartão de terceiro sem autorização;</li>
<li>fazer engenharia reversa, automação abusiva ou exploração de falhas da Plataforma;</li>
<li>revender Moedas, contas ou itens do Servidor.</li>
</ul>
<h3>8. Suspensão e banimento</h3>
<ul>
<li>Em caso de violação destes Termos, a administração pode suspender ou encerrar a conta, registrando o motivo da medida e observando a proporcionalidade.</li>
<li>Quando tecnicamente possível, o Usuário é informado da medida e pode contestá-la pelo [E-MAIL DE ATENDIMENTO] ou pelo [DISCORD OFICIAL].</li>
<li>A suspensão ou o banimento não afastam os direitos de restituição, reembolso ou indenização previstos na lei. Em caso de fraude comprovada, os valores obtidos ou utilizados de forma fraudulenta poderão ser objeto das medidas cabíveis após apuração.</li>
</ul>
<h3>9. Disponibilidade</h3>
<p>A Plataforma e o Servidor podem sofrer interrupções por manutenção, falha técnica ou indisponibilidade de terceiros. Isso não afasta a responsabilidade da administração por falhas que lhe sejam imputáveis, nem os direitos do consumidor em caso de não fornecimento, vício ou cobrança sem entrega.</p>
<h3>10. Responsabilidade</h3>
<p>A administração não responde por perda de Moedas causada pelo comprometimento da conta Steam do próprio Usuário, nem por atos de outros jogadores dentro do Servidor.</p>
<p>Esta cláusula não se aplica aos casos de dolo, culpa, falha de segurança, vício ou defeito do serviço, cobrança indevida, descumprimento da oferta, violação de dados pessoais, danos à integridade do consumidor ou qualquer outra hipótese em que a lei proíba limitar a responsabilidade.</p>
<h3>11. Alterações destes Termos</h3>
<ul>
<li>Cada versão destes Termos tem número e data de vigência, e as versões anteriores ficam disponíveis mediante pedido.</li>
<li>Alterações relevantes, como as que afetam preço, reembolso, idade, uso de dados ou saldo adquirido, são comunicadas com destaque no site e valem apenas daí em diante.</li>
<li>Quando a lei exigir, a Plataforma pede novo aceite. O site registra qual versão cada Comprador aceitou e quando.</li>
</ul>
<h3>12. Foro e contato</h3>
<p>Estes Termos são regidos pelas leis do Brasil. Fica eleito o foro do domicílio do consumidor (CDC, art. 101, I).</p>
<p>Atendimento: [E-MAIL DE ATENDIMENTO] e [DISCORD OFICIAL].</p>
', '<p class="legal-meta">This page is available in Portuguese only. The Portuguese text below prevails.</p>
<h2>Termos de Uso</h2>

<p class="legal-meta">Versão [VERSÃO] · Vigente desde [DATA DE VIGÊNCIA]</p>
<h3>1. Quem somos e aceitação</h3>
<p>Este site ([DOMÍNIO], doravante "Plataforma") e o servidor de jogo DayZ [NOME DO SERVIDOR] (doravante "Servidor") são operados por <strong>[RAZÃO SOCIAL]</strong>, [DOCUMENTO], com endereço em [ENDEREÇO], que vende as Moedas, recebe os pagamentos e responde perante o consumidor.</p>
<p>A Plataforma usa software fornecido pela Tecplay. A Tecplay não vende as Moedas e não recebe os pagamentos.</p>
<p>Ao utilizar a Plataforma, você concorda com estes Termos, com a Política de Reembolso e com a Política de Privacidade.</p>
<h3>2. Definições</h3>
<ul>
<li><strong>Usuário:</strong> pessoa que acessa ou utiliza a Plataforma ou o Servidor.</li>
<li><strong>Comprador:</strong> Usuário maior de 18 anos que adquire Moedas.</li>
<li><strong>Usuário verificado:</strong> Usuário cuja idade foi confirmada por verificação de CPF, exigida para funcionalidade sujeita a restrição de idade.</li>
<li><strong>Moedas:</strong> créditos virtuais de uso limitado, utilizáveis exclusivamente dentro do Servidor. As Moedas não transferem propriedade de nenhum bem e não podem ser convertidas em dinheiro.</li>
<li><strong>Caixas de recompensa:</strong> funcionalidade que entrega itens de jogo por sorteio.</li>
<li><strong>SteamID:</strong> identificador público da conta Steam, usado para vincular as compras ao Usuário.</li>
</ul>
<h3>3. Idade</h3>
<ul>
<li><strong>Público:</strong> o DayZ tem classificação indicativa de 18 anos no Brasil, por violência extrema, temas sensíveis e drogas lícitas. O Servidor e a Plataforma são destinados a maiores de 18 anos e não são divulgados para crianças ou adolescentes.</li>
<li><strong>Regra legal:</strong> caixas de recompensa, inclusive a caixa diária gratuita, só abrem para Usuário verificado maior de 18 anos, conforme a legislação de proteção de crianças e adolescentes em ambientes digitais (Lei 15.211/2025).</li>
<li><strong>Política deste Servidor:</strong> por medida de proteção, menores de 18 anos também não podem comprar Moedas.</li>
<li>Para comprar Moedas, o Usuário entra com a conta Steam, informa a data de nascimento e aceita estes Termos. [SE O DONO LIGAR A VERIFICAÇÃO NA COMPRA: a idade também é verificada por CPF.]</li>
<li>Para abrir caixas de recompensa, a idade é verificada por CPF junto a fornecedor especializado, inclusive quando as Moedas usadas na caixa foram compradas. A declaração de idade não substitui essa verificação.</li>
<li>Quem declarar ser menor de 18 anos, ou tiver a menoridade constatada na verificação, fica impedido de comprar e de abrir caixas. Uma nova declaração não desfaz o bloqueio. Somente uma verificação por CPF que comprove a maioridade o libera.</li>
<li>Se a administração identificar que um Usuário é menor de 18 anos, bloqueia as compras e as caixas dessa conta. O responsável pode pedir a eliminação dos dados pelo [E-MAIL DE ATENDIMENTO].</li>
<li>Se o serviço de verificação estiver indisponível, a funcionalidade que depende dele fica suspensa até o serviço voltar. A indisponibilidade nunca libera o acesso sem verificação. O Usuário pode tentar novamente depois, sem custo, e o site não repete a consulta automaticamente.</li>
</ul>
<h3>4. Conta Steam</h3>
<p>A Plataforma usa o SteamID público, nunca a senha. O Usuário é responsável pela segurança da própria conta Steam.</p>
<h3>5. Compra de Moedas</h3>
<ul>
<li>Antes do pagamento, a Plataforma informa a quantidade de Moedas, o bônus quando houver, o preço total, a validade da promoção, as regras de uso, a restrição de idade e o canal de suporte.</li>
<li>Os pagamentos são processados pelo Mercado Pago (Pix, cartão de crédito e boleto).</li>
<li>O crédito das Moedas é automático após a confirmação do pagamento, em geral imediato no Pix e em até 48 horas no boleto. As Moedas ficam disponíveis para uso imediato após o crédito.</li>
<li>Os preços são em Reais (BRL). Vale o preço exibido antes da confirmação da compra. Alterações de preço valem apenas para compras futuras e não modificam compras já concluídas.</li>
</ul>
<h3>6. Uso das Moedas e mudanças no jogo</h3>
<ul>
<li>As Moedas ficam vinculadas ao SteamID do Comprador e só podem ser usadas dentro do Servidor. Não podem ser transferidas a outro Usuário nem trocadas por dinheiro.</li>
<li>A administração pode modificar funcionalidades, preços de itens futuros, balanceamento e regras do Servidor por razões técnicas, de segurança, de atualização ou de equilíbrio do jogo.</li>
<li>Essas mudanças não reduzem retroativamente o saldo de Moedas já adquirido sem comunicação prévia. Quando houver impacto relevante sobre direito já contratado, será oferecida solução razoável ao consumidor, inclusive reembolso quando a lei o assegurar.</li>
</ul>
<h3>7. Conduta do Usuário</h3>
<p>É vedado:</p>
<ul>
<li>usar cheats, hacks, exploits ou qualquer método que dê vantagem indevida no jogo;</li>
<li>praticar assédio, ameaça, discriminação ou qualquer conduta ilícita;</li>
<li>fraudar o pagamento, por exemplo com cartão de terceiro sem autorização;</li>
<li>fazer engenharia reversa, automação abusiva ou exploração de falhas da Plataforma;</li>
<li>revender Moedas, contas ou itens do Servidor.</li>
</ul>
<h3>8. Suspensão e banimento</h3>
<ul>
<li>Em caso de violação destes Termos, a administração pode suspender ou encerrar a conta, registrando o motivo da medida e observando a proporcionalidade.</li>
<li>Quando tecnicamente possível, o Usuário é informado da medida e pode contestá-la pelo [E-MAIL DE ATENDIMENTO] ou pelo [DISCORD OFICIAL].</li>
<li>A suspensão ou o banimento não afastam os direitos de restituição, reembolso ou indenização previstos na lei. Em caso de fraude comprovada, os valores obtidos ou utilizados de forma fraudulenta poderão ser objeto das medidas cabíveis após apuração.</li>
</ul>
<h3>9. Disponibilidade</h3>
<p>A Plataforma e o Servidor podem sofrer interrupções por manutenção, falha técnica ou indisponibilidade de terceiros. Isso não afasta a responsabilidade da administração por falhas que lhe sejam imputáveis, nem os direitos do consumidor em caso de não fornecimento, vício ou cobrança sem entrega.</p>
<h3>10. Responsabilidade</h3>
<p>A administração não responde por perda de Moedas causada pelo comprometimento da conta Steam do próprio Usuário, nem por atos de outros jogadores dentro do Servidor.</p>
<p>Esta cláusula não se aplica aos casos de dolo, culpa, falha de segurança, vício ou defeito do serviço, cobrança indevida, descumprimento da oferta, violação de dados pessoais, danos à integridade do consumidor ou qualquer outra hipótese em que a lei proíba limitar a responsabilidade.</p>
<h3>11. Alterações destes Termos</h3>
<ul>
<li>Cada versão destes Termos tem número e data de vigência, e as versões anteriores ficam disponíveis mediante pedido.</li>
<li>Alterações relevantes, como as que afetam preço, reembolso, idade, uso de dados ou saldo adquirido, são comunicadas com destaque no site e valem apenas daí em diante.</li>
<li>Quando a lei exigir, a Plataforma pede novo aceite. O site registra qual versão cada Comprador aceitou e quando.</li>
</ul>
<h3>12. Foro e contato</h3>
<p>Estes Termos são regidos pelas leis do Brasil. Fica eleito o foro do domicílio do consumidor (CDC, art. 101, I).</p>
<p>Atendimento: [E-MAIL DE ATENDIMENTO] e [DISCORD OFICIAL].</p>
', 0, 2)
ON DUPLICATE KEY UPDATE
  title_ptbr = IF(title_ptbr IS NULL OR title_ptbr = '', VALUES(title_ptbr), title_ptbr),
  body_ptbr  = IF(body_ptbr  IS NULL OR body_ptbr  = '', VALUES(body_ptbr),  body_ptbr),
  body_enus  = IF(body_enus  IS NULL OR body_enus  = '', VALUES(body_enus),  body_enus);

-- privacy
INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
VALUES ('privacy', 'Política de Privacidade (LGPD)', 'Privacy Policy (LGPD)', '<h2>Política de Privacidade</h2>

<p class="legal-meta">Lei 13.709/2018 (LGPD) · Versão [VERSÃO] · Vigente desde [DATA DE VIGÊNCIA]</p>
<h3>1. Quem é o controlador</h3>
<p>O controlador dos dados tratados nesta Plataforma é <strong>[RAZÃO SOCIAL]</strong>, [DOCUMENTO], com endereço em [ENDEREÇO], responsável pelo Servidor [NOME DO SERVIDOR] e pela operação da loja em [DOMÍNIO].</p>
<p>A Tecplay fornece o software da Plataforma e pode atuar como operadora, suboperadora ou fornecedora de infraestrutura, conforme as atividades que efetivamente realizar e os contratos aplicáveis.</p>
<p>Canal de privacidade: [E-MAIL DE ATENDIMENTO].</p>
<h3>2. Dados que tratamos</h3>
<ul>
<li><strong>Conta Steam:</strong> SteamID64, nome de exibição e avatar (dados públicos da Steam).</li>
<li><strong>Acesso:</strong> endereço IP, navegador, data e hora de cada login no site.</li>
<li><strong>Compras:</strong> identificador, valor, status e forma de pagamento da transação no Mercado Pago. No pagamento com cartão, o e-mail e o CPF do titular do cartão são enviados diretamente ao Mercado Pago e não são guardados pelo site. O site não recebe nem guarda o número do cartão.</li>
<li><strong>Moedas:</strong> saldo, créditos e gastos, com data e hora.</li>
<li><strong>Verificação de idade:</strong> ano de nascimento (a data completa informada serve só para o cálculo e não é guardada), resultado da verificação (maior ou menor de idade), método usado (declaração ou CPF), um identificador derivado do CPF, a referência da consulta devolvida pelo fornecedor, data, IP e navegador. O CPF em si não é guardado pelo site.</li>
<li><strong>Aceite dos Termos:</strong> versão aceita, data, IP e navegador.</li>
<li><strong>Atendimento:</strong> mensagens trocadas no suporte e no [DISCORD OFICIAL], e registros de fraude ou contestação de pagamento, quando houver.</li>
</ul>
<p>O identificador derivado do CPF é usado só para impedir o reuso do mesmo CPF em outra conta, e continua sendo tratado como dado pessoal.</p>
<h3>3. Para que usamos e com qual base legal</h3>
<ul>
<li><strong>Entregar as Moedas e manter o saldo:</strong> execução de contrato (LGPD, art. 7º, V).</li>
<li><strong>Verificar a idade:</strong> cumprimento das obrigações legais aplicáveis (LGPD, art. 7º, II; Lei 15.211/2025).</li>
<li><strong>Registrar o aceite e a versão dos Termos:</strong> documentar a contratação e as informações apresentadas ao Usuário, e permitir o exercício regular de direitos (LGPD, art. 7º, VI).</li>
<li><strong>Guardar os registros de acesso:</strong> cumprimento de obrigação legal (Marco Civil da Internet, art. 15).</li>
<li><strong>Registros fiscais:</strong> cumprimento de obrigação legal, quando aplicável.</li>
<li><strong>Segurança e prevenção de fraude:</strong> usamos os dados estritamente necessários para prevenir fraudes, proteger a conta, validar transações e proteger a Plataforma, com fundamento no legítimo interesse (LGPD, art. 7º, IX), observadas as legítimas expectativas dos titulares, a necessidade, a transparência e o balanceamento dos direitos envolvidos.</li>
</ul>
<p>Não usamos os dados para publicidade, perfilamento de comportamento ou venda a terceiros. Não há outros usos além dos listados acima.</p>
<h3>4. Com quem compartilhamos</h3>
<ul>
<li><strong>Mercado Pago:</strong> processa o pagamento e nos devolve o status.</li>
<li><strong>Steam (Valve):</strong> o login é feito pela Steam, e buscamos na API pública da Steam o nome de exibição e o avatar.</li>
<li><strong>[FORNECEDOR DE VERIFICAÇÃO DE IDADE]:</strong> recebe o CPF no momento da consulta e devolve a informação de idade. Atua como operador. O site não guarda o CPF, mas o fornecedor pode guardar o registro da consulta por [PRAZO DE GUARDA DO FORNECEDOR], conforme a política dele. Pedidos sobre esse registro podem ser feitos pelo nosso canal de privacidade, que os encaminha ao fornecedor.</li>
<li><strong>[PROVEDOR DE HOSPEDAGEM]:</strong> hospeda o site e o banco de dados, em [PAÍS DE ARMAZENAMENTO].</li>
<li><strong>Tecplay:</strong> fornecedora do software, nos limites do item 1. Quando o Servidor usa o bot de Discord da Tecplay, o SteamID, as compras e o vínculo com a conta do Discord são enviados ao servidor da Tecplay, no Brasil, para entregar as Moedas e os cargos no Discord.</li>
<li><strong>Discord:</strong> quando o Servidor usa o bot, as mensagens de atendimento (tickets) ficam no Discord.</li>
<li><strong>CFTools:</strong> quando o Servidor usa essa integração, o site recebe da CFTools as estatísticas de jogo e a lista de jogadores online.</li>
</ul>
<h3>5. Transferência internacional</h3>
<p>Alguns fornecedores tratam dados fora do Brasil. Para cada um, informamos o país, os dados, a finalidade e o fundamento da transferência (LGPD, art. 33):</p>
<ul>
<li><strong>Valve (Steam), Estados Unidos:</strong> SteamID, nome de exibição e avatar, e o IP e o navegador de quem carrega os avatares. Finalidade: o login e a identificação da conta. Fundamento: necessária à execução do contrato pedido pelo Usuário, que escolhe entrar com a Steam (art. 33, IX).</li>
<li><strong>Google (YouTube), Estados Unidos:</strong> IP e navegador. Finalidade: exibir vídeo. O vídeo só carrega depois que o Usuário clica nele, com o aviso de que o conteúdo vem do YouTube. Antes do clique, nada é enviado ao Google. Fundamento: consentimento específico, dado no clique (art. 33, VIII).</li>
<li><strong>CFTools, Alemanha:</strong> o site recebe estatísticas de jogo e a lista de jogadores online, quando essa integração está ligada. Fundamento: país com nível de proteção reconhecido como adequado pela ANPD (União Europeia).</li>
<li><strong>Discord, Estados Unidos:</strong> mensagens de atendimento, quando o Servidor usa o bot. Fundamento: necessária à execução do atendimento pedido pelo Usuário (art. 33, IX).</li>
<li><strong>Mercado Pago:</strong> trata os dados do pagamento como responsável por eles e pode tratá-los fora do Brasil, conforme a própria política.</li>
<li><strong>[PROVEDOR DE HOSPEDAGEM]:</strong> todos os dados do site, em [PAÍS DE ARMAZENAMENTO]. Fundamento: [MECANISMO DA HOSPEDAGEM].</li>
</ul>
<p>Ao incluir um fornecedor novo, o controlador confere o país, os subcontratados e o fundamento antes de ligar a integração. A relação atualizada pode ser pedida pelo canal de privacidade.</p>
<h3>6. Por quanto tempo guardamos</h3>
<ul>
<li><strong>Registros de login no site (IP, navegador, data e hora):</strong> 6 meses (Marco Civil da Internet, art. 15). Depois, são eliminados.</li>
<li><strong>Compras:</strong> 5 anos após a compra.</li>
<li><strong>Verificação de idade:</strong> 5 anos após o encerramento da conta.</li>
<li><strong>Aceite dos Termos:</strong> enquanto a conta existir, mais 5 anos.</li>
<li><strong>Mensagens de atendimento:</strong> 12 meses após o encerramento do atendimento.</li>
<li><strong>Saldo de Moedas e conta Steam:</strong> enquanto a conta existir.</li>
</ul>
<p>Os prazos de 5 anos são a nossa política de retenção para cumprir as obrigações aplicáveis e permitir a defesa de direitos. Um registro só é mantido além deles se houver disputa, investigação ou obrigação legal em andamento.</p>
<p>Após o encerramento da conta, os dados são eliminados ou anonimizados, ressalvados os registros necessários ao cumprimento de obrigação legal, à prevenção de fraude ou ao exercício regular de direitos.</p>
<h3>7. Seus direitos</h3>
<p>Você pode pedir: confirmação de que tratamos seus dados, acesso, correção, anonimização, bloqueio ou eliminação de dados desnecessários, portabilidade, informação sobre com quem compartilhamos e revogação de consentimento (LGPD, art. 18).</p>
<p>Envie o pedido para [E-MAIL DE ATENDIMENTO] ou pelo [DISCORD OFICIAL]. Para confirmar que o pedido é seu, pedimos apenas o necessário, em geral que você entre no site com a mesma conta Steam. Não pedimos cópia de documento sem necessidade. Respondemos em até 15 dias.</p>
<p>A eliminação pode ser limitada quando a lei exigir a conservação de algum registro. Nesse caso, informamos qual registro foi mantido e por quê.</p>
<h3>8. Segurança</h3>
<p>Adotamos medidas técnicas e administrativas compatíveis com os riscos do tratamento, incluindo controle de acesso, criptografia em trânsito, autenticação, registros de auditoria, limitação de requisições e proteção das integrações.</p>
<p>Em caso de incidente de segurança que possa causar risco ou dano relevante, o controlador comunica a Autoridade Nacional de Proteção de Dados e os titulares afetados, nos termos da LGPD (art. 48).</p>
<h3>9. Cookies e armazenamento no navegador</h3>
<ul>
<li><strong>Cookie de sessão:</strong> mantém você conectado enquanto navega. É essencial ao funcionamento do site.</li>
<li><strong>Cookie de idioma:</strong> lembra o idioma escolhido.</li>
<li><strong>Armazenamento local do navegador:</strong> guarda que você já viu o aviso de cookies e quais avisos do site você fechou.</li>
<li><strong>Página de pagamento:</strong> o Mercado Pago carrega o próprio código de segurança, que pode usar cookies e identificar o dispositivo para prevenir fraude, conforme a política de privacidade do Mercado Pago.</li>
</ul>
<p>Não usamos cookies de rastreamento, de publicidade nem ferramentas de análise de terceiros.</p>
<h3>10. Menores de idade</h3>
<p>O Servidor e a Plataforma são destinados a maiores de 18 anos, conforme a classificação indicativa do DayZ, e não coletam dados de propósito de crianças (até 12 anos) ou de adolescentes (de 12 a 17 anos). A conta Steam, exigida para entrar, também tem idade mínima própria.</p>
<p>Se um menor usar a Plataforma mesmo assim, o site trata os mesmos dados de qualquer Usuário que entra: os da conta Steam, o registro de login e, se ele abrir um atendimento, as mensagens. Quem declara ser menor ou tem a menoridade constatada fica bloqueado para compras e caixas.</p>
<p>O responsável pode pedir, pelo [E-MAIL DE ATENDIMENTO], a eliminação dos dados do menor ou a análise de uma compra feita por ele. Para confirmar a responsabilidade, pedimos só o necessário e não exigimos documentos além disso. Esses pedidos observam o melhor interesse da criança e do adolescente (LGPD, art. 14).</p>
<h3>11. Alterações desta Política</h3>
<p>Cada versão desta Política tem número e data de vigência, e as versões anteriores ficam disponíveis mediante pedido. Alterações relevantes são comunicadas com destaque no site. Os registros feitos durante uma versão anterior continuam regidos por ela.</p>
', '<p class="legal-meta">This page is available in Portuguese only. The Portuguese text below prevails.</p>
<h2>Política de Privacidade</h2>

<p class="legal-meta">Lei 13.709/2018 (LGPD) · Versão [VERSÃO] · Vigente desde [DATA DE VIGÊNCIA]</p>
<h3>1. Quem é o controlador</h3>
<p>O controlador dos dados tratados nesta Plataforma é <strong>[RAZÃO SOCIAL]</strong>, [DOCUMENTO], com endereço em [ENDEREÇO], responsável pelo Servidor [NOME DO SERVIDOR] e pela operação da loja em [DOMÍNIO].</p>
<p>A Tecplay fornece o software da Plataforma e pode atuar como operadora, suboperadora ou fornecedora de infraestrutura, conforme as atividades que efetivamente realizar e os contratos aplicáveis.</p>
<p>Canal de privacidade: [E-MAIL DE ATENDIMENTO].</p>
<h3>2. Dados que tratamos</h3>
<ul>
<li><strong>Conta Steam:</strong> SteamID64, nome de exibição e avatar (dados públicos da Steam).</li>
<li><strong>Acesso:</strong> endereço IP, navegador, data e hora de cada login no site.</li>
<li><strong>Compras:</strong> identificador, valor, status e forma de pagamento da transação no Mercado Pago. No pagamento com cartão, o e-mail e o CPF do titular do cartão são enviados diretamente ao Mercado Pago e não são guardados pelo site. O site não recebe nem guarda o número do cartão.</li>
<li><strong>Moedas:</strong> saldo, créditos e gastos, com data e hora.</li>
<li><strong>Verificação de idade:</strong> ano de nascimento (a data completa informada serve só para o cálculo e não é guardada), resultado da verificação (maior ou menor de idade), método usado (declaração ou CPF), um identificador derivado do CPF, a referência da consulta devolvida pelo fornecedor, data, IP e navegador. O CPF em si não é guardado pelo site.</li>
<li><strong>Aceite dos Termos:</strong> versão aceita, data, IP e navegador.</li>
<li><strong>Atendimento:</strong> mensagens trocadas no suporte e no [DISCORD OFICIAL], e registros de fraude ou contestação de pagamento, quando houver.</li>
</ul>
<p>O identificador derivado do CPF é usado só para impedir o reuso do mesmo CPF em outra conta, e continua sendo tratado como dado pessoal.</p>
<h3>3. Para que usamos e com qual base legal</h3>
<ul>
<li><strong>Entregar as Moedas e manter o saldo:</strong> execução de contrato (LGPD, art. 7º, V).</li>
<li><strong>Verificar a idade:</strong> cumprimento das obrigações legais aplicáveis (LGPD, art. 7º, II; Lei 15.211/2025).</li>
<li><strong>Registrar o aceite e a versão dos Termos:</strong> documentar a contratação e as informações apresentadas ao Usuário, e permitir o exercício regular de direitos (LGPD, art. 7º, VI).</li>
<li><strong>Guardar os registros de acesso:</strong> cumprimento de obrigação legal (Marco Civil da Internet, art. 15).</li>
<li><strong>Registros fiscais:</strong> cumprimento de obrigação legal, quando aplicável.</li>
<li><strong>Segurança e prevenção de fraude:</strong> usamos os dados estritamente necessários para prevenir fraudes, proteger a conta, validar transações e proteger a Plataforma, com fundamento no legítimo interesse (LGPD, art. 7º, IX), observadas as legítimas expectativas dos titulares, a necessidade, a transparência e o balanceamento dos direitos envolvidos.</li>
</ul>
<p>Não usamos os dados para publicidade, perfilamento de comportamento ou venda a terceiros. Não há outros usos além dos listados acima.</p>
<h3>4. Com quem compartilhamos</h3>
<ul>
<li><strong>Mercado Pago:</strong> processa o pagamento e nos devolve o status.</li>
<li><strong>Steam (Valve):</strong> o login é feito pela Steam, e buscamos na API pública da Steam o nome de exibição e o avatar.</li>
<li><strong>[FORNECEDOR DE VERIFICAÇÃO DE IDADE]:</strong> recebe o CPF no momento da consulta e devolve a informação de idade. Atua como operador. O site não guarda o CPF, mas o fornecedor pode guardar o registro da consulta por [PRAZO DE GUARDA DO FORNECEDOR], conforme a política dele. Pedidos sobre esse registro podem ser feitos pelo nosso canal de privacidade, que os encaminha ao fornecedor.</li>
<li><strong>[PROVEDOR DE HOSPEDAGEM]:</strong> hospeda o site e o banco de dados, em [PAÍS DE ARMAZENAMENTO].</li>
<li><strong>Tecplay:</strong> fornecedora do software, nos limites do item 1. Quando o Servidor usa o bot de Discord da Tecplay, o SteamID, as compras e o vínculo com a conta do Discord são enviados ao servidor da Tecplay, no Brasil, para entregar as Moedas e os cargos no Discord.</li>
<li><strong>Discord:</strong> quando o Servidor usa o bot, as mensagens de atendimento (tickets) ficam no Discord.</li>
<li><strong>CFTools:</strong> quando o Servidor usa essa integração, o site recebe da CFTools as estatísticas de jogo e a lista de jogadores online.</li>
</ul>
<h3>5. Transferência internacional</h3>
<p>Alguns fornecedores tratam dados fora do Brasil. Para cada um, informamos o país, os dados, a finalidade e o fundamento da transferência (LGPD, art. 33):</p>
<ul>
<li><strong>Valve (Steam), Estados Unidos:</strong> SteamID, nome de exibição e avatar, e o IP e o navegador de quem carrega os avatares. Finalidade: o login e a identificação da conta. Fundamento: necessária à execução do contrato pedido pelo Usuário, que escolhe entrar com a Steam (art. 33, IX).</li>
<li><strong>Google (YouTube), Estados Unidos:</strong> IP e navegador. Finalidade: exibir vídeo. O vídeo só carrega depois que o Usuário clica nele, com o aviso de que o conteúdo vem do YouTube. Antes do clique, nada é enviado ao Google. Fundamento: consentimento específico, dado no clique (art. 33, VIII).</li>
<li><strong>CFTools, Alemanha:</strong> o site recebe estatísticas de jogo e a lista de jogadores online, quando essa integração está ligada. Fundamento: país com nível de proteção reconhecido como adequado pela ANPD (União Europeia).</li>
<li><strong>Discord, Estados Unidos:</strong> mensagens de atendimento, quando o Servidor usa o bot. Fundamento: necessária à execução do atendimento pedido pelo Usuário (art. 33, IX).</li>
<li><strong>Mercado Pago:</strong> trata os dados do pagamento como responsável por eles e pode tratá-los fora do Brasil, conforme a própria política.</li>
<li><strong>[PROVEDOR DE HOSPEDAGEM]:</strong> todos os dados do site, em [PAÍS DE ARMAZENAMENTO]. Fundamento: [MECANISMO DA HOSPEDAGEM].</li>
</ul>
<p>Ao incluir um fornecedor novo, o controlador confere o país, os subcontratados e o fundamento antes de ligar a integração. A relação atualizada pode ser pedida pelo canal de privacidade.</p>
<h3>6. Por quanto tempo guardamos</h3>
<ul>
<li><strong>Registros de login no site (IP, navegador, data e hora):</strong> 6 meses (Marco Civil da Internet, art. 15). Depois, são eliminados.</li>
<li><strong>Compras:</strong> 5 anos após a compra.</li>
<li><strong>Verificação de idade:</strong> 5 anos após o encerramento da conta.</li>
<li><strong>Aceite dos Termos:</strong> enquanto a conta existir, mais 5 anos.</li>
<li><strong>Mensagens de atendimento:</strong> 12 meses após o encerramento do atendimento.</li>
<li><strong>Saldo de Moedas e conta Steam:</strong> enquanto a conta existir.</li>
</ul>
<p>Os prazos de 5 anos são a nossa política de retenção para cumprir as obrigações aplicáveis e permitir a defesa de direitos. Um registro só é mantido além deles se houver disputa, investigação ou obrigação legal em andamento.</p>
<p>Após o encerramento da conta, os dados são eliminados ou anonimizados, ressalvados os registros necessários ao cumprimento de obrigação legal, à prevenção de fraude ou ao exercício regular de direitos.</p>
<h3>7. Seus direitos</h3>
<p>Você pode pedir: confirmação de que tratamos seus dados, acesso, correção, anonimização, bloqueio ou eliminação de dados desnecessários, portabilidade, informação sobre com quem compartilhamos e revogação de consentimento (LGPD, art. 18).</p>
<p>Envie o pedido para [E-MAIL DE ATENDIMENTO] ou pelo [DISCORD OFICIAL]. Para confirmar que o pedido é seu, pedimos apenas o necessário, em geral que você entre no site com a mesma conta Steam. Não pedimos cópia de documento sem necessidade. Respondemos em até 15 dias.</p>
<p>A eliminação pode ser limitada quando a lei exigir a conservação de algum registro. Nesse caso, informamos qual registro foi mantido e por quê.</p>
<h3>8. Segurança</h3>
<p>Adotamos medidas técnicas e administrativas compatíveis com os riscos do tratamento, incluindo controle de acesso, criptografia em trânsito, autenticação, registros de auditoria, limitação de requisições e proteção das integrações.</p>
<p>Em caso de incidente de segurança que possa causar risco ou dano relevante, o controlador comunica a Autoridade Nacional de Proteção de Dados e os titulares afetados, nos termos da LGPD (art. 48).</p>
<h3>9. Cookies e armazenamento no navegador</h3>
<ul>
<li><strong>Cookie de sessão:</strong> mantém você conectado enquanto navega. É essencial ao funcionamento do site.</li>
<li><strong>Cookie de idioma:</strong> lembra o idioma escolhido.</li>
<li><strong>Armazenamento local do navegador:</strong> guarda que você já viu o aviso de cookies e quais avisos do site você fechou.</li>
<li><strong>Página de pagamento:</strong> o Mercado Pago carrega o próprio código de segurança, que pode usar cookies e identificar o dispositivo para prevenir fraude, conforme a política de privacidade do Mercado Pago.</li>
</ul>
<p>Não usamos cookies de rastreamento, de publicidade nem ferramentas de análise de terceiros.</p>
<h3>10. Menores de idade</h3>
<p>O Servidor e a Plataforma são destinados a maiores de 18 anos, conforme a classificação indicativa do DayZ, e não coletam dados de propósito de crianças (até 12 anos) ou de adolescentes (de 12 a 17 anos). A conta Steam, exigida para entrar, também tem idade mínima própria.</p>
<p>Se um menor usar a Plataforma mesmo assim, o site trata os mesmos dados de qualquer Usuário que entra: os da conta Steam, o registro de login e, se ele abrir um atendimento, as mensagens. Quem declara ser menor ou tem a menoridade constatada fica bloqueado para compras e caixas.</p>
<p>O responsável pode pedir, pelo [E-MAIL DE ATENDIMENTO], a eliminação dos dados do menor ou a análise de uma compra feita por ele. Para confirmar a responsabilidade, pedimos só o necessário e não exigimos documentos além disso. Esses pedidos observam o melhor interesse da criança e do adolescente (LGPD, art. 14).</p>
<h3>11. Alterações desta Política</h3>
<p>Cada versão desta Política tem número e data de vigência, e as versões anteriores ficam disponíveis mediante pedido. Alterações relevantes são comunicadas com destaque no site. Os registros feitos durante uma versão anterior continuam regidos por ela.</p>
', 0, 3)
ON DUPLICATE KEY UPDATE
  title_ptbr = IF(title_ptbr IS NULL OR title_ptbr = '', VALUES(title_ptbr), title_ptbr),
  body_ptbr  = IF(body_ptbr  IS NULL OR body_ptbr  = '', VALUES(body_ptbr),  body_ptbr),
  body_enus  = IF(body_enus  IS NULL OR body_enus  = '', VALUES(body_enus),  body_enus);

-- refund
INSERT INTO pages (slug, title_ptbr, title_enus, body_ptbr, body_enus, published, sort_order)
VALUES ('refund', 'Política de Reembolso', 'Refund Policy', '<h2>Política de Reembolso</h2>

<p class="legal-meta">Código de Defesa do Consumidor (Lei 8.078/1990), arts. 18, 20 e 49 · Versão [VERSÃO] · Vigente desde [DATA DE VIGÊNCIA]</p>
<h3>1. Direito de arrependimento</h3>
<p>Compras feitas pela internet podem ser canceladas em até 7 (sete) dias corridos a partir da confirmação do pagamento (CDC, art. 49).</p>
<ul>
<li>Moedas ainda não usadas: reembolso integral do valor pago.</li>
<li>Parte das Moedas já usada: o pedido é analisado individualmente, sem prejuízo do direito de arrependimento e dos demais direitos previstos em lei.</li>
</ul>
<h3>2. Falha, cobrança indevida e fraude</h3>
<ul>
<li><strong>Cobrança em duplicidade:</strong> reembolso integral da cobrança repetida.</li>
<li><strong>Moedas não entregues:</strong> se o pagamento foi aprovado e as Moedas não chegaram, a equipe corrige o crédito ou, à escolha do consumidor, devolve o valor pago.</li>
<li><strong>Vício ou falha no serviço:</strong> conforme o caso, a equipe corrige o problema, restitui o valor ou concede abatimento proporcional (CDC, arts. 18 e 20).</li>
<li><strong>Compra não reconhecida pelo titular do cartão:</strong> o caso é apurado. Confirmada a fraude, o valor é devolvido ao titular.</li>
</ul>
<h3>3. Como analisamos cada pedido</h3>
<p>Avaliamos a extensão do fornecimento, o consumo das Moedas, falha técnica, duplicidade, fraude e as demais circunstâncias do caso. O reembolso pode ser integral ou proporcional, conforme a obrigação não cumprida, o valor efetivamente pago e os direitos previstos em lei.</p>
<p>O uso intenso das Moedas, isoladamente, não é considerado prova de má-fé. A análise considera os registros da transação, a comunicação do consumidor e as demais circunstâncias.</p>
<p>Em pacotes com bônus, o reembolso considera o valor pago, não o valor das Moedas de bônus.</p>
<h3>4. Suspensão, banimento e reembolso</h3>
<p>A suspensão ou o banimento da conta não eliminam, por si só, o direito ao reembolso previsto em lei. Em caso de fraude comprovada, os valores ligados à fraude poderão ser objeto das medidas cabíveis após apuração.</p>
<h3>5. Contestação de pagamento (chargeback)</h3>
<p>Se o pagamento for contestado junto ao cartão, a Plataforma pode suspender temporariamente o uso das Moedas ligadas àquela transação e pedir informações ao Comprador, sem impedir o exercício dos seus direitos. Medidas definitivas dependem da confirmação de fraude, duplicidade ou irregularidade.</p>
<h3>6. Como pedir</h3>
<ul>
<li>Envie o pedido para [E-MAIL DE ATENDIMENTO] ou abra um ticket no [DISCORD OFICIAL].</li>
<li>Informe o número da transação do Mercado Pago (está no e-mail de confirmação) e o motivo. Se não tiver o número, informe a data e o valor da compra, que localizamos pelo seu login.</li>
<li>A equipe responde em até 5 (cinco) dias úteis. Aprovado o reembolso, o estorno é feito pelo Mercado Pago. O prazo do estorno depende do meio de pagamento e do Mercado Pago, e costuma ser de até 14 dias úteis. Esses prazos são estimativas de operação e não reduzem nenhum prazo previsto em lei.</li>
</ul>
<h3>7. Pedidos repetidos</h3>
<p>Cada pedido é analisado individualmente. Um padrão de pedidos repetidos pode levar a uma análise mais detalhada das compras seguintes, sempre respeitando os direitos do consumidor.</p>
', '<p class="legal-meta">This page is available in Portuguese only. The Portuguese text below prevails.</p>
<h2>Política de Reembolso</h2>

<p class="legal-meta">Código de Defesa do Consumidor (Lei 8.078/1990), arts. 18, 20 e 49 · Versão [VERSÃO] · Vigente desde [DATA DE VIGÊNCIA]</p>
<h3>1. Direito de arrependimento</h3>
<p>Compras feitas pela internet podem ser canceladas em até 7 (sete) dias corridos a partir da confirmação do pagamento (CDC, art. 49).</p>
<ul>
<li>Moedas ainda não usadas: reembolso integral do valor pago.</li>
<li>Parte das Moedas já usada: o pedido é analisado individualmente, sem prejuízo do direito de arrependimento e dos demais direitos previstos em lei.</li>
</ul>
<h3>2. Falha, cobrança indevida e fraude</h3>
<ul>
<li><strong>Cobrança em duplicidade:</strong> reembolso integral da cobrança repetida.</li>
<li><strong>Moedas não entregues:</strong> se o pagamento foi aprovado e as Moedas não chegaram, a equipe corrige o crédito ou, à escolha do consumidor, devolve o valor pago.</li>
<li><strong>Vício ou falha no serviço:</strong> conforme o caso, a equipe corrige o problema, restitui o valor ou concede abatimento proporcional (CDC, arts. 18 e 20).</li>
<li><strong>Compra não reconhecida pelo titular do cartão:</strong> o caso é apurado. Confirmada a fraude, o valor é devolvido ao titular.</li>
</ul>
<h3>3. Como analisamos cada pedido</h3>
<p>Avaliamos a extensão do fornecimento, o consumo das Moedas, falha técnica, duplicidade, fraude e as demais circunstâncias do caso. O reembolso pode ser integral ou proporcional, conforme a obrigação não cumprida, o valor efetivamente pago e os direitos previstos em lei.</p>
<p>O uso intenso das Moedas, isoladamente, não é considerado prova de má-fé. A análise considera os registros da transação, a comunicação do consumidor e as demais circunstâncias.</p>
<p>Em pacotes com bônus, o reembolso considera o valor pago, não o valor das Moedas de bônus.</p>
<h3>4. Suspensão, banimento e reembolso</h3>
<p>A suspensão ou o banimento da conta não eliminam, por si só, o direito ao reembolso previsto em lei. Em caso de fraude comprovada, os valores ligados à fraude poderão ser objeto das medidas cabíveis após apuração.</p>
<h3>5. Contestação de pagamento (chargeback)</h3>
<p>Se o pagamento for contestado junto ao cartão, a Plataforma pode suspender temporariamente o uso das Moedas ligadas àquela transação e pedir informações ao Comprador, sem impedir o exercício dos seus direitos. Medidas definitivas dependem da confirmação de fraude, duplicidade ou irregularidade.</p>
<h3>6. Como pedir</h3>
<ul>
<li>Envie o pedido para [E-MAIL DE ATENDIMENTO] ou abra um ticket no [DISCORD OFICIAL].</li>
<li>Informe o número da transação do Mercado Pago (está no e-mail de confirmação) e o motivo. Se não tiver o número, informe a data e o valor da compra, que localizamos pelo seu login.</li>
<li>A equipe responde em até 5 (cinco) dias úteis. Aprovado o reembolso, o estorno é feito pelo Mercado Pago. O prazo do estorno depende do meio de pagamento e do Mercado Pago, e costuma ser de até 14 dias úteis. Esses prazos são estimativas de operação e não reduzem nenhum prazo previsto em lei.</li>
</ul>
<h3>7. Pedidos repetidos</h3>
<p>Cada pedido é analisado individualmente. Um padrão de pedidos repetidos pode levar a uma análise mais detalhada das compras seguintes, sempre respeitando os direitos do consumidor.</p>
', 0, 4)
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
<p>Não. O DayZ tem classificação indicativa de 18 anos, e este Servidor é destinado a maiores de 18. Menores não podem comprar Moedas nem abrir caixas de recompensa. Para abrir caixas, a idade é verificada por fornecedor especializado. O site recebe só o resultado necessário e não guarda o CPF nem imagem de documento.</p>
<p>Se uma compra foi feita por um menor, o responsável pode entrar em contato pelo [E-MAIL DE ATENDIMENTO]. Analisamos o caso e pedimos só a informação necessária para confirmar a responsabilidade.</p>
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
<summary class="faq-q">O servidor caiu - perco minhas Moedas?</summary>
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
<p>Eventualmente realizamos promoções com bônus de Moedas em pacotes ou cupons. Acompanhe o Discord oficial. <strong>Nunca</strong> entregamos Moedas via mensagem privada - qualquer "promoção" fora dos canais oficiais é golpe.</p>
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
<li>(Opcional) <strong>DZSA Launcher</strong> - facilita gerenciar mods. Download grátis: <a href=''https://dayzsalauncher.com/'' target=''_blank'' rel=''noopener''>dayzsalauncher.com</a></li>
</ul>

<h3>Método 1 - Via DZSA Launcher (recomendado)</h3>
<ol>
<li>Baixa e instala o <strong>DZSA Launcher</strong></li>
<li>Abre o launcher e clica em <strong>Servers</strong></li>
<li>Na barra de busca digita: <code>[NOME DO SERVIDOR]</code></li>
<li>Clica no servidor <strong>[BR] [NOME DO SERVIDOR] | PVP | RAID-FIND | VANILLA+</strong></li>
<li>Clica <strong>JOIN</strong> - o launcher baixa os mods automaticamente</li>
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
<li>Loga com Steam - moedas são vinculadas ao seu Steam ID</li>
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
<p>O servidor tem mods exclusivos da nossa comunidade + mods da comunidade DayZ. A lista completa atualizada está no <strong>Steam Workshop Collection</strong> linkada no DZSA Launcher quando você clica em JOIN - basta aceitar download.</p>

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
-- TABELA: help_articles (v2.14.0) - Central de Ajuda / Guia
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
-- TABELA: site_releases (migration v2.19.0) - Novidades / Notas de Atualização
-- ============================================================
CREATE TABLE site_releases (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    version     VARCHAR(40)  NULL,
    category    VARCHAR(20)  NOT NULL DEFAULT 'atualizacao',
    title       VARCHAR(160) NOT NULL,
    body        MEDIUMTEXT   NULL,
    released_at DATE         NULL,
    published   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rel_pub (published, released_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABELAS: eventos de clã (migration v2.15.0) - placar por delta
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
