-- v3.3.0 - Protecao de menores (ECA Digital, Lei 15.211/2025 + Decreto 12.880/2026).
-- Caixa de recompensa so abre para adulto verificado por fonte externa; loja exige
-- declaracao de idade e consentimento real. Guarda SO metadados: o CPF nunca e gravado.
-- Idempotente: pode rodar duas vezes.

CREATE TABLE IF NOT EXISTS age_verifications (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    steam_id         VARCHAR(20)  NOT NULL,
    method           ENUM('declaracao','flagcheck','serpro','cpfhub') NOT NULL,
    result           ENUM('adulto','menor','falhou') NOT NULL,
    birth_year       SMALLINT     NULL,
    cpf_hash         CHAR(64)     NULL,
    cpf_hash_revoked CHAR(64)     NULL,
    provider_ref     VARCHAR(120) NULL,
    provider_status  VARCHAR(40)  NULL,
    ip               VARCHAR(45)  NULL,
    user_agent       VARCHAR(255) NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at       DATETIME     NULL,
    revoked_reason   VARCHAR(160) NULL,
    UNIQUE KEY uq_age_cpf (cpf_hash),
    KEY idx_age_player (steam_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Em MariaDB o ALTER com IF NOT EXISTS por coluna e valido.
ALTER TABLE age_verifications ADD COLUMN IF NOT EXISTS cpf_hash_revoked CHAR(64) NULL AFTER cpf_hash;

ALTER TABLE players
    ADD COLUMN IF NOT EXISTS age_status ENUM('desconhecido','menor','adulto_declarado','adulto_verificado')
        NOT NULL DEFAULT 'desconhecido',
    ADD COLUMN IF NOT EXISTS age_verified_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS consents (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    steam_id    VARCHAR(20)  NOT NULL,
    kind        ENUM('termos','privacidade','idade') NOT NULL,
    version     VARCHAR(20)  NOT NULL,
    text_hash   CHAR(64)     NOT NULL,
    ip          VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_consent_player (steam_id, kind, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings: modo nasce em 'declaracao' (caixas fechadas, loja com declaracao) e o
-- painel mostra aviso amarelo ate o cliente colar a chave do fornecedor.
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('age_gate_mode', 'declaracao'),
    ('age_provider', 'cpfhub'),
    ('age_provider_key', ''),
    ('age_provider_secret', ''),
    ('age_daily_box_gated', '1'),
    ('terms_version', '1');
-- Sal aleatorio por site: gerado UMA vez, no banco, nunca no codigo.
INSERT IGNORE INTO settings (`key`, `value`) VALUES ('age_hash_salt', SHA2(CONCAT(UUID(), RAND(), NOW(6)), 256));
