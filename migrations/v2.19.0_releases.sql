-- v2.19.0 — Novidades / Notas de Atualização (patch notes pro player). Idempotente.
-- O admin publica o que mudou (update de mod, correção, novidade); o player lê em /novidades.
-- Nome da tabela = site_releases (evita "releases"/"release", que flerta com palavra reservada).
CREATE TABLE IF NOT EXISTS site_releases (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    version     VARCHAR(40)  NULL,                              -- ex: v2.5.2 (opcional)
    category    VARCHAR(20)  NOT NULL DEFAULT 'atualizacao',    -- novo|atualizacao|correcao|hotfix
    title       VARCHAR(160) NOT NULL,
    body        MEDIUMTEXT   NULL,                              -- HTML sanitizado
    released_at DATE         NULL,                              -- data da release (admin escolhe)
    published   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rel_pub (published, released_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
