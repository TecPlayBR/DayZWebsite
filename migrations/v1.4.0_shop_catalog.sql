-- ============================================================
-- DayZWebsite v1.4.0 - Loja Fase 2: catálogo in-game + spend
-- ============================================================
-- Itens gastáveis (moeda → item entregue in-game). O bot lista via
-- ?action=shop_items, debita via ?action=spend (atômico + idempotente).
-- O PBO dropa o deliver[] in-game. Saldo = players.coins (fonte da verdade).
--
-- Idempotente: rode quantas vezes quiser.
-- ============================================================

CREATE TABLE IF NOT EXISTS shop_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sku          VARCHAR(64)  NOT NULL UNIQUE,
    name         VARCHAR(120) NOT NULL,
    icon         VARCHAR(16)  NULL,
    coins_cost   INT          NOT NULL,
    enabled      TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order   INT          NOT NULL DEFAULT 0,
    -- deliver[] = [{classname, quantity, attachments[], cargo[], health}]
    -- formato travado com o Claude dos Mods (o PBO lê isso pra dropar).
    deliver_json JSON         NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_enabled_order (enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_spends (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    -- spend_ref = id único do bot. UNIQUE garante idempotência (retry de rede
    -- não debita 2x). Toda a lógica de "não cobrar de novo" pende disto.
    spend_ref    VARCHAR(80)  NOT NULL UNIQUE,
    steam_id     VARCHAR(20)  NOT NULL,
    sku          VARCHAR(64)  NOT NULL,
    coins_spent  INT          NOT NULL,
    new_balance  INT          NOT NULL,
    -- snapshot do que foi entregue (o item pode mudar depois; o gasto fica fiel).
    deliver_json JSON         NOT NULL,
    server_id    INT          NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_steam (steam_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
