-- ============================================================
-- v1.8.0 — Caixas / Lootboxes
-- ============================================================
-- Caixas que o player abre (gastando moedas ou diária grátis). Cada caixa tem
-- um pool de itens com peso (chance). Ao abrir, sorteia por peso e dropa o item
-- in-game via CFTools (CFCloud_SpawnPlayerItem). Se o player está offline ou
-- perto do restart, a abertura fica 'pending' e entrega depois.
-- ============================================================

CREATE TABLE IF NOT EXISTS boxes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80)  NOT NULL,
    slug            VARCHAR(80)  NOT NULL,
    image           VARCHAR(255) DEFAULT NULL,
    description     TEXT         DEFAULT NULL,
    cost_coins      INT          NOT NULL DEFAULT 0,   -- 0 quando é diária grátis
    is_daily        TINYINT(1)   NOT NULL DEFAULT 0,   -- caixa diária grátis (cooldown)
    cooldown_hours  INT          NOT NULL DEFAULT 24,
    enabled         TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      INT          NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS box_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    box_id      INT          NOT NULL,
    classname   VARCHAR(120) NOT NULL,             -- classname DayZ pro spawn
    name        VARCHAR(120) NOT NULL,             -- nome exibido
    image       VARCHAR(255) DEFAULT NULL,
    quantity    INT          NOT NULL DEFAULT 1,
    weight      INT          NOT NULL DEFAULT 1,   -- peso do sorteio (chance = weight/soma)
    rarity      VARCHAR(20)  NOT NULL DEFAULT 'common', -- common|uncommon|rare|epic|legendary
    enabled     TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0,
    KEY idx_box (box_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS box_openings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    box_id       INT          NOT NULL,
    steam_id     VARCHAR(20)  NOT NULL,
    item_id      INT          DEFAULT NULL,        -- box_items sorteado
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
