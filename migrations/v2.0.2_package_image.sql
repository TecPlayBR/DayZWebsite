-- ============================================================
-- v2.0.2 - imagem do pacote (capa)
-- ============================================================
-- Capa do pacote (PNG transparente ~512x512) pra dar destaque no shop/home,
-- em vez do emoji. Guarda só o nome do arquivo em assets/img/packages/.
-- Seguro: só ADICIONA coluna; dados existentes intactos.
-- ============================================================

ALTER TABLE packages ADD COLUMN image VARCHAR(255) NULL AFTER icon;
