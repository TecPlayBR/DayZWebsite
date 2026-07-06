-- ============================================================
-- DayZWebsite v1.5.0 - Cobranças Pix de valor livre (/cobrar do bot)
-- ============================================================
-- Helpdesk + pagamentos: o admin gera uma cobrança Pix de valor arbitrário
-- com dados do cliente; o bot manda o QR; o mp-webhook.php marca 'paid'.
-- Idempotente por invoice_ref. external_reference no MP = "inv-<invoice_ref>"
-- (prefixo distingue do fluxo de purchases no webhook). Cobra no MP do site.
--
-- ⚠️ LGPD: cpf/name/email/phone = dado pessoal. Fica SÓ aqui (site tem política).
-- Idempotente: rode quantas vezes quiser.
-- ============================================================

CREATE TABLE IF NOT EXISTS invoices (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    -- invoice_ref = id único gerado pelo bot. UNIQUE garante idempotência
    -- (retry de rede não cria 2 cobranças nem cobra 2x).
    invoice_ref   VARCHAR(80)   NOT NULL UNIQUE,
    name          VARCHAR(120)  NOT NULL,
    cpf           VARCHAR(14)   NULL,
    email         VARCHAR(160)  NULL,
    phone         VARCHAR(20)   NULL,
    description   VARCHAR(255)  NOT NULL,
    amount_brl    DECIMAL(10,2) NOT NULL,
    status        ENUM('pending','paid','expired','cancelled') NOT NULL DEFAULT 'pending',
    mp_payment_id VARCHAR(40)   NULL,
    -- copia-e-cola Pix (payload EMV) guardado p/ retry idempotente devolver o
    -- MESMO QR sem criar nova cobrança. O bot regenera o PNG via qrpix.
    qr_code       TEXT          NULL,
    created_by    VARCHAR(40)   NULL,   -- discord id do admin que gerou (audit)
    guild_id      VARCHAR(32)   NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at       DATETIME      NULL,
    expires_at    DATETIME      NULL,
    INDEX idx_inv_status (status, created_at),
    INDEX idx_inv_payment (mp_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
