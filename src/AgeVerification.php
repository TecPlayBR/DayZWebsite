<?php
// ============================================================
// AgeVerification - grava e le o estado de idade de um jogador.
// ============================================================
// Regra esta em AgeGate; fornecedor esta em AgeVerifier. Aqui e so persistencia e
// orquestracao. O CPF entra em verificar(), vira hash, vai pro fornecedor e some.
// Retornos de declarar/verificar: ['ok','status','erro','cod'] - 'cod' e uma chave curta
// (cpf_taken, failed_retry, nasc_invalida, cpf_invalido, menor_so_cpf, sem_sal) pra rota
// escolher o texto do stringtable e nao mostrar o erro cru do fornecedor ao jogador.
// ============================================================

namespace App;

class AgeVerification
{
    public static function modo(): string
    {
        $m = (string) Settings::get('age_gate_mode', 'declaracao');
        return in_array($m, AgeGate::MODOS, true) ? $m : 'declaracao';
    }

    public static function diariaExige(): bool
    {
        return Settings::getBool('age_daily_box_gated', true);
    }

    public static function statusDe(string $steamId): string
    {
        try {
            $s = Database::fetchColumn("SELECT age_status FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
        } catch (\Throwable $e) { $s = null; }   // migration pendente: trata como desconhecido
        return in_array($s, AgeGate::STATUS, true) ? $s : 'desconhecido';
    }

    public static function podeComprar(string $steamId): bool
    {
        return AgeGate::podeComprar(self::statusDe($steamId), self::modo());
    }

    public static function podeAbrirCaixa(string $steamId, bool $diaria): bool
    {
        return AgeGate::podeAbrirCaixa(self::statusDe($steamId), self::modo(), $diaria, self::diariaExige());
    }

    /** Falhas do fornecedor nas ultimas N horas (alerta no painel: creditos acabaram, chave morta). */
    public static function falhasRecentes(int $horas = 24): int
    {
        try {
            return (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM age_verifications WHERE result = 'falhou' AND created_at >= NOW() - INTERVAL ? HOUR", [$horas]);
        } catch (\Throwable $e) { return 0; }
    }

    private static function erro(string $cod, string $msg): array
    {
        return ['ok' => false, 'status' => null, 'erro' => $msg, 'cod' => $cod];
    }

    private static function garantePlayer(string $steamId): void
    {
        $ex = Database::fetchOne("SELECT id FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
        if (!$ex) Database::query("INSERT INTO players (steam_id, coins, origin, last_seen_at) VALUES (?, 0, 'panel', NOW())", [$steamId]);
    }

    private static function registra(string $steamId, string $method, string $result, ?int $ano, ?string $hash, ?string $ref, ?string $status): int
    {
        Database::query(
            "INSERT INTO age_verifications (steam_id, method, result, birth_year, cpf_hash, provider_ref, provider_status, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$steamId, $method, $result, $ano, $hash, $ref, $status, RateLimit::clientIp(),
             substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    private static function anoDe(string $nascimento): int
    {
        return (int) substr(preg_replace('#^(\d{2})/(\d{2})/(\d{4})$#', '$3-$2-$1', trim($nascimento)), 0, 4);
    }

    /** Passo 1 do fluxo: data de nascimento. Nunca chama fornecedor. Menor so sai por CPF. */
    public static function declarar(string $steamId, string $nascimento): array
    {
        $decl = AgeGate::statusPorNascimento($nascimento);
        if ($decl === null) return self::erro('nasc_invalida', 'Data de nascimento inválida.');
        self::garantePlayer($steamId);
        $atual = self::statusDe($steamId);
        $novo = AgeGate::proximoStatusDeclaracao($atual, $decl);
        if ($novo === null) return self::erro('menor_so_cpf', 'Quem se declarou menor só sai dessa condição pela verificação por CPF.');
        self::registra($steamId, 'declaracao', $decl === 'menor' ? 'menor' : 'adulto', self::anoDe($nascimento), null, null, null);
        Database::query("UPDATE players SET age_status = ? WHERE steam_id = ?", [$novo, $steamId]);
        return ['ok' => true, 'status' => $novo, 'erro' => null, 'cod' => null];
    }

    /** Passo 2: CPF no fornecedor. O CPF nao sobrevive a esta funcao. */
    public static function verificar(string $steamId, string $cpf, ?string $nascimento): array
    {
        $limpo = AgeGate::cpfLimpo($cpf);
        if ($limpo === null) return self::erro('cpf_invalido', 'CPF inválido. Confira os 11 dígitos.');
        $nascIso = null;
        if ($nascimento !== null && $nascimento !== '') {
            if (AgeGate::idade($nascimento) === null) return self::erro('nasc_invalida', 'Data de nascimento inválida.');
            $nascIso = preg_replace('#^(\d{2})/(\d{2})/(\d{4})$#', '$3-$2-$1', trim($nascimento));
        }
        $sal = (string) Settings::get('age_hash_salt', '');
        if ($sal === '') return self::erro('sem_sal', 'Verificação indisponível: rode o /update.php.');
        $hash = AgeGate::cpfHash($limpo, $sal);

        // Um CPF, uma conta por site. Checa ANTES de gastar a consulta.
        $dono = Database::fetchColumn(
            "SELECT steam_id FROM age_verifications WHERE cpf_hash = ? AND revoked_at IS NULL AND result = 'adulto' LIMIT 1", [$hash]);
        if ($dono && $dono !== $steamId) return self::erro('cpf_taken', 'Este CPF já verificou outra conta.');
        if ($dono === $steamId) {
            // Ja verificado com este mesmo CPF (clique duplo, segunda aba, revogacao parcial):
            // so reaplica o status. Sem chamar o fornecedor, sem segundo INSERT no UNIQUE.
            unset($cpf, $limpo);
            Database::query("UPDATE players SET age_status = 'adulto_verificado', age_verified_at = COALESCE(age_verified_at, NOW()) WHERE steam_id = ?", [$steamId]);
            return ['ok' => true, 'status' => 'adulto_verificado', 'erro' => null, 'cod' => null];
        }

        $v = AgeVerifierFactory::fromSettings();
        $r = $v->verify($limpo, $nascIso);
        unset($cpf, $limpo);   // o CPF acaba aqui

        self::garantePlayer($steamId);
        $ano = $nascIso ? (int) substr($nascIso, 0, 4) : null;
        $provider = (string) Settings::get('age_provider', 'flagcheck');
        if ($r['result'] === 'falhou') {
            self::registra($steamId, $provider, 'falhou', $ano, null, null, substr((string) $r['error'], 0, 40));
            error_log('[eca] verificacao falhou para ' . $steamId . ': ' . $r['error']);
            return self::erro('failed_retry', 'Não foi possível verificar agora. Tente de novo em instantes.');
        }
        $adulto = $r['result'] === 'adulto';
        try {
            self::registra($steamId, $provider, $adulto ? 'adulto' : 'menor', $ano, $adulto ? $hash : null, $r['ref'], $r['status']);
        } catch (\PDOException $e) {
            // Corrida no UNIQUE (duas requisicoes com o mesmo CPF ao mesmo tempo): a outra
            // ganhou. Nao e 500: e "CPF ja usado" ou, se foi a propria conta, sucesso.
            if ((string) $e->getCode() !== '23000') throw $e;
            $quem = Database::fetchColumn("SELECT steam_id FROM age_verifications WHERE cpf_hash = ? AND revoked_at IS NULL LIMIT 1", [$hash]);
            if ($quem !== $steamId) return self::erro('cpf_taken', 'Este CPF já verificou outra conta.');
        }
        Database::query("UPDATE players SET age_status = ?, age_verified_at = ? WHERE steam_id = ?",
            [$adulto ? 'adulto_verificado' : 'menor', $adulto ? date('Y-m-d H:i:s') : null, $steamId]);
        return ['ok' => true, 'status' => $adulto ? 'adulto_verificado' : 'menor', 'erro' => null, 'cod' => null];
    }

    public static function consentir(string $steamId, string $kind, string $version, string $texto): void
    {
        Database::query(
            "INSERT INTO consents (steam_id, kind, version, text_hash, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)",
            [$steamId, $kind, $version, hash('sha256', $texto), RateLimit::clientIp(),
             substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
        );
    }

    /** Registra termos + privacidade + idade de uma vez, na versao atual. Uma linha por tipo. */
    public static function consentirTudo(string $steamId, string $texto): void
    {
        $versao = (string) Settings::get('terms_version', '1');
        foreach (['termos', 'privacidade', 'idade'] as $k) self::consentir($steamId, $k, $versao, $texto);
    }

    public static function temConsentimento(string $steamId, string $kind, string $version): bool
    {
        try {
            return (bool) Database::fetchColumn("SELECT 1 FROM consents WHERE steam_id = ? AND kind = ? AND version = ? LIMIT 1", [$steamId, $kind, $version]);
        } catch (\Throwable $e) { return false; }
    }

    /** Admin: revoga uma verificacao. O hash sai do UNIQUE (vai pra cpf_hash_revoked) e o jogador volta a declarado. */
    public static function revogar(int $id, string $motivo): bool
    {
        $row = Database::fetchOne("SELECT id, steam_id FROM age_verifications WHERE id = ? AND revoked_at IS NULL LIMIT 1", [$id]);
        if (!$row) return false;
        Database::query(
            "UPDATE age_verifications SET cpf_hash_revoked = cpf_hash, cpf_hash = NULL, revoked_at = NOW(), revoked_reason = ? WHERE id = ?",
            [substr($motivo, 0, 160), $id]);
        $restam = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM age_verifications WHERE steam_id = ? AND result = 'adulto' AND method <> 'declaracao' AND revoked_at IS NULL", [$row['steam_id']]);
        if ($restam === 0) {
            Database::query("UPDATE players SET age_status = 'adulto_declarado', age_verified_at = NULL WHERE steam_id = ? AND age_status = 'adulto_verificado'", [$row['steam_id']]);
        }
        return true;
    }
}
