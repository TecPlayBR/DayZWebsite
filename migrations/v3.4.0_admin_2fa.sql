-- Login em duas etapas OPCIONAL do admin (3.4.0). Cada admin liga na propria conta.
-- totp_secret: segredo do app autenticador, CIFRADO (a chave fica em storage/keys, fora do banco)
-- totp_last_step: ultimo passo de 30s aceito, pra o mesmo codigo nao entrar duas vezes
-- totp_recovery: JSON com os hashes dos codigos de recuperacao (uso unico)
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS totp_secret TEXT NULL;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS totp_last_step BIGINT NULL;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS totp_recovery TEXT NULL;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS totp_enabled_at DATETIME NULL;
