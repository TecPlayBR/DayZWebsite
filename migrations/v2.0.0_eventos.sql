-- ============================================================
-- v2.0.0 - Eventos & Sorteios
-- ============================================================
-- Eventos/sorteios que aparecem em /eventos (nav) + teaser na home. O status
-- (próximo / ativo / encerrado) é calculado pelas datas. Sorteio pode ter um
-- vencedor registrado pelo admin (steam_id + nome).
-- ============================================================

CREATE TABLE IF NOT EXISTS events (
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
