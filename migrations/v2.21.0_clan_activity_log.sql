-- v2.21.0 — Log de atividade do clã (entra/sai/kick/liderança), visível a
-- membros+líder na página do clã. Ajuda o clã a saber quem entrou/saiu (pedido
-- do Bryan, junto com o anti-acidente do rejoin nos eventos). Idempotente.

CREATE TABLE IF NOT EXISTS clan_activity_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    clan_id         INT          NOT NULL,
    steam_id        VARCHAR(20)  NOT NULL,             -- alvo da ação (quem entrou/saiu)
    action          VARCHAR(20)  NOT NULL,             -- join|leave|kick|lead
    actor_steam_id  VARCHAR(20)  NULL,                 -- quem executou (kick/lead); NULL = o próprio
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cal_clan (clan_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
