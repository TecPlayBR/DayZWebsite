-- Streamers: entidade PROPRIA (codigo separado do cupom de desconto).
-- O "Apoie seu Streamer" valida contra streamers.code - um streamer PODE ter um
-- cupom de desconto vinculado (coupon_code), mas o codigo de apoio e distinto.
CREATE TABLE IF NOT EXISTS streamers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(40) NOT NULL UNIQUE,   -- codigo do streamer (apoiar)
    name            VARCHAR(80) NOT NULL,
    bio             TEXT NULL,
    avatar_url      VARCHAR(300) NULL,
    photos_json     TEXT NULL,                     -- ["/assets/img/streamers/x.webp", ...]
    channel_url     VARCHAR(300) NULL,             -- canal principal (twitch/youtube)
    video_urls_json TEXT NULL,                     -- ["https://youtu.be/...", ...]
    coupon_code     VARCHAR(40) NULL,              -- cupom de desconto vinculado (opcional, distinto)
    featured        TINYINT(1) NOT NULL DEFAULT 0, -- destaque na home
    active          TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed do primeiro streamer (Hardo) - fotos ja copiadas pro assets.
INSERT INTO streamers (code, name, bio, avatar_url, photos_json, featured, active, sort_order)
SELECT 'HARDO', 'Hardo',
       'Streamer parceiro do servidor. Apoie o Hardo usando o codigo dele - suas compras ajudam ele direto.',
       '/assets/img/streamers/hardo.webp',
       '["/assets/img/streamers/hardo-1.webp","/assets/img/streamers/hardo-2.webp"]',
       1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM streamers WHERE code = 'HARDO');
