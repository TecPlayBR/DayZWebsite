-- ============================================================
-- (c) 2026 Tecplay - DayZ Website Template
-- Migration v1.1.0 — Integração Discord
-- ============================================================
-- Adiciona suporte ao endpoint /api/bot-integration.php que o
-- Tecplay Bot Discord (Pro/Free) consome com Bearer token.
--
-- Como aplicar:
--   1. Conecta no DB do site (phpMyAdmin ou linha de comando)
--   2. Roda este arquivo inteiro (idempotente — pode rodar de novo
--      em sites já com tabela criada que não quebra)
--
-- Em phpMyAdmin: Importar → escolhe este .sql → Executar
-- Em shell:      mysql -u USER -p DB < migrations/v1.1.0_discord_integration.sql
-- ============================================================

-- ============ TABELA: discord_integration_log ============
-- Auditoria de toda chamada ao endpoint. Mantém só os 200
-- registros mais recentes (limpeza automática no endpoint, 5%
-- das chamadas). Histórico curto pra debug, não pra contabilidade.

CREATE TABLE IF NOT EXISTS discord_integration_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    called_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip          VARCHAR(45) NOT NULL DEFAULT '',
    action      VARCHAR(64) NOT NULL DEFAULT '',
    status_code SMALLINT    NOT NULL DEFAULT 0,
    INDEX idx_called (called_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============ SETTINGS: chaves novas ============
-- INSERT IGNORE = se a chave já existe (re-rodando migration), preserva o valor.
-- Token vazio = endpoint retorna 401 "token_not_configured" até admin gerar.

INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('discord_integration_token', ''),
    ('discord_integration_last_ok', '0');

-- ============ ENUM players.origin: adicionar 'bot' ============
-- Sites instalados na v1.0 têm ENUM('agent','panel','payment','manual') — falta 'bot'.
-- ALTER TABLE com mesmo ENUM + 'bot' é idempotente (re-rodar não quebra).
-- Players vinculados via comando /perfil ou /link-steam no bot Discord ganham origin='bot'.

ALTER TABLE players
    MODIFY COLUMN origin ENUM('agent','panel','payment','manual','bot')
    NOT NULL DEFAULT 'agent';

-- ============ FIM ============
-- Pra desfazer (rollback):
--   DROP TABLE IF EXISTS discord_integration_log;
--   DELETE FROM settings WHERE `key` IN
--     ('discord_integration_token','discord_integration_last_ok');
