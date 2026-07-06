-- v2.10.0 - adiciona balance_log.delta (faltava no schema).
-- BUG latente: BalanceLog::record SEMPRE inseriu a coluna `delta`, mas ela nunca
-- existiu na tabela (nem no schema.sql nem em migration) → todo INSERT de
-- balance_log falhava em silêncio (o catch engolia) e o histórico de saldo do
-- jogador (admin → detalhe do player) ficava vazio. Esta migration cria a coluna
-- e o log volta a funcionar pra conquista/premiação/ajuste manual/compra de VIP.
-- Idempotente: só adiciona se ainda não existir.

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'balance_log'
      AND COLUMN_NAME = 'delta'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE balance_log ADD COLUMN delta INT NOT NULL DEFAULT 0 AFTER steam_id',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
