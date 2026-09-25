<?php
// ============================================================
// AgeVerifier - fornecedor externo que confirma se um CPF e de maior de idade.
// ============================================================
// Tres drivers. O CPF entra por parametro, vai na requisicao e MORRE aqui: nenhum
// driver loga, guarda ou devolve o CPF. O que sobe pro banco e so ['result','ref','status'].
// HTTP e injetavel (callable) pra teste sem rede; o padrao e curl com 8 s de timeout.
// ============================================================

namespace App;

interface AgeVerifier
{
    /** @return array{result:string, ref:?string, status:?string, error:?string} */
    public function verify(string $cpf, ?string $nascimentoIso): array;
    /** @return array{ok:bool, msg:string} */
    public function test(): array;
    public function nome(): string;
}

abstract class AgeVerifierBase implements AgeVerifier
{
    protected string $key;
    protected string $secret;
    /** @var callable */
    protected $http;

    public function __construct(string $key, string $secret, ?callable $http)
    {
        $this->key = trim($key);
        $this->secret = trim($secret);
        $this->http = $http ?? [self::class, 'curl'];
    }

    protected static function falhou(string $motivo): array
    {
        return ['result' => 'falhou', 'ref' => null, 'status' => null, 'error' => $motivo];
    }

    protected static function porNascimento(?string $ddmmaaaaOuIso, string $ref, ?string $status): array
    {
        if ($ddmmaaaaOuIso === null || $ddmmaaaaOuIso === '') return self::falhou('fornecedor nao devolveu data de nascimento');
        $n = $ddmmaaaaOuIso;
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $n, $m)) $n = "{$m[3]}-{$m[2]}-{$m[1]}";   // DDMMAAAA (Serpro)
        $st = AgeGate::statusPorNascimento($n);
        if ($st === null) return self::falhou('data de nascimento invalida no retorno');
        return ['result' => $st === 'menor' ? 'menor' : 'adulto', 'ref' => $ref, 'status' => $status, 'error' => null];
    }

    /** @return array{status:int, body:string} */
    public static function curl(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $out = curl_exec($ch);
        $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $out === false ? 0 : $st, 'body' => (string) $out];
    }

    protected function req(string $method, string $url, array $headers, ?string $body = null): array
    {
        try {
            $r = ($this->http)($method, $url, $headers, $body);
            return is_array($r) ? $r : ['status' => 0, 'body' => ''];
        } catch (\Throwable $e) {
            return ['status' => 0, 'body' => ''];
        }
    }

    protected static function json(array $resp): ?array
    {
        if (($resp['status'] ?? 0) < 200 || $resp['status'] >= 300) return null;
        $d = json_decode((string) ($resp['body'] ?? ''), true);
        return is_array($d) ? $d : null;
    }
}

/** FlagCheck: POST /v1/verify/age {cpf} -> {maior_de_18, status_cpf, audit_token}. So devolve o minimo. */
class FlagCheckVerifier extends AgeVerifierBase
{
    public const URL = 'https://api.flagcheck.com.br/v1/verify/age';
    public function nome(): string { return 'FlagCheck'; }

    public function verify(string $cpf, ?string $nascimentoIso): array
    {
        if ($this->key === '') return self::falhou('chave do FlagCheck nao configurada');
        $r = $this->req('POST', self::URL,
            ['Authorization: Bearer ' . $this->key, 'Content-Type: application/json', 'Accept: application/json'],
            json_encode(['cpf' => $cpf]));
        if (($r['status'] ?? 0) === 401 || ($r['status'] ?? 0) === 403) return self::falhou('chave do FlagCheck recusada');
        $d = self::json($r);
        if ($d === null || !array_key_exists('maior_de_18', $d)) return self::falhou('resposta invalida do FlagCheck (HTTP ' . (int) ($r['status'] ?? 0) . ')');
        return ['result' => $d['maior_de_18'] ? 'adulto' : 'menor',
                'ref' => isset($d['audit_token']) ? substr((string) $d['audit_token'], 0, 120) : null,
                'status' => isset($d['status_cpf']) ? substr((string) $d['status_cpf'], 0, 40) : null,
                'error' => null];
    }

    public function test(): array
    {
        if ($this->key === '') return ['ok' => false, 'msg' => 'Cole a chave da API do FlagCheck.'];
        // CPF de teste publico (gerador da Receita): nao pertence a ninguem.
        $r = $this->verify('52998224725', null);
        return $r['result'] === 'falhou' ? ['ok' => false, 'msg' => (string) $r['error']] : ['ok' => true, 'msg' => 'FlagCheck respondeu.'];
    }
}

