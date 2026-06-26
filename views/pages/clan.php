<?php /** @var array $config, $clan, $members; @var bool $is_owner; @var ?array $my_clan, $my_request, $steam_user; @var array $pending, $sent_invites */ ?>
<?php $sent_invites = $sent_invites ?? []; $my_invite = $my_invite ?? false; ?>
<?php \App\View::with('title', '[' . $clan['tag'] . '] ' . $clan['name'] . ' — Clã'); ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::with('hero_image', 'img/background2.png'); ?>
<?php \App\View::section('content'); ?>
<?php
$sid = $steam_user['steam_id'] ?? null;
$inThisClan = $my_clan && (int)$my_clan['id'] === (int)$clan['id'];
$inOtherClan = $my_clan && !$inThisClan;
$requestedThis = $my_request && (int)$my_request['clan_id'] === (int)$clan['id'];
$full = (int)$clan['member_count'] >= (int)$clan['member_cap'];
$okMsg = match($_GET['ok'] ?? '') {
    'requested'=>'Pedido enviado! O dono do clã vai avaliar.', 'invited'=>'Convite enviado.',
    'joined'=>'🎉 Você entrou no clã! Bem-vindo.',
    'accepted'=>'Membro aceito.', 'rejected'=>'Pedido recusado.', 'kicked'=>'Membro removido.', 'saved'=>'Clã atualizado.',
    'transferred'=>'Pronto! Você passou o comando do clã pra outro membro — agora você é membro comum.',
    'invite_cancelled'=>'Convite revogado.',
    default=>'',
};
$errMsg = ($_GET['err'] ?? '') !== '' ? \App\Clan::errorMessage($_GET['err']) : '';
?>
<section class="hero" style="min-height:30vh;padding-bottom:1.5rem;">
    <div class="hero-bg" style="background-image:linear-gradient(180deg,rgba(0,0,0,0.6) 0%,rgba(0,0,0,0.95) 100%),url('<?= asset('img/background2.png') ?>');"></div>
    <div class="container hero-content" style="display:flex;gap:1.4rem;align-items:center;flex-wrap:wrap;">
        <div class="clan-hero-logo">
            <?php if (!empty($clan['logo'])): ?>
                <img src="<?= e($clan['logo']) ?>" alt="<?= e($clan['name']) ?>" onerror="this.outerHTML='<span class=\'clan-hero-fb\'><?= e(mb_strtoupper(mb_substr($clan['tag'],0,2))) ?></span>'">
            <?php else: ?>
                <span class="clan-hero-fb"><?= e(mb_strtoupper(mb_substr($clan['tag'],0,2))) ?></span>
            <?php endif; ?>
        </div>
        <div style="min-width:0;">
            <span class="hero-kicker">// CLÃ</span>
            <h1 class="hero-title" style="font-size:clamp(1.8rem,5vw,3rem);">[<?= e($clan['tag']) ?>] <?= e($clan['name']) ?></h1>
            <p style="color:var(--dim);margin:.2rem 0;">👥 <?= (int)$clan['member_count'] ?>/<?= (int)$clan['member_cap'] ?> membros<?php if (!empty($clan['discord_url'])): ?> · <a href="<?= e($clan['discord_url']) ?>" target="_blank" rel="noopener" style="color:var(--hazard);">Discord do clã →</a><?php endif; ?></p>
        </div>
    </div>
</section>

