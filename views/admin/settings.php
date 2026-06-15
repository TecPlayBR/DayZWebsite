<?php /** @var array $config, $settings */ ?>
<?php $title = 'Configurações'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Configurações</h1>
        <p>Ajustes editáveis sem mexer no <code>config.php</code>.</p>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?>
    <div class="alert-toast">Configurações salvas.</div>
<?php endif; ?>

<form method="POST" action="/admin/settings" style="max-width: 720px;">
    <?= \App\Csrf::field() ?>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Site</div>
        <div style="margin-top: 1rem; display: grid; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Nome do site</label>
                <input type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Tagline / subtítulo (PT-BR)</label>
                <input type="text" name="site_tagline" value="<?= e($settings['site_tagline'] ?? '') ?>" placeholder="O melhor servidor..." style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Tagline / subtitle (EN-US) <small style="color:var(--dim);">— mostrado quando visitante escolhe English</small></label>
                <input type="text" name="site_tagline_enus" value="<?= e($settings['site_tagline_enus'] ?? '') ?>" placeholder="The best..." style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Servidor DayZ</div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">IP público</label>
                <input type="text" name="server_ip" value="<?= e($settings['server_ip'] ?? '') ?>" placeholder="ex: 177.10.108.239" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Porta</label>
                <input type="text" name="server_port" value="<?= e($settings['server_port'] ?? '2302') ?>" placeholder="2302" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
        </div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">
                    Próximo wipe <small style="color:var(--dim);">(opcional)</small>
                </label>
                <input type="datetime-local" name="next_wipe_at"
                       value="<?= e($settings['next_wipe_at'] ?? '') ?>"
                       style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">
                    Rótulo do evento
                </label>
                <input type="text" name="wipe_label"
                       value="<?= e($settings['wipe_label'] ?? 'Próximo wipe') ?>"
                       placeholder="Próximo wipe"
                       style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
            </div>
        </div>

        <div style="margin-top: 1rem;">
            <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">
                BattleMetrics Server ID
                <small style="color: var(--dim); font-weight: 400;">— pra mostrar status online/players em tempo real no hero</small>
            </label>
            <input type="text" name="battlemetrics_id" value="<?= e($settings['battlemetrics_id'] ?? '') ?>" placeholder="ex: 12345678" pattern="[0-9]+" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            <p style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--dim);">
                Encontre seu servidor em <a href="https://www.battlemetrics.com/servers/dayz" target="_blank" rel="noopener" style="color: var(--hazard);">battlemetrics.com/servers/dayz</a> e copie o número da URL (ex: <code>/servers/dayz/<strong>12345678</strong></code>). Deixe vazio se não quiser mostrar status.
            </p>
        </div>

        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.2rem;">
            <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.9rem; color:var(--bone); margin-bottom:0.6rem;">
                <input type="checkbox" name="restart_enabled" value="1" <?= !empty($settings['restart_enabled']) ? 'checked' : '' ?> style="width:18px;height:18px;">
                🔄 Mostrar próximo restart do servidor
            </label>
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Horários de restart (BR)</label>
                    <input type="text" name="restart_times" id="restart_times" value="<?= e($settings['restart_times'] ?? '') ?: '00:00, 04:00, 08:00, 12:00, 16:00, 20:00' ?>" placeholder="00:00, 04:00, 08:00, 12:00, 16:00, 20:00" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
                    <p id="restart-hint" style="display:none; margin-top:0.35rem; font-size:0.78rem; color:var(--hazard);">💡 Coloque os horários separados por vírgula (ex: <code>00:00, 06:00, 12:00, 18:00</code>). Deixe vazio só se NÃO quiser mostrar o próximo restart no site.</p>
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Aviso (min antes)</label>
                    <input type="number" name="restart_warn_minutes" value="<?= e($settings['restart_warn_minutes'] ?? '5') ?>" min="1" max="30" style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
                </div>
            </div>
            <p style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--dim);">
                Horários separados por vírgula. O site mostra o próximo discretamente e <strong>blinda o drop de itens</strong> perto do restart (segura como pendente pra não cair no limbo).
            </p>
        </div>

        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1.2rem;">
            <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">💳 Parcelamento no cartão — valor mínimo (R$)</label>
            <input type="number" name="card_installments_min" value="<?= e($settings['card_installments_min'] ?? '30') ?>" min="0" step="1" style="width:200px; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            <p style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--dim); max-width:640px;">
                Abaixo desse valor, o checkout trava o cartão em <strong>1x (à vista)</strong> e o campo de parcelas fica cinza. Acima, libera as parcelas que sua conta Mercado Pago oferece. <strong>0</strong> = sempre liberar parcelamento.
            </p>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem; border-left-color: var(--hazard);">
        <div class="label" style="color: var(--hazard);">⚠ Modo Manutenção</div>
        <p style="font-size: 0.85rem; color: var(--dim); margin-top: 0.3rem;">
            Quando ligado, o site público mostra "Voltamos em breve". <strong>Admin continua acessível</strong> normalmente.
        </p>
        <div style="margin-top: 1rem;">
            <label style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--bone); cursor: pointer; font-size: 0.95rem;">
                <input type="checkbox" name="maintenance_enabled" value="1" <?= ((int)($settings['maintenance_enabled'] ?? 0)) ? 'checked' : '' ?> style="width: 20px; height: 20px;">
                <strong>Site em manutenção</strong>
            </label>
        </div>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Mensagem pro público</label>
                <input type="text" name="maintenance_message"
                       value="<?= e($settings['maintenance_message'] ?? '') ?>"
                       placeholder="Estamos fazendo um update no servidor. Voltamos já."
                       style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit;">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Previsão de retorno <small style="color:var(--dim);">(opcional)</small></label>
                <input type="datetime-local" name="maintenance_eta"
                       value="<?= e($settings['maintenance_eta'] ?? '') ?>"
                       style="width:100%; padding:0.65rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1.5rem;">
        <div class="label">Redes Sociais (deixe vazio pra esconder)</div>
        <div style="margin-top: 1rem; display: grid; gap: 0.8rem;">
            <?php
            $socials = [
                'social_discord'   => ['Discord',     'https://discord.gg/...'],
                'social_youtube'   => ['YouTube',     'https://youtube.com/@...'],
                'social_tiktok'    => ['TikTok',      'https://www.tiktok.com/@...'],
                'social_instagram' => ['Instagram',   'https://instagram.com/...'],
                'social_twitch'    => ['Twitch',      'https://www.twitch.tv/...'],
                'social_kick'      => ['Kick',        'https://kick.com/...'],
                'social_x'         => ['X (Twitter)', 'https://x.com/...'],
                'social_facebook'  => ['Facebook',    'https://facebook.com/...'],
                'social_whatsapp'  => ['WhatsApp grupo', 'https://chat.whatsapp.com/...'],
            ];
            foreach ($socials as $key => [$label, $ph]):
            ?>
                <div>
                    <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;"><?= $label ?></label>
                    <input type="url" name="<?= $key ?>" value="<?= e($settings[$key] ?? '') ?>" placeholder="<?= $ph ?>" style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:inherit; font-size:0.9rem;">
                </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top: 0.8rem; font-size: 0.8rem; color: var(--dim);">
            O campo <strong>Discord</strong> alimenta o link "Discord" do header e o ícone no rodapé. Os demais aparecem como ícones no rodapé.
        </p>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem; border-left-color: var(--rust);">
        <div class="label" style="color: var(--rust);">⚡ Promoção Sazonal Global</div>
        <p style="font-size: 0.85rem; color: var(--dim); margin-top: 0.3rem;">
            Aplica um cupom <strong>automaticamente</strong> em todos os pacotes (mostra "X% OFF" + preço riscado).
            Pra usar: crie o cupom em <a href="/admin/coupons" style="color: var(--hazard);">/admin/coupons</a> e cole o código aqui.
        </p>
        <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Código do cupom <small style="color:var(--dim);">(vazio = sem promo)</small></label>
                <input type="text" name="promo_coupon_code"
                       value="<?= e($settings['promo_coupon_code'] ?? '') ?>"
                       placeholder="ex: BLACKFRIDAY20"
                       style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--hazard); font-family:var(--font-mono); text-transform: uppercase;">
            </div>
            <div>
                <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">Texto do banner promo</label>
                <input type="text" name="promo_label"
                       value="<?= e($settings['promo_label'] ?? '') ?>"
                       placeholder="ex: 🔥 Black Friday — todos os pacotes com 20% OFF"
                       style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
            </div>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1rem;">
        <div class="label">Mural de Vendas Ao Vivo</div>
        <p style="font-size: 0.85rem; color: var(--dim); margin-top: 0.3rem;">
            Strip na home mostrando últimas compras aprovadas. Funciona como <strong>prova social</strong> — quem entra
            vê o servidor "vivo". Você decide se aparece e quão privado é.
        </p>

        <div style="margin-top: 1rem;">
            <label style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--bone); cursor: pointer; font-size: 0.95rem;">
                <input type="checkbox" name="live_purchases_enabled" value="1" <?= ((int)($settings['live_purchases_enabled'] ?? 0)) ? 'checked' : '' ?> style="width: 20px; height: 20px;">
                <strong>Ativar mural na home</strong>
            </label>
        </div>

        <div style="margin-top: 0.8rem; padding-left: 1.7rem;">
            <label style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--bone); cursor: pointer; font-size: 0.9rem;">
                <input type="checkbox" name="live_purchases_anonymize" value="1" <?= ((int)($settings['live_purchases_anonymize'] ?? 1)) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                Anonimizar nome dos jogadores (recomendado — LGPD)
            </label>
            <p style="font-size: 0.78rem; color: var(--dim); margin: 0.3rem 0 0;">
                Liga: <code>B*****m comprou 100 moedas</code>. Desliga: <code>BryanPaim comprou 100 moedas</code>.
            </p>
        </div>

        <div style="margin-top: 0.8rem; padding-left: 1.7rem;">
            <label style="display: inline-flex; align-items: center; gap: 0.5rem; color: var(--bone); cursor: pointer; font-size: 0.9rem;">
                <input type="checkbox" name="live_purchases_show_price" value="1" <?= ((int)($settings['live_purchases_show_price'] ?? 0)) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                Mostrar o valor em R$ junto da compra
            </label>
            <p style="font-size: 0.78rem; color: var(--dim); margin: 0.3rem 0 0;">
                Por padrão desligado — mostra só "X moedas". Se ligar, vai aparecer "X moedas — R$ Y,YY".
            </p>
        </div>
    </div>

    <div class="stat-card" style="margin-bottom: 1.5rem;">
        <div class="label">Webhook Discord — Notificação de Vendas</div>
        <div style="margin-top: 1rem;">
            <label style="display:block; font-size:0.85rem; color:var(--bone); margin-bottom:0.3rem;">URL do Webhook</label>
            <input type="url" name="discord_sales_webhook"
                   value="<?= e($settings['discord_sales_webhook'] ?? '') ?>"
                   placeholder="https://discord.com/api/webhooks/..."
                   pattern="https://(discord|discordapp)\.com/api/webhooks/.*"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono); font-size:0.85rem;">
        </div>
        <p style="margin-top: 0.6rem; font-size: 0.8rem; color: var(--dim);">
            Quando uma compra é aprovada, posta automaticamente no canal Discord (nome + pacote + valor).
            Crie em <strong>Discord → Servidor → Configurações → Integrações → Webhooks</strong>.
        </p>
    </div>

    <button type="submit" class="btn-mini" style="padding: 0.7rem 1.6rem; font-size: 0.85rem;">Salvar alterações</button>

