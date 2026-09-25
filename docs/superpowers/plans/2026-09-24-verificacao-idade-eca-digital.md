# Verificação de idade e consentimento (ECA Digital) - plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fechar as caixas de recompensa para quem não verificou idade por fonte externa, exigir declaração de idade e consentimento real para comprar, e dar ao admin o painel e o relatório de conformidade, tudo entregue por uma migration no `/update.php`.

**Architecture:** Uma classe pura `AgeGate` decide (sem banco) o que cada estado pode fazer; uma camada fina `AgeVerification` grava e lê `age_verifications`/`consents`/`players.age_status`; um `AgeVerifier` plugável (FlagCheck, Serpro, CPFHub) com HTTP injetável fala com o fornecedor e nunca deixa o CPF sair da requisição. As rotas existentes (checkout, cartão, abrir caixa, bot) só consultam `AgeGate` e redirecionam para `/idade`.

**Tech Stack:** PHP 8.3 sem framework (Router próprio, `Database` PDO, `Settings` com SCHEMA), MariaDB, views PHP + `lang/*.php`, testes em `tests/*.php` (scripts com `ok()`/`falha()`, rodam com `php tests/arquivo.php`, sem banco).

**Spec:** `docs/superpowers/specs/2026-09-24-verificacao-idade-eca-digital-design.md`

## Global Constraints

- PHP 8.3, sem dependência nova (curl nativo, como `MercadoPago.php`).
- **CPF nunca é gravado, logado ou posto em sessão.** Só `cpf_hash = sha256(sal . cpf)`.
- Registro de verificação guarda **só metadados** (resultado, método, hora, `provider_ref`, `provider_status`, ano de nascimento).
- Texto ao jogador e ao admin em PT-BR, **sem travessão** (hífen, vírgula ou dois-pontos), e com chave em `lang/pt-br.php` + `lang/en-us.php` para tudo que o jogador vê.
- Nome do produto: "Proteção de menores (ECA Digital)". Versão: **3.3.0**. Migration: `migrations/v3.3.0_eca_digital.sql`, idempotente (`IF NOT EXISTS` em tudo).
- Padrão de settings: chave nova entra no `Settings::SCHEMA` **e** no whitelist do `POST /admin/settings`, senão salvar é no-op silencioso (bug já vivido).
- Toda ação de admin passa por `\App\AuditLog::record(acao, tipo, id, payload)`.
- Commits sem linha de coautoria. Mensagens em PT-BR, prefixo `feat(eca):`, `fix(eca):`, `test(eca):`, `docs(eca):`.
- Não tocar em `views/pages/streamer*.php`, nem em nada do 3.2.7 pendente do Bryan além do listado aqui.

## Review Focus

Entradas que a spec implica, ninguém testa por padrão, e que vão morder alguém:

1. **Jogador faz 18 anos hoje.** `AgeGate::idade()` tem que devolver 18 no dia do aniversário, não 17. Teste em Task 2.
2. **Data de nascimento em formato errado ou impossível** (31/02, ano 1800, ano futuro, "abc"). Tem que recusar com mensagem, nunca virar `menor` nem `adulto`. Teste em Task 2.
3. **Fornecedor caiu (timeout, 5xx, JSON quebrado, chave inválida).** Resultado `falhou`, jogador vê mensagem e pode tentar de novo, nada muda no `age_status`, admin recebe alerta. Teste em Task 3.
4. **Mesmo CPF em duas contas**, inclusive quando a primeira foi revogada (a revogada tem que liberar o hash). Teste em Task 4.
5. **`age_gate_mode = desligado` com jogador já marcado `menor`.** Modo desligado não pode reabrir caixa para quem se declarou menor: o site já sabe. Teste em Task 2.

---

### Task 1: Migration e settings

**Files:**
- Create: `migrations/v3.3.0_eca_digital.sql`
- Modify: `src/Settings.php:104-119` (SCHEMA)
- Test: `tests/eca-migration-e-settings.php`

**Interfaces:**
- Produces: tabelas `age_verifications`, `consents`; colunas `players.age_status`, `players.age_verified_at`; chaves de settings `age_gate_mode`, `age_provider`, `age_provider_key`, `age_provider_secret`, `age_daily_box_gated`, `age_hash_salt`, `terms_version`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php
/**
 * Garante que a migration do ECA Digital existe, e idempotente e que toda chave de
 * settings que ela cria esta no SCHEMA (senao o painel nao consegue gravar).
 * Rodar: php tests/eca-migration-e-settings.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/Settings.php';

echo "\n1. A migration existe e e idempotente\n";
$mig = $ROOT . '/migrations/v3.3.0_eca_digital.sql';
if (!file_exists($mig)) { falha('migrations/v3.3.0_eca_digital.sql nao existe'); }
$sql = file_exists($mig) ? file_get_contents($mig) : '';
foreach (['CREATE TABLE IF NOT EXISTS age_verifications', 'CREATE TABLE IF NOT EXISTS consents',
          'ADD COLUMN IF NOT EXISTS age_status', 'ADD COLUMN IF NOT EXISTS age_verified_at',
          'ADD COLUMN IF NOT EXISTS cpf_hash_revoked', 'INSERT IGNORE INTO settings'] as $trecho) {
    if (stripos($sql, $trecho) !== false) ok("tem `$trecho`"); else falha("falta `$trecho`", 'sem IF NOT EXISTS a migration quebra na segunda rodada');
}
if (preg_match('/\bcpf\b(?!_hash)/i', preg_replace('/--[^\n]*/', '', $sql))) {
    falha('a migration tem uma coluna/campo chamado `cpf` sem ser hash', 'CPF nunca e gravado');
} else { ok('nenhuma coluna guarda o CPF em claro'); }
if (stripos($sql, "'age_gate_mode', 'declaracao'") !== false) ok('modo inicial e declaracao'); else falha('modo inicial nao e declaracao');
if (stripos($sql, "'age_hash_salt', SHA2(") !== false) ok('sal do hash e gerado no banco, aleatorio por site'); else falha('sal do hash nao e gerado na migration');

echo "\n2. Toda chave nova esta no Settings::SCHEMA\n";
$esperado = ['age_gate_mode' => 'string', 'age_provider' => 'string', 'age_provider_key' => 'string',
             'age_provider_secret' => 'string', 'age_daily_box_gated' => 'bool',
             'age_hash_salt' => 'string', 'terms_version' => 'string'];
