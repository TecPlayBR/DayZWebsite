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
    /** CPF nulo: invalido por construcao, nao pertence a ninguem. Usado so pra testar a chave. */
    public const CPF_NULO = '00000000000';
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

/**
 * FlagCheck, contrato real (docs de 25/09/2026, flagcheck.com.br/blog/age-verification-api-brazil):
 *   POST https://api.flagcheck.com.br/api/felca/age-check   header X-API-Key   body {"cpf": "..."}
 *   -> {"success": true, "data": {"is_adult": bool, "age", "date_of_birth", "document": {"type","valid"}},
 *       "meta": {"request_id": "felca_...", "timestamp": ISO}}
 * Para menor, age e date_of_birth vem omitidos (LGPD do proprio fornecedor). So guardamos
 * is_adult, document.valid e o request_id (e o que a ANPD pede pro log de auditoria).
 */
class FlagCheckVerifier extends AgeVerifierBase
{
    public const URL = 'https://api.flagcheck.com.br/api/felca/age-check';
    public function nome(): string { return 'FlagCheck'; }

    private function headers(): array
    {
        return ['X-API-Key: ' . $this->key, 'Content-Type: application/json', 'Accept: application/json'];
    }

    public function verify(string $cpf, ?string $nascimentoIso): array
    {
        if ($this->key === '') return self::falhou('chave do FlagCheck nao configurada');
        $r = $this->req('POST', self::URL, $this->headers(), json_encode(['cpf' => $cpf]));
        // Codigos documentados em /api-parceiros. Nenhum destes e cobrado pelo fornecedor.
        switch ((int) ($r['status'] ?? 0)) {
            case 401: case 403: return self::falhou('chave do FlagCheck recusada');
            case 402: return self::falhou('FlagCheck sem creditos: o site precisa adicionar saldo');
            case 404: return self::falhou('CPF nao encontrado na base do FlagCheck');
            case 422: return self::falhou('CPF invalido para o FlagCheck');
        }
        $d = self::json($r);
        if ($d === null) return self::falhou('resposta invalida do FlagCheck (HTTP ' . (int) ($r['status'] ?? 0) . ')');
        // Duas paginas deles mostram formatos diferentes: com envelope {success,data:{is_adult}}
        // e sem envelope {is_adult}. Aceita os dois; recusa success:false explicito.
        if (array_key_exists('success', $d) && empty($d['success'])) {
            $motivo = isset($d['error']) ? substr((string) (is_array($d['error']) ? json_encode($d['error']) : $d['error']), 0, 80) : 'sem detalhe';
            return self::falhou('FlagCheck nao confirmou: ' . $motivo);
        }
        $dados = (isset($d['data']) && is_array($d['data'])) ? $d['data'] : $d;
        if (!array_key_exists('is_adult', $dados)) return self::falhou('FlagCheck nao devolveu is_adult');
        $valid = $dados['document']['valid'] ?? null;
        return ['result' => $dados['is_adult'] ? 'adulto' : 'menor',
                'ref' => isset($d['meta']['request_id']) ? substr((string) $d['meta']['request_id'], 0, 120) : null,
                'status' => $valid === null ? null : ($valid ? 'valid' : 'invalid'),
                'error' => null];
    }

    public function test(): array
    {
        if ($this->key === '') return ['ok' => false, 'msg' => 'Cole a chave da API do FlagCheck.'];
        // Testa SO a chave: manda o CPF nulo (000.000.000-00, nao e de ninguem). O fornecedor
        // responde 4xx/success:false com a chave boa e 401/403 com a chave ruim. Nenhuma
        // pessoa e consultada.
        $r = $this->req('POST', self::URL, $this->headers(), json_encode(['cpf' => self::CPF_NULO]));
        $st = (int) ($r['status'] ?? 0);
        if ($st === 401 || $st === 403) return ['ok' => false, 'msg' => 'Chave do FlagCheck recusada.'];
        if ($st === 0) return ['ok' => false, 'msg' => 'FlagCheck nao respondeu (rede ou timeout).'];
        return ['ok' => true, 'msg' => 'FlagCheck aceitou a chave (HTTP ' . $st . ').'];
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
        switch ((int) ($r['status'] ?? 0)) {
            case 401: case 403: return self::falhou('chave do CPFHub recusada');
            case 404: return self::falhou('CPF nao encontrado na base do CPFHub');
            case 429: return self::falhou('CPFHub: limite de consultas do plano atingido');
        }
        $d = self::json($r);
        if ($d === null) return self::falhou('resposta invalida do CPFHub (HTTP ' . (int) ($r['status'] ?? 0) . ')');
        // Contrato real (docs de 25/09): {"success": true, "data": {"birthDate": "DD/MM/AAAA", ...}}.
        // Aceita tambem sem envelope, por compatibilidade.
        if (array_key_exists('success', $d) && empty($d['success'])) {
            return self::falhou('CPFHub nao confirmou: ' . substr((string) ($d['message'] ?? $d['error'] ?? 'sem detalhe'), 0, 80));
        }
        $dados = (isset($d['data']) && is_array($d['data'])) ? $d['data'] : $d;
        if (empty($dados['birthDate'])) return self::falhou('CPFHub nao devolveu data de nascimento');
        return self::porNascimento((string) $dados['birthDate'], 'cpfhub-' . gmdate('YmdHis'), 'ok');
    }

    public function test(): array
    {
        if ($this->key === '') return ['ok' => false, 'msg' => 'Cole a chave da API do CPFHub.'];
        // So a chave, com o CPF nulo (ver FlagCheckVerifier::test).
        $r = $this->req('GET', self::BASE . self::CPF_NULO, ['x-api-key: ' . $this->key, 'Accept: application/json']);
        $st = (int) ($r['status'] ?? 0);
        if ($st === 401 || $st === 403) return ['ok' => false, 'msg' => 'Chave do CPFHub recusada.'];
        if ($st === 0) return ['ok' => false, 'msg' => 'CPFHub nao respondeu (rede ou timeout).'];
        return ['ok' => true, 'msg' => 'CPFHub aceitou a chave (HTTP ' . $st . ').'];
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
        return self::make((string) Settings::get('age_provider', 'cpfhub'),
                          (string) Settings::get('age_provider_key', ''),
                          (string) Settings::get('age_provider_secret', ''), $http);
    }
}
