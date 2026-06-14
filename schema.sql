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
-- TABELA: newsletter_emails
-- Inscrições no email capture do footer. Endpoint /api/newsletter-subscribe.
-- ============================================================
DROP TABLE IF EXISTS newsletter_emails;
CREATE TABLE newsletter_emails (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(190) NOT NULL UNIQUE,
    source          VARCHAR(40)  NOT NULL DEFAULT 'footer',
    ip              VARCHAR(45)  NULL,
    user_agent      VARCHAR(255) NULL,
    confirmed_at    TIMESTAMP    NULL,
    unsubscribed_at TIMESTAMP    NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(8,2) NOT NULL,
    max_uses INT NULL,
    used_count INT NOT NULL DEFAULT 0,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    package_ids JSON NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
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

-- Settings padrao
INSERT INTO settings (`key`, `value`) VALUES
('bonus_enabled', '1'),
('site_name', 'MEU SERVIDOR'),
('site_tagline', 'Sobreviva. Construa. Domine. A apocalipse nao espera.'),
('server_ip', ''),
('server_port', '2302'),
('battlemetrics_id', ''),
('next_wipe_at', ''),
('wipe_label', 'Proximo wipe'),
('discord_invite', ''),
('social_discord', ''),
('social_instagram', ''),
('social_whatsapp', ''),
('social_facebook', ''),
('social_youtube', ''),
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
('discord_integration_last_ok', '0');
