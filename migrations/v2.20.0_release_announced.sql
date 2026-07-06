-- v2.20.0 - coluna announced_at em site_releases: marca quando a novidade JA foi
-- cross-postada no Discord (via bot). Garante que o site avisa o bot 1x por release
-- (nao re-posta em cada edicao). NULL = ainda nao anunciada. Idempotente.

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'site_releases'
      AND COLUMN_NAME = 'announced_at'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE site_releases ADD COLUMN announced_at DATETIME NULL AFTER published',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
