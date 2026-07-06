-- ============================================================
-- v2.4.0 - Programa de afiliado / streamer ("Apoie seu Streamer")
-- ============================================================
-- O cliente atrela o perfil a UM streamer (digitando o codigo dele 1x). O
-- streamer ganha cache escalonado pela recorrencia do cliente (1a/2a/3a+ compra),
-- calculado sobre o VALOR CHEIO, so em compra aprovada. O beneficio pro cliente
-- (% / R$ / moedas) e definido por cupom e aplica 1x (na hora que ele se atrela).
-- Seguro: so ADICIONA colunas/seeds e AMPLIA um ENUM; dados existentes intactos.
-- ============================================================

-- Cupom: novo tipo de beneficio "coins" (moedas bonus) + campos de comissao do streamer.
ALTER TABLE coupons MODIFY COLUMN discount_type ENUM('percent','fixed','coins') NOT NULL DEFAULT 'percent';
ALTER TABLE coupons ADD COLUMN affiliate_name VARCHAR(120) NULL;
ALTER TABLE coupons ADD COLUMN commission_pct_1 DECIMAL(5,2) NOT NULL DEFAULT 0;
ALTER TABLE coupons ADD COLUMN commission_pct_2 DECIMAL(5,2) NOT NULL DEFAULT 0;
ALTER TABLE coupons ADD COLUMN commission_pct_3plus DECIMAL(5,2) NOT NULL DEFAULT 0;

-- Cliente atrelado a um streamer (vinculo do programa).
ALTER TABLE players ADD COLUMN affiliate_coupon_code VARCHAR(40) NULL;
ALTER TABLE players ADD COLUMN affiliate_bound_at DATETIME NULL;

-- Carimbo da atribuicao no momento da compra (historico estavel mesmo se trocar de streamer).
ALTER TABLE purchases ADD COLUMN affiliate_coupon_code VARCHAR(40) NULL;

-- Toggles do sistema (geral + permitir troca de streamer).
INSERT INTO settings (`key`, `value`) VALUES
('affiliate_enabled', '0'),
('affiliate_allow_switch', '0')
ON DUPLICATE KEY UPDATE `key` = `key`;
