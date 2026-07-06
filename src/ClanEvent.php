<?php
namespace App;

// ============================================================
// ClanEvent - Eventos de Clã com placar por DELTA.
// ============================================================
// Regras (desenhadas com o Bryan):
//  - Só o LÍDER inscreve o clã, e só ANTES do evento começar.
//  - O placar conta só o que rolou DENTRO do evento: baseline (foto) no início,
//    placar = SUM( max(stat_atual - baseline, 0) ) dos membros ATIVOS do clã.
//  - Entrou no clã no meio do evento ativo: baseline = valor atual (começa em 0).
//  - Saiu/foi kickado no meio: vira inativo -> some da soma (clã perde os pontos).
//  - No fim, o resultado CONGELA (pra premiar com calma).
//  - Métricas só PVE/não-burláveis: zumbis e tempo jogado.
//  - O ciclo (tirar baseline no início / congelar no fim) é disparado pela
//    própria batida de stats do Bot (tick()) + backup ao abrir a página. Sem cron.
// ============================================================

class ClanEvent
{
    /** Métricas permitidas: chave = coluna em player_stats. SOMA de delta. */
    public const METRICS = [
        'kills_infected'   => 'Zumbis mortos',
        'playtime_seconds' => 'Tempo jogado',
    ];

    public static function metricLabel(string $m): string {
        return self::METRICS[$m] ?? $m;
    }

    /** Coluna validada (whitelist) - protege contra SQL injection no nome da coluna. */
    private static function col(string $metric): string {
        return isset(self::METRICS[$metric]) ? $metric : 'kills_infected';
    }

    // ---------- Leitura ----------

    public static function all(): array {
        return Database::fetchAll("SELECT * FROM clan_events ORDER BY sort_order ASC, starts_at DESC, id DESC");
    }

    public static function get(int $id): ?array {
        return Database::fetchOne("SELECT * FROM clan_events WHERE id = ? LIMIT 1", [$id]) ?: null;
    }

    /** Eventos visíveis ao público (habilitados), mais recentes primeiro. */
    public static function publicEvents(): array {
        return Database::fetchAll(
            "SELECT * FROM clan_events WHERE enabled = 1 ORDER BY (frozen_at IS NULL) DESC, starts_at DESC LIMIT 30"
        );
    }

    /** Fase do evento pra exibição. */
    public static function phase(array $ev): string {
        if (!empty($ev['frozen_at'])) return 'ended';
        $now = time();
        $s = strtotime($ev['starts_at']); $e = strtotime($ev['ends_at']);
        if ($now < $s) return 'scheduled';
        if ($now < $e) return 'active';
        return 'ended';
    }

    public static function phaseLabel(string $p): string {
        return match($p) {
            'scheduled' => 'Em breve',
            'active'    => 'Acontecendo agora',
            'ended'     => 'Encerrado',
            default     => $p,
        };
    }

    // ---------- Inscrição (líder) ----------

    public static function isRegistered(int $eventId, int $clanId): bool {
        return (bool) Database::fetchColumn(
            "SELECT 1 FROM clan_event_entries WHERE event_id = ? AND clan_id = ? LIMIT 1", [$eventId, $clanId]
        );
    }

    /** Inscreve o clã. Erros: not_found, closed, started, already, no_clan. */
    public static function register(int $eventId, int $clanId, string $bySteamId): ?string {
        $ev = self::get($eventId);
        if (!$ev || !$ev['enabled']) return 'not_found';
        if (self::phase($ev) !== 'scheduled') return 'started'; // só antes de começar
        if (self::isRegistered($eventId, $clanId)) return 'already';
        try {
            Database::query(
                "INSERT INTO clan_event_entries (event_id, clan_id, registered_by) VALUES (?, ?, ?)",
                [$eventId, $clanId, $bySteamId]
            );
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') return 'already';
            throw $e;
        }
        return null;
    }

    /** Cancela a inscrição (só antes de começar). */
    public static function unregister(int $eventId, int $clanId): ?string {
        $ev = self::get($eventId);
        if (!$ev) return 'not_found';
        if (self::phase($ev) !== 'scheduled') return 'started';
        Database::query("DELETE FROM clan_event_entries WHERE event_id = ? AND clan_id = ?", [$eventId, $clanId]);
        return null;
    }

    /** Clãs inscritos num evento (id + tag + nome). */
    public static function entries(int $eventId): array {
        return Database::fetchAll(
            "SELECT e.clan_id, e.final_score, e.final_rank, c.tag, c.name, c.logo
               FROM clan_event_entries e JOIN clans c ON c.id = e.clan_id
              WHERE e.event_id = ? ORDER BY c.name ASC", [$eventId]
        );
    }

    // ---------- Ciclo de vida (baseline / congelamento) ----------

