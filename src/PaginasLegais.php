<?php
// ============================================================
// PaginasLegais - aplica o texto modelo de Termos, Privacidade, Reembolso e a resposta do FAQ
// sobre menores, preenchido com os dados que o dono cadastra no painel (/admin/eca).
// O modelo mora em src/legal/*.html (mesmo texto do seed da instalacao). O update nunca troca
// as paginas sozinho: quem aplica e o dono, num clique, e o texto anterior vai para page_versions.
// ============================================================

namespace App;

class PaginasLegais
{
    /** Versao do modelo. Mudou o texto em src/legal? Suba esta data: o painel volta a oferecer. */
    public const MODELO = '2026-09-26';
    public const SLUGS = ['terms', 'privacy', 'refund'];
    private const SE_COMPRA = ' [SE O DONO LIGAR A VERIFICAÇÃO NA COMPRA: a idade também é verificada por CPF.]';
    /** Prazo de guarda do registro da consulta, conforme a politica publicada de cada fornecedor. */
    private const PRAZO_FORNECEDOR = ['cpfhub' => '12 meses'];

    public static function precisaAplicar(): bool
    {
        return (string) Settings::get('legal_modelo_aplicado', '') !== self::MODELO;
    }

    /** Rotulos dos dados que faltam para aplicar. Vazio = pode aplicar. */
    public static function faltando(): array
    {
        $f = [];
        if (self::v('legal_razao_social') === '') $f[] = 'Razão social';
        if (self::v('legal_cnpj') === '') $f[] = 'CNPJ';
        if (!filter_var(self::v('legal_email'), FILTER_VALIDATE_EMAIL)) $f[] = 'E-mail de atendimento';
        if (self::v('legal_hospedagem') === '') $f[] = 'Provedor de hospedagem';
        if (!self::noBrasil() && self::v('legal_mecanismo_hospedagem') === '') $f[] = 'Fundamento da transferência da hospedagem';
        return $f;
    }

    /**
     * Troca as tres paginas e a resposta do FAQ sobre menores pelo modelo preenchido.
     * @return array{ok:bool, faltando?:array, paginas?:int, faq?:bool}
     */
    public static function aplicar(string $nomeSite, string $dominio, string $quem): array
    {
        $falta = self::faltando();
        if ($falta) return ['ok' => false, 'faltando' => $falta];

        $campos = self::campos($nomeSite, $dominio);
        $nota = self::arquivo('nota_en');
        $n = 0;
        foreach (self::SLUGS as $slug) {
            $novo = self::preencher(self::arquivo($slug), $campos);
            $atual = Database::fetchOne("SELECT body_ptbr, body_enus FROM pages WHERE slug = ?", [$slug]);
            if ($atual === null) continue;   // pagina apagada pelo dono: nao recria sem ele pedir
            self::guardaVersao($slug, $atual, $quem);
            Database::query("UPDATE pages SET body_ptbr = ?, body_enus = ? WHERE slug = ?", [$novo, $nota . $novo, $slug]);
            $n++;
        }
        $faqOk = false;
        $faq = Database::fetchOne("SELECT body_ptbr, body_enus FROM pages WHERE slug = 'faq'");
        $padrao = '#<details class="faq-item">\s*<summary class="faq-q">Sou menor de 18[^<]*</summary>.*?</details>#su';
        if ($faq !== null && preg_match_all($padrao, (string) $faq['body_ptbr']) === 1) {
            self::guardaVersao('faq', $faq, $quem);
            $bloco = self::preencher(trim(self::arquivo('faq_menor')), $campos);
            $novoFaq = preg_replace_callback($padrao, fn() => $bloco, (string) $faq['body_ptbr']);
            Database::query("UPDATE pages SET body_ptbr = ? WHERE slug = 'faq'", [$novoFaq]);
            $faqOk = true;
        }
        Settings::set('terms_version', date('Y-m-d'));   // jogadores aceitam o texto novo na proxima compra
        Settings::set('legal_modelo_aplicado', self::MODELO);
        return ['ok' => true, 'paginas' => $n, 'faq' => $faqOk];
    }

    private static function campos(string $nomeSite, string $dominio): array
    {
        $h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $email = self::v('legal_email');
        $invite = trim((string) Settings::get('discord_invite', ''));
        $discord = preg_match('#^https://(discord\.gg|discord\.com/invite)/[A-Za-z0-9-]+/?$#', $invite)
            ? 'Discord oficial (<a href="' . $h($invite) . '" target="_blank" rel="noopener">' . $h(preg_replace('#^https://#', '', $invite)) . '</a>)'
            : 'Discord oficial';
        $prov = (string) Settings::get('age_provider', 'cpfhub');
        $prazo = self::v('legal_prazo_fornecedor') ?: (self::PRAZO_FORNECEDOR[$prov] ?? 'o prazo informado na política do fornecedor');
        $pais = self::noBrasil() ? 'Brasil' : self::v('legal_pais');
        return [
            self::SE_COMPRA => '',
            '[NOME DO SERVIDOR]' => $h($nomeSite),
            '[RAZÃO SOCIAL]' => $h(self::v('legal_razao_social')),
            '[CNPJ]' => $h(self::v('legal_cnpj')),
            '[DOMÍNIO]' => $h($dominio),
            '[E-MAIL DE ATENDIMENTO]' => '<a href="mailto:' . $h($email) . '">' . $h($email) . '</a>',
            '[DISCORD OFICIAL]' => $discord,
            'em [PAÍS DE ARMAZENAMENTO]' => self::noBrasil() ? 'no Brasil' : 'em ' . $h($pais),
            '[PROVEDOR DE HOSPEDAGEM]' => $h(self::v('legal_hospedagem')),
            '[MECANISMO DA HOSPEDAGEM]' => $h(self::v('legal_mecanismo_hospedagem')),
            '[FORNECEDOR DE VERIFICAÇÃO DE IDADE]' => $h(AgeVerifierFactory::PROVIDERS[$prov] ?? $prov),
            '[PRAZO DE GUARDA DO FORNECEDOR]' => $h($prazo),
            '[VERSÃO]' => date('Y-m-d'),
            '[DATA DE VIGÊNCIA]' => date('d/m/Y'),
        ];
    }

    private static function preencher(string $html, array $campos): string
    {
        // Hospedagem no Brasil nao e transferencia internacional: sai da lista do item 5.
        if (self::noBrasil()) $html = preg_replace('#<li><strong>\[PROVEDOR DE HOSPEDAGEM\]:</strong> todos os dados do site[^\n]*</li>\n?#u', '', $html);
        return strtr($html, $campos);
    }

    private static function guardaVersao(string $slug, array $atual, string $quem): void
    {
        Database::query("INSERT INTO page_versions (slug, body_ptbr, body_enus, motivo, saved_by) VALUES (?, ?, ?, ?, ?)",
            [$slug, $atual['body_ptbr'], $atual['body_enus'], 'antes de aplicar o modelo ' . self::MODELO, $quem]);
    }

    private static function arquivo(string $nome): string
    {
        return (string) file_get_contents(__DIR__ . '/legal/' . $nome . '.html');
    }

    private static function noBrasil(): bool
    {
        $p = mb_strtolower(trim((string) Settings::get('legal_pais', 'Brasil')));
        return $p === '' || $p === 'brasil' || $p === 'brazil';
    }

    private static function v(string $k): string
    {
        return trim((string) Settings::get($k, ''));
    }
}
