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
        // Aniversario conta no proprio dia: diff->y ja faz isso.
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
     * Caixa de recompensa: so adulto verificado por CPF, sem excecao para a diaria gratis
     * (ela tambem e caixa de recompensa). Com o modo desligado o site nao verifica ninguem,
     * entao as caixas ficam indisponiveis para todos (parecer juridico de 26/09).
     */
    public static function podeAbrirCaixa(string $status, string $modo): bool
    {
        return self::caixasDisponiveis($modo) && $status === 'adulto_verificado';
    }

    /** Modo desligado = site sem verificacao de idade = sem caixas de recompensa. */
    public static function caixasDisponiveis(string $modo): bool
    {
        return $modo !== 'desligado';
    }

    /**
     * Aviso do painel: o que o JOGADOR vive, nao o nome do modo (Declaracao e Verificado
     * se comportam igual). null = nada a avisar. 'sem_chave' = ha caixa ativa e ninguem
     * consegue abrir; 'falhando' = o fornecedor falhou 3+ vezes em 24 h. Sem caixa ativa, a
     * falta de chave nao afeta ninguem e o dono nao e incomodado.
     * (02/10/2026: o Danoninho-Z ficou 6 dias sem chave e o painel so dizia "em transicao".)
     */
    public static function avisoPainel(string $modo, bool $temChave, int $caixasAtivas, int $falhas24h): ?string
    {
        if ($modo === 'desligado') return 'desligado';
        if (!$temChave) return $caixasAtivas > 0 ? 'sem_chave' : null;
        return $falhas24h >= 3 ? 'falhando' : null;
    }

    /**
     * Quais blocos a tela /idade mostra, em ordem: 'menor', 'declarar', 'consentir', 'verificar'.
     * Revisao 24/09: quem verificou por CPF ANTES de declarar nao tinha consentimento e caia
     * numa tela vazia; o bloco 'consentir' existe pra qualquer status sem aceite da versao atual.
     */
    public static function telas(string $status, string $motivo, bool $temConsentimento): array
    {
        if ($status === 'menor') return ['menor', 'verificar'];
        $out = [];
        if ($status === 'desconhecido') {
            $out[] = 'declarar';                       // declarar ja inclui o aceite dos Termos
        } elseif (!$temConsentimento) {
            $out[] = 'consentir';
        }
        if ($motivo === 'caixa' && $status !== 'adulto_verificado') $out[] = 'verificar';
        return $out;
    }

    /**
     * Novo status depois de uma declaracao de nascimento. null = recusar.
     * Menor so sai por CPF (redeclarar nao reabre); verificado nao e rebaixado por declaracao;
     * menor sempre vence.
     */
    public static function proximoStatusDeclaracao(string $atual, string $declarado): ?string
    {
        if ($declarado === 'menor') return 'menor';
        if ($atual === 'menor') return null;
        if ($atual === 'adulto_verificado') return 'adulto_verificado';
        return 'adulto_declarado';
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

    /**
     * Caminho de retorno depois do fluxo de idade: SO caminho interno. Fora disso, '/'.
     * Recusa '//host', '/\host' (o navegador normaliza a barra invertida e vira '//host'),
     * esquema explicito, host relativo, espaco e quebra de linha (injecao de header).
     */
    public static function returnSeguro(string $r): string
    {
        if ($r === '' || $r[0] !== '/') return '/';
        if (strlen($r) > 1 && ($r[1] === '/' || $r[1] === '\\')) return '/';
        if (preg_match('/[\\\\\s\x00-\x1f\x7f]/', $r)) return '/';
        return $r;
    }

    /** sha256(sal . cpf limpo). O sal e por site (settings.age_hash_salt): hash de um site nao cruza com outro. */
    public static function cpfHash(string $cpf, string $sal): string
    {
        $d = preg_replace('/\D+/', '', $cpf);
        return hash('sha256', $sal . '|' . $d);
    }
}