foreach ($esperado as $k => $tipo) {
    if ((\App\Settings::SCHEMA[$k] ?? null) === $tipo) ok("$k => $tipo"); else falha("$k nao esta no SCHEMA como $tipo", 'Settings::set() rejeita chave fora do SCHEMA');
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php tests/eca-migration-e-settings.php`
Expected: `FALHA migrations/v3.3.0_eca_digital.sql nao existe` e 7 FALHA de SCHEMA.

- [ ] **Step 3: Escrever a migration**

```sql
-- v3.3.0 - Protecao de menores (ECA Digital, Lei 15.211/2025 + Decreto 12.880/2026).
-- Caixa de recompensa so abre para adulto verificado por fonte externa; loja exige
-- declaracao de idade e consentimento real. Guarda SO metadados: o CPF nunca e gravado.
-- Idempotente: pode rodar duas vezes.

CREATE TABLE IF NOT EXISTS age_verifications (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    steam_id         VARCHAR(20)  NOT NULL,
    method           ENUM('declaracao','flagcheck','serpro','cpfhub') NOT NULL,
    result           ENUM('adulto','menor','falhou') NOT NULL,
    birth_year       SMALLINT     NULL,
    cpf_hash         CHAR(64)     NULL,
    cpf_hash_revoked CHAR(64)     NULL,
    provider_ref     VARCHAR(120) NULL,
    provider_status  VARCHAR(40)  NULL,
    ip               VARCHAR(45)  NULL,
    user_agent       VARCHAR(255) NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at       DATETIME     NULL,
    revoked_reason   VARCHAR(160) NULL,
    UNIQUE KEY uq_age_cpf (cpf_hash),
    KEY idx_age_player (steam_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Em MariaDB o ALTER com IF NOT EXISTS por coluna e valido.
ALTER TABLE age_verifications ADD COLUMN IF NOT EXISTS cpf_hash_revoked CHAR(64) NULL AFTER cpf_hash;

ALTER TABLE players
    ADD COLUMN IF NOT EXISTS age_status ENUM('desconhecido','menor','adulto_declarado','adulto_verificado')
        NOT NULL DEFAULT 'desconhecido',
    ADD COLUMN IF NOT EXISTS age_verified_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS consents (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    steam_id    VARCHAR(20)  NOT NULL,
    kind        ENUM('termos','privacidade','idade') NOT NULL,
    version     VARCHAR(20)  NOT NULL,
    text_hash   CHAR(64)     NOT NULL,
    ip          VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_consent_player (steam_id, kind, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings: modo nasce em 'declaracao' (caixas fechadas, loja com declaracao) e o
-- painel mostra aviso amarelo ate o cliente colar a chave do fornecedor.
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('age_gate_mode', 'declaracao'),
    ('age_provider', 'flagcheck'),
    ('age_provider_key', ''),
    ('age_provider_secret', ''),
    ('age_daily_box_gated', '1'),
    ('terms_version', '1');
-- Sal aleatorio por site: gerado UMA vez, no banco, nunca no codigo.
INSERT IGNORE INTO settings (`key`, `value`) VALUES ('age_hash_salt', SHA2(CONCAT(UUID(), RAND(), NOW(6)), 256));
```

- [ ] **Step 4: Adicionar as chaves ao SCHEMA**

Em `src/Settings.php`, logo após `'hide_online_players'      => 'bool',` (linha 111):

```php
        // Protecao de menores (ECA Digital, 3.3.0). Modo: desligado | declaracao | verificado.
        // O CPF nunca passa por aqui: so a chave do fornecedor e o sal do hash.
        'age_gate_mode'            => 'string',
        'age_provider'             => 'string',   // flagcheck | serpro | cpfhub
        'age_provider_key'         => 'string',
        'age_provider_secret'      => 'string',   // so o Serpro (OAuth2) usa
        'age_daily_box_gated'      => 'bool',     // 1 = a diaria gratis tambem exige verificacao
        'age_hash_salt'            => 'string',   // gerado na migration; nunca editar pelo painel
        'terms_version'            => 'string',   // versao dos Termos que o consentimento carimba
```

- [ ] **Step 5: Rodar e ver passar**

Run: `php tests/eca-migration-e-settings.php`
Expected: `TUDO OK`.

- [ ] **Step 6: Commit**

```bash
git add migrations/v3.3.0_eca_digital.sql src/Settings.php tests/eca-migration-e-settings.php
git commit -m "feat(eca): migration da verificacao de idade e settings da protecao de menores"
```

---

### Task 2: `AgeGate`, a regra pura

**Files:**
- Create: `src/AgeGate.php`
- Test: `tests/eca-age-gate.php`

**Interfaces:**
- Produces:
  - `AgeGate::idade(string $nascimento, ?\DateTimeImmutable $hoje = null): ?int` (aceita `AAAA-MM-DD` e `DD/MM/AAAA`; `null` se inválida ou impossível)
  - `AgeGate::statusPorNascimento(string $nascimento): ?string` (`'menor'`|`'adulto_declarado'`|`null`)
  - `AgeGate::podeComprar(string $status, string $modo): bool`
  - `AgeGate::podeAbrirCaixa(string $status, string $modo, bool $diaria, bool $diariaExige): bool`
  - `AgeGate::cpfHash(string $cpf, string $sal): string`
  - `AgeGate::cpfLimpo(string $cpf): ?string` (11 dígitos válidos ou `null`)
  - constantes `MODOS = ['desligado','declaracao','verificado']`, `STATUS = [...]`

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php
/**
 * A regra de idade em si, sem banco e sem rede. Se isto passa, o gate esta certo;
 * o resto do sistema so pergunta pra esta classe.
 * Rodar: php tests/eca-age-gate.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/AgeGate.php';
use App\AgeGate;

$hoje = new DateTimeImmutable('2026-09-24');

echo "\n1. Idade em bordas\n";
$casos = [
    ['2008-09-24', 18, 'faz 18 HOJE conta como 18'],
    ['2008-09-25', 17, 'faz 18 amanha ainda e 17'],
    ['24/09/2008', 18, 'aceita DD/MM/AAAA'],
    ['2008-02-29', 18, 'nascido em 29/02 de bissexto'],
    ['2000-01-01', 26, 'adulto comum'],
];
foreach ($casos as [$n, $esp, $d]) {
    $r = AgeGate::idade($n, $hoje);
    if ($r === $esp) ok($d); else falha($d, "esperava $esp, veio " . var_export($r, true));
}

echo "\n2. Data invalida ou impossivel nunca vira idade\n";
foreach (['', 'abc', '31/02/2005', '2005-13-01', '1800-01-01', '2030-01-01', '2026-09-25', '05/2005'] as $n) {
    if (AgeGate::idade($n, $hoje) === null) ok("recusa '$n'"); else falha("aceitou '$n'", 'data impossivel nao pode virar menor nem adulto');
}
if (AgeGate::statusPorNascimento('abc', $hoje) === null) ok('statusPorNascimento devolve null em data invalida'); else falha('statusPorNascimento inventou status');
if (AgeGate::statusPorNascimento('2010-01-01', $hoje) === 'menor') ok('16 anos = menor'); else falha('16 anos nao virou menor');
if (AgeGate::statusPorNascimento('2000-01-01', $hoje) === 'adulto_declarado') ok('26 anos = adulto_declarado'); else falha('26 anos nao virou adulto_declarado');

echo "\n3. Comprar (loja) por estado e modo\n";
$c = [
    ['desconhecido',       'declaracao', false], ['menor', 'declaracao', false],
    ['adulto_declarado',   'declaracao', true],  ['adulto_verificado', 'declaracao', true],
    ['desconhecido',       'verificado', false], ['adulto_declarado', 'verificado', true],
    ['desconhecido',       'desligado',  true],  ['menor', 'desligado', false],
];
foreach ($c as [$st, $modo, $esp]) {
    if (AgeGate::podeComprar($st, $modo) === $esp) ok("comprar: $st em $modo = " . ($esp ? 'sim' : 'nao'));
    else falha("comprar: $st em $modo deveria ser " . ($esp ? 'sim' : 'nao'));
}

echo "\n4. Abrir caixa por estado, modo e diaria\n";
$c = [
    // status, modo, diaria?, diariaExige?, esperado
    ['adulto_verificado', 'verificado', false, true,  true],
    ['adulto_declarado',  'verificado', false, true,  false],
    ['adulto_declarado',  'declaracao', false, true,  false],   // declaracao NAO abre caixa
    ['adulto_declarado',  'declaracao', true,  true,  false],   // diaria gated
    ['adulto_declarado',  'declaracao', true,  false, true],    // diaria liberada por config
    ['desconhecido',      'declaracao', true,  false, false],   // mas so pra quem declarou
    ['desconhecido',      'desligado',  false, true,  true],    // modo desligado = como hoje
    ['menor',             'desligado',  false, true,  false],   // menor NUNCA, nem desligado
    ['menor',             'desligado',  true,  false, false],
];
foreach ($c as [$st, $modo, $di, $dx, $esp]) {
    if (AgeGate::podeAbrirCaixa($st, $modo, $di, $dx) === $esp) ok("caixa: $st/$modo/diaria=" . (int)$di . "/exige=" . (int)$dx . " = " . ($esp ? 'sim' : 'nao'));
    else falha("caixa: $st/$modo/diaria=" . (int)$di . "/exige=" . (int)$dx . " deveria ser " . ($esp ? 'sim' : 'nao'));
}

echo "\n5. CPF: limpeza, validacao e hash\n";
if (AgeGate::cpfLimpo('529.982.247-25') === '52998224725') ok('tira mascara e aceita CPF valido'); else falha('nao limpou/validou CPF valido');
foreach (['111.111.111-11', '123', '52998224726', ''] as $c2) {
    if (AgeGate::cpfLimpo($c2) === null) ok("recusa '$c2'"); else falha("aceitou CPF invalido '$c2'");
}
$h1 = AgeGate::cpfHash('52998224725', 'sal-a'); $h2 = AgeGate::cpfHash('529.982.247-25', 'sal-a'); $h3 = AgeGate::cpfHash('52998224725', 'sal-b');
if ($h1 === $h2) ok('hash ignora mascara'); else falha('hash muda com mascara');
if ($h1 !== $h3) ok('hash muda com o sal (site A nao cruza com site B)'); else falha('hash nao depende do sal');
if (strlen($h1) === 64 && ctype_xdigit($h1)) ok('hash e sha256 hex'); else falha('hash nao e sha256 hex');
if (strpos($h1, '52998224725') === false) ok('o CPF nao aparece no hash'); else falha('CPF em claro no hash');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php tests/eca-age-gate.php`
Expected: erro fatal `Failed opening required 'src/AgeGate.php'`.

- [ ] **Step 3: Implementar `src/AgeGate.php`**

```php
<?php
// ============================================================
// AgeGate - a regra de idade do ECA Digital, sem banco e sem rede.
// ============================================================
// Quem decide se pode comprar ou abrir caixa e ESTA classe. As rotas so perguntam.
// Lei 15.211/2025, art. 9 (vedada a autodeclaracao) e art. 20 (caixas de recompensa);
// Decreto 12.880/2026, art. 23 (caixa fechada por padrao dispensa verificacao).
// ============================================================

namespace App;

class AgeGate
{
    public const MODOS  = ['desligado', 'declaracao', 'verificado'];
    public const STATUS = ['desconhecido', 'menor', 'adulto_declarado', 'adulto_verificado'];
    public const MAIORIDADE = 18;

    /**
     * Idade completa em anos, ou null se a data nao existe, esta no futuro ou e absurda.
     * Aceita 'AAAA-MM-DD' (input type=date) e 'DD/MM/AAAA' (digitado).
     */
    public static function idade(string $nascimento, ?\DateTimeImmutable $hoje = null): ?int
    {
        $hoje = $hoje ?? new \DateTimeImmutable('today');
        $n = trim($nascimento);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $n, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $n, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }
        if (!checkdate($mo, $d, $y)) return null;           // 31/02, mes 13
        if ($y < 1900) return null;                          // absurdo
        $data = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $mo, $d));
        if ($data > $hoje) return null;                      // futuro
        // Aniversario conta no proprio dia: diff ->y ja faz isso (29/02 cai em 28/02 ou 01/03 pelo PHP).
        return $hoje->diff($data)->y;
    }

    public static function statusPorNascimento(string $nascimento, ?\DateTimeImmutable $hoje = null): ?string
    {
        $i = self::idade($nascimento, $hoje);
        if ($i === null) return null;
        return $i >= self::MAIORIDADE ? 'adulto_declarado' : 'menor';
    }

    /** Loja (moeda, pacotes): exige declaracao; menor nunca; modo desligado = como antes. */
    public static function podeComprar(string $status, string $modo): bool
    {
        if ($status === 'menor') return false;
        if ($modo === 'desligado') return true;
        return in_array($status, ['adulto_declarado', 'adulto_verificado'], true);
    }

    /**
     * Caixa de recompensa: so adulto verificado. A diaria gratis segue a config
     * (age_daily_box_gated): se liberada, basta ter declarado. Menor nunca abre,
     * nem com o modo desligado: o site ja sabe que e menor.
     */
    public static function podeAbrirCaixa(string $status, string $modo, bool $diaria, bool $diariaExige): bool
    {
        if ($status === 'menor') return false;
        if ($modo === 'desligado') return true;
        if ($status === 'adulto_verificado') return true;
        if ($diaria && !$diariaExige) return $status === 'adulto_declarado';
        return false;
    }

    /** 11 digitos com verificador valido, ou null. Mesma regra do MercadoPago::isValidCpf. */
    public static function cpfLimpo(string $cpf): ?string
    {
        $d = preg_replace('/\D+/', '', $cpf);
        if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) return null;
        for ($t = 9; $t < 11; $t++) {
            $s = 0;
            for ($i = 0; $i < $t; $i++) $s += (int) $d[$i] * (($t + 1) - $i);
            $dv = ((10 * $s) % 11) % 10;
            if ((int) $d[$t] !== $dv) return null;
        }
        return $d;
    }

    /** sha256(sal . cpf limpo). O sal e por site (settings.age_hash_salt): hash de um site nao cruza com outro. */
    public static function cpfHash(string $cpf, string $sal): string
    {
        $d = preg_replace('/\D+/', '', $cpf);
        return hash('sha256', $sal . '|' . $d);
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php tests/eca-age-gate.php`
Expected: `TUDO OK`.

- [ ] **Step 5: Commit**

```bash
git add src/AgeGate.php tests/eca-age-gate.php
git commit -m "feat(eca): AgeGate, a regra pura de idade, compra e caixa"
```

---

### Task 3: Verificadores plugáveis (FlagCheck, Serpro, CPFHub)

**Files:**
- Create: `src/AgeVerifier.php` (interface + fábrica + os três drivers no mesmo arquivo, como `Streamer.php` agrupa o que é de um domínio)
- Test: `tests/eca-verificadores.php`

**Interfaces:**
- Produces:
  - `interface App\AgeVerifier { public function verify(string $cpf, ?string $nascimentoIso): array; public function test(): array; public function nome(): string; }`
  - retorno de `verify()`: `['result' => 'adulto'|'menor'|'falhou', 'ref' => ?string, 'status' => ?string, 'error' => ?string]`
  - retorno de `test()`: `['ok' => bool, 'msg' => string]`
  - `App\AgeVerifierFactory::make(string $provider, string $key, string $secret, ?callable $http = null): AgeVerifier`
  - `$http` tem assinatura `function(string $method, string $url, array $headers, ?string $body): array{status:int, body:string}`; o padrão usa curl com timeout 8 s.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php
/**
 * Os tres drivers de verificacao, com HTTP falso. Nenhum teste toca fornecedor real.
 * O que importa: adulto/menor/falhou saem certos, o CPF nao vaza no 'ref', e falha de
 * rede nunca vira 'menor' nem 'adulto'.
 * Rodar: php tests/eca-verificadores.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }

$ROOT = dirname(__DIR__);
require_once $ROOT . '/src/AgeGate.php';
require_once $ROOT . '/src/AgeVerifier.php';
use App\AgeVerifierFactory;

$CPF = '52998224725';
$chamadas = [];
function httpFalso(int $status, string $body, array &$log): callable {
    return function (string $m, string $url, array $h, ?string $b) use ($status, $body, &$log): array {
        $log[] = ['m' => $m, 'url' => $url, 'h' => $h, 'b' => $b];
        return ['status' => $status, 'body' => $body];
    };
}

echo "\n1. FlagCheck\n";
$v = AgeVerifierFactory::make('flagcheck', 'chave-x', '', httpFalso(200, json_encode(['maior_de_18' => true, 'status_cpf' => 'regular', 'audit_token' => 'aud_123']), $chamadas));
$r = $v->verify($CPF, '2000-01-01');
if ($r['result'] === 'adulto' && $r['ref'] === 'aud_123' && $r['status'] === 'regular') ok('adulto com audit_token'); else falha('flagcheck adulto', json_encode($r));
if (($chamadas[0]['m'] ?? '') === 'POST' && str_contains($chamadas[0]['url'], 'flagcheck.com.br')) ok('POST no endpoint do FlagCheck'); else falha('flagcheck: metodo/url errados');
if (str_contains(implode(' ', $chamadas[0]['h']), 'Bearer chave-x')) ok('manda a chave como Bearer'); else falha('flagcheck sem Bearer');
$r = AgeVerifierFactory::make('flagcheck', 'k', '', httpFalso(200, json_encode(['maior_de_18' => false, 'status_cpf' => 'regular', 'audit_token' => 'aud_9']), $chamadas))->verify($CPF, null);
if ($r['result'] === 'menor') ok('menor'); else falha('flagcheck menor', json_encode($r));

echo "\n2. Serpro v3\n";
$log = [];
$tok = json_encode(['access_token' => 'tk', 'expires_in' => 3600]);
$cons = json_encode(['ni' => $CPF, 'nome' => 'FULANO', 'situacao' => ['codigo' => '0', 'descricao' => 'Regular'], 'nascimento' => '01012000']);
$seq = [ ['status' => 200, 'body' => $tok], ['status' => 200, 'body' => $cons] ];
$http = function (string $m, string $url, array $h, ?string $b) use (&$seq, &$log): array { $log[] = [$m, $url, $h, $b]; return array_shift($seq); };
$r = AgeVerifierFactory::make('serpro', 'consumer', 'secret', $http)->verify($CPF, '2000-01-01');
if ($r['result'] === 'adulto' && $r['status'] === 'Regular') ok('adulto pela data de nascimento devolvida'); else falha('serpro adulto', json_encode($r));
if (count($log) === 2 && $log[0][0] === 'POST' && str_contains($log[0][1], '/token')) ok('pega token OAuth2 antes'); else falha('serpro sem token');
if (str_contains($log[1][1], '/consulta-cpf-df/v3/')) ok('consulta na v3'); else falha('serpro nao usa v3', $log[1][1] ?? '');
if (!str_contains((string) $r['ref'], $CPF)) ok('ref nao contem o CPF'); else falha('ref vazou o CPF');
$seq = [ ['status' => 200, 'body' => $tok], ['status' => 200, 'body' => json_encode(['ni' => $CPF, 'nome' => 'X', 'situacao' => ['codigo' => '0', 'descricao' => 'Regular'], 'nascimento' => '01012012'])] ];
$r = AgeVerifierFactory::make('serpro', 'c', 's', $http)->verify($CPF, '2012-01-01');
if ($r['result'] === 'menor') ok('menor pela data devolvida'); else falha('serpro menor', json_encode($r));

echo "\n3. CPFHub\n";
$log = [];
$r = AgeVerifierFactory::make('cpfhub', 'k', '', httpFalso(200, json_encode(['name' => 'X', 'gender' => 'M', 'birthDate' => '01/01/2000']), $log))->verify($CPF, null);
if ($r['result'] === 'adulto') ok('adulto por birthDate'); else falha('cpfhub adulto', json_encode($r));
if (str_contains($log[0]['url'], 'cpfhub.io')) ok('endpoint do CPFHub'); else falha('cpfhub url');

echo "\n4. Falhas nunca viram adulto nem menor\n";
foreach ([[500, '{}'], [401, '{"error":"unauthorized"}'], [200, 'nao-e-json'], [200, '{}'], [0, '']] as [$st, $body]) {
    foreach (['flagcheck', 'cpfhub'] as $p) {
        $r = AgeVerifierFactory::make($p, 'k', '', httpFalso($st, $body, $log))->verify($CPF, null);
        if ($r['result'] === 'falhou' && !empty($r['error'])) ok("$p: status $st / body " . substr($body, 0, 12) . " = falhou com erro"); else falha("$p: status $st nao virou falhou", json_encode($r));
    }
}
$seq = [ ['status' => 401, 'body' => '{}'] ];
$r = AgeVerifierFactory::make('serpro', 'c', 's', $http)->verify($CPF, '2000-01-01');
if ($r['result'] === 'falhou') ok('serpro: token negado = falhou'); else falha('serpro token negado nao falhou');

echo "\n5. Fabrica e test()\n";
try { AgeVerifierFactory::make('inventado', 'k', '', null); falha('fabrica aceitou provider inexistente'); }
catch (\InvalidArgumentException $e) { ok('fabrica recusa provider inexistente'); }
$r = AgeVerifierFactory::make('flagcheck', '', '', httpFalso(200, '{}', $log))->test();
if ($r['ok'] === false) ok('test() sem chave diz que nao esta pronto'); else falha('test() sem chave disse ok');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php tests/eca-verificadores.php`
Expected: erro fatal `Failed opening required 'src/AgeVerifier.php'`.

- [ ] **Step 3: Implementar `src/AgeVerifier.php`**

```php
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
        try { return ($this->http)($method, $url, $headers, $body); }
        catch (\Throwable $e) { return ['status' => 0, 'body' => '']; }
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
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php tests/eca-verificadores.php`
Expected: `TUDO OK`.

- [ ] **Step 5: Commit**

```bash
git add src/AgeVerifier.php tests/eca-verificadores.php
git commit -m "feat(eca): verificadores plugaveis FlagCheck, Serpro e CPFHub com HTTP injetavel"
```

---

### Task 4: `AgeVerification`, a camada de banco

**Files:**
- Create: `src/AgeVerification.php`
- Test: `tests/eca-verification-invariantes.php` (estrutural: sem banco nos testes deste repo; os invariantes que importam são verificáveis lendo o código)

**Interfaces:**
- Consumes: `AgeGate`, `AgeVerifierFactory::fromSettings()`, `Database`, `Settings`, `RateLimit::clientIp()`.
- Produces:
  - `AgeVerification::statusDe(string $steamId): string` (um dos `AgeGate::STATUS`; `desconhecido` se o player não existe)
  - `AgeVerification::modo(): string` e `AgeVerification::diariaExige(): bool`
  - `AgeVerification::declarar(string $steamId, string $nascimento): array{ok:bool, status:?string, erro:?string}`
  - `AgeVerification::verificar(string $steamId, string $cpf, ?string $nascimento): array{ok:bool, status:?string, erro:?string}`
  - `AgeVerification::consentir(string $steamId, string $kind, string $version, string $texto): void`
  - `AgeVerification::temConsentimento(string $steamId, string $kind, string $version): bool`
  - `AgeVerification::revogar(int $id, string $motivo): bool`
  - `AgeVerification::podeComprar(string $steamId): bool` e `podeAbrirCaixa(string $steamId, bool $diaria): bool`

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php
/**
 * Invariantes da camada de banco da verificacao de idade, lidos do codigo:
 *  - o CPF nunca vai pro banco, pro log ou pra sessao
 *  - a revogacao move o hash pra cpf_hash_revoked (libera o UNIQUE)
 *  - falha do fornecedor nao muda o age_status
 * Rodar: php tests/eca-verification-invariantes.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }

$ROOT = dirname(__DIR__);
$src = @file_get_contents($ROOT . '/src/AgeVerification.php') ?: '';
if ($src === '') { falha('src/AgeVerification.php nao existe'); }
require_once $ROOT . '/src/AgeGate.php';
require_once $ROOT . '/src/AgeVerifier.php';
if ($src !== '') require_once $ROOT . '/src/AgeVerification.php';

echo "\n1. O CPF nao vaza\n";
$semComentario = preg_replace('#//[^\n]*|/\*.*?\*/#s', '', $src);
if (!preg_match('/\$_SESSION\[[^\]]*cpf/i', $semComentario)) ok('nenhum CPF em sessao'); else falha('CPF posto em $_SESSION');
if (!preg_match('/error_log\([^;]*\$cpf/i', $semComentario)) ok('nenhum error_log com $cpf'); else falha('error_log com o CPF');
if (!preg_match('/INSERT INTO age_verifications[^;]*\bcpf\b(?!_hash)/i', $semComentario)) ok('o INSERT so grava cpf_hash'); else falha('INSERT grava uma coluna cpf');
if (preg_match('/AgeGate::cpfHash\(/', $semComentario)) ok('usa AgeGate::cpfHash'); else falha('nao usa AgeGate::cpfHash');

echo "\n2. Revogacao libera o hash\n";
if (preg_match('/UPDATE age_verifications SET[^;]*cpf_hash_revoked\s*=\s*cpf_hash[^;]*cpf_hash\s*=\s*NULL/is', $semComentario)) ok('revogar move o hash e zera cpf_hash'); else falha('revogar nao move o hash pra cpf_hash_revoked');

echo "\n3. Falha do fornecedor nao muda o status\n";
if (preg_match("/'falhou'[^;]*return \[\s*'ok'\s*=>\s*false/s", $semComentario) || preg_match("/result'\]\s*===\s*'falhou'\)\s*\{[^}]*return/s", $semComentario)) ok('retorna antes de mexer em players quando falhou'); else falha('falha do fornecedor pode estar mudando o age_status');

echo "\n4. Assinaturas publicas que as rotas usam\n";
foreach (['statusDe', 'modo', 'diariaExige', 'declarar', 'verificar', 'consentir', 'temConsentimento', 'revogar', 'podeComprar', 'podeAbrirCaixa'] as $f) {
    if ($src !== '' && method_exists('App\\AgeVerification', $f)) ok("AgeVerification::$f existe"); else falha("AgeVerification::$f nao existe");
}

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php tests/eca-verification-invariantes.php`
Expected: `FALHA src/AgeVerification.php nao existe` e as 10 assinaturas ausentes.

- [ ] **Step 3: Implementar `src/AgeVerification.php`**

```php
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
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php tests/eca-verification-invariantes.php`
Expected: `TUDO OK`. Também `php -l src/AgeVerification.php`.

- [ ] **Step 5: Commit**

```bash
git add src/AgeVerification.php tests/eca-verification-invariantes.php
git commit -m "feat(eca): camada de banco da verificacao: declarar, verificar, consentir, revogar"
```

---

### Task 5: Textos (lang) e a página `/idade`

**Files:**
- Create: `views/pages/idade.php`
- Modify: `lang/pt-br.php` (nova seção `'idade' => [...]` no fim do array), `lang/en-us.php` (mesma seção em inglês)
- Modify: `public/index.php` (novas rotas, inserir antes de `// ============ ADMIN ============`, linha 1758)
- Test: `tests/eca-textos-e-rotas.php`

**Interfaces:**
- Consumes: `AgeVerification::declarar/verificar/consentir/temConsentimento/statusDe`, `Csrf`, `SteamAuth`, `RateLimit`, `View`, `__()`.
- Produces: rotas `GET /idade`, `POST /idade/declarar`, `POST /idade/verificar`; a página lê `?return=` (só caminho interno) e `?motivo=comprar|caixa`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php
/**
 * As chaves de texto existem nas duas linguas, sem travessao, e as rotas /idade estao
 * registradas com CSRF e rate limit. Estrutural: nao sobe servidor.
 * Rodar: php tests/eca-textos-e-rotas.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);

echo "\n1. Chaves de texto\n";
$pt = require $ROOT . '/lang/pt-br.php'; $en = require $ROOT . '/lang/en-us.php';
$chaves = ['title', 'confirm_title', 'confirm_intro', 'birth_label', 'terms_label', 'continue', 'verify_title', 'verify_intro',
           'cpf_label', 'cpf_not_stored', 'one_account', 'verify_btn', 'minor_title', 'minor_body', 'cpf_taken', 'verified_ok', 'failed_retry', 'why'];
foreach ($chaves as $k) {
    $p = $pt['idade'][$k] ?? null; $e = $en['idade'][$k] ?? null;
    if (is_string($p) && $p !== '' && is_string($e) && $e !== '') ok("idade.$k nas duas linguas"); else falha("idade.$k falta em pt-br ou en-us");
    if (is_string($p) && (str_contains($p, "\u{2014}") || str_contains($p, "\u{2013}"))) falha("idade.$k tem travessao");
}

echo "\n2. Rotas\n";
$idx = file_get_contents($ROOT . '/public/index.php');
foreach (["Router::get('/idade'", "Router::post('/idade/declarar'", "Router::post('/idade/verificar'"] as $r) {
    if (str_contains($idx, $r)) ok("$r registrada"); else falha("$r nao registrada");
}
if (preg_match("/Router::post\('\/idade\/verificar'.*?Csrf::check\(\).*?RateLimit::check\('idade-verificar:/s", $idx)) ok('verificar tem CSRF e rate limit'); else falha('/idade/verificar sem CSRF ou sem rate limit', 'cada tentativa custa dinheiro do cliente');
if (preg_match("/Router::post\('\/idade\/verificar'.*?unset\(\\\$cpf\)/s", $idx) || preg_match("/Router::post\('\/idade\/verificar'.*?\\\$_POST\['cpf'\][^;]*;\s*(?:[^;]*;){0,6}[^;]*AgeVerification::verificar/s", $idx)) ok('o CPF vai direto pra AgeVerification::verificar'); else falha('rota /idade/verificar guarda o CPF em variavel demais', 'quanto menos maos, menos vazamento');
if (!preg_match("/\\\$_SESSION\[[^\]]*cpf/i", $idx)) ok('nenhum CPF em sessao no index.php'); else falha('index.php poe CPF na sessao');

echo "\n3. A view existe e nao tem input de CPF com autocomplete\n";
$v = @file_get_contents($ROOT . '/views/pages/idade.php') ?: '';
if ($v !== '') ok('views/pages/idade.php existe'); else falha('views/pages/idade.php nao existe');
if (preg_match('/name="cpf"[^>]*autocomplete="off"/', $v)) ok('campo CPF com autocomplete=off'); else falha('campo CPF sem autocomplete=off');
if (preg_match('/name="terms_ok"[^>]*type="checkbox"|type="checkbox"[^>]*name="terms_ok"/', $v)) ok('checkbox real de termos'); else falha('sem checkbox real de termos');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php tests/eca-textos-e-rotas.php`
Expected: 18 FALHA de chave, 3 de rota, view ausente.

- [ ] **Step 3: Adicionar os textos**

Em `lang/pt-br.php`, antes do `);` final do array:

```php
  'idade' =>
  array (
    'title' => 'Verificação de idade',
    'why' => 'A Lei 15.211/2025 (ECA Digital) exige que sites com caixas de recompensa confirmem que o jogador é maior de 18 anos. Guardamos só o resultado, a data e o método. Nunca o seu CPF.',
    'confirm_title' => 'Confirme sua idade',
    'confirm_intro' => 'Para comprar neste site você precisa ter 18 anos ou mais e aceitar os Termos.',
    'birth_label' => 'Data de nascimento',
    'terms_label' => 'Li e aceito os Termos de Uso e a Política de Privacidade',
    'continue' => 'Continuar',
    'verify_title' => 'Verifique sua identidade',
    'verify_intro' => 'Para abrir caixas, a lei pede uma verificação de verdade: seu CPF é conferido numa base oficial e não fica guardado.',
    'cpf_label' => 'CPF',
    'cpf_not_stored' => 'O CPF é usado só nesta conferência e descartado na hora. Fica registrado apenas: verificado, quando e por qual serviço.',
    'one_account' => 'Cada CPF verifica uma única conta neste site.',
    'verify_btn' => 'Verificar',
    'minor_title' => 'Este site vende para maiores de 18 anos',
    'minor_body' => 'Você continua podendo jogar, aparecer no ranking e entrar em clãs. Compras e caixas ficam fechadas. Se houve engano na data, é possível corrigir fazendo a verificação por CPF.',
    'cpf_taken' => 'Este CPF já verificou outra conta neste site.',
    'verified_ok' => 'Idade verificada. Obrigado.',
    'failed_retry' => 'Não foi possível verificar agora. Tente de novo em instantes.',
  ),
```

Em `lang/en-us.php`, no mesmo lugar:

```php
  'idade' =>
  array (
    'title' => 'Age verification',
    'why' => 'Brazilian law 15.211/2025 requires sites with loot boxes to confirm the player is 18 or older. We keep only the result, the date and the method. Never your CPF.',
    'confirm_title' => 'Confirm your age',
    'confirm_intro' => 'To buy on this site you must be 18 or older and accept the Terms.',
    'birth_label' => 'Date of birth',
    'terms_label' => 'I have read and accept the Terms of Use and the Privacy Policy',
    'continue' => 'Continue',
    'verify_title' => 'Verify your identity',
    'verify_intro' => 'To open boxes the law requires a real check: your CPF is verified against an official database and is not stored.',
    'cpf_label' => 'CPF',
    'cpf_not_stored' => 'The CPF is used only for this check and discarded right away. We keep only: verified, when, and by which service.',
    'one_account' => 'Each CPF can verify a single account on this site.',
    'verify_btn' => 'Verify',
    'minor_title' => 'This site sells to adults only (18+)',
    'minor_body' => 'You can still play, appear in the ranking and join clans. Purchases and boxes stay closed. If the date was a mistake, you can fix it by verifying with your CPF.',
    'cpf_taken' => 'This CPF has already verified another account on this site.',
    'verified_ok' => 'Age verified. Thank you.',
    'failed_retry' => 'We could not verify right now. Please try again in a moment.',
  ),
```

- [ ] **Step 4: Criar `views/pages/idade.php`**

```php
<?php
/** @var array $config; @var string $status; @var string $motivo; @var string $return; @var ?string $erro; @var ?string $ok_msg */
?>
<?php \App\View::with('title', __('idade.title') . ' - ' . site_name('Loja')); ?>
<?php \App\View::with('description', __('idade.why')); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>
<?php
$precisaDeclarar = in_array($status, ['desconhecido'], true);
$precisaVerificar = $motivo === 'caixa' && $status !== 'adulto_verificado' && $status !== 'menor';
$ehMenor = $status === 'menor';
?>
<section class="section section-bg-2" style="min-height:60vh;">
    <div class="container" style="max-width:640px;">
        <?php if ($erro): ?>
            <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--danger-border); background:var(--danger-overlay);"><?= e($erro) ?></div>
        <?php endif; ?>
        <?php if ($ok_msg): ?>
            <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--moss);"><?= e($ok_msg) ?></div>
        <?php endif; ?>

        <?php if ($ehMenor): ?>
            <h1 class="hero-title" style="font-size:1.8rem;"><?= e(__('idade.minor_title')) ?></h1>
            <p style="color:var(--dim); margin:.8rem 0 1.4rem;"><?= e(__('idade.minor_body')) ?></p>
        <?php endif; ?>

        <?php if ($precisaDeclarar && !$ehMenor): ?>
            <h1 class="hero-title" style="font-size:1.8rem;"><?= e(__('idade.confirm_title')) ?></h1>
            <p style="color:var(--dim); margin:.8rem 0 1.2rem;"><?= e(__('idade.confirm_intro')) ?></p>
            <form method="POST" action="/idade/declarar" class="stat-card" style="display:grid; gap:1rem;">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <input type="hidden" name="motivo" value="<?= e($motivo) ?>">
                <label style="display:block;">
                    <span style="display:block; margin-bottom:.35rem; color:var(--bone);"><?= e(__('idade.birth_label')) ?> <span style="color:var(--hazard);">*</span></span>
                    <input type="date" name="nascimento" required max="<?= date('Y-m-d') ?>" min="1900-01-01" style="width:100%; padding:.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                </label>
                <label style="display:flex; gap:.6rem; align-items:flex-start; color:var(--bone);">
                    <input type="checkbox" name="terms_ok" value="1" required style="width:18px; height:18px; margin-top:.15rem;">
                    <span><?= e(__('idade.terms_label')) ?> (<a href="/terms" target="_blank" style="color:var(--hazard);">Termos</a>, <a href="/privacy" target="_blank" style="color:var(--hazard);">Privacidade</a>)</span>
                </label>
                <button type="submit" class="btn"><?= e(__('idade.continue')) ?></button>
            </form>
        <?php endif; ?>

        <?php if ($precisaVerificar || $ehMenor): ?>
            <h2 style="font-size:1.4rem; margin-top:1.6rem; color:var(--bone);"><?= e(__('idade.verify_title')) ?></h2>
            <p style="color:var(--dim); margin:.6rem 0 1rem;"><?= e(__('idade.verify_intro')) ?></p>
            <form method="POST" action="/idade/verificar" class="stat-card" style="display:grid; gap:1rem;" autocomplete="off">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <?php if ($precisaDeclarar || $ehMenor): ?>
                <label style="display:block;">
                    <span style="display:block; margin-bottom:.35rem; color:var(--bone);"><?= e(__('idade.birth_label')) ?> <span style="color:var(--hazard);">*</span></span>
                    <input type="date" name="nascimento" required max="<?= date('Y-m-d') ?>" min="1900-01-01" style="width:100%; padding:.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                </label>
                <?php endif; ?>
                <label style="display:block;">
                    <span style="display:block; margin-bottom:.35rem; color:var(--bone);"><?= e(__('idade.cpf_label')) ?> <span style="color:var(--hazard);">*</span></span>
                    <input type="text" name="cpf" inputmode="numeric" autocomplete="off" required maxlength="14" placeholder="000.000.000-00" style="width:100%; padding:.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                    <small style="display:block; margin-top:.4rem; color:var(--dim);"><?= e(__('idade.cpf_not_stored')) ?> <?= e(__('idade.one_account')) ?></small>
                </label>
                <button type="submit" class="btn"><?= e(__('idade.verify_btn')) ?></button>
            </form>
        <?php endif; ?>

        <p style="margin-top:1.6rem; font-size:.85rem; color:var(--dim);"><?= e(__('idade.why')) ?></p>
    </div>
</section>
<?php \App\View::endSection(); ?>
```

- [ ] **Step 5: Registrar as rotas em `public/index.php`** (antes de `// ============ ADMIN ============`)

```php
// ============ VERIFICACAO DE IDADE (ECA Digital, Lei 15.211/2025) ============
// Regra em AgeGate, persistencia em AgeVerification. O CPF entra no POST /idade/verificar
// e vai DIRETO pra AgeVerification::verificar(): nao passa por sessao, log ou variavel a mais.
$idadeReturnSeguro = function (string $r): string {
    return (str_starts_with($r, '/') && !str_starts_with($r, '//')) ? $r : '/';
};

\App\Router::get('/idade', function() use ($config, $idadeReturnSeguro) {
    if (!\App\SteamAuth::check()) { $_SESSION['steam_login_return'] = '/idade?' . http_build_query($_GET); header('Location: /auth/steam'); exit; }
    $steamId = \App\SteamAuth::steamId();
    \App\View::display('pages.idade', [
        'config' => $config,
        'status' => \App\AgeVerification::statusDe($steamId),
        'motivo' => ($_GET['motivo'] ?? '') === 'caixa' ? 'caixa' : 'comprar',
        'return' => $idadeReturnSeguro((string) ($_GET['return'] ?? '/')),
        'erro'   => isset($_GET['erro']) ? (string) $_GET['erro'] : null,
        'ok_msg' => isset($_GET['ok']) ? __('idade.verified_ok') : null,
    ]);
});

\App\Router::post('/idade/declarar', function() use ($config, $idadeReturnSeguro) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /idade?erro=' . rawurlencode('Sessão expirada. Tente de novo.')); exit; }
    $steamId = \App\SteamAuth::steamId();
    $return  = $idadeReturnSeguro((string) ($_POST['return'] ?? '/'));
    $motivo  = ($_POST['motivo'] ?? '') === 'caixa' ? 'caixa' : 'comprar';
    if (empty($_POST['terms_ok'])) { header('Location: /idade?motivo=' . $motivo . '&return=' . rawurlencode($return) . '&erro=' . rawurlencode('Você precisa aceitar os Termos para continuar.')); exit; }
    $r = \App\AgeVerification::declarar($steamId, (string) ($_POST['nascimento'] ?? ''));
    if (!$r['ok']) { header('Location: /idade?motivo=' . $motivo . '&return=' . rawurlencode($return) . '&erro=' . rawurlencode($r['erro'])); exit; }
    $versao = (string) \App\Settings::get('terms_version', '1');
    $texto  = __('idade.terms_label');
    foreach (['termos', 'privacidade', 'idade'] as $k) \App\AgeVerification::consentir($steamId, $k, $versao, $texto);
    if ($r['status'] === 'menor') { header('Location: /idade'); exit; }
    // Quem veio pra abrir caixa ainda precisa do passo 2.
    header('Location: ' . ($motivo === 'caixa' ? '/idade?motivo=caixa&return=' . rawurlencode($return) : $return)); exit;
});

\App\Router::post('/idade/verificar', function() use ($config, $idadeReturnSeguro) {
    if (!\App\SteamAuth::check()) { header('Location: /auth/steam'); exit; }
    if (!\App\Csrf::check()) { header('Location: /idade?motivo=caixa&erro=' . rawurlencode('Sessão expirada. Tente de novo.')); exit; }
    $steamId = \App\SteamAuth::steamId();
    $return  = $idadeReturnSeguro((string) ($_POST['return'] ?? '/'));
    // Cada tentativa pode custar uma consulta paga do cliente: 5 por hora por jogador.
    $rl = \App\RateLimit::check('idade-verificar:' . $steamId, 5, 3600);
    if (empty($rl['allowed'])) { header('Location: /idade?motivo=caixa&erro=' . rawurlencode('Muitas tentativas. Tente de novo em uma hora.')); exit; }
    $r = \App\AgeVerification::verificar($steamId, (string) ($_POST['cpf'] ?? ''), isset($_POST['nascimento']) ? (string) $_POST['nascimento'] : null);
    unset($_POST['cpf']);
    if (!$r['ok']) { header('Location: /idade?motivo=caixa&return=' . rawurlencode($return) . '&erro=' . rawurlencode($r['erro'])); exit; }
    if ($r['status'] === 'menor') { header('Location: /idade'); exit; }
    header('Location: ' . $return . (str_contains($return, '?') ? '&' : '?') . 'idade=ok'); exit;
});
```

- [ ] **Step 6: Rodar e ver passar**

Run: `php tests/eca-textos-e-rotas.php && php -l public/index.php && php -l views/pages/idade.php`
Expected: `TUDO OK` e `No syntax errors`.

- [ ] **Step 7: Commit**

```bash
git add lang/pt-br.php lang/en-us.php views/pages/idade.php public/index.php tests/eca-textos-e-rotas.php
git commit -m "feat(eca): pagina /idade com declaracao, consentimento real e verificacao por CPF"
```

---

### Task 6: Os gates (checkout, cartão, caixa, bot) e o fim do aceite falso

**Files:**
- Modify: `public/index.php:1117-1145` (checkout), `:699-712` (abrir caixa), `:669-697` (página de caixas), `:1393-1400` (cartão)
- Modify: `views/pages/shop.php:183`, `views/pages/checkout_pix.php:32` (remover o hidden)
- Modify: `views/pages/caixas.php:266-271` (JS trata `error === 'age'`), e um aviso na página quando o jogador não pode abrir
- Modify: `public/api/bot-integration.php:206-211` (`_prepare_purchase`)
- Test: `tests/eca-gates.php`

**Interfaces:**
- Consumes: `AgeVerification::podeComprar/podeAbrirCaixa/statusDe/temConsentimento`, `Settings::get('terms_version')`.
- Produces: resposta JSON `{"ok":false,"error":"age","url":"/idade?motivo=caixa&return=/caixas"}` em `/caixas/{slug}/open`; erro `age_required` (HTTP 403) no bot.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php
/**
 * Os pontos de gate existem e o aceite de termos deixou de ser um campo oculto.
 * Rodar: php tests/eca-gates.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$idx = file_get_contents($ROOT . '/public/index.php');
$bot = file_get_contents($ROOT . '/public/api/bot-integration.php');

echo "\n1. O aceite de termos nao e mais um campo oculto\n";
foreach (['views/pages/shop.php', 'views/pages/checkout_pix.php'] as $v) {
    $s = file_get_contents($ROOT . '/' . $v);
    if (!preg_match('/name="terms_accepted"\s+value="1"/', $s) && !preg_match('/type="hidden"[^>]*name="terms_accepted"/', $s)) ok("$v sem hidden terms_accepted"); else falha("$v ainda marca os termos pelo jogador", 'consentimento que o site marca sozinho nao vale');
}

echo "\n2. Checkout exige podeComprar e consentimento\n";
$chk = substr($idx, strpos($idx, "Router::post('/shop/checkout'"), 4000);
if (str_contains($chk, 'AgeVerification::podeComprar(')) ok('checkout consulta podeComprar'); else falha('checkout nao consulta podeComprar');
if (str_contains($chk, 'AgeVerification::temConsentimento(')) ok('checkout exige consentimento da versao atual'); else falha('checkout nao exige consentimento');
if (str_contains($chk, "'/idade?motivo=comprar")) ok('checkout redireciona pra /idade'); else falha('checkout nao redireciona pra /idade');

echo "\n3. Abrir caixa\n";
$op = substr($idx, strpos($idx, "Router::post('/caixas/{slug}/open'"), 2500);
if (str_contains($op, 'AgeVerification::podeAbrirCaixa(')) ok('abrir caixa consulta podeAbrirCaixa'); else falha('abrir caixa sem gate');
if (str_contains($op, "'error' => 'age'")) ok('devolve error=age'); else falha('nao devolve error=age');
if (strpos($op, 'podeAbrirCaixa(') < strpos($op, 'Boxes::open(')) ok('gate vem ANTES de Boxes::open'); else falha('gate depois do open', 'a caixa ja teria sido sorteada');
$js = file_get_contents($ROOT . '/views/pages/caixas.php');
if (preg_match("/data\.error\s*===\s*'age'/", $js)) ok('JS da pagina redireciona em error=age'); else falha('JS nao trata error=age');

echo "\n4. Cartao\n";
$cd = substr($idx, strpos($idx, "Router::post('/shop/card-pay/{id}'"), 2500);
if (str_contains($cd, 'AgeVerification::podeComprar(')) ok('card-pay consulta podeComprar pelo steam_id da compra'); else falha('card-pay sem gate');

echo "\n5. Bot\n";
$pp = substr($bot, strpos($bot, 'function _prepare_purchase'), 1500);
if (str_contains($pp, 'AgeVerification::podeComprar(') && str_contains($pp, "'age_required'")) ok('_prepare_purchase recusa com age_required'); else falha('bot sem gate age_required');
if (preg_match("/require(_once)?\s+[^;]*AgeGate\.php/", $bot) && preg_match("/require(_once)?\s+[^;]*AgeVerification\.php/", $bot)) ok('bot-integration carrega AgeGate e AgeVerification'); else falha('bot-integration nao carrega as classes', 'este arquivo nao passa pelo bootstrap do index.php');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php tests/eca-gates.php`
Expected: falhas em todas as seções.

- [ ] **Step 3: Carregar as classes no bootstrap**

Em `public/index.php`, logo após `require $ROOT . '/src/Streamer.php';` (linha 76):

```php
require $ROOT . '/src/AgeGate.php';
require $ROOT . '/src/AgeVerifier.php';
require $ROOT . '/src/AgeVerification.php';
```

Em `public/api/bot-integration.php`, junto dos outros `require` do topo (procure o bloco que carrega `src/Database.php`; adicione após o último `require` de `src/`):

```php
require_once $ROOT . '/src/AgeGate.php';
require_once $ROOT . '/src/AgeVerifier.php';
require_once $ROOT . '/src/AgeVerification.php';
```

- [ ] **Step 4: Checkout** (`public/index.php:1137-1144`). Substituir o bloco do `terms_accepted` por:

```php
    $packageId = trim($_POST['package_id'] ?? '');
    $steamId   = preg_replace('/\s+/', '', $_POST['steam_id'] ?? '');

    // ECA Digital: comprar exige declaracao de idade + consentimento REAL da versao atual
    // dos Termos. O campo oculto terms_accepted=1 morreu na 3.3.0; quem decide e /idade.
    $termsVersion = (string) \App\Settings::get('terms_version', '1');
    $retorno = '/shop' . (isset($_POST['server_id']) ? '?server=' . (int) $_POST['server_id'] : '');
    if (preg_match('/^7656119[0-9]{10}$/', $steamId) && \App\SteamAuth::check() && \App\SteamAuth::steamId() === $steamId) {
        if (!\App\AgeVerification::podeComprar($steamId) || !\App\AgeVerification::temConsentimento($steamId, 'termos', $termsVersion)) {
            header('Location: /idade?motivo=comprar&return=' . rawurlencode($retorno)); exit;
        }
    } else {
        // Compra sem login Steam (SteamID digitado): nao ha conta pra declarar. Exige login.
        $_SESSION['steam_login_return'] = '/idade?motivo=comprar&return=' . rawurlencode($retorno);
        header('Location: /auth/steam'); exit;
    }
    $termsAccepted = true;
```

Observação: isso muda uma regra antiga (comprar sem login, só digitando o SteamID). A spec exige que o consentimento seja da conta; sem login não há conta. Registrar no CHANGELOG (Task 8).

- [ ] **Step 5: Cartão** (`public/index.php`, logo após a busca `$p = ...purchases...` na linha 1401-1402):

```php
    // ECA Digital: a compra foi criada por quem passou no gate, mas a sessao pode ter mudado.
    if (!\App\AgeVerification::podeComprar((string) $p['steam_id'])) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'age', 'url' => '/idade?motivo=comprar&return=/shop']); return; }
```

- [ ] **Step 6: Abrir caixa** (`public/index.php:707-712`, depois do rate limit e antes do `Boxes::find`):

```php
    $box = \App\Boxes::find($slug);
    if (!$box) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Caixa não encontrada.']); return; }
    // ECA Digital, art. 20 + Decreto art. 23: caixa so pra adulto verificado. Antes do sorteio.
    if (!\App\AgeVerification::podeAbrirCaixa($steamId, (int) $box['is_daily'] === 1)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'age', 'url' => '/idade?motivo=caixa&return=' . rawurlencode('/caixas')]);
        return;
    }
```

(remova a linha original `$box = \App\Boxes::find($slug);` e o `if (!$box)` que ficavam abaixo, para não duplicar.)

- [ ] **Step 7: Página de caixas** (`public/index.php:693-696`), passar o estado para a view:

```php
    \App\View::display('pages.caixas', [
        'config' => $config, 'boxes' => $boxes,
        'steam_user' => $steamUser, 'coins' => $coins,
        'age_status' => $steamUser ? \App\AgeVerification::statusDe($steamUser['steam_id']) : 'desconhecido',
        'age_mode'   => \App\AgeVerification::modo(),
    ]);
```

Em `views/pages/caixas.php`, logo após o `</section>` do hero (linha 35), um aviso quando não pode abrir:

```php
<?php if ($steam_user && $age_mode !== 'desligado' && $age_status !== 'adulto_verificado'): ?>
<section class="section" style="padding:1.2rem 0 0;">
    <div class="container">
        <div class="stat-card" style="border-left:3px solid var(--hazard);">
            <?php if ($age_status === 'menor'): ?>
                <strong><?= e(__('idade.minor_title')) ?></strong>
                <p style="color:var(--dim); margin:.4rem 0 0;"><?= e(__('idade.minor_body')) ?></p>
            <?php else: ?>
                <strong><?= e(__('idade.verify_title')) ?></strong>
                <p style="color:var(--dim); margin:.4rem 0 .8rem;"><?= e(__('idade.verify_intro')) ?></p>
                <a class="btn" href="/idade?motivo=caixa&return=<?= rawurlencode('/caixas') ?>"><?= e(__('idade.verify_btn')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
```

E no JS (linha 267), logo após a linha do `login`:

```js
            if (data.error === 'age'){ window.location.href = data.url; return; }
```

- [ ] **Step 8: Remover o hidden nas duas views**

`views/pages/shop.php:183`: apagar a linha `<input type="hidden" name="terms_accepted" value="1">`.
`views/pages/checkout_pix.php:32`: apagar a mesma linha (esse form só reaplica cupom; o checkout já exige consentimento pela conta).

- [ ] **Step 9: Bot** (`public/api/bot-integration.php:209-211`), logo após validar o SteamID:

```php
    // ECA Digital: comprar pelo Discord obedece a mesma regra do site. O bot mostra o link.
    if (!\App\AgeVerification::podeComprar($steamId)) {
        _bail(403, 'age_required', $action);
    }
```

Confirme que `_bail()` devolve JSON com o código de erro (já é assim para `invalid_steam_id`); o bot do Discord trata `age_required` mostrando `site_url . '/idade'` (mudança no repositório do bot, fora deste plano: registrar no handoff da Task 8).

- [ ] **Step 10: Rodar e ver passar**

Run: `php tests/eca-gates.php && php -l public/index.php && php -l public/api/bot-integration.php && php -l views/pages/caixas.php && php tests/eca-age-gate.php`
Expected: `TUDO OK` em todos.

- [ ] **Step 11: Commit**

```bash
git add public/index.php public/api/bot-integration.php views/pages/shop.php views/pages/checkout_pix.php views/pages/caixas.php tests/eca-gates.php
git commit -m "feat(eca): gates de idade no checkout, cartao, caixa e bot; fim do aceite de termos oculto"
```

---

### Task 7: Painel do admin: configuração, conformidade, relatório e aviso

**Files:**
- Modify: `views/admin/settings.php` (nova seção após o bloco do afiliado, linha 197), `public/index.php:2608-2624` (whitelists) e o handler de settings (bloco novo para a chave, como o do CFTools secret)
- Create: `views/admin/eca.php` (conformidade) e `views/admin/eca_relatorio.php` (imprimível)
- Modify: `views/admin/layout.php:66` (entrada de menu) e o topo do layout (faixa de aviso por modo)
- Modify: `public/index.php` (rotas admin, antes de `// ============ ADMIN: ENTITLEMENTS`)
- Test: `tests/eca-admin.php`

**Interfaces:**
- Consumes: `AgeVerification::revogar/modo`, `AgeVerifierFactory::fromSettings()->test()`, `AuditLog::record`, `Auth::requireCan('settings')`.
- Produces: rotas `GET /admin/eca`, `POST /admin/eca/revogar`, `GET /admin/eca/relatorio`, `GET /admin/eca/export.csv`, `POST /admin/eca/testar-chave`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php
/**
 * Painel do ECA Digital: chaves no whitelist do handler, secret nunca ecoado, rotas com
 * permissao, relatorio sem PII, aviso por modo. Estrutural.
 * Rodar: php tests/eca-admin.php
 */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$idx = file_get_contents($ROOT . '/public/index.php');
$set = file_get_contents($ROOT . '/views/admin/settings.php');
$lay = file_get_contents($ROOT . '/views/admin/layout.php');

echo "\n1. Handler de settings\n";
$h = substr($idx, strpos($idx, "Router::post('/admin/settings'"), 6000);
foreach (['age_gate_mode', 'age_provider'] as $k) { if (str_contains($h, "'$k'")) ok("$k no whitelist"); else falha("$k fora do whitelist", 'salvar seria no-op'); }
if (str_contains($h, "'age_daily_box_gated'")) ok('age_daily_box_gated nos toggles'); else falha('age_daily_box_gated fora dos toggles');
if (preg_match("/age_provider_key'\]\)[^;]*!==\s*''/", $h) || str_contains($h, "trim((string)\$_POST['age_provider_key']) !== ''")) ok('chave so grava se digitada (vazio mantem)'); else falha('chave do fornecedor sobrescreve com vazio');
if (str_contains($h, "AuditLog::record('age.settings'")) ok('audita mudanca de config'); else falha('nao audita age.settings');
if (!str_contains($h, "'age_hash_salt'")) ok('sal do hash NAO e editavel pelo painel'); else falha('sal do hash editavel pelo painel', 'trocar o sal invalida todo hash');

echo "\n2. Form de settings\n";
if (str_contains($set, 'name="age_gate_mode"') && str_contains($set, 'name="age_provider"') && str_contains($set, 'name="age_provider_key"')) ok('campos no form'); else falha('faltam campos no form');
if (!preg_match('/name="age_provider_key"[^>]*value="<\?=/', $set)) ok('a chave nao e ecoada no HTML'); else falha('chave do fornecedor ecoada no HTML');

echo "\n3. Rotas admin\n";
foreach (["Router::get('/admin/eca'", "Router::post('/admin/eca/revogar'", "Router::get('/admin/eca/relatorio'", "Router::get('/admin/eca/export.csv'", "Router::post('/admin/eca/testar-chave'"] as $r) {
    $i = strpos($idx, $r);
    if ($i !== false && str_contains(substr($idx, $i, 400), "Auth::requireCan('settings')")) ok("$r com requireCan"); else falha("$r ausente ou sem requireCan");
}
$rv = substr($idx, (int) strpos($idx, "Router::post('/admin/eca/revogar'"), 900);
if (str_contains($rv, 'Csrf::check()') && str_contains($rv, "AuditLog::record('age.revoked'")) ok('revogar com CSRF e auditoria'); else falha('revogar sem CSRF ou sem auditoria');

echo "\n4. Views\n";
$eca = @file_get_contents($ROOT . '/views/admin/eca.php') ?: ''; $rel = @file_get_contents($ROOT . '/views/admin/eca_relatorio.php') ?: '';
if ($eca !== '' && $rel !== '') ok('views existem'); else falha('faltam views eca.php / eca_relatorio.php');
if (!preg_match('/cpf_hash/', $eca) && !preg_match('/birth_year/', $eca)) ok('tabela do admin nao mostra hash nem ano de nascimento'); else falha('tabela do admin expoe hash ou ano', 'metadados so: resultado, metodo, data, ref');
if (str_contains($rel, 'Lei 15.211/2025') && str_contains($rel, 'Decreto 12.880/2026')) ok('relatorio cita a base legal'); else falha('relatorio sem base legal');

echo "\n5. Menu e aviso\n";
if (str_contains($lay, "'/admin/eca'")) ok('entrada de menu'); else falha('sem entrada de menu');
if (str_contains($lay, 'AgeVerification::modo()') && str_contains($lay, 'desligado')) ok('layout mostra aviso por modo'); else falha('layout sem aviso por modo');

echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php tests/eca-admin.php`
Expected: falhas em todas as seções.

- [ ] **Step 3: Whitelists e handler** (`public/index.php:2608-2638`)

Em `$fields`, adicionar `'age_gate_mode','age_provider'`. Em `$toggles`, adicionar `'age_daily_box_gated'`. Após o bloco do `cftools_secret` (linha 2638):

```php
    // ECA Digital: chave/segredo do fornecedor so gravam se digitados (o form nao ecoa o salvo).
    $ecaMudou = [];
    foreach (['age_provider_key', 'age_provider_secret'] as $k) {
        if (isset($_POST[$k]) && trim((string)$_POST[$k]) !== '') { \App\Settings::set($k, trim((string)$_POST[$k])); $ecaMudou[] = $k; }
    }
    if (!in_array((string)($_POST['age_gate_mode'] ?? ''), \App\AgeGate::MODOS, true)) \App\Settings::set('age_gate_mode', 'declaracao');
    if (!isset(\App\AgeVerifierFactory::PROVIDERS[(string)($_POST['age_provider'] ?? '')])) \App\Settings::set('age_provider', 'flagcheck');
    \App\AuditLog::record('age.settings', 'settings', 'eca', [
        'modo' => \App\Settings::get('age_gate_mode'), 'fornecedor' => \App\Settings::get('age_provider'),
        'diaria_exige' => \App\Settings::getBool('age_daily_box_gated'), 'chaves_alteradas' => $ecaMudou,
    ]);
```

- [ ] **Step 4: Seção no form** (`views/admin/settings.php`, após a linha 197, dentro do mesmo `<form>`):

```php
        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.2rem;">
            <label style="display:block; font-size:0.9rem; color:var(--bone); margin-bottom:0.5rem;">
                🛡 Proteção de menores (ECA Digital) <small style="color: var(--dim); font-weight: 400;">- Lei 15.211/2025: caixa de recompensa só abre para adulto verificado por fonte oficial</small>
            </label>
            <?php $modo = $settings['age_gate_mode'] ?? 'declaracao'; $prov = $settings['age_provider'] ?? 'flagcheck'; ?>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <label style="display:block;">
                    <span style="display:block; font-size:.85rem; margin-bottom:.3rem;">Modo</span>
                    <select name="age_gate_mode" style="width:100%; padding:.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                        <option value="verificado" <?= $modo === 'verificado' ? 'selected' : '' ?>>Verificado (conforme): caixa só com CPF verificado</option>
                        <option value="declaracao" <?= $modo === 'declaracao' ? 'selected' : '' ?>>Declaração (transição): loja com declaração, caixas fechadas</option>
                        <option value="desligado" <?= $modo === 'desligado' ? 'selected' : '' ?>>Desligado (não conforme): como era antes da 3.3.0</option>
                    </select>
                </label>
                <label style="display:block;">
                    <span style="display:block; font-size:.85rem; margin-bottom:.3rem;">Fornecedor da verificação</span>
                    <select name="age_provider" style="width:100%; padding:.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                        <?php foreach (\App\AgeVerifierFactory::PROVIDERS as $k => $lbl): ?>
                            <option value="<?= e($k) ?>" <?= $prov === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="display:block;">
                    <span style="display:block; font-size:.85rem; margin-bottom:.3rem;">Chave da API <small style="color:var(--dim);">(vazio mantém a atual<?= !empty($settings['age_provider_key']) ? '; há uma chave salva' : '' ?>)</small></span>
                    <input type="password" name="age_provider_key" autocomplete="new-password" placeholder="cole a chave do fornecedor" style="width:100%; padding:.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                </label>
                <label style="display:block;">
                    <span style="display:block; font-size:.85rem; margin-bottom:.3rem;">Segredo <small style="color:var(--dim);">(só Serpro)</small></span>
                    <input type="password" name="age_provider_secret" autocomplete="new-password" placeholder="consumer secret" style="width:100%; padding:.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                </label>
            </div>
            <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.9rem; color:var(--bone); margin-top:.8rem;">
                <input type="checkbox" name="age_daily_box_gated" value="1" <?= !empty($settings['age_daily_box_gated']) ? 'checked' : '' ?> style="width:18px;height:18px;">
                A caixa <strong>diária grátis</strong> também exige verificação (recomendado)
            </label>
            <p style="margin-top: 0.6rem; font-size: 0.8rem; color: var(--dim);">
                Depois de salvar, use <strong>Testar chave</strong> na tela <a href="/admin/eca" style="color: var(--hazard);">Conformidade ECA</a>. FlagCheck: cadastro em flagcheck.com.br, créditos por PIX, cerca de R$ 3 a 5 por verificação, uma vez por jogador.
            </p>
        </div>
```

- [ ] **Step 5: Rotas admin** (`public/index.php`, antes de `// ============ ADMIN: ENTITLEMENTS`):

```php
// ============ ADMIN: PROTECAO DE MENORES (ECA Digital) ============
\App\Router::get('/admin/eca', function() use ($config) {
    \App\Auth::requireCan('settings');
    $q = fn(string $sql, array $a = []) => (int) \App\Database::fetchColumn($sql, $a);
    \App\View::display('admin.eca', [
        'config' => $config,
        'modo' => \App\AgeVerification::modo(),
        'fornecedor' => (string) \App\Settings::get('age_provider', 'flagcheck'),
        'tem_chave' => trim((string) \App\Settings::get('age_provider_key', '')) !== '',
        'n_verificados' => $q("SELECT COUNT(*) FROM players WHERE age_status = 'adulto_verificado'"),
        'n_declarados'  => $q("SELECT COUNT(*) FROM players WHERE age_status = 'adulto_declarado'"),
        'n_menores'     => $q("SELECT COUNT(*) FROM players WHERE age_status = 'menor'"),
        'n_falhas_30d'  => $q("SELECT COUNT(*) FROM age_verifications WHERE result = 'falhou' AND created_at >= NOW() - INTERVAL 30 DAY"),
        'ultimas' => \App\Database::fetchAll(
            "SELECT id, steam_id, method, result, provider_ref, provider_status, created_at, revoked_at, revoked_reason
             FROM age_verifications ORDER BY id DESC LIMIT 200"),
        'teste' => isset($_GET['teste']) ? json_decode((string) $_GET['teste'], true) : null,
        'ok' => isset($_GET['ok']),
    ]);
});

\App\Router::post('/admin/eca/testar-chave', function() use ($config) {
    \App\Auth::requireCan('settings');
    if (!\App\Csrf::check()) { header('Location: /admin/eca'); exit; }
    $r = \App\AgeVerifierFactory::fromSettings()->test();
    \App\AuditLog::record('age.key_tested', 'settings', 'eca', ['ok' => $r['ok']]);
    header('Location: /admin/eca?teste=' . rawurlencode(json_encode($r))); exit;
});

\App\Router::post('/admin/eca/revogar', function() use ($config) {
    \App\Auth::requireCan('settings');
    if (!\App\Csrf::check()) { header('Location: /admin/eca'); exit; }
    $id = (int) ($_POST['id'] ?? 0);
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    if ($id < 1 || $motivo === '') { header('Location: /admin/eca'); exit; }
    if (\App\AgeVerification::revogar($id, $motivo)) {
        \App\AuditLog::record('age.revoked', 'age_verification', (string) $id, ['motivo' => $motivo]);
    }
    header('Location: /admin/eca?ok=1'); exit;
});

\App\Router::get('/admin/eca/export.csv', function() use ($config) {
    \App\Auth::requireCan('settings');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="verificacoes-idade-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'steam_id', 'metodo', 'resultado', 'fornecedor_ref', 'situacao', 'criado_em', 'revogado_em', 'motivo_revogacao']);
    foreach (\App\Database::fetchAll("SELECT id, steam_id, method, result, provider_ref, provider_status, created_at, revoked_at, revoked_reason FROM age_verifications ORDER BY id") as $r) {
        fputcsv($out, array_values($r));
    }
    fclose($out);
    \App\AuditLog::record('age.exported', 'age_verification', 'csv');
    exit;
});

\App\Router::get('/admin/eca/relatorio', function() use ($config) {
    \App\Auth::requireCan('settings');
    \App\View::display('admin.eca_relatorio', [
        'config' => $config,
        'site' => site_name('Site'),
        'modo' => \App\AgeVerification::modo(),
        'fornecedor' => \App\AgeVerifierFactory::PROVIDERS[(string) \App\Settings::get('age_provider', 'flagcheck')] ?? 'FlagCheck',
        'diaria_exige' => \App\AgeVerification::diariaExige(),
        'terms_version' => (string) \App\Settings::get('terms_version', '1'),
        'ultima_ok' => \App\Database::fetchColumn("SELECT MAX(created_at) FROM age_verifications WHERE result = 'adulto' AND method <> 'declaracao' AND revoked_at IS NULL"),
        'n_verificados' => (int) \App\Database::fetchColumn("SELECT COUNT(*) FROM players WHERE age_status = 'adulto_verificado'"),
        'gerado_em' => date('d/m/Y H:i'),
    ]);
});
```

- [ ] **Step 6: View `views/admin/eca.php`**

```php
<?php /** @var array $config, $ultimas; @var string $modo, $fornecedor; @var bool $tem_chave, $ok; @var int $n_verificados, $n_declarados, $n_menores, $n_falhas_30d; @var ?array $teste */ ?>
<?php $title = 'Conformidade ECA Digital'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>
<div class="admin-page-head">
    <div>
        <h1>🛡 Proteção de menores (ECA Digital)</h1>
        <p>Lei 15.211/2025 e Decreto 12.880/2026. Caixa de recompensa só para adulto verificado. Esta tela mostra só metadados: nunca CPF, nunca data de nascimento.</p>
    </div>
</div>

<?php if ($ok): ?><div class="stat-card" style="margin-bottom:1rem; border-left:3px solid var(--moss);">✓ Feito.</div><?php endif; ?>
<?php if ($teste): ?>
    <div class="stat-card" style="margin-bottom:1rem; border-left:3px solid <?= !empty($teste['ok']) ? 'var(--moss)' : 'var(--danger-border)' ?>;">
        Teste da chave: <?= e((string) ($teste['msg'] ?? '')) ?>
    </div>
<?php endif; ?>

<div class="stat-card" style="margin-bottom:1.2rem; border-left:3px solid <?= $modo === 'verificado' ? 'var(--moss)' : ($modo === 'declaracao' ? 'var(--hazard)' : 'var(--danger-border)') ?>;">
    <strong>Modo atual: <?= e($modo) ?></strong>
    <?php if ($modo === 'desligado'): ?> · <span style="color:var(--rust-2);">não conforme: caixas abertas sem verificação</span>
    <?php elseif ($modo === 'declaracao'): ?> · transição: caixas fechadas até colar a chave e mudar para Verificado
    <?php else: ?> · conforme<?php endif; ?>
    · fornecedor <strong><?= e($fornecedor) ?></strong> <?= $tem_chave ? '(chave salva)' : '<span style="color:var(--rust-2);">(sem chave)</span>' ?>
    <form method="POST" action="/admin/eca/testar-chave" style="display:inline; margin-left:.8rem;"><?= \App\Csrf::field() ?><button class="btn btn-sm" type="submit">Testar chave</button></form>
    <a class="btn btn-sm" href="/admin/settings#eca" style="margin-left:.4rem;">Configurar</a>
    <a class="btn btn-sm" href="/admin/eca/relatorio" target="_blank" style="margin-left:.4rem;">Relatório de conformidade</a>
    <a class="btn btn-sm" href="/admin/eca/export.csv" style="margin-left:.4rem;">Exportar CSV</a>
</div>

<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.2rem;">
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_verificados ?></div><div style="color:var(--dim);">adultos verificados</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_declarados ?></div><div style="color:var(--dim);">adultos só declarados</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_menores ?></div><div style="color:var(--dim);">menores (bloqueados)</div></div>
    <div class="stat-card"><div style="font-size:1.8rem;"><?= (int) $n_falhas_30d ?></div><div style="color:var(--dim);">falhas do fornecedor (30 dias)</div></div>
</div>

<div class="stat-card">
    <h3 style="margin-top:0;">Últimas 200 verificações</h3>
    <table class="admin-table" style="width:100%;">
        <thead><tr><th>#</th><th>SteamID</th><th>Método</th><th>Resultado</th><th>Ref. do fornecedor</th><th>Situação</th><th>Quando</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($ultimas as $r): ?>
            <tr style="<?= $r['revoked_at'] ? 'opacity:.55;' : '' ?>">
                <td><?= (int) $r['id'] ?></td>
                <td><a href="/player/<?= e($r['steam_id']) ?>" style="color:var(--hazard);"><?= e($r['steam_id']) ?></a></td>
                <td><?= e($r['method']) ?></td>
                <td><?= e($r['result']) ?><?= $r['revoked_at'] ? ' (revogada: ' . e((string) $r['revoked_reason']) . ')' : '' ?></td>
                <td style="font-family:var(--font-mono); font-size:.8rem;"><?= e((string) ($r['provider_ref'] ?? '')) ?></td>
                <td><?= e((string) ($r['provider_status'] ?? '')) ?></td>
                <td><?= e($r['created_at']) ?></td>
                <td>
                    <?php if (!$r['revoked_at'] && $r['result'] === 'adulto' && $r['method'] !== 'declaracao'): ?>
                    <form method="POST" action="/admin/eca/revogar" onsubmit="return confirm('Revogar esta verificação?');" style="display:flex; gap:.3rem;">
                        <?= \App\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input name="motivo" placeholder="motivo" required maxlength="160" style="padding:.3rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
                        <button class="btn btn-sm" type="submit">Revogar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php \App\View::endSection(); ?>
```

- [ ] **Step 7: View `views/admin/eca_relatorio.php`** (página solta, imprimível, sem o layout do admin)

```php
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>Relatório de conformidade ECA Digital - <?= e($site) ?></title>
<style>body{font:14px/1.5 Georgia,serif;max-width:800px;margin:2rem auto;color:#111} h1{font-size:1.5rem} h2{font-size:1.1rem;margin-top:1.6rem} table{border-collapse:collapse;width:100%} td,th{border:1px solid #999;padding:.4rem;text-align:left} @media print{a{color:#111;text-decoration:none}}</style></head>
<body>
<h1>Relatório de conformidade: proteção de menores (ECA Digital)</h1>
<p><strong>Site:</strong> <?= e($site) ?> · <strong>Gerado em:</strong> <?= e($gerado_em) ?></p>

<h2>1. Base legal</h2>
<p>Lei 15.211/2025 (Estatuto Digital da Criança e do Adolescente), art. 9º (mecanismos confiáveis de verificação de idade, vedada a autodeclaração) e art. 20 (vedação de caixas de recompensa a crianças e adolescentes). Decreto 12.880/2026, art. 23 (verificação de idade para caixas de recompensa; caixa restrita por padrão) e art. 24 (requisitos do mecanismo). Orientações preliminares da ANPD sobre mecanismos confiáveis de aferição de idade (março de 2026).</p>

<h2>2. Mecanismo adotado</h2>
<table>
<tr><th>Modo em operação</th><td><?= e($modo) ?></td></tr>
<tr><th>Fornecedor da verificação</th><td><?= e($fornecedor) ?> (conferência do CPF em base oficial)</td></tr>
<tr><th>Caixa diária gratuita</th><td><?= $diaria_exige ? 'também exige verificação' : 'liberada para adulto declarado' ?></td></tr>
<tr><th>Versão dos Termos com consentimento registrado</th><td><?= e($terms_version) ?></td></tr>
<tr><th>Adultos verificados (ativos)</th><td><?= (int) $n_verificados ?></td></tr>
<tr><th>Última verificação bem-sucedida</th><td><?= e((string) ($ultima_ok ?? 'nenhuma')) ?></td></tr>
</table>

<h2>3. Como funciona</h2>
<ol>
<li>Caixas de recompensa ficam fechadas por padrão. Só abrem para conta com verificação de idade concluída por fonte externa.</li>
<li>Compra de moeda exige declaração de data de nascimento (18+) e aceite registrado dos Termos de Uso e da Política de Privacidade, com data, hora, IP e versão do texto.</li>
<li>A verificação usa o CPF apenas durante a consulta ao fornecedor. O CPF não é armazenado, registrado em log ou mantido em sessão. Fica gravado: resultado, método, data, referência de auditoria do fornecedor, situação cadastral e um hash irreversível com sal próprio do site, usado só para impedir que um CPF verifique mais de uma conta.</li>
<li>Menores declarados ou detectados ficam com compras e caixas bloqueadas, mantendo o acesso ao jogo, ranking e clãs.</li>
<li>O jogador pode contestar e retificar refazendo a verificação; o administrador pode revogar uma verificação com motivo registrado.</li>
</ol>

<h2>4. Requisitos do art. 24 do Decreto 12.880/2026</h2>
<table>
<tr><th>I. Proporcionalidade</th><td>Verificação exigida só para a funcionalidade vedada (caixas); declaração para compra de moeda.</td></tr>
<tr><th>II. Acurácia e confiabilidade</th><td>Fonte oficial (Receita Federal) via fornecedor especializado; autodeclaração não libera caixas.</td></tr>
<tr><th>III. Finalidade única</th><td>Dados usados só para aferir idade; sem perfil comportamental.</td></tr>
<tr><th>IV. Minimização</th><td>Guardados só resultado, método, data e ano de nascimento; CPF descartado.</td></tr>
<tr><th>V. Privacidade</th><td>Sem biometria, sem imagem de documento.</td></tr>
<tr><th>VI. Sem compartilhamento contínuo</th><td>Uma consulta por verificação; nenhum envio recorrente.</td></tr>
<tr><th>VII. Segurança</th><td>Hash com sal por site; chaves do fornecedor gravadas no servidor, nunca exibidas.</td></tr>
<tr><th>VIII. Sem rastreabilidade de histórico</th><td>Não há registro de acessos do jogador ligado à verificação.</td></tr>
<tr><th>IX. Interoperabilidade</th><td>Fornecedor substituível (FlagCheck, Serpro, CPFHub) sem alterar o registro.</td></tr>
<tr><th>X. Inclusão</th><td>Sem exigência de documento com foto ou dispositivo específico; CPF é universal.</td></tr>
<tr><th>XI. Transparência e auditabilidade</th><td>Este relatório, a página pública /idade explicando o mecanismo, exportação CSV e trilha de auditoria do administrador.</td></tr>
</table>
<p style="margin-top:2rem;font-size:.85rem;color:#555;">Gerado automaticamente pelo painel do site. Reflete a configuração no momento da emissão.</p>
</body></html>
```

- [ ] **Step 8: Menu e aviso no layout** (`views/admin/layout.php`)

Na lista de menu, após a linha 78 (`/admin/settings`):

```php
            ['settings',            '/admin/eca',                '🛡 Conformidade ECA',    str_starts_with($current, '/admin/eca')],
```

No layout, logo antes da linha 103 (`<?= \App\View::yield('content') ?>`):

```php
<?php $ecaModo = \App\AgeVerification::modo(); if ($ecaModo !== 'verificado'): ?>
    <div style="margin:0 0 1rem; padding:.7rem 1rem; border-radius:6px; border-left:4px solid <?= $ecaModo === 'desligado' ? 'var(--danger-border)' : 'var(--hazard)' ?>; background:<?= $ecaModo === 'desligado' ? 'var(--danger-overlay)' : 'transparent' ?>;">
        <?php if ($ecaModo === 'desligado'): ?>
            <strong>Não conforme com a Lei 15.211/2025:</strong> caixas de recompensa abertas sem verificação de idade.
        <?php else: ?>
            <strong>ECA Digital em transição:</strong> caixas fechadas até configurar o fornecedor de verificação.
        <?php endif; ?>
        <a href="/admin/eca" style="color:var(--hazard); margin-left:.5rem;">Resolver</a>
    </div>
<?php endif; ?>
```

- [ ] **Step 9: Rodar e ver passar**

Run: `php tests/eca-admin.php && php -l public/index.php && php -l views/admin/settings.php && php -l views/admin/eca.php && php -l views/admin/eca_relatorio.php && php -l views/admin/layout.php`
Expected: `TUDO OK` e sem erro de sintaxe.

- [ ] **Step 10: Commit**

```bash
git add public/index.php views/admin/settings.php views/admin/eca.php views/admin/eca_relatorio.php views/admin/layout.php tests/eca-admin.php
git commit -m "feat(eca): painel de configuracao, conformidade, relatorio imprimivel e aviso por modo"
```

---

### Task 8: Documentação, versão e comunicado

**Files:**
- Modify: `CHANGELOG.md` (nova seção `## [3.3.0] - <data do dia>` acima da 3.2.7), `README.md` (parágrafo na lista de recursos), `RELEASE_NOTES.md`
- Create: `_handoff-claudes/BOT_para_bot-discord_<data>_age_required.md` **fora do repositório** (em `C:\Development\Test\_handoff-claudes\`)
- Test: `tests/eca-docs.php`

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php
/** O CHANGELOG tem a 3.3.0 no topo, sem travessao, e o README fala do recurso. Rodar: php tests/eca-docs.php */
$falhas = 0;
function ok(string $d): void { echo "  OK   $d\n"; }
function falha(string $d, string $x = ''): void { global $falhas; $falhas++; echo "  FALHA $d" . ($x !== '' ? "\n         $x" : '') . "\n"; }
$ROOT = dirname(__DIR__);
$cl = file_get_contents($ROOT . '/CHANGELOG.md');
if (preg_match('/^## \[3\.3\.0\]/m', $cl) && strpos($cl, '## [3.3.0]') < strpos($cl, '## [3.2.7]')) ok('3.3.0 acima da 3.2.7'); else falha('CHANGELOG sem 3.3.0 no topo');
$sec = substr($cl, strpos($cl, '## [3.3.0]'), strpos($cl, '## [3.2.7]') - strpos($cl, '## [3.3.0]'));
foreach (['Lei 15.211/2025', 'update.php', 'terms_accepted', 'age_required', 'sem login'] as $t) { if (stripos($sec, $t) !== false) ok("changelog cita '$t'"); else falha("changelog nao cita '$t'"); }
if (!str_contains($sec, "\u{2014}") && !str_contains($sec, "\u{2013}")) ok('sem travessao'); else falha('travessao no changelog');
if (stripos(file_get_contents($ROOT . '/README.md'), 'ECA Digital') !== false) ok('README cita ECA Digital'); else falha('README nao cita ECA Digital');
echo "\n" . str_repeat('-', 62) . "\n";
if ($falhas === 0) { echo "TUDO OK\n"; exit(0); }
echo "$falhas FALHA(S).\n"; exit(1);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php tests/eca-docs.php`
Expected: FALHA em tudo.

- [ ] **Step 3: CHANGELOG** (acima de `## [3.2.7] - 2026-09-04`)

```markdown
## [3.3.0] - AAAA-MM-DD

### Adicionado

- **Proteção de menores (ECA Digital, Lei 15.211/2025).** Caixas de recompensa passam a abrir
  só para jogador com idade **verificada por fonte oficial** (CPF conferido num fornecedor
  externo: FlagCheck, Serpro ou CPFHub, à escolha do site, com a chave dele no painel). Comprar
  moeda passa a exigir data de nascimento (18+) e aceite **real** dos Termos, com data, hora,
  IP e versão. O CPF nunca é guardado: fica só o resultado, o método, a data e um hash com sal
  do site, que impede um CPF de verificar mais de uma conta.
- Nova tela pública `/idade` e, no painel, **Conformidade ECA** com contadores, últimas
  verificações (só metadados), exportação CSV, revogação com motivo e um **relatório de
  conformidade** imprimível que responde aos 11 requisitos do art. 24 do Decreto 12.880/2026.
- Três modos no painel: `verificado` (conforme), `declaracao` (transição, caixas fechadas) e
  `desligado` (não conforme, com aviso vermelho fixo). A migration entra em `declaracao`.

### Alterado

- Rode o `/update.php`: migration `v3.3.0_eca_digital.sql` (tabelas `age_verifications` e
  `consents`, colunas `players.age_status` e `players.age_verified_at`, settings novas).
- O campo oculto `terms_accepted=1` das telas de compra **foi removido**: o site gravava um
  aceite que o jogador nunca marcou. Agora o aceite vem da conta, na tela `/idade`.
- Comprar **sem login Steam** (só digitando o SteamID) deixou de existir: sem conta não há
  como registrar declaração de idade nem consentimento. O botão leva ao login.
- O bot do Discord recebe `age_required` (HTTP 403) ao tentar criar cobrança para jogador que
  ainda não declarou idade; a versão do bot que mostra o link `/idade` sai em separado.
```

- [ ] **Step 4: README**: na lista de recursos, uma linha nova junto das caixas:

```markdown
- **🛡 Proteção de menores (ECA Digital)**: caixas só para adulto verificado por CPF (fornecedor plugável), consentimento real dos Termos, painel de conformidade e relatório imprimível. Detalhes em `docs/superpowers/specs/2026-09-24-verificacao-idade-eca-digital-design.md`.
```

- [ ] **Step 5: Handoff para o Claude do Bot** (em `C:\Development\Test\_handoff-claudes\BOT_para_bot-discord_AAAA-MM-DD_age_required.md`), sem segredo nenhum:

```markdown
# Template 3.3.0: o site responde `age_required` na compra pelo Discord

`POST /api/bot-integration.php` (`create_checkout`, `create_pix`, `create_coin_pix`, `create_invoice`)
passa a devolver **HTTP 403** com `{"ok":false,"error":"age_required"}` quando o jogador ainda não
declarou idade no site (Lei 15.211/2025). O bot deve responder: "Antes da primeira compra, confirme
sua idade no site: <site_url>/idade". Nada muda para quem já declarou. Sem gate em `claim_box`.
```

- [ ] **Step 6: Rodar e ver passar**

Run: `php tests/eca-docs.php`
Expected: `TUDO OK`.

- [ ] **Step 7: Commit**

```bash
git add CHANGELOG.md README.md RELEASE_NOTES.md tests/eca-docs.php
git commit -m "docs(eca): changelog 3.3.0, readme e comunicado do age_required"
```

---

### Task 9: Rodar tudo, staging, release

**Files:** nenhum novo. Verificação de ponta a ponta.

- [ ] **Step 1: Todos os testes**

Run: `for f in tests/eca-*.php; do php "$f" || exit 1; done; for f in tests/*.php; do php "$f" > /dev/null || echo "REGRESSAO: $f"; done`
Expected: todos `TUDO OK`, nenhuma linha `REGRESSAO`.

- [ ] **Step 2: Migration duas vezes no staging**

No staging da Hostinger (ver memória `reference-staging-site`): subir `migrations/v3.3.0_eca_digital.sql`, `src/AgeGate.php`, `src/AgeVerifier.php`, `src/AgeVerification.php`, `public/index.php`, `public/api/bot-integration.php`, `views/pages/idade.php`, `views/pages/caixas.php`, `views/pages/shop.php`, `views/pages/checkout_pix.php`, `views/admin/settings.php`, `views/admin/eca.php`, `views/admin/eca_relatorio.php`, `views/admin/layout.php`, `lang/pt-br.php`, `lang/en-us.php`. Abrir `/update.php` e aplicar; abrir de novo: tem que dizer "nada pendente".
Expected: `age_verifications`, `consents` criadas; `settings.age_hash_salt` com 64 hex; `age_gate_mode = declaracao`.

- [ ] **Step 3: Fluxo do jogador no staging**

1. Login Steam com conta de teste (TEC Z pode). Ir em `/caixas`: aviso "Verifique sua identidade" aparece; clicar abrir caixa pelo JS: redireciona para `/idade?motivo=caixa`.
2. Declarar nascimento 2010-01-01 + marcar termos: cai em "Este site vende para maiores de 18 anos"; `/shop` comprar: redireciona para `/idade`; `players.age_status = menor`.
3. Na mesma tela, verificar por CPF com chave **sandbox** do FlagCheck e um CPF de teste adulto: `age_status = adulto_verificado`; `/caixas` abre; `age_verifications` tem `cpf_hash` (64 hex), `provider_ref`, nenhum CPF em claro (`SELECT * FROM age_verifications` a olho).
4. Segunda conta Steam com o mesmo CPF: "Este CPF já verificou outra conta".
5. Admin: `/admin/eca` mostra 1 verificado; revogar com motivo; jogador volta a `adulto_declarado`; verificar de novo com o mesmo CPF: passa (hash liberado).
6. `/admin/eca/relatorio` imprime; `/admin/eca/export.csv` baixa sem coluna de CPF.
7. Modo `desligado`: aviso vermelho no admin; a conta `menor` continua sem abrir caixa.
Expected: tudo como descrito; prints salvos em `D:\Tecplay\00-Geral\eca-staging-AAAA-MM-DD\`.

- [ ] **Step 4: Release**

Run: `python D:\Tecplay\00-Geral\scripts-uteis-claude\criar-release.py` (prévia) e depois `--aplicar` quando a árvore estiver limpa e empurrada.
Expected: tag `v3.3.0` e release publicado com as notas do CHANGELOG.

- [ ] **Step 5: Deploy no NomadeZ e no Danoninho-Z**

Mesma lista de arquivos do Step 2, pelo layout de cada hospedagem (memória `reference-deploy-layout-hospedagem`: NomadeZ usa `public_html/` no lugar de `public/`). Rodar `/update.php` em cada um. No NomadeZ, o cliente cadastra a chave do FlagCheck e muda o modo para `verificado`. Avisar a Matriz para atualizar a página do produto.
