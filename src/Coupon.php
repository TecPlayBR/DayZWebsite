<?php
// ============================================================
// Coupon - validação e aplicação de cupons de desconto.
// ============================================================

namespace App;

class Coupon {

    /**
     * Busca cupom pelo código (case-insensitive) e valida elegibilidade.
     * Retorna array com [coupon, error]. Se error != null, cupom inválido.
     */
    public static function lookup(string $code, ?string $packageId = null, ?float $packagePrice = null, ?string $steamId = null): array {
        $code = strtoupper(trim($code));
        if ($code === '') return [null, 'empty'];

        $coupon = Database::fetchOne(
            "SELECT * FROM coupons WHERE UPPER(code) = ? LIMIT 1",
            [$code]
        );

        if (!$coupon) return [null, 'not_found'];
        if (!(int)$coupon['active']) return [null, 'inactive'];

        $now = time();
        if (!empty($coupon['valid_from']) && strtotime($coupon['valid_from']) > $now) {
            return [null, 'not_yet_valid'];
        }
        if (!empty($coupon['valid_until']) && strtotime($coupon['valid_until']) < $now) {
            return [null, 'expired'];
        }
        if (!empty($coupon['max_uses']) && (int)$coupon['used_count'] >= (int)$coupon['max_uses']) {
            return [null, 'exhausted'];
        }
        // Limite POR jogador (ex: cupom de aniversário = 1x por pessoa).
        // Conta as compras pagas dele com este código. Degrada limpo em base antiga (coluna ausente).
        if ($steamId !== null && !empty($coupon['per_user_limit'])) {
            $usedByUser = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM purchases
                  WHERE steam_id = ? AND UPPER(coupon_code) = UPPER(?)
                    AND (mp_status = 'approved' OR delivered_at IS NOT NULL)",
                [$steamId, $code]
            );
            if ($usedByUser >= (int)$coupon['per_user_limit']) return [null, 'user_exhausted'];
        }

        // Filtro por pacote
        if ($packageId !== null && !empty($coupon['package_ids'])) {
            $allowedIds = json_decode($coupon['package_ids'], true) ?: [];
            if (!empty($allowedIds) && !in_array($packageId, $allowedIds, true)) {
                return [null, 'not_for_this_package'];
            }
        }

        return [$coupon, null];
    }

    /**
     * Calcula desconto a aplicar dado o cupom + valor original.
     * Retorna [discount_brl, final_price] (ambos floats).
     */
    public static function applyDiscount(array $coupon, float $price): array {
        $type = $coupon['discount_type'] ?? 'percent';
        if ($type === 'coins') {
            // Benefício é moeda bônus (creditada à parte), NÃO desconto no preço.
            return [0.0, round($price, 2)];
        }
        if ($type === 'percent') {
            $discount = round($price * ((float)$coupon['discount_value'] / 100), 2);
        } else {
            $discount = (float)$coupon['discount_value'];
        }
        $discount = min($discount, $price);   // nunca maior que o preço
        $discount = max($discount, 0);        // nunca negativo
        $final = round($price - $discount, 2);
        return [$discount, $final];
    }

    /** Moedas bônus que o cupom concede (tipo 'coins'); 0 nos demais tipos. */
    public static function bonusCoins(array $coupon): int {
        return ($coupon['discount_type'] ?? '') === 'coins'
            ? max(0, (int) round((float) $coupon['discount_value']))
            : 0;
    }

    /** O cupom faz parte do programa de afiliado/streamer? (tem nome OU alguma comissão > 0) */
    public static function isAffiliate(array $coupon): bool {
        return trim((string) ($coupon['affiliate_name'] ?? '')) !== ''
            || (float) ($coupon['commission_pct_1'] ?? 0) > 0
            || (float) ($coupon['commission_pct_2'] ?? 0) > 0
            || (float) ($coupon['commission_pct_3plus'] ?? 0) > 0;
    }

    /**
     * Mensagem amigável pro erro retornado por lookup().
     */
    public static function errorMessage(string $code): string {
        return match($code) {
            'empty'                => 'Digite um código de cupom.',
            'not_found'            => 'Cupom não encontrado.',
            'inactive'             => 'Este cupom não está ativo.',
            'not_yet_valid'        => 'Este cupom ainda não está válido.',
            'expired'              => 'Este cupom expirou.',
            'exhausted'            => 'Este cupom já atingiu o limite de usos.',
            'user_exhausted'       => 'Você já usou este cupom o máximo de vezes permitido.',
            'not_for_this_package' => 'Este cupom não vale pro pacote selecionado.',
            default                => 'Cupom inválido.',
        };
    }

    /** Incrementa contador de uso após compra aprovada. */
    public static function incrementUse(string $code): void {
        Database::query(
            "UPDATE coupons SET used_count = used_count + 1 WHERE UPPER(code) = UPPER(?)",
            [$code]
        );
    }
}
