-- v2.15.5 — moedas de prêmio dos eventos de clã (pro botão "Premiar").
-- Cada membro do clã vencedor recebe `prize_coins` ao premiar. Idempotente.
SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'clan_events' AND column_name = 'prize_coins');
SET @sql := IF(@col = 0,
    'ALTER TABLE clan_events ADD COLUMN prize_coins INT NOT NULL DEFAULT 0 AFTER prize',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
