-- ============================================================
-- v2.15.0 - Eventos de Clã (placar por DELTA)
-- ============================================================
-- Competição entre clãs num período (ex: "Mate o máximo de zumbis").
-- A pontuação conta SÓ o que rolou DENTRO do evento: tira-se um baseline
-- (foto do contador) no início e o placar = atual - baseline, somado pelos
-- membros ATIVOS do clã. Quem entra no meio começa em 0; quem sai é removido.
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- ============================================================

CREATE TABLE IF NOT EXISTS clan_events (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(120) NOT NULL,
    slug           VARCHAR(140) NOT NULL,
    description    TEXT         NULL,
    metric         VARCHAR(30)  NOT NULL DEFAULT 'kills_infected', -- kills_infected | playtime_seconds
    prize          VARCHAR(255) NULL,
    starts_at      DATETIME     NOT NULL,
    ends_at        DATETIME     NOT NULL,
    baseline_taken TINYINT(1)   NOT NULL DEFAULT 0,    -- foto do início já tirada?
    frozen_at      DATETIME     NULL,                  -- resultado congelado no fim?
    winner_clan_id INT          NULL,
    winner_name    VARCHAR(120) NULL,
    rewarded_at    DATETIME     NULL,                  -- premiação já entregue?
    enabled        TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order     INT          NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cevt_slug (slug),
    KEY idx_cevt_dates (starts_at, ends_at),
    KEY idx_cevt_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clan_event_entries (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_id      INT          NOT NULL,
    clan_id       INT          NOT NULL,
    registered_by VARCHAR(20)  NULL,                   -- steam_id do líder que inscreveu
    final_score   BIGINT       NULL,                   -- congelado no fim
    final_rank    INT          NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_centry (event_id, clan_id),
    KEY idx_centry_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clan_event_members (
    id             INT          AUTO_INCREMENT PRIMARY KEY,
    event_id       INT          NOT NULL,
    clan_id        INT          NOT NULL,
    steam_id       VARCHAR(20)  NOT NULL,
    baseline       BIGINT       NOT NULL DEFAULT 0,    -- valor do contador quando entrou no evento
    active         TINYINT(1)   NOT NULL DEFAULT 1,    -- ainda conta pro clã?
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deactivated_at DATETIME     NULL,
    UNIQUE KEY uq_cmem (event_id, steam_id),
    KEY idx_cmem_clan (event_id, clan_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
