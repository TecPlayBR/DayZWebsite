<?php
declare(strict_types=1);

namespace App;

/**
 * Login em duas etapas OPCIONAL por admin (3.4.0). Cada admin liga na propria conta; quem nao
 * ligar entra so com a senha.
 *
 * - O segredo do app autenticador fica CIFRADO no banco (AES-256-GCM). A chave mora num arquivo
 *   em storage/keys/2fa.key, fora do banco e fora da parte publica, criado no primeiro uso. O
 *   update.php nao mexe em storage/.
 * - Os 8 codigos de recuperacao sao de uso unico e ficam so como hash (bcrypt: o codigo tem pouca
 *   entropia, hash rapido seria quebrado se o banco vazasse).
 * - Se o arquivo da chave se perder, o app para de funcionar mas o codigo de recuperacao continua
 *   entrando (nao depende da chave), e o dono desliga a protecao do admin pela tela da equipe.
 */
final class DoisFatores
{
    public const QTD_CODIGOS = 8;
    private const ALFABETO_CODIGO = 'abcdefghjkmnpqrstuvwxyz23456789';   // sem 0/o, 1/l/i
    private static ?string $dirChave = null;

    /** So pra teste: aponta a chave pra outra pasta. */
    public static function usarDirChave(?string $dir): void { self::$dirChave = $dir; }

    private static function chave(): string
    {
        $dir = self::$dirChave ?? (dirname(__DIR__) . '/storage/keys');
        $arq = $dir . '/2fa.key';
        if (is_file($arq)) {
            $hex = trim((string) file_get_contents($arq));
            if (preg_match('/^[0-9a-f]{64}$/', $hex)) return hex2bin($hex);
        }
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        $bin = random_bytes(32);
        if (@file_put_contents($arq, bin2hex($bin), LOCK_EX) === false) {
            throw new \RuntimeException('nao consegui gravar a chave das duas etapas em ' . $dir);
        }
        @chmod($arq, 0600);
        return $bin;
    }

    public static function cifrar(string $texto): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($texto, 'aes-256-gcm', self::chave(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ct === false) throw new \RuntimeException('falha ao cifrar o segredo');
        return 'v1:' . base64_encode($iv . $tag . $ct);
    }

    public static function decifrar(?string $blob): ?string
    {
        if (!is_string($blob) || !str_starts_with($blob, 'v1:')) return null;
        $raw = base64_decode(substr($blob, 3), true);
        if ($raw === false || strlen($raw) < 29) return null;
        $txt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::chave(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $txt === false ? null : $txt;
    }

    /** [codigos em texto (mostrar UMA vez), hashes (gravar)]. Formato abcd-efgh. */
    private static function novosCodigos(): array
    {
        $texto = [];
        while (count($texto) < self::QTD_CODIGOS) {
            $c = '';
            for ($i = 0; $i < 8; $i++) $c .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
            $texto[substr($c, 0, 4) . '-' . substr($c, 4)] = true;
        }
        $texto = array_keys($texto);
        return [$texto, array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $texto)];
    }

    private static function normalizarCodigo(string $e): string
    {
        $e = strtolower(preg_replace('/[\s-]+/', '', $e));
        return strlen($e) === 8 ? substr($e, 0, 4) . '-' . substr($e, 4) : $e;
    }

    private static function linha(int $adminId): ?array
    {
        return Database::fetchOne(
            "SELECT id, totp_secret, totp_enabled, totp_last_step, totp_recovery, totp_enabled_at FROM admin_users WHERE id = ? LIMIT 1",
            [$adminId]
        );
    }

    public static function ativoPara(int $adminId): bool
    {
        $l = self::linha($adminId);
        return $l !== null && (int) $l['totp_enabled'] === 1;
    }

    /** ['ativo' => bool, 'codigos_restantes' => int, 'desde' => ?string] */
    public static function estado(int $adminId): array
    {
        $l = self::linha($adminId);
        $h = $l ? json_decode((string) ($l['totp_recovery'] ?? ''), true) : null;
        return [
            'ativo'             => $l !== null && (int) $l['totp_enabled'] === 1,
            'codigos_restantes' => is_array($h) ? count($h) : 0,
            'desde'             => $l['totp_enabled_at'] ?? null,
        ];
    }

    /** Liga se o codigo bater com o segredo novo. Devolve os codigos de recuperacao ou null. */
    public static function ativar(int $adminId, string $segredoB32, string $codigo): ?array
    {
        $passo = Totp::verificar($segredoB32, $codigo, null);
        if ($passo === null) return null;
        [$texto, $hashes] = self::novosCodigos();
        Database::query(
            "UPDATE admin_users SET totp_secret = ?, totp_enabled = 1, totp_last_step = ?, totp_recovery = ?, totp_enabled_at = NOW() WHERE id = ?",
            [self::cifrar($segredoB32), $passo, json_encode($hashes), $adminId]
        );
        return $texto;
    }

    /**
     * Confere o codigo do app (6 digitos) OU um codigo de recuperacao. Codigo do app nao entra duas
     * vezes; codigo de recuperacao some depois de usado. As duas gravacoes sao condicionais, pra
     * dois logins ao mesmo tempo nao aproveitarem o mesmo codigo.
     */
    public static function conferir(int $adminId, string $entrada): bool
    {
        $l = self::linha($adminId);
        if (!$l || (int) $l['totp_enabled'] !== 1) return false;
        $e = trim($entrada);

        if (preg_match('/^\d{3}\s?\d{3}$/', $e)) {
            $segredo = self::decifrar($l['totp_secret']);
            if ($segredo === null) return false;
            $ultimo = $l['totp_last_step'] !== null ? (int) $l['totp_last_step'] : null;
            $passo = Totp::verificar($segredo, $e, $ultimo);
            if ($passo === null) return false;
            return Database::execute(
                "UPDATE admin_users SET totp_last_step = ? WHERE id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)",
                [$passo, $adminId, $passo]
            ) > 0;
        }

        $codigo = self::normalizarCodigo($e);
        if (!preg_match('/^[a-z0-9]{4}-[a-z0-9]{4}$/', $codigo)) return false;
        $antes = (string) ($l['totp_recovery'] ?? '');
        $hashes = json_decode($antes, true);
        if (!is_array($hashes)) return false;
        foreach ($hashes as $i => $h) {
            if (is_string($h) && password_verify($codigo, $h)) {
                unset($hashes[$i]);
                return Database::execute(
                    "UPDATE admin_users SET totp_recovery = ? WHERE id = ? AND totp_recovery = ?",
                    [json_encode(array_values($hashes)), $adminId, $antes]
                ) > 0;
            }
        }
        return false;
    }

    public static function regenerarCodigos(int $adminId): array
    {
        [$texto, $hashes] = self::novosCodigos();
        Database::query("UPDATE admin_users SET totp_recovery = ? WHERE id = ? AND totp_enabled = 1", [json_encode($hashes), $adminId]);
        return $texto;
    }

    public static function desligar(int $adminId): void
    {
        Database::query(
            "UPDATE admin_users SET totp_secret = NULL, totp_enabled = 0, totp_last_step = NULL, totp_recovery = NULL, totp_enabled_at = NULL WHERE id = ?",
            [$adminId]
        );
    }
}
