-- ============================================================
-- v2.5.0 - peso da caixa DERIVADO da raridade (remove o "peso" manual)
-- ============================================================
-- O campo "peso" saiu do admin: a RARIDADE passa a definir a chance sozinha.
-- Esta migration normaliza o peso de todos os itens existentes pela raridade
-- (mesmo mapa do src/Boxes.php RARITY_WEIGHT e do JS do caixa_edit.php).
-- Idempotente: rodar de novo seta os mesmos valores. So altera a coluna weight.
-- ============================================================

UPDATE box_items SET weight = CASE rarity
    WHEN 'common'    THEN 100
    WHEN 'uncommon'  THEN 40
    WHEN 'rare'      THEN 15
    WHEN 'epic'      THEN 5
    WHEN 'legendary' THEN 2
    ELSE 100
END;
