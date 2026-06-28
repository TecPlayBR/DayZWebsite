-- ============================================================
-- v2.18.0 — Remove o Sistema de Pontos (reverte v2.16.0 + v2.17.0)
-- ============================================================
-- Decisao definitiva do Bryan: loja de pontos no site foi APOSENTADA
-- (gera cadastro demais; as caixas ja entregam item in-game via CFTools).
-- Esta migration desfaz tabelas/colunas criadas em v2.16.0/v2.17.0.
-- Idempotente: DROP TABLE IF EXISTS + DROP COLUMN condicional (so dropa se existir).
-- NAO toca em nenhuma outra coisa (coins, caixas, players continuam intactos).
-- ============================================================

DROP TABLE IF EXISTS point_purchases;
DROP TABLE IF EXISTS point_shop_item_attachments;
DROP TABLE IF EXISTS point_shop_items;
DROP TABLE IF EXISTS points_log;

-- players.points (remove se existir)
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'players' AND column_name = 'points');
SET @s := IF(@c = 1, 'ALTER TABLE players DROP COLUMN points', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- boxes.points_reward (remove se existir)
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'boxes' AND column_name = 'points_reward');
SET @s := IF(@c = 1, 'ALTER TABLE boxes DROP COLUMN points_reward', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
