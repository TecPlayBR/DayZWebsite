-- ============================================================
-- v2.0.3 — recompensa de caixa pode ser ITEM ou MOEDAS
-- ============================================================
-- Cada item do pool da caixa agora tem um tipo: 'item' (dropa classname in-game)
-- ou 'coins' (credita moedas no saldo). Quando 'coins', a coluna quantity guarda
-- a quantidade de moedas. Permite a Caixa Diária dar item OU moedas.
-- Seguro: só ADICIONA coluna com default 'item' (pool existente continua item).
-- ============================================================

ALTER TABLE box_items ADD COLUMN type ENUM('item','coins') NOT NULL DEFAULT 'item' AFTER box_id;
