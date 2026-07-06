-- ============================================================
-- v1.9.0 - Premiações do leaderboard (histórico + dedup por período)
-- ============================================================
-- Registra cada moeda creditada por colocação/categoria/período. A UNIQUE
-- (period_label, category, place) impede creditar 2x a mesma posição no mesmo
-- período (clique duplo / cron repetido). O crédito só acontece se o INSERT
-- IGNORE inseriu linha nova.
-- ============================================================

CREATE TABLE IF NOT EXISTS reward_payouts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    period_label VARCHAR(20)  NOT NULL,   -- "2026-06" (mensal) | "2026-W24" (semanal) | "2026-06-13" (manual)
    category     VARCHAR(30)  NOT NULL,   -- kills, kdratio, playtime, ...
    place        TINYINT      NOT NULL,   -- 1, 2, 3
    steam_id     VARCHAR(20)  NOT NULL,
    player_name  VARCHAR(120) DEFAULT NULL,
    coins        INT          NOT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_period_cat_place (period_label, category, place),
    KEY idx_steam (steam_id),
    KEY idx_period (period_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
