-- v2.10.1 - foto Steam nos depoimentos: coluna reviews.avatar.
-- Capturada do login no momento do envio (reviews antigas ficam sem -> fallback
-- pra inicial do nome). Idempotente: só adiciona se ainda não existir.

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reviews'
      AND COLUMN_NAME = 'avatar'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE reviews ADD COLUMN avatar VARCHAR(255) NULL AFTER display_name',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
