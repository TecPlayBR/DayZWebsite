-- ============================================================
-- v2.6.0 - Entitlements (VIP / BattlePass) gerenciados pelo SITE
-- ============================================================
-- O site (template) e quem manda no servidor de jogo. O admin concede VIP/Passe
-- aqui; o tecplay-agent puxa os pendentes (GET /api/entitlements) e escreve os
-- JSON do mod Sparda no servidor (VipPanel/PlayersVIP.json + BattlePass/premiums/),
-- depois confirma (POST ack). Escopo por servidor (multi-server), igual sync-players.
-- Idempotente: CREATE TABLE IF NOT EXISTS. So ADICIONA.
-- ============================================================

CREATE TABLE IF NOT EXISTS player_grants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    server_id       INT          NOT NULL,
    steam_id        VARCHAR(20)  NOT NULL,
    nickname        VARCHAR(120) NULL,
    type            VARCHAR(20)  NOT NULL,                 -- 'vip' | 'battlepass'
    tier            VARCHAR(20)  NULL,                     -- 'PanelVip1'..'PanelVip4' | 'CUSTOM' (vip); null (battlepass)
    days            INT          NOT NULL DEFAULT 0,       -- informativo (renovacao)
    expiration_date DATE         NULL,                     -- expiracao efetiva (fonte da verdade pro agent)
    status          VARCHAR(20)  NOT NULL DEFAULT 'pending', -- pending|applied|revoked|removed|expired
    notes           VARCHAR(255) NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_at      DATETIME     NULL,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_grants_server_status (server_id, status),
    INDEX idx_grants_steam (steam_id),
    INDEX idx_grants_exp (expiration_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
