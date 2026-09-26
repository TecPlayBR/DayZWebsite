-- 3.8.0: historico das paginas (versoes anteriores de Termos, Privacidade, Reembolso e demais)
-- O painel grava aqui o texto antigo antes de cada edicao e antes de aplicar o modelo legal.
CREATE TABLE IF NOT EXISTS page_versions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(80)  NOT NULL,
    body_ptbr  MEDIUMTEXT   NULL,
    body_enus  MEDIUMTEXT   NULL,
    motivo     VARCHAR(160) NULL,
    saved_by   VARCHAR(60)  NULL,
    saved_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pv_slug (slug, saved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
