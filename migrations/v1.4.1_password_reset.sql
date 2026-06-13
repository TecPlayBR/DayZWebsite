-- ============================================================
-- DayZWebsite v1.4.1 — "Esqueci minha senha" do admin (reset por email)
-- ============================================================
-- Adiciona token de reset em admin_users. O painel manda um link por email
-- (vale 1h, uso unico, token guardado como SHA-256 — se o banco vazar, o
-- token cru nao serve). Sem isso, admin que perde a senha fica trancado.
--
-- Roda 1x. Se as colunas ja existem, ignore o erro de "duplicate column".
-- (MariaDB aceita IF NOT EXISTS; MySQL 8 nao — se der erro, tire o IF NOT EXISTS.)
-- ============================================================

ALTER TABLE admin_users
  ADD COLUMN IF NOT EXISTS reset_token_hash VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS reset_expires    DATETIME    NULL;
