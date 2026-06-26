-- v2.14.0 — Central de Ajuda (artigos/guia por categoria). Idempotente.
-- Conteúdo migrado do "Começo" do Discord. Cada artigo: categoria, título, corpo
-- (HTML), vídeo do YouTube (opcional) e imagem (opcional). Editável no painel.

CREATE TABLE IF NOT EXISTS help_articles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(30)  NOT NULL,
    slug        VARCHAR(120) NOT NULL,
    title       VARCHAR(160) NOT NULL,
    summary     VARCHAR(300) NULL,
    body        MEDIUMTEXT   NULL,
    video_url   VARCHAR(255) NULL,            -- link do YouTube (embuto na página)
    image       VARCHAR(255) NULL,            -- imagem de capa/ilustração
    sort_order  INT          NOT NULL DEFAULT 0,
    published   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_help_slug (slug),
    KEY idx_help_cat (category, published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
