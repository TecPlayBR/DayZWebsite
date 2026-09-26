<?php
// ============================================================
// LoginLog - prazo de guarda do registro de login (login_log).
// Marco Civil da Internet, art. 15: registros de acesso a aplicacao por 6 meses.
// Passado o prazo, a Politica de Privacidade promete eliminar, e esta classe cumpre.
// So elimina com a retencao LIGADA (login_log_retencao = 1). Instalacao nova nasce ligada.
// Site que ja existia nasce desligado: o dono decide no painel (/admin/eca), porque o
// template nunca apaga dado do cliente sem ele autorizar.
// ============================================================

namespace App;

class LoginLog
{
    public const MESES = 6;

    /** Registros com mais de 6 meses (o painel mostra antes de o dono ligar). */
    public static function antigos(): int
    {
        return (int) Database::fetchColumn("SELECT COUNT(*) FROM login_log WHERE created_at < NOW() - INTERVAL " . self::MESES . " MONTH");
    }

    /** Apaga o que passou do prazo e devolve quantos. Quem chama ja conferiu a autorizacao. */
    public static function limpar(): int
    {
        return Database::execute("DELETE FROM login_log WHERE created_at < NOW() - INTERVAL " . self::MESES . " MONTH");
    }

    /**
     * Chamado a cada login: com a retencao ligada, limpa no maximo uma vez por dia.
     * Sem cron na hospedagem compartilhada, o proprio movimento do site dispara a limpeza.
     */
    public static function limparSeDevido(): void
    {
        if (!Settings::getBool('login_log_retencao', false)) return;
        $hoje = date('Y-m-d');
        if ((string) Settings::get('login_log_limpo_em', '') === $hoje) return;
        Settings::set('login_log_limpo_em', $hoje);   // marca antes: dois logins juntos nao limpam em dobro
        self::limpar();
        self::limparAuditoria();
        if (class_exists(RateLimit::class)) RateLimit::limparAntigos(24);
    }

    /** Registro de acoes dos administradores: 12 meses (tabela de retencao da Politica). */
    public static function limparAuditoria(): int
    {
        return Database::execute("DELETE FROM audit_log WHERE created_at < NOW() - INTERVAL 12 MONTH");
    }
}
