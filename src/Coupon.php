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
    public static function lookup(string $code, ?string $packageId = null, ?float $packagePrice = null): array {
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
        if ($coupon['discount_type'] === 'percent') {
            $discount = round($price * ((float)$coupon['discount_value'] / 100), 2);
        } else {
            $discount = (float)$coupon['discount_value'];
        }
        $discount = min($discount, $price);   // nunca maior que o preço
        $discount = max($discount, 0);        // nunca negativo
        $final = round($price - $discount, 2);
        return [$discount, $final];
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
