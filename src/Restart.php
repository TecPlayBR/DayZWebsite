<?php
// ============================================================
// Restart - horário do próximo restart do servidor DayZ.
// ============================================================
// O admin cadastra os horários (BR) em que o servidor reinicia
// (ex: "00:00, 04:00, 08:00, 12:00, 16:00, 20:00"). O site calcula
// o PRÓXIMO e usa pra:
//   1. Mostrar discretamente pro player ("próximo restart em Xh").
//   2. BLINDAR o drop de item: dentro da janela de perigo (~min antes
//      do restart) NÃO dropa ao vivo (item iria pro limbo) - segura
//      como pendente e entrega quando o server voltar.
//
// CFTools NÃO expõe o schedule de restart (restart é config do host,
// não do CF). Por isso o cadastro é manual - confiável e universal.
// ============================================================

namespace App;

class Restart {
    /** Lista de horários "HH:MM" válidos cadastrados. */
    public static function times(): array {
        $raw = (string) Settings::get('restart_times', '');
        $out = [];
        foreach (preg_split('/[,;\s]+/', $raw) ?: [] as $t) {
            $t = trim($t);
            if ($t !== '' && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $t)) $out[] = $t;
        }
        return $out;
    }

    public static function enabled(): bool {
        return Settings::getBool('restart_enabled') && !empty(self::times());
    }

    /** Timestamp unix do próximo restart, ou null se não configurado. */
    public static function next(): ?int {
        if (!self::enabled()) return null;
        $times = self::times();
        $now = time();
        $cands = [];
        foreach ([0, 1] as $dayOffset) {            // hoje e amanhã (cobre virada de dia)
            $day = date('Y-m-d', $now + $dayOffset * 86400);
            foreach ($times as $t) {
                $ts = strtotime($day . ' ' . $t . ':00');
                if ($ts !== false && $ts > $now) $cands[] = $ts;
            }
        }
        if (!$cands) return null;
        sort($cands);
        return $cands[0];
    }

    /** Minutos até o próximo restart (null se não configurado). */
    public static function minutesUntil(): ?int {
        $n = self::next();
        return $n === null ? null : (int) max(0, ceil(($n - time()) / 60));
    }

    /** Janela de aviso (mostrar alerta vermelho). Default 5min (= bloqueio do servidor). */
    public static function warnMinutes(): int {
        $m = (int) Settings::get('restart_warn_minutes', 5);
        return $m > 0 ? $m : 5;
    }

    /**
     * Dentro da janela de PERIGO pro drop? (não dropar ao vivo - vai pro limbo).
     * Buffer maior que o aviso (default warn+5) pra dar folga antes do servidor travar.
     */
    public static function inDangerWindow(?int $bufferMin = null): bool {
        $m = self::minutesUntil();
        if ($m === null) return false;
        $buffer = $bufferMin ?? (self::warnMinutes() + 5);
        return $m <= $buffer;
    }

    /** Resumo pronto pra view: ['enabled','next_ts','minutes','label','warn'] ou null. */
    public static function summary(): ?array {
        if (!self::enabled()) return null;
        $next = self::next();
        if ($next === null) return null;
        $mins = (int) max(0, ceil(($next - time()) / 60));
        $h = intdiv($mins, 60); $m = $mins % 60;
        $rel = $h > 0 ? "{$h}h {$m}min" : "{$m}min";
        return [
            'enabled'  => true,
            'next_ts'  => $next,
            'minutes'  => $mins,
            'at'       => date('H:i', $next),
            'relative' => $rel,
            'warn'     => $mins <= self::warnMinutes(),
            'warn_min' => self::warnMinutes(),
        ];
    }
}
