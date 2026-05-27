<?php
// ============================================================
// Achievements - calculado on-the-fly a partir das compras do player.
// Sem tabela própria: derivado de purchases + players.
// ============================================================

namespace App;

class Achievements {

    /** Lista canônica de achievements disponíveis */
    public static function all(): array {
        return [
            ['slug' => 'first_blood',  'icon' => '🩸', 'name' => 'Primeiro Sangue',  'description' => 'Sua primeira compra aprovada'],
            ['slug' => 'veteran',      'icon' => '⚔',  'name' => 'Veterano',         'description' => '5 compras aprovadas'],
            ['slug' => 'patron',       'icon' => '★',  'name' => 'Padroeiro',        'description' => 'R$ 100 ou mais investidos'],
            ['slug' => 'legendary',    'icon' => '☠',  'name' => 'Lendário',         'description' => 'R$ 500 ou mais investidos'],
            ['slug' => 'night_owl',    'icon' => '☾',  'name' => 'Madrugador',       'description' => 'Compra feita entre 00h e 06h'],
            ['slug' => 'collector',    'icon' => '◇',  'name' => 'Colecionador',     'description' => 'Comprou pacotes de 3 tipos diferentes'],
        ];
    }

    /**
     * Retorna achievements do player como [slug => true]
     */
    public static function unlocked(string $steamId): array {
        $stats = Database::fetchOne(
            "SELECT COUNT(*) AS purchase_count,
                    COALESCE(SUM(price_brl), 0) AS total_spent,
                    COUNT(DISTINCT package_id) AS distinct_packages,
                    SUM(HOUR(created_at) BETWEEN 0 AND 5) AS night_purchases
               FROM purchases
              WHERE steam_id = ? AND mp_status = 'approved'",
            [$steamId]
        );

        $unlocked = [];
        if ((int)$stats['purchase_count']   >= 1)   $unlocked['first_blood'] = true;
        if ((int)$stats['purchase_count']   >= 5)   $unlocked['veteran']     = true;
        if ((float)$stats['total_spent']    >= 100) $unlocked['patron']      = true;
        if ((float)$stats['total_spent']    >= 500) $unlocked['legendary']   = true;
        if ((int)$stats['night_purchases']  >= 1)   $unlocked['night_owl']   = true;
        if ((int)$stats['distinct_packages']>= 3)   $unlocked['collector']   = true;
        return $unlocked;
    }
}
