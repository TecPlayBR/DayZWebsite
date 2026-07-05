<?php
// ============================================================
// Clan - clãs registrados no site (Fase 1).
// ============================================================
// 1 jogador = 1 clã (UNIQUE em clan_members.steam_id). Entrada SÓ com aceite dos
// dois lados: pedido do jogador (owner aceita) OU convite do owner (jogador aceita).
// Nada é exibido além do que já é público (nick/SteamID). Membro pode sair sempre;
// admin pode remover (LGPD + moderação). Eventos de clã (premiar) = Fase 2.
// ============================================================

namespace App;

class Clan {
    public const TAG_MIN = 2, TAG_MAX = 6, NAME_MIN = 3, NAME_MAX = 60;
    public const CAP_DEFAULT = 20, CAP_MAX = 100;

    /** Clã ativo por id (com contagem de membros). null se não existe/removido. */
    public static function get(int $id): ?array {
        $c = Database::fetchOne("SELECT * FROM clans WHERE id = ? AND status = 'active' LIMIT 1", [$id]);
        if (!$c) return null;
        $c['member_count'] = self::memberCount($id);
        return $c;
    }

    /** Lista pública de clãs ativos (+ contagem de membros), maiores primeiro. */
    public static function all(): array {
        return Database::fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM clan_members m WHERE m.clan_id = c.id) AS member_count
               FROM clans c WHERE c.status = 'active'
              ORDER BY member_count DESC, c.created_at ASC"
        );
    }

    public static function memberCount(int $clanId): int {
        return (int) Database::fetchColumn("SELECT COUNT(*) FROM clan_members WHERE clan_id = ?", [$clanId]);
    }

    /** O clã do jogador (com role) ou null. */
    public static function forPlayer(string $steamId): ?array {
        $m = Database::fetchOne("SELECT clan_id, role FROM clan_members WHERE steam_id = ? LIMIT 1", [$steamId]);
        if (!$m) return null;
        $c = self::get((int) $m['clan_id']);
        if (!$c) return null;
        $c['my_role'] = $m['role'];
        return $c;
    }

    /** TAG do clã do jogador (pra prefixar o nick no site). '' se não tem. Cacheia no request. */
    public static function tagForPlayer(string $steamId): string {
        static $cache = [];
        if (array_key_exists($steamId, $cache)) return $cache[$steamId];
        $tag = (string) (Database::fetchColumn(
            "SELECT c.tag FROM clan_members m JOIN clans c ON c.id = m.clan_id
              WHERE m.steam_id = ? AND c.status = 'active' LIMIT 1", [$steamId]
        ) ?: '');
        return $cache[$steamId] = $tag;
    }

    /** [id, tag] do clã pelo cftools_id (ranking de gameplay não tem steam_id direto;
     *  mapeia via player_stats, como a premiação). null se não achar. Cacheia. */
    public static function badgeByCftools(string $cftoolsId): ?array {
        static $cache = [];
        $cftoolsId = trim($cftoolsId);
        if ($cftoolsId === '') return null;
        if (array_key_exists($cftoolsId, $cache)) return $cache[$cftoolsId];
        $r = Database::fetchOne(
            "SELECT c.id, c.tag FROM player_stats s
               JOIN clan_members m ON m.steam_id = s.steam_id
               JOIN clans c ON c.id = m.clan_id
              WHERE s.cftools_id = ? AND c.status = 'active' LIMIT 1", [$cftoolsId]
        );
        return $cache[$cftoolsId] = ($r ?: null);
    }

    /** Membros do clã (com nick + última atividade do players, se houver). */
    public static function members(int $clanId): array {
        return Database::fetchAll(
            "SELECT m.steam_id, m.role, m.joined_at, p.display_name, p.last_seen_at
               FROM clan_members m LEFT JOIN players p ON p.steam_id = m.steam_id
              WHERE m.clan_id = ?
              ORDER BY (m.role = 'owner') DESC, m.joined_at ASC", [$clanId]
        );
    }

    /** [id, tag] do clã do jogador (pro badge clicável [TAG]); null se não tem. Cacheia. */
    public static function badgeForPlayer(string $steamId): ?array {
        static $cache = [];
        if (array_key_exists($steamId, $cache)) return $cache[$steamId];
        $r = Database::fetchOne(
            "SELECT c.id, c.tag FROM clan_members m JOIN clans c ON c.id = m.clan_id
              WHERE m.steam_id = ? AND c.status = 'active' LIMIT 1", [$steamId]
        );
        return $cache[$steamId] = ($r ?: null);
    }

    /** Registra atividade do clã (join|leave|kick|lead). Best-effort (não derruba a ação). */
    public static function logActivity(int $clanId, string $steamId, string $action, ?string $actor = null): void {
        try {
            Database::query(
                "INSERT INTO clan_activity_log (clan_id, steam_id, action, actor_steam_id) VALUES (?, ?, ?, ?)",
                [$clanId, $steamId, $action, $actor]
            );
        } catch (\Throwable $e) { /* tabela pode nao existir (migration atrasada) -> ignora */ }
    }

    /** Últimas atividades do clã (com nick), pra mostrar aos membros/líder. */
    public static function activity(int $clanId, int $limit = 15): array {
        try {
            return Database::fetchAll(
                "SELECT a.steam_id, a.action, a.actor_steam_id, a.created_at,
                        p.display_name AS name, pa.display_name AS actor_name
                   FROM clan_activity_log a
                   LEFT JOIN players p  ON p.steam_id = a.steam_id
                   LEFT JOIN players pa ON pa.steam_id = a.actor_steam_id
                  WHERE a.clan_id = ?
                  ORDER BY a.id DESC LIMIT " . max(1, min(50, $limit)),
                [$clanId]
            );
        } catch (\Throwable $e) { return []; }
    }

    public static function isOwner(int $clanId, string $steamId): bool {
        return (string) (Database::fetchColumn(
            "SELECT role FROM clan_members WHERE clan_id = ? AND steam_id = ? LIMIT 1", [$clanId, $steamId]
        )) === 'owner';
    }

    /** Pedidos de entrada pendentes (kind=request) — o dono revisa. Com nick. */
    public static function pendingRequests(int $clanId): array {
        return Database::fetchAll(
            "SELECT r.steam_id, r.created_at, p.display_name
               FROM clan_requests r LEFT JOIN players p ON p.steam_id = r.steam_id
              WHERE r.clan_id = ? AND r.kind = 'request' ORDER BY r.created_at ASC", [$clanId]
        );
    }

    /** Convites que o clã ENVIOU e ainda estão pendentes (pro dono ver/revogar). Com nick. */
    public static function sentInvites(int $clanId): array {
        return Database::fetchAll(
            "SELECT r.steam_id, r.created_at, p.display_name
               FROM clan_requests r LEFT JOIN players p ON p.steam_id = r.steam_id
              WHERE r.clan_id = ? AND r.kind = 'invite' ORDER BY r.created_at ASC", [$clanId]
        );
    }

    /** Convites pendentes pro jogador (kind=invite) — ele aceita. Com dados do clã. */
    public static function invitesForPlayer(string $steamId): array {
        return Database::fetchAll(
            "SELECT r.clan_id, r.created_at, c.name, c.tag, c.logo
               FROM clan_requests r JOIN clans c ON c.id = r.clan_id
              WHERE r.steam_id = ? AND r.kind = 'invite' AND c.status = 'active'
              ORDER BY r.created_at ASC", [$steamId]
        );
    }

    /** O jogador tem convite pendente PRA ESTE clã? (mostra Aceitar/Recusar na página do clã). */
    public static function hasInvite(int $clanId, string $steamId): bool {
        return (bool) Database::fetchOne(
            "SELECT 1 FROM clan_requests WHERE clan_id = ? AND steam_id = ? AND kind = 'invite' LIMIT 1",
            [$clanId, $steamId]
        );
    }

    /** Pedido de entrada que o jogador já mandou (pra mostrar "pendente"). */
    public static function outgoingRequest(string $steamId): ?array {
        return Database::fetchOne(
            "SELECT r.clan_id, c.name, c.tag FROM clan_requests r JOIN clans c ON c.id = r.clan_id
              WHERE r.steam_id = ? AND r.kind = 'request' AND c.status = 'active' LIMIT 1", [$steamId]
        ) ?: null;
    }

    /**
     * Cria um clã. O criador vira owner + 1º membro. Retorna [id|null, error|null].
     * Erros: in_clan (já está num clã), name/tag inválidos, dup (nome/tag em uso).
     */
    public static function create(string $ownerSteamId, string $name, string $tag, ?string $desc, ?string $discord, ?string $logo): array {
        if (self::forPlayer($ownerSteamId)) return [null, 'in_clan'];
        $name = trim($name);
        $tag  = strtoupper(trim($tag));
        if (mb_strlen($name) < self::NAME_MIN || mb_strlen($name) > self::NAME_MAX) return [null, 'name'];
        if (!preg_match('/^[A-Z0-9]{' . self::TAG_MIN . ',' . self::TAG_MAX . '}$/', $tag)) return [null, 'tag'];
        $discord = $discord ? mb_substr(trim($discord), 0, 255) : null;
        if ($discord && !preg_match('#^https?://#i', $discord)) $discord = null;
        $desc = $desc ? mb_substr(trim($desc), 0, 500) : null;

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO clans (name, tag, owner_steam_id, description, discord_url, logo) VALUES (?,?,?,?,?,?)");
            $ins->execute([$name, $tag, $ownerSteamId, $desc, $discord, $logo]);
            $clanId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO clan_members (clan_id, steam_id, role) VALUES (?, ?, 'owner')")
                ->execute([$clanId, $ownerSteamId]);
            $pdo->commit();
            return [$clanId, null];
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000') return [null, 'dup']; // nome/tag já existe (ou já é membro)
            throw $e;
        }
    }

    /** Jogador pede pra entrar. Erros: in_clan, has_request, full, not_found. */
    public static function requestJoin(int $clanId, string $steamId): ?string {
        $clan = self::get($clanId);
        if (!$clan) return 'not_found';
        if (self::forPlayer($steamId)) return 'in_clan';
        if ((int)$clan['member_count'] >= (int)$clan['member_cap']) return 'full';
        try {
            Database::query("INSERT INTO clan_requests (clan_id, steam_id, kind) VALUES (?, ?, 'request')", [$clanId, $steamId]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') return 'has_request'; // já pediu pra esse clã
            throw $e;
        }
        return null;
    }

    /** Dono convida um SteamID. Erros: not_owner, target_in_clan, full, already. */
    public static function invite(int $clanId, string $actorSteamId, string $targetSteamId): ?string {
        if (!self::isOwner($clanId, $actorSteamId)) return 'not_owner';
        if (!preg_match('/^7656119\d{10}$/', $targetSteamId)) return 'bad_steam';
        $clan = self::get($clanId);
        if (!$clan) return 'not_found';
        if (self::forPlayer($targetSteamId)) return 'target_in_clan';
        if ((int)$clan['member_count'] >= (int)$clan['member_cap']) return 'full';
        try {
            Database::query("INSERT INTO clan_requests (clan_id, steam_id, kind) VALUES (?, ?, 'invite')", [$clanId, $targetSteamId]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') return 'already';
            throw $e;
        }
        return null;
    }

    /** Efetiva a entrada (move request→membro). Checa cap + 1-clã. Remove o request. */
    public static function accept(int $clanId, string $steamId): ?string {
        $clan = self::get($clanId);
        if (!$clan) return 'not_found';
        if (self::forPlayer($steamId)) { // já entrou em algum clã nesse meio tempo
            Database::query("DELETE FROM clan_requests WHERE clan_id = ? AND steam_id = ?", [$clanId, $steamId]);
            return 'in_clan';
        }
        if ((int)$clan['member_count'] >= (int)$clan['member_cap']) return 'full';
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO clan_members (clan_id, steam_id, role) VALUES (?, ?, 'member')")->execute([$clanId, $steamId]);
            $pdo->prepare("DELETE FROM clan_requests WHERE clan_id = ? AND steam_id = ?")->execute([$clanId, $steamId]);
            $pdo->commit();
            ClanEvent::onMemberJoin($clanId, $steamId); // entra em eventos ativos com baseline = atual (0 de delta)
            self::logActivity($clanId, $steamId, 'join');
            return null;
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000') return 'in_clan';
            throw $e;
        }
    }

    /** Remove um pedido/convite (recusa). */
    public static function dropRequest(int $clanId, string $steamId): void {
        Database::query("DELETE FROM clan_requests WHERE clan_id = ? AND steam_id = ?", [$clanId, $steamId]);
    }

    /** Membro sai do clã (o DONO não sai — tem que dissolver). */
    public static function leave(string $steamId): ?string {
        $m = Database::fetchOne("SELECT clan_id, role FROM clan_members WHERE steam_id = ? LIMIT 1", [$steamId]);
        if (!$m) return 'not_member';
        if ($m['role'] === 'owner') return 'owner_must_disband';
        Database::query("DELETE FROM clan_members WHERE steam_id = ?", [$steamId]);
        ClanEvent::onMemberLeave((int)$m['clan_id'], $steamId); // sai dos eventos ativos (clã perde a contribuição)
        self::logActivity((int)$m['clan_id'], $steamId, 'leave');
        return null;
    }

    /** Dono remove (kicka) um membro. */
    public static function removeMember(int $clanId, string $actorSteamId, string $targetSteamId): ?string {
        if (!self::isOwner($clanId, $actorSteamId)) return 'not_owner';
        if ($actorSteamId === $targetSteamId) return 'cant_self';
        Database::query("DELETE FROM clan_members WHERE clan_id = ? AND steam_id = ? AND role <> 'owner'", [$clanId, $targetSteamId]);
        ClanEvent::onMemberLeave($clanId, $targetSteamId); // idem ao sair
        self::logActivity($clanId, $targetSteamId, 'kick', $actorSteamId);
        return null;
    }

    /** Dono passa a liderança pra outro MEMBRO do clã. O antigo dono vira membro. */
    public static function transferOwnership(int $clanId, string $actorSteamId, string $targetSteamId): ?string {
        if (!self::isOwner($clanId, $actorSteamId)) return 'not_owner';
        if ($actorSteamId === $targetSteamId) return 'cant_self';
        $isMember = Database::fetchColumn("SELECT 1 FROM clan_members WHERE clan_id = ? AND steam_id = ? LIMIT 1", [$clanId, $targetSteamId]);
        if (!$isMember) return 'not_member';
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE clan_members SET role = 'member' WHERE clan_id = ? AND steam_id = ?")->execute([$clanId, $actorSteamId]);
            $pdo->prepare("UPDATE clan_members SET role = 'owner' WHERE clan_id = ? AND steam_id = ?")->execute([$clanId, $targetSteamId]);
            $pdo->prepare("UPDATE clans SET owner_steam_id = ? WHERE id = ?")->execute([$targetSteamId, $clanId]);
            $pdo->commit();
            self::logActivity($clanId, $targetSteamId, 'lead', $actorSteamId);
            return null;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Dissolve o clã (dono ou admin): remove membros, pedidos e o clã. */
    public static function disband(int $clanId): void {
        ClanEvent::onClanDisband($clanId); // tira os membros da soma dos eventos não congelados
        Database::query("DELETE FROM clan_members WHERE clan_id = ?", [$clanId]);
        Database::query("DELETE FROM clan_requests WHERE clan_id = ?", [$clanId]);
        Database::query("DELETE FROM clans WHERE id = ?", [$clanId]);
    }

    /** Dono edita dados do clã (não muda a TAG aqui pra não bagunçar identidade). */
    public static function updateInfo(int $clanId, ?string $desc, ?string $discord, ?string $logo): void {
        $discord = $discord ? mb_substr(trim($discord), 0, 255) : null;
        if ($discord && !preg_match('#^https?://#i', $discord)) $discord = null;
        $desc = $desc !== null ? mb_substr(trim($desc), 0, 500) : null;
        if ($logo !== null) {
            Database::query("UPDATE clans SET description = ?, discord_url = ?, logo = ? WHERE id = ?", [$desc, $discord, $logo, $clanId]);
        } else {
            Database::query("UPDATE clans SET description = ?, discord_url = ? WHERE id = ?", [$desc, $discord, $clanId]);
        }
    }

    public static function errorMessage(string $code): string {
        return match($code) {
            'in_clan'            => 'Você já está em um clã (saia antes de criar/entrar em outro).',
            'target_in_clan'     => 'Esse jogador já está em um clã.',
            'name'               => 'Nome inválido (3 a 60 caracteres).',
            'tag'                => 'TAG inválida (2 a 6 letras/números, sem espaço).',
            'dup'                => 'Já existe um clã com esse nome ou TAG.',
            'full'               => 'Esse clã está lotado.',
            'has_request'        => 'Você já mandou um pedido pra esse clã.',
            'already'            => 'Esse jogador já tem convite/pedido pendente.',
            'not_owner'          => 'Só o dono do clã pode fazer isso.',
            'bad_steam'          => 'SteamID inválido (17 dígitos, 7656119...).',
            'owner_must_disband' => 'O dono não pode sair — passe a liderança ou dissolva o clã.',
            'not_member'         => 'Esse jogador não é membro do clã.',
            'cant_self'          => 'Você já é o dono do clã.',
            'not_found'          => 'Clã não encontrado.',
            default              => 'Não foi possível concluir a ação.',
        };
    }
}
