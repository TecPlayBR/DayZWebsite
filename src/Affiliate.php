<?php

namespace App;

/**
 * Programa de afiliado / streamer ("Apoie seu Streamer").
 *
 * - O cliente atrela o perfil a UM streamer digitando o código de afiliado dele 1x.
 *   O vínculo fica em players.affiliate_coupon_code (só troca se o admin permitir).
 * - Cada compra carimba purchases.affiliate_coupon_code = vínculo do momento (histórico
 *   estável mesmo se o cliente trocar de streamer depois).
 * - O streamer ganha cachê ESCALONADO pela recorrência do cliente (1ª/2ª/3ª+ compra),
 *   calculado sobre o VALOR CHEIO (preço + desconto), só em compra aprovada.
 * - O benefício pro cliente (% / R$ / moedas) aplica 1x: no momento em que ele se atrela
 *   (evento de bind/troca). Depois ele não redigita o cupom → desconto não repete.
 */
class Affiliate
{
    public static function enabled(): bool
    {
        return Settings::getBool('affiliate_enabled', false);
    }

    public static function allowSwitch(): bool
    {
        return Settings::getBool('affiliate_allow_switch', false);
    }

    /** Código do streamer ao qual o steam_id está atrelado (ou null). */
    public static function binding(string $steamId): ?string
    {
        $code = Database::fetchColumn(
            "SELECT affiliate_coupon_code FROM players WHERE steam_id = ? LIMIT 1",
            [$steamId]
        );
        $code = is_string($code) ? trim($code) : '';
        return $code !== '' ? $code : null;
    }

    /**
     * Atrela o cliente a um streamer. Retorna o resultado:
     *  'disabled' | 'already' | 'blocked' | 'switched' | 'bound'
     * O benefício pro cliente só deve ser concedido quando o retorno for 'bound' ou 'switched'
     * (um NOVO vínculo) — nunca em 'already' (evita desconto repetido).
     */
    public static function bind(string $steamId, string $couponCode): string
    {
        if (!self::enabled()) return 'disabled';
        $couponCode = trim($couponCode);
        if ($couponCode === '') return 'disabled';

        $current = self::binding($steamId);
        if ($current !== null) {
            if (strcasecmp($current, $couponCode) === 0) return 'already';
            if (!self::allowSwitch()) return 'blocked';
            Database::query(
                "UPDATE players SET affiliate_coupon_code = ?, affiliate_bound_at = NOW() WHERE steam_id = ?",
                [$couponCode, $steamId]
            );
            return 'switched';
        }

        // Ainda não atrelado: grava no player (cria a linha mínima se ele nem existe ainda).
        $exists = Database::fetchColumn("SELECT id FROM players WHERE steam_id = ? LIMIT 1", [$steamId]);
        if ($exists) {
            Database::query(
                "UPDATE players SET affiliate_coupon_code = ?, affiliate_bound_at = NOW() WHERE steam_id = ?",
                [$couponCode, $steamId]
            );
        } else {
            Database::query(
                "INSERT INTO players (steam_id, affiliate_coupon_code, affiliate_bound_at, origin)
                 VALUES (?, ?, NOW(), 'payment')",
                [$steamId, $couponCode]
            );
        }
        return 'bound';
    }

    /** % de comissão pra um determinado nº de compra do cliente (1, 2, 3+). */
    public static function pctForNth(array $coupon, int $nth): float
    {
        if ($nth <= 1) return (float) ($coupon['commission_pct_1'] ?? 0);
        if ($nth === 2) return (float) ($coupon['commission_pct_2'] ?? 0);
        return (float) ($coupon['commission_pct_3plus'] ?? 0);
    }

    /**
     * Relatório de cachê por streamer. Lê as compras APROVADAS carimbadas com um
     * affiliate_coupon_code, calcula o tier (1ª/2ª/3ª+ compra do mesmo cliente naquele
     * streamer) e a comissão sobre o valor cheio (price_brl + discount_brl).
     *
     * Retorna lista de streamers (1 por cupom de afiliado) com totais, quebra por mês
     * e as vendas individuais.
     */
    public static function report(): array
    {
        $rows = Database::fetchAll(
            "SELECT p.steam_id, p.price_brl, p.discount_brl, p.created_at,
                    UPPER(p.affiliate_coupon_code) AS code,
                    c.affiliate_name, c.commission_pct_1, c.commission_pct_2, c.commission_pct_3plus
               FROM purchases p
               JOIN coupons c ON UPPER(c.code) = UPPER(p.affiliate_coupon_code)
              WHERE p.mp_status = 'approved'
                AND p.affiliate_coupon_code IS NOT NULL
                AND p.affiliate_coupon_code <> ''
              ORDER BY UPPER(p.affiliate_coupon_code), p.steam_id, p.created_at ASC, p.id ASC"
        );

        $out = [];
        $nthByCustomer = []; // [code][steam_id] => contador de compras

        foreach ($rows as $r) {
            $code = $r['code'];
            if (!isset($out[$code])) {
                $out[$code] = [
                    'code'           => $code,
                    'affiliate_name' => $r['affiliate_name'] ?: $code,
                    'pct1'           => (float) $r['commission_pct_1'],
                    'pct2'           => (float) $r['commission_pct_2'],
                    'pct3plus'       => (float) $r['commission_pct_3plus'],
                    'sales'          => 0,
                    'gross'          => 0.0,
                    'commission'     => 0.0,
                    'buyers'         => [],
                    'by_month'       => [],
                    'rows'           => [],
                ];
            }
            $nthByCustomer[$code][$r['steam_id']] = ($nthByCustomer[$code][$r['steam_id']] ?? 0) + 1;
            $nth   = $nthByCustomer[$code][$r['steam_id']];
            $gross = round((float) $r['price_brl'] + (float) $r['discount_brl'], 2);
            $pct   = self::pctForNth($r, $nth);
            $comm  = round($gross * $pct / 100, 2);
            $month = substr((string) $r['created_at'], 0, 7); // YYYY-MM

            $out[$code]['sales']++;
            $out[$code]['gross']      += $gross;
            $out[$code]['commission'] += $comm;
            $out[$code]['buyers'][$r['steam_id']] = true;

            if (!isset($out[$code]['by_month'][$month])) {
                $out[$code]['by_month'][$month] = ['sales' => 0, 'gross' => 0.0, 'commission' => 0.0];
            }
            $out[$code]['by_month'][$month]['sales']++;
            $out[$code]['by_month'][$month]['gross']      += $gross;
            $out[$code]['by_month'][$month]['commission'] += $comm;

            $out[$code]['rows'][] = [
                'steam_id'   => $r['steam_id'],
                'gross'      => $gross,
                'nth'        => $nth,
                'pct'        => $pct,
                'commission' => $comm,
                'created_at' => $r['created_at'],
            ];
        }

        // Normaliza: buyers vira contagem, ordena meses desc, rows desc por data.
        foreach ($out as &$s) {
            $s['buyers'] = count($s['buyers']);
            krsort($s['by_month']);
            usort($s['rows'], fn($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
        }
        unset($s);

        // Ordena streamers por cachê total desc.
        uasort($out, fn($a, $b) => $b['commission'] <=> $a['commission']);
        return $out;
    }
}
