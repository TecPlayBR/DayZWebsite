-- ============================================================
-- v2.16.0 — Sistema de Pontos (Fase 1: moeda nova + ganhar por caixa)
-- ============================================================
-- 2ª moeda (separada de coins). Ganha abrindo caixa (cada caixa define
-- quantos pontos dá, ajustável no admin). Fase 2 = loja de pontos (gastar).
-- Idempotente.
-- ============================================================

-- players.points
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'players' AND column_name = 'points');
SET @s := IF(@c = 0, 'ALTER TABLE players ADD COLUMN points INT NOT NULL DEFAULT 0 AFTER coins', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- boxes.points_reward (quantos pontos a caixa concede ao abrir)
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'boxes' AND column_name = 'points_reward');
SET @s := IF(@c = 0, 'ALTER TABLE boxes ADD COLUMN points_reward INT NOT NULL DEFAULT 0 AFTER cost_coins', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Histórico de pontos (ganho/gasto) — pro card, FAQ e auditoria.
CREATE TABLE IF NOT EXISTS points_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    player_id     INT          NULL,
    steam_id      VARCHAR(20)  NOT NULL,
    delta         INT          NOT NULL,        -- + ganho / - gasto
    balance_after INT          NOT NULL,
    source        VARCHAR(30)  NOT NULL,        -- box | shop | admin | ...
    ref_type      VARCHAR(30)  NULL,
    ref_id        VARCHAR(40)  NULL,
    note          VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pl_steam (steam_id),
    KEY idx_pl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
