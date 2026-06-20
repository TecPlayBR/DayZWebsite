-- ============================================================
-- v2.7.0 — Recompensa por conquista + Log de login
-- ============================================================
-- Idempotente (CREATE TABLE IF NOT EXISTS). NUNCA apaga dados.
-- ============================================================

-- Recompensa por conquista: registra CADA recompensa já paga (1 por conquista/jogador).
-- A UNIQUE(steam_id, slug) garante idempotência: o crédito só acontece uma vez por
-- conquista por jogador (INSERT IGNORE = "claim atômico"). Também é o LOG do painel.
CREATE TABLE IF NOT EXISTS achievement_rewards_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    steam_id   VARCHAR(20)  NOT NULL,
    slug       VARCHAR(40)  NOT NULL,
    coins      INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ach_steam_slug (steam_id, slug),
    KEY idx_ach_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Log de quem logou no site via Steam (privacidade/auditoria). Sem PII sensível —
-- SteamID é público; IP/UA pra auditoria de acesso. Decoupled do ranking (que segue público).
CREATE TABLE IF NOT EXISTS login_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    steam_id     VARCHAR(20)  NOT NULL,
    display_name VARCHAR(190) NULL,
    ip           VARCHAR(45)  NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_steam (steam_id),
    KEY idx_login_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