    /** Dispara transições pendentes. Barato: só pega o que falta processar. */
    public static function tick(): void {
        try {
            $due = Database::fetchAll(
                "SELECT * FROM clan_events
                  WHERE enabled = 1
                    AND ( (baseline_taken = 0 AND starts_at <= NOW())
                       OR (frozen_at IS NULL AND ends_at <= NOW()) )"
            );
        } catch (\Throwable $e) { return; }
        foreach ($due as $ev) {
            try {
                if (!$ev['baseline_taken'] && strtotime($ev['starts_at']) <= time()) {
                    self::takeBaseline($ev);
                    $ev['baseline_taken'] = 1;
                }
                if (empty($ev['frozen_at']) && strtotime($ev['ends_at']) <= time()) {
                    self::freeze($ev);
                }
            } catch (\Throwable $e) { /* não derruba o request */ }
        }
    }

    /**
     * Força sync do CFTools dos stats de uma lista de steam_ids (pra baseline/freeze/ao vivo).
     * O `player_stats` só atualiza em view de perfil; sem isto o evento de clã pega número velho.
     * Throttle natural: CFTools::player tem cache de 5min e o lookup steam->cftools cacheia 7 dias,
     * então recarregar a página seguidamente NÃO martela a API. No-op seguro se o CFTools não está
     * carregado (ex.: chamado pelo /api/player-stats do Bot) ou não está configurado.
     */
    private static function syncMembers(array $steamIds, int $cap = 80): int {
        if (!class_exists('App\\CFTools', false) || !CFTools::isConfigured()) return 0;
        $seen = []; $done = 0;
        foreach ($steamIds as $sid) {
            $sid = (string)$sid;
            if ($sid === '' || isset($seen[$sid])) continue;
            $seen[$sid] = true;
            if ($done >= $cap) break;
            try { CFTools::syncSteam($sid); } catch (\Throwable $e) { /* não derruba o ciclo */ }
            $done++;
        }
        return $done;
    }

    /** Ao vivo: força refresh dos stats dos membros ativos de um evento ATIVO (placar atualiza). */
    public static function refreshActiveMembers(int $eventId, ?array $ev = null): int {
        $ev = $ev ?: self::get($eventId);
        if (!$ev || self::phase($ev) !== 'active') return 0;
        $ids = Database::fetchAll(
            "SELECT DISTINCT steam_id FROM clan_event_members WHERE event_id = ? AND active = 1", [$eventId]
        );
        return self::syncMembers(array_column($ids, 'steam_id'), 30);
    }

    /**
     * Tick OPORTUNISTA throttled - pra páginas de tráfego (ex.: home) tocarem o ciclo
     * (baseline/congela) na hora SEM precisar de cron. Roda no máx 1x a cada $minSecs
     * (flag por mtime de arquivo). Zero-config: funciona em qualquer host do template.
     */
    public static function tickThrottled(string $cacheDir, int $minSecs = 120): void {
        $flag = rtrim($cacheDir, "/\\") . '/ce_tick.flag';
        if (is_file($flag) && (time() - (int)@filemtime($flag)) < max(30, $minSecs)) return;
        @touch($flag);
        self::tick();
    }

    /** Cron: refresca os participantes de TODOS os eventos ativos. Retorna nº de membros sincronizados. */
    public static function refreshAllActive(): int {
        $n = 0;
        foreach (self::publicEvents() as $ev) {
            if (self::phase($ev) === 'active') $n += self::refreshActiveMembers((int)$ev['id'], $ev);
        }
        return $n;
    }