/** Serpro Consulta CPF v3: OAuth2 client_credentials + GET /consulta-cpf-df/v3/{cpf}?dataNascimento=DDMMAAAA. */
class SerproVerifier extends AgeVerifierBase
{
    public const TOKEN = 'https://gateway.apiserpro.serpro.gov.br/token';
    public const BASE  = 'https://gateway.apiserpro.serpro.gov.br/consulta-cpf-df/v3/';
    public function nome(): string { return 'Serpro'; }

    private function token(): ?string
    {
        $r = $this->req('POST', self::TOKEN,
            ['Authorization: Basic ' . base64_encode($this->key . ':' . $this->secret), 'Content-Type: application/x-www-form-urlencoded'],
            'grant_type=client_credentials');
        $d = self::json($r);
        return isset($d['access_token']) ? (string) $d['access_token'] : null;
    }

    public function verify(string $cpf, ?string $nascimentoIso): array
    {
        if ($this->key === '' || $this->secret === '') return self::falhou('consumer key/secret do Serpro nao configurados');
        $tk = $this->token();
        if ($tk === null) return self::falhou('Serpro recusou as credenciais (token)');
        $ddmmaaaa = '';
        if ($nascimentoIso && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nascimentoIso, $m)) $ddmmaaaa = $m[3] . $m[2] . $m[1];
        $r = $this->req('GET', self::BASE . $cpf . ($ddmmaaaa ? '?dataNascimento=' . $ddmmaaaa : ''),
            ['Authorization: Bearer ' . $tk, 'Accept: application/json']);
        $d = self::json($r);
        if ($d === null) return self::falhou('Serpro nao respondeu (HTTP ' . (int) ($r['status'] ?? 0) . ')');
        $status = isset($d['situacao']['descricao']) ? substr((string) $d['situacao']['descricao'], 0, 40) : null;
        // ref: nunca o CPF. Um carimbo de quando e por qual metodo, suficiente pra auditoria.
        $ref = 'serpro-v3-' . gmdate('YmdHis');
        return self::porNascimento($d['nascimento'] ?? null, $ref, $status);
    }

    public function test(): array
    {
        if ($this->key === '' || $this->secret === '') return ['ok' => false, 'msg' => 'Cole consumer key e consumer secret do Serpro.'];
        return $this->token() ? ['ok' => true, 'msg' => 'Serpro emitiu token.'] : ['ok' => false, 'msg' => 'Serpro recusou as credenciais.'];
    }
}

/** CPFHub: GET /cpf/{cpf} -> {name, gender, birthDate DD/MM/AAAA}. Base nao e tempo real: documentado como opcao barata. */
class CpfHubVerifier extends AgeVerifierBase
{
    public const BASE = 'https://api.cpfhub.io/cpf/';
    public function nome(): string { return 'CPFHub'; }

    public function verify(string $cpf, ?string $nascimentoIso): array
    {
        if ($this->key === '') return self::falhou('chave do CPFHub nao configurada');
        $r = $this->req('GET', self::BASE . $cpf, ['x-api-key: ' . $this->key, 'Accept: application/json']);
        if (($r['status'] ?? 0) === 401 || ($r['status'] ?? 0) === 403) return self::falhou('chave do CPFHub recusada');
        $d = self::json($r);
        if ($d === null || empty($d['birthDate'])) return self::falhou('resposta invalida do CPFHub (HTTP ' . (int) ($r['status'] ?? 0) . ')');
        return self::porNascimento((string) $d['birthDate'], 'cpfhub-' . gmdate('YmdHis'), 'ok');
    }

    public function test(): array
    {
        if ($this->key === '') return ['ok' => false, 'msg' => 'Cole a chave da API do CPFHub.'];
        $r = $this->verify('52998224725', null);
        return $r['result'] === 'falhou' ? ['ok' => false, 'msg' => (string) $r['error']] : ['ok' => true, 'msg' => 'CPFHub respondeu.'];
    }
}

class AgeVerifierFactory
{
    public const PROVIDERS = ['flagcheck' => 'FlagCheck', 'serpro' => 'Serpro (Consulta CPF v3)', 'cpfhub' => 'CPFHub'];

    public static function make(string $provider, string $key, string $secret, ?callable $http = null): AgeVerifier
    {
        switch ($provider) {
            case 'flagcheck': return new FlagCheckVerifier($key, $secret, $http);
            case 'serpro':    return new SerproVerifier($key, $secret, $http);
            case 'cpfhub':    return new CpfHubVerifier($key, $secret, $http);
        }
        throw new \InvalidArgumentException('fornecedor de verificacao desconhecido: ' . $provider);
    }

    /** Monta a partir das settings do site. */
    public static function fromSettings(?callable $http = null): AgeVerifier
    {
        return self::make((string) Settings::get('age_provider', 'flagcheck'),
                          (string) Settings::get('age_provider_key', ''),
                          (string) Settings::get('age_provider_secret', ''), $http);
    }
}