<section class="section section-bg-2">
    <div class="container" style="max-width:900px;">
        <?php if ($okMsg): ?><div style="background:rgba(90,108,78,0.18);border-left:3px solid var(--moss);color:var(--text-success);padding:.8rem 1.1rem;margin-bottom:1.2rem;border-radius:4px;"><?= e($okMsg) ?></div><?php endif; ?>
        <?php if ($errMsg): ?><div style="background:rgba(231,76,60,0.18);border-left:3px solid var(--rust-2);color:var(--text-danger);padding:.8rem 1.1rem;margin-bottom:1.2rem;border-radius:4px;"><?= e($errMsg) ?></div><?php endif; ?>

        <?php if (!empty($clan['description'])): ?>
            <p style="color:var(--bone);line-height:1.6;margin-bottom:1.5rem;"><?= nl2br(e($clan['description'])) ?></p>
        <?php endif; ?>

        <!-- AÇÃO do visitante -->
        <div style="margin-bottom:2rem;">
            <?php if (!$steam_user): ?>
                <a href="/auth/steam" class="btn btn-steam">Entrar com Steam pra pedir entrada</a>
            <?php elseif ($inThisClan && !$is_owner): ?>
                <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/leave" onsubmit="return confirm('Sair do clã [<?= e($clan['tag']) ?>]?');" style="display:inline;">
                    <?= \App\Csrf::field() ?><button class="btn-mini danger">Sair do clã</button>
                </form>
            <?php elseif ($inOtherClan): ?>
                <p style="color:var(--dim);">Você já faz parte de <a href="/clan/<?= (int)$my_clan['id'] ?>" style="color:var(--hazard);">[<?= e($my_clan['tag']) ?>] <?= e($my_clan['name']) ?></a>. Saia de lá antes de entrar em outro.</p>
            <?php elseif ($my_invite): ?>
                <div style="background:rgba(212,160,23,0.10);border:1px solid var(--hazard);border-radius:6px;padding:1rem 1.1rem;">
                    <p style="color:var(--bone);margin:0 0 .7rem;">📨 Você foi <strong>convidado</strong> pra entrar nesse clã. Aceita?</p>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                        <form method="POST" action="/clan-invite/accept" style="margin:0;">
                            <?= \App\Csrf::field() ?><input type="hidden" name="clan_id" value="<?= (int)$clan['id'] ?>">
                            <button class="btn">Aceitar convite</button>
                        </form>
                        <form method="POST" action="/clan-invite/reject" style="margin:0;" onsubmit="return confirm('Recusar o convite do clã [<?= e($clan['tag']) ?>]?');">
                            <?= \App\Csrf::field() ?><input type="hidden" name="clan_id" value="<?= (int)$clan['id'] ?>">
                            <button class="btn-mini outline">Recusar</button>
                        </form>
                    </div>
                </div>
            <?php elseif ($requestedThis): ?>
                <p style="color:var(--hazard);">⏳ Seu pedido pra entrar está pendente — aguarde o dono aceitar.</p>
            <?php elseif (!$is_owner): ?>
                <?php if ($full): ?>
                    <p style="color:var(--dim);">Esse clã está lotado (<?= (int)$clan['member_cap'] ?> membros).</p>
                <?php else: ?>
                    <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/request" style="display:inline;">
                        <?= \App\Csrf::field() ?><button class="btn">Pedir pra entrar</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- MEMBROS -->
        <?php $canSeeActivity = $is_owner || $inThisClan; // atividade só pra quem é do clã (candidato não vê) ?>
        <h2 class="clan-h2">Membros (<?= count($members) ?>)</h2>
        <?php if ($canSeeActivity): ?><p style="color:var(--dim);font-size:.78rem;margin:-.5rem 0 1rem;">🕓 Última atividade visível só pros membros do clã — pra você acompanhar quem anda ativo.</p><?php endif; ?>
        <div class="clan-members">
            <?php foreach ($members as $m): $nm = $m['display_name'] ?: 'Sobrevivente'; ?>
                <div class="clan-member">
                    <div style="min-width:0;display:flex;flex-direction:column;">
                        <a href="/player/<?= e($m['steam_id']) ?>" class="clan-member-name"><?php if ($m['role']==='owner'): ?>👑 <?php endif; ?><?= e($nm) ?></a>
                        <?php if ($canSeeActivity): ?>
                            <span class="clan-member-seen">🕓 <?= e(time_ago($m['last_seen_at'] ?? null, 'nunca conectou')) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_owner && $m['role'] !== 'owner'): ?>
                        <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/kick" onsubmit="return confirm('Remover <?= e(addslashes($nm)) ?> do clã?');" style="margin:0;">
                            <?= \App\Csrf::field() ?><input type="hidden" name="steam_id" value="<?= e($m['steam_id']) ?>">
                            <button class="clan-kick" title="Remover">✕</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAINEL DO DONO -->
        <?php if ($is_owner): ?>
        <details class="clan-owner" open>
            <summary>⚙ Gerenciar clã</summary>
            <div class="clan-owner-body">
                <!-- Pedidos pendentes -->
                <h3 class="clan-h3">Pedidos pra entrar (<?= count($pending) ?>)</h3>
                <?php if (empty($pending)): ?>
                    <p style="color:var(--dim);font-size:.88rem;">Nenhum pedido no momento.</p>
                <?php else: foreach ($pending as $r): $rn = $r['display_name'] ?: $r['steam_id']; ?>
                    <div class="clan-req">
                        <a href="/player/<?= e($r['steam_id']) ?>" style="color:var(--bone);"><?= e($rn) ?></a>
                        <span style="display:flex;gap:.4rem;">
                            <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/requests/accept" style="margin:0;"><?= \App\Csrf::field() ?><input type="hidden" name="steam_id" value="<?= e($r['steam_id']) ?>"><button class="btn-mini">Aceitar</button></form>
                            <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/requests/reject" style="margin:0;"><?= \App\Csrf::field() ?><input type="hidden" name="steam_id" value="<?= e($r['steam_id']) ?>"><button class="btn-mini outline">Recusar</button></form>
                        </span>
                    </div>
                <?php endforeach; endif; ?>

                <!-- Convidar por SteamID -->
                <h3 class="clan-h3">Convidar por SteamID</h3>
                <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/invite" style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    <?= \App\Csrf::field() ?>
                    <input type="text" name="steam_id" required pattern="7656119[0-9]{10}" placeholder="7656119..." class="field mono grow">
                    <button class="btn-mini">Convidar</button>
                </form>
                <p style="color:var(--dim);font-size:.76rem;margin:.4rem 0 0;">O convidado precisa <strong>aceitar</strong> no perfil dele.</p>

                <?php if (!empty($sent_invites)): ?>
                <h3 class="clan-h3">Convites enviados (pendentes)</h3>
                <?php foreach ($sent_invites as $iv): $in = $iv['display_name'] ?: $iv['steam_id']; ?>
                    <div class="clan-req">
                        <a href="/player/<?= e($iv['steam_id']) ?>" style="color:var(--bone);"><?= e($in) ?></a>
                        <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/invite-cancel" style="margin:0;" onsubmit="return confirm('Revogar o convite pra <?= e(addslashes($in)) ?>?');">
                            <?= \App\Csrf::field() ?><input type="hidden" name="steam_id" value="<?= e($iv['steam_id']) ?>">
                            <button class="btn-mini danger">Revogar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <!-- Editar -->
                <h3 class="clan-h3">Editar clã</h3>
                <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/edit" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:.7rem;">
                    <?= \App\Csrf::field() ?>
                    <textarea name="description" rows="3" maxlength="500" placeholder="Descrição do clã" class="field"><?= e($clan['description'] ?? '') ?></textarea>
                    <input type="url" name="discord_url" value="<?= e($clan['discord_url'] ?? '') ?>" placeholder="https://discord.gg/... (do clã)" class="field">
                    <label style="font-size:.8rem;color:var(--dim);">Trocar logo (opcional): <input type="file" name="logo_file" accept="image/png,image/webp,image/jpeg" style="color:var(--bone);"></label>
                    <button class="btn-mini" style="align-self:flex-start;">Salvar</button>
                </form>

                <!-- Passar liderança -->
                <?php $others = array_values(array_filter($members, fn($m) => $m['role'] !== 'owner')); ?>
                <?php if ($others): ?>
                <h3 class="clan-h3">Passar liderança</h3>
                <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/transfer" onsubmit="return confirm('Passar a liderança do clã pra esse membro? Você vira membro comum e não dá pra desfazer sozinho.');" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                    <?= \App\Csrf::field() ?>
                    <select name="steam_id" required class="field grow">
                        <option value="">Escolha um membro…</option>
                        <?php foreach ($others as $o): ?>
                            <option value="<?= e($o['steam_id']) ?>"><?= e($o['display_name'] ?: $o['steam_id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn-mini outline">Passar liderança</button>
                </form>
                <p style="color:var(--dim);font-size:.76rem;margin:.4rem 0 0;">Depois de passar, você vira membro comum (e aí pode sair se quiser).</p>
                <?php endif; ?>

                <!-- Dissolver -->
                <h3 class="clan-h3" style="color:var(--rust-2);">Zona de perigo</h3>
                <form method="POST" action="/clans/<?= (int)$clan['id'] ?>/disband" onsubmit="return confirm('DISSOLVER o clã [<?= e($clan['tag']) ?>]? Isso remove todos os membros e não dá pra desfazer.');">
                    <?= \App\Csrf::field() ?><button class="btn-mini danger">Dissolver clã</button>
                </form>
            </div>
        </details>
        <?php endif; ?>

        <p style="text-align:center;margin-top:2.5rem;"><a href="/clans" class="btn btn-outline">← Todos os clãs</a></p>
    </div>
</section>

<style>
.clan-hero-logo img, .clan-hero-fb { width:90px; height:90px; border-radius:10px; object-fit:cover; background:var(--bg-2); border:2px solid var(--rust); display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:2rem; color:var(--hazard); }
.clan-h2 { font-family:var(--font-display); color:var(--bone); font-size:1.2rem; border-bottom:2px solid var(--rust); padding-bottom:.4rem; margin:0 0 1rem; }
.clan-h3 { font-family:var(--font-display); color:var(--bone); font-size:.98rem; margin:1.4rem 0 .6rem; }
.clan-members { display:grid; grid-template-columns:repeat(auto-fill,minmax(min(200px,100%),1fr)); gap:.5rem; }
.clan-member { display:flex; align-items:center; justify-content:space-between; gap:.5rem; background:var(--bg-1); border:1px solid var(--border); border-radius:5px; padding:.5rem .8rem; }
.clan-member-name { color:var(--bone); text-decoration:none; font-size:.9rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.clan-member-name:hover { color:var(--hazard); }
.clan-member-seen { color:var(--dim); font-size:.7rem; font-family:var(--font-mono); }
.clan-kick { background:none; border:none; color:var(--rust-2); cursor:pointer; font-size:.95rem; opacity:.7; }
.clan-kick:hover { opacity:1; }
.clan-owner { margin-top:2rem; border:1px solid var(--border); border-radius:8px; background:var(--bg-1); }
.clan-owner > summary { cursor:pointer; list-style:none; padding:1rem 1.2rem; font-family:var(--font-display); color:var(--bone); }
.clan-owner > summary::-webkit-details-marker { display:none; }
.clan-owner-body { padding:0 1.2rem 1.2rem; }
.clan-req { display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap; padding:.5rem .8rem; background:var(--bg-0); border:1px solid var(--border); border-radius:5px; margin-bottom:.4rem; }
</style>
<?php \App\View::endSection(); ?>
