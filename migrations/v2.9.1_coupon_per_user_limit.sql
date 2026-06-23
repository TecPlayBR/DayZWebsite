-- v2.9.1 — limite de uso de cupom POR jogador (além do limite global max_uses).
-- per_user_limit NULL = ilimitado por jogador; 1 = cada jogador usa uma vez só
-- (ex: cupom de aniversário). A contagem usa as compras aprovadas do jogador.
-- Idempotente: só adiciona a coluna se ainda não existir.

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'coupons'
      AND COLUMN_NAME = 'per_user_limit'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE coupons ADD COLUMN per_user_limit INT NULL AFTER max_uses',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
