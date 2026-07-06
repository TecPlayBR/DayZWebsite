-- ============================================================
-- DayZWebsite v1.6.0 - Estatísticas de gameplay (leaderboard + perfil)
-- ============================================================
-- Stats vêm do CFTools (via Bot, que resolve Steam64->cftools_id e empurra
-- pro site em /api/player-stats.php). Colunas fixas pros stats ordenáveis
-- (leaderboard rápido por índice) + extra_json pros campos ricos do mod
-- (precisão, top-armas, kills de animais) que PODEM ou não vir da Data API.
--
-- Idempotente: CREATE TABLE IF NOT EXISTS. NÃO apaga nada.
-- ============================================================

CREATE TABLE IF NOT EXISTS player_stats (
    steam_id          VARCHAR(20)  NOT NULL PRIMARY KEY,
    cftools_id        VARCHAR(40)  NULL,
    -- core (ordenáveis no leaderboard)
    kills             INT          NOT NULL DEFAULT 0,
    deaths            INT          NOT NULL DEFAULT 0,
    kdratio           DECIMAL(8,2) NOT NULL DEFAULT 0,
    playtime_seconds  INT          NOT NULL DEFAULT 0,
    longest_kill_m    INT          NOT NULL DEFAULT 0,
    longest_shot_m    INT          NOT NULL DEFAULT 0,
    suicides          INT          NOT NULL DEFAULT 0,
    kills_infected    INT          NOT NULL DEFAULT 0,
    -- ricos / opcionais (precisão, hits, top_weapons[], kills_animals, etc.)
    extra_json        JSON         NULL,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_kills     (kills DESC),
    INDEX idx_kd        (kdratio DESC),
    INDEX idx_playtime  (playtime_seconds DESC),
    INDEX idx_longest   (longest_kill_m DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
