-- ============================================================
-- v2.17.0 — Loja de Pontos (Fase 2: gastar pontos em itens in-game)
-- ============================================================
-- O jogador gasta os "pontos" (Fase 1) em itens que dropam in-game via CFTools
-- (mesmo mecanismo das caixas). Item pode ter ANEXOS/KIT (arma + mira + mag...).
-- Template nasce vazio; o servidor cadastra os itens (classnames validados).
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ============================================================

CREATE TABLE IF NOT EXISTS point_shop_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    description TEXT         NULL,
    image       VARCHAR(255) NULL,
    category    VARCHAR(60)  NOT NULL DEFAULT 'Geral',
    classname   VARCHAR(120) NOT NULL,
    quantity    INT          NOT NULL DEFAULT 1,
    point_cost  INT          NOT NULL DEFAULT 0,
    vip_only    TINYINT(1)   NOT NULL DEFAULT 0,
    enabled     TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_psi_cat (category, enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Anexos/kit de um item (spawnam junto; caem no chão se não anexar).
CREATE TABLE IF NOT EXISTS point_shop_item_attachments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    item_id    INT          NOT NULL,
    classname  VARCHAR(120) NOT NULL,
    quantity   INT          NOT NULL DEFAULT 1,
    sort_order INT          NOT NULL DEFAULT 0,
    KEY idx_psa_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compras (fila de entrega + histórico). attachments_json = snapshot no momento.
CREATE TABLE IF NOT EXISTS point_purchases (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    steam_id        VARCHAR(20) NOT NULL,
    item_id         INT         NULL,
    item_name       VARCHAR(120) NOT NULL,
    classname       VARCHAR(120) NOT NULL,
    quantity        INT         NOT NULL DEFAULT 1,
    attachments_json TEXT       NULL,
    point_cost      INT         NOT NULL DEFAULT 0,
    status          ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at    DATETIME    NULL,
    KEY idx_pp_steam (steam_id),
    KEY idx_pp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
