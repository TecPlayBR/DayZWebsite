<?php
declare(strict_types=1);

namespace App;

/**
 * Codigo de 6 digitos que muda a cada 30 segundos (TOTP, RFC 6238, HMAC-SHA1), o mesmo que o
 * Google Authenticator, Microsoft Authenticator, Authy e afins geram. Sem banco e sem estado:
 * quem guarda segredo e ultimo passo usado e a DoisFatores.
 */
final class Totp
{
    public const PERIODO = 30;
    public const DIGITOS = 6;
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            if ($c === '') continue;
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $grupo) {
            if ($grupo === '') continue;
            $out .= self::ALFABETO[bindec(str_pad($grupo, 5, '0'))];
        }
        return $out;
    }

    /** Aceita minusculo, espaco e '=' (o app e o usuario escrevem assim). Invalido devolve ''. */
    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[\s=]+/', '', $b32));
        if ($b32 === '' || !preg_match('/^[A-Z2-7]+$/', $b32)) return '';
        $bits = '';
        foreach (str_split($b32) as $c) $bits .= str_pad(decbin(strpos(self::ALFABETO, $c)), 5, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr(bindec($byte));
        }
        return $out;
    }

    /** 160 bits aleatorios (tamanho recomendado pela RFC 4226), em base32. */
    public static function novoSegredo(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function passoAtual(?int $agora = null): int
    {
        return intdiv($agora ?? time(), self::PERIODO);
    }

    public static function codigo(string $segredoB32, int $passo, int $digitos = self::DIGITOS): string
    {
        $chave = self::base32Decode($segredoB32);
        $msg = pack('N*', ($passo >> 32) & 0xFFFFFFFF, $passo & 0xFFFFFFFF);
        $h = hash_hmac('sha1', $msg, $chave, true);
        $o = ord($h[19]) & 0x0F;
        $num = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string) ($num % (10 ** $digitos)), $digitos, '0', STR_PAD_LEFT);
    }

    /**
     * Devolve o passo que bateu (pra gravar como "ultimo usado") ou null. Tolera 1 passo (30s)
     * pra cada lado, porque relogio de celular escorrega. Passo <= $ultimoPasso nunca vale: o
     * mesmo codigo nao entra duas vezes.
     */
    public static function verificar(string $segredoB32, string $codigo, ?int $ultimoPasso, ?int $agora = null): ?int
    {
        $codigo = preg_replace('/\s+/', '', $codigo);
        if (!preg_match('/^\d{' . self::DIGITOS . '}$/', $codigo) || self::base32Decode($segredoB32) === '') return null;
        $atual = self::passoAtual($agora);
        foreach ([0, -1, 1] as $d) {
            $p = $atual + $d;
            if ($ultimoPasso !== null && $p <= $ultimoPasso) continue;
            if (hash_equals(self::codigo($segredoB32, $p), $codigo)) return $p;
        }
        return null;
    }

    /** Endereco que vira o QR code lido pelo app. */
    public static function uri(string $emissor, string $conta, string $segredoB32): string
    {
        $rotulo = rawurlencode($emissor) . ':' . rawurlencode($conta);
        return 'otpauth://totp/' . $rotulo . '?secret=' . $segredoB32 . '&issuer=' . rawurlencode($emissor)
             . '&algorithm=SHA1&digits=' . self::DIGITOS . '&period=' . self::PERIODO;
    }
}