</form>

<div style="margin-top: 3rem;">
    <h2 style="font-family: var(--font-display); color: var(--bone); font-size: 1.1rem; margin-bottom: 1rem;">Toggle de Bônus (pacotes)</h2>
    <p style="color: var(--dim); margin-bottom: 1rem; max-width: 640px;">
        Quando o bônus está <strong>ligado</strong>, jogadores recebem <code>coins + bonusCoins</code> ao comprar. Desligado, recebem só <code>coins</code>.
    </p>
    <?php $bonus = (int)($settings['bonus_enabled'] ?? 1); ?>
    <form method="POST" action="/admin/packages/toggle-bonus" style="display: inline;">
        <?= \App\Csrf::field() ?>
        <button type="submit" class="<?= $bonus ? 'btn-mini' : 'btn-mini outline' ?>">
            <?= $bonus ? '✓ BÔNUS LIGADO — desligar' : '✗ BÔNUS DESLIGADO — ligar' ?>
        </button>
    </form>
</div>

<script>
(function(){
    var inp = document.getElementById('restart_times'), hint = document.getElementById('restart-hint');
    if (!inp || !hint) return;
    function upd(){ hint.style.display = inp.value.trim() === '' ? 'block' : 'none'; }
    inp.addEventListener('input', upd); upd();
})();
</script>

<?php \App\View::endSection(); ?>
