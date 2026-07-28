-- Ponte bot->site (migracao de saldo): idempotencia do import da carteira do bot pro
-- players.coins. Cada import e identificado por `ref` UNIQUE (ex: wmig:<guild>:<steam>)
-- -> re-rodar a migracao NUNCA credita duas vezes. Usado 1x quando um guild que era
-- so-bot conecta o site. O endpoint bot-integration.php?action=import_coins tambem cria
-- esta tabela sob demanda (clientes que ainda nao rodaram o migrate).
CREATE TABLE IF NOT EXISTS coin_imports (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    ref        VARCHAR(80) NOT NULL UNIQUE,
    steam_id   VARCHAR(20) NOT NULL,
    coins      BIGINT NOT NULL,
    source     VARCHAR(20) NOT NULL DEFAULT 'bot_wallet',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_steam (steam_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
