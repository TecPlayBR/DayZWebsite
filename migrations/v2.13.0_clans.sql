-- v2.13.0 - Clãs (Fase 1): registro de clãs no site + membros + pedidos/convites.
-- 1 jogador = 1 clã (UNIQUE em clan_members.steam_id). Entrada só com aceite dos
-- dois lados (pedido do jogador OU convite do dono) → LGPD/consentimento.
-- Idempotente: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS clans (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(60)  NOT NULL,
    tag            VARCHAR(8)   NOT NULL,            -- ex: RVH (aparece antes do nick)
    owner_steam_id VARCHAR(20)  NOT NULL,
    description    VARCHAR(500) NULL,
    discord_url    VARCHAR(255) NULL,
    logo           VARCHAR(255) NULL,                -- imagem (upload); fallback = inicial
    member_cap     INT          NOT NULL DEFAULT 20,
    status         VARCHAR(20)  NOT NULL DEFAULT 'active',  -- active | removed
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_clan_tag (tag),
    UNIQUE KEY uq_clan_name (name),
    KEY idx_clan_owner (owner_steam_id),
    KEY idx_clan_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clan_members (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    clan_id   INT         NOT NULL,
    steam_id  VARCHAR(20) NOT NULL,
    role      VARCHAR(10) NOT NULL DEFAULT 'member',  -- owner | member
    joined_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_steam (steam_id),            -- 1 jogador = 1 clã
    KEY idx_member_clan (clan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clan_requests (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    clan_id    INT         NOT NULL,
    steam_id   VARCHAR(20) NOT NULL,
    kind       VARCHAR(10) NOT NULL DEFAULT 'request', -- request (jogador→clã) | invite (dono→jogador)
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_req (clan_id, steam_id),
    KEY idx_req_steam (steam_id),
    KEY idx_req_clan (clan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
