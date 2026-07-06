-- ============================================================
-- DayZ Website Template - Migration v1.1.x → v1.2.0
-- ============================================================
-- Aplica APÓS atualizar arquivos de v1.1.x pra v1.2.0.
-- Idempotente: pode rodar várias vezes sem quebrar.
--
-- Mudanças:
--   1. admin_users.role - RBAC (super_admin/finance/support/editor)
--   2. reviews.source + purchase_id/steam_id NULL - reviews públicas
--   3. newsletter_emails - captura de email no footer
-- ============================================================

SET @db := DATABASE();

-- ============================================================
-- 1) admin_users: coluna 'role' pra RBAC
-- ============================================================
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'role');
SET @sql := IF(@col = 0,
    "ALTER TABLE admin_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'super_admin' AFTER username",
    "SELECT 'skip: admin_users.role já existe' AS info");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 2) reviews: aceita reviews públicas (sem purchase nem steam_id)
-- ============================================================

-- 2a) purchase_id passa a aceitar NULL
SET @nul := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'purchase_id');
SET @sql := IF(@nul = 'NO',
    "ALTER TABLE reviews MODIFY purchase_id INT NULL",
    "SELECT 'skip: reviews.purchase_id já NULL' AS info");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2b) steam_id passa a aceitar NULL
SET @nul := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'steam_id');
SET @sql := IF(@nul = 'NO',
    "ALTER TABLE reviews MODIFY steam_id VARCHAR(20) NULL",
    "SELECT 'skip: reviews.steam_id já NULL' AS info");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2c) coluna 'source' ('purchase' vs 'public')
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'source');
SET @sql := IF(@col = 0,
    "ALTER TABLE reviews ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'purchase' AFTER body",
    "SELECT 'skip: reviews.source já existe' AS info");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2d) remove UNIQUE em purchase_id (públicos podem se repetir)
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND INDEX_NAME = 'purchase_id' AND NON_UNIQUE = 0);
SET @sql := IF(@idx > 0,
    "ALTER TABLE reviews DROP INDEX purchase_id",
    "SELECT 'skip: index purchase_id não existe ou não é UNIQUE' AS info");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2e) novo índice em source
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND INDEX_NAME = 'idx_source');
SET @sql := IF(@idx = 0,
    "ALTER TABLE reviews ADD INDEX idx_source (source)",
    "SELECT 'skip: idx_source já existe' AS info");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3) newsletter_emails: captura no footer
-- ============================================================
CREATE TABLE IF NOT EXISTS newsletter_emails (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(190) NOT NULL UNIQUE,
    source          VARCHAR(40)  NOT NULL DEFAULT 'footer',
    ip              VARCHAR(45)  NULL,
    user_agent      VARCHAR(255) NULL,
    confirmed_at    TIMESTAMP    NULL,
    unsubscribed_at TIMESTAMP    NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Pronto. Verifique no admin → Equipe que os usuários existentes
-- ficaram como 'super_admin' (default). Atribua roles específicos
-- conforme necessidade.
-- ============================================================
