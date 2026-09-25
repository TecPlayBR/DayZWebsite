<?php
// ============================================================
// AgeVerification - grava e le o estado de idade de um jogador.
// ============================================================
// Regra esta em AgeGate; fornecedor esta em AgeVerifier. Aqui e so persistencia e
// orquestracao. O CPF entra em verificar(), vira hash, vai pro fornecedor e some.
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

    /** Passo 1 do fluxo: data de nascimento. Nunca chama fornecedor. */
    public static function declarar(string $steamId, string $nascimento): array
    {
        $st = AgeGate::statusPorNascimento($nascimento);
        if ($st === null) return ['ok' => false, 'status' => null, 'erro' => 'Data de nascimento inválida.'];
        $ano = (int) substr(preg_replace('#^(\d{2})/(\d{2})/(\d{4})$#', '$3-$2-$1', trim($nascimento)), 0, 4);
        self::garantePlayer($steamId);
        $atual = self::statusDe($steamId);
        self::registra($steamId, 'declaracao', $st === 'menor' ? 'menor' : 'adulto', $ano, null, null, null);
        // Nao rebaixa quem ja e verificado; menor sempre vence.
        if ($st === 'menor' || $atual !== 'adulto_verificado') {
            Database::query("UPDATE players SET age_status = ? WHERE steam_id = ?", [$st, $steamId]);
        }
        return ['ok' => true, 'status' => $st, 'erro' => null];
    }

    /** Passo 2: CPF no fornecedor. O CPF nao sobrevive a esta funcao. */
    public static function verificar(string $steamId, string $cpf, ?string $nascimento): array
    {
        $limpo = AgeGate::cpfLimpo($cpf);
        if ($limpo === null) return ['ok' => false, 'status' => null, 'erro' => 'CPF inválido. Confira os 11 dígitos.'];
        $nascIso = null;
        if ($nascimento !== null && $nascimento !== '') {
            if (AgeGate::idade($nascimento) === null) return ['ok' => false, 'status' => null, 'erro' => 'Data de nascimento inválida.'];
            $nascIso = preg_replace('#^(\d{2})/(\d{2})/(\d{4})$#', '$3-$2-$1', trim($nascimento));
        }
        $sal = (string) Settings::get('age_hash_salt', '');
        if ($sal === '') return ['ok' => false, 'status' => null, 'erro' => 'Verificação indisponível: rode o /update.php.'];
        $hash = AgeGate::cpfHash($limpo, $sal);

        // Um CPF, uma conta por site. Checa ANTES de gastar a consulta.
        $dono = Database::fetchColumn(
            "SELECT steam_id FROM age_verifications WHERE cpf_hash = ? AND revoked_at IS NULL AND result = 'adulto' LIMIT 1", [$hash]);
        if ($dono && $dono !== $steamId) return ['ok' => false, 'status' => null, 'erro' => 'Este CPF já verificou outra conta.'];

        $v = AgeVerifierFactory::fromSettings();
        $r = $v->verify($limpo, $nascIso);
        unset($cpf, $limpo);   // o CPF acaba aqui

        self::garantePlayer($steamId);
        $ano = $nascIso ? (int) substr($nascIso, 0, 4) : null;
        if ($r['result'] === 'falhou') {
            self::registra($steamId, (string) Settings::get('age_provider', 'flagcheck'), 'falhou', $ano, null, null, substr((string) $r['error'], 0, 40));
            error_log('[eca] verificacao falhou para ' . $steamId . ': ' . $r['error']);
            return ['ok' => false, 'status' => null, 'erro' => 'Não foi possível verificar agora: ' . $r['error'] . ' Tente de novo em instantes.'];
        }
        $adulto = $r['result'] === 'adulto';
        self::registra($steamId, (string) Settings::get('age_provider', 'flagcheck'), $adulto ? 'adulto' : 'menor', $ano, $adulto ? $hash : null, $r['ref'], $r['status']);
        Database::query("UPDATE players SET age_status = ?, age_verified_at = ? WHERE steam_id = ?",
            [$adulto ? 'adulto_verificado' : 'menor', $adulto ? date('Y-m-d H:i:s') : null, $steamId]);
        return ['ok' => true, 'status' => $adulto ? 'adulto_verificado' : 'menor', 'erro' => null];
    }

    public static function consentir(string $steamId, string $kind, string $version, string $texto): void
    {
        Database::query(
            "INSERT INTO consents (steam_id, kind, version, text_hash, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)",
            [$steamId, $kind, $version, hash('sha256', $texto), RateLimit::clientIp(),
             substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
        );
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