    /** Foto do início: snapshot do contador de cada membro dos clãs inscritos. */
    private static function takeBaseline(array $ev): void {
        $col = self::col($ev['metric']);
        $eventId = (int)$ev['id'];
        $entries = Database::fetchAll("SELECT clan_id FROM clan_event_entries WHERE event_id = ?", [$eventId]);
        // Antes do snapshot: refresh dos stats dos membros no CFTools (senão baseline pega valor velho).
        $all = [];
        foreach ($entries as $en) {
            foreach (Database::fetchAll("SELECT steam_id FROM clan_members WHERE clan_id = ?", [(int)$en['clan_id']]) as $m) {
                $all[] = $m['steam_id'];
            }
        }
        self::syncMembers($all);
        foreach ($entries as $en) {
            $clanId = (int)$en['clan_id'];
            $members = Database::fetchAll("SELECT steam_id FROM clan_members WHERE clan_id = ?", [$clanId]);
            foreach ($members as $m) {
                $val = (int) (Database::fetchColumn(
                    "SELECT `$col` FROM player_stats WHERE steam_id = ?", [$m['steam_id']]
                ) ?? 0);
                Database::query(
                    "INSERT INTO clan_event_members (event_id, clan_id, steam_id, baseline, active)
                     VALUES (?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE baseline = VALUES(baseline), clan_id = VALUES(clan_id), active = 1, deactivated_at = NULL",
                    [$eventId, $clanId, $m['steam_id'], $val]
                );
            }
        }
        Database::query("UPDATE clan_events SET baseline_taken = 1 WHERE id = ?", [$eventId]);
    }

    /** Congela o resultado final no fim do evento. */
    private static function freeze(array $ev): void {
        $eventId = (int)$ev['id'];
        // Antes de fechar: refresh final dos stats dos membros ativos (resultado certo, não 0).
        $ids = Database::fetchAll(
            "SELECT DISTINCT steam_id FROM clan_event_members WHERE event_id = ? AND active = 1", [$eventId]
        );
        self::syncMembers(array_column($ids, 'steam_id'));
        $scores = self::liveScores($eventId, $ev); // ja ordenado desc
        $rank = 0; $winnerClan = null; $winnerName = null;
        foreach ($scores as $s) {
            $rank++;
            Database::query(
                "UPDATE clan_event_entries SET final_score = ?, final_rank = ? WHERE event_id = ? AND clan_id = ?",
                [(int)$s['score'], $rank, $eventId, (int)$s['clan_id']]
            );
            if ($rank === 1) { $winnerClan = (int)$s['clan_id']; $winnerName = '[' . $s['tag'] . '] ' . $s['name']; }
        }
        Database::query(
            "UPDATE clan_events SET frozen_at = NOW(), winner_clan_id = ?, winner_name = ? WHERE id = ?",
            [$winnerClan, $winnerName, $eventId]
        );
    }

    // ---------- Placar ----------

    /**
     * Placar do evento. Se congelado, usa final_score; senão calcula ao vivo
     * (soma do delta dos membros ativos). Retorna [clan_id, tag, name, logo, score].
     */
    public static function liveScores(int $eventId, ?array $ev = null): array {
        $ev = $ev ?: self::get($eventId);
        if (!$ev) return [];
        // Congelado: lê o resultado fixo.
        if (!empty($ev['frozen_at'])) {
            return Database::fetchAll(
                "SELECT e.clan_id, COALESCE(e.final_score,0) AS score, c.tag, c.name, c.logo
                   FROM clan_event_entries e JOIN clans c ON c.id = e.clan_id
                  WHERE e.event_id = ? ORDER BY score DESC, c.name ASC", [$eventId]
            );
        }
        $col = self::col($ev['metric']);
        // Ao vivo: soma dos deltas dos membros ativos por clã (clamp em 0).
        $rows = Database::fetchAll(
            "SELECT e.clan_id, c.tag, c.name, c.logo,
                    COALESCE(SUM(GREATEST(CAST(ps.`$col` AS SIGNED) - cem.baseline, 0)), 0) AS score
               FROM clan_event_entries e
               JOIN clans c ON c.id = e.clan_id
               LEFT JOIN clan_event_members cem
                      ON cem.event_id = e.event_id AND cem.clan_id = e.clan_id AND cem.active = 1
               LEFT JOIN player_stats ps ON ps.steam_id = cem.steam_id
              WHERE e.event_id = ?
              GROUP BY e.clan_id, c.tag, c.name, c.logo
              ORDER BY score DESC, c.name ASC",
            [$eventId]
        );
        return $rows;
    }

    // ---------- Premiação (igual ao individual: botão Premiar credita moedas) ----------

    /** Dá pra premiar? Congelado + tem vencedor + tem prêmio em moedas + ainda não pago. */
    public static function canReward(array $ev): bool {
        return !empty($ev['frozen_at'])
            && (int)($ev['winner_clan_id'] ?? 0) > 0
            && (int)($ev['prize_coins'] ?? 0) > 0
            && empty($ev['rewarded_at']);
    }

    /**
     * Credita prize_coins a CADA membro que participou do clã vencedor.
     * Idempotente (rewarded_at). Loga em balance_log. Retorna null no sucesso.
     */
    public static function reward(int $eventId): ?string {
        $ev = self::get($eventId);
        if (!$ev) return 'not_found';
        if (empty($ev['frozen_at']))        return 'not_frozen';
        if (!empty($ev['rewarded_at']))     return 'already';
        $coins  = (int)($ev['prize_coins'] ?? 0);
        $clanId = (int)($ev['winner_clan_id'] ?? 0);
        if ($coins <= 0)  return 'no_prize';
        if ($clanId <= 0) return 'no_winner';

        // Quem participou de verdade (ativos no fim do evento). Fallback: membros atuais.
        $members = Database::fetchAll(
            "SELECT steam_id FROM clan_event_members WHERE event_id = ? AND clan_id = ? AND active = 1",
            [$eventId, $clanId]
        );
        if (!$members) {
            $members = Database::fetchAll("SELECT steam_id FROM clan_members WHERE clan_id = ?", [$clanId]);
        }
        foreach ($members as $m) {
            $sid = (string)$m['steam_id'];
            $p = Database::fetchOne("SELECT id, coins FROM players WHERE steam_id = ? LIMIT 1", [$sid]);
            if ($p) {
                $old = (int)$p['coins']; $pid = (int)$p['id'];
                Database::query("UPDATE players SET coins = coins + ? WHERE id = ?", [$coins, $pid]);
            } else {
                Database::query(
                    "INSERT INTO players (steam_id, coins, origin, last_seen_at) VALUES (?, ?, 'reward', NOW())",
                    [$sid, $coins]
                );
                $pid = (int)Database::pdo()->lastInsertId(); $old = 0;
            }
            try {
                BalanceLog::record($pid, $sid, $old, $old + $coins, 'reward', 'clan_event', $eventId,
                    'Prêmio evento de clã: ' . $ev['title']);
            } catch (\Throwable $e) { /* não derruba o resto */ }
        }
        Database::query("UPDATE clan_events SET rewarded_at = NOW() WHERE id = ? AND rewarded_at IS NULL", [$eventId]);
        return null;
    }

    // ---------- Ganchos de roster (chamados pelo Clan) ----------

    /** Eventos ATIVOS (baseline tirado, não congelado) em que o clã está inscrito. */
    private static function activeEventsForClan(int $clanId): array {
        return Database::fetchAll(
            "SELECT ev.id, ev.metric FROM clan_events ev
               JOIN clan_event_entries e ON e.event_id = ev.id
              WHERE e.clan_id = ? AND ev.baseline_taken = 1 AND ev.frozen_at IS NULL", [$clanId]
        );
    }

    /**
     * Entrou no clã durante evento ativo. Baseline = valor atual (0 de delta) SÓ pra
     * membro novo ou quem trocou de clã. Se é REJOIN no MESMO clã (saiu e voltou), NÃO
     * reseta o baseline - só reativa, pra não zerar os pontos que ele já tinha feito
     * (ex.: clicou sair sem querer e voltou). Anti-acidente pedido pelo Bryan.
     */
    public static function onMemberJoin(int $clanId, string $steamId): void {
        try {
            foreach (self::activeEventsForClan($clanId) as $ev) {
                $eventId = (int)$ev['id'];
                $existing = Database::fetchOne(
                    "SELECT clan_id FROM clan_event_members WHERE event_id = ? AND steam_id = ? LIMIT 1",
                    [$eventId, $steamId]
                );
                if ($existing && (int)$existing['clan_id'] === $clanId) {
                    // Rejoin no MESMO clã -> reativa mantendo o baseline (não perde pontos).
                    Database::query(
                        "UPDATE clan_event_members SET active = 1, deactivated_at = NULL
                          WHERE event_id = ? AND steam_id = ?",
                        [$eventId, $steamId]
                    );
                } else {
                    // Membro novo OU trocou de clã -> baseline = valor atual (começa em 0).
                    $col = self::col($ev['metric']);
                    $val = (int) (Database::fetchColumn("SELECT `$col` FROM player_stats WHERE steam_id = ?", [$steamId]) ?? 0);
                    Database::query(
                        "INSERT INTO clan_event_members (event_id, clan_id, steam_id, baseline, active)
                         VALUES (?, ?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE baseline = VALUES(baseline), clan_id = VALUES(clan_id), active = 1, deactivated_at = NULL",
                        [$eventId, $clanId, $steamId, $val]
                    );
                }
            }
        } catch (\Throwable $e) { /* nunca derruba a operação do clã */ }
    }

    /** Saiu/kickado durante evento ativo -> inativo (clã perde a contribuição dele). */
    public static function onMemberLeave(int $clanId, string $steamId): void {
        try {
            Database::query(
                "UPDATE clan_event_members cem
                   JOIN clan_events ev ON ev.id = cem.event_id
                    SET cem.active = 0, cem.deactivated_at = NOW()
                  WHERE cem.clan_id = ? AND cem.steam_id = ? AND ev.frozen_at IS NULL",
                [$clanId, $steamId]
            );
        } catch (\Throwable $e) { /* idem */ }
    }

    /** Clã dissolvido -> tira todos da soma nos eventos não congelados. */
    public static function onClanDisband(int $clanId): void {
        try {
            Database::query(
                "UPDATE clan_event_members cem
                   JOIN clan_events ev ON ev.id = cem.event_id
                    SET cem.active = 0, cem.deactivated_at = NOW()
                  WHERE cem.clan_id = ? AND ev.frozen_at IS NULL",
                [$clanId]
            );
        } catch (\Throwable $e) { }
    }

    // ---------- Admin ----------

    public static function slugify(string $s): string {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-') ?: ('evento-' . substr(md5($s . microtime()), 0, 6));
    }
}
