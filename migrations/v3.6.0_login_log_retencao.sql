-- 3.6.0: prazo de 6 meses para o registro de login (Marco Civil, art. 15)
-- Site que ja existia comeca com a limpeza DESLIGADA: o dono decide no painel em /admin/eca,
-- porque ligar apaga registros antigos. Instalacao nova ja vem ligada pelo schema.sql e o
-- INSERT IGNORE nao sobrescreve.
INSERT IGNORE INTO settings (`key`, `value`) VALUES
('login_log_retencao', '0');
