<?php /** @var array $config */ ?>
<?php $title = 'Suporte Tecplay'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Suporte Tecplay</h1>
        <p>Precisa de ajuda? Manda mensagem que a gente atende. Você tem <strong style="color: var(--hazard);">prioridade</strong> como cliente Tecplay.</p>
    </div>
</div>

<div class="support-grid">

    <!-- Discord (primário) -->
    <a href="https://discord.gg/uwSE3WSjNH" target="_blank" rel="noopener" class="support-card support-card-primary">
        <div class="support-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                <path d="M20.317 4.3698a19.7913 19.7913 0 0 0-4.8851-1.5152.0741.0741 0 0 0-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 0 0-.0785-.037 19.7363 19.7363 0 0 0-4.8852 1.515.0699.0699 0 0 0-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 0 0 .0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 0 0 .0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 0 0-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 0 1-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 0 1 .0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 0 1 .0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 0 1-.0066.1276 12.2986 12.2986 0 0 1-1.873.8914.0766.0766 0 0 0-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 0 0 .0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 0 0 .0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 0 0-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189z"/>
            </svg>
        </div>
        <h3>Discord Tecplay</h3>
        <p>Atendimento mais rápido. Abre um ticket e te respondemos em horário comercial.</p>
        <span class="support-cta">Entrar no Discord →</span>
    </a>

    <!-- Site institucional -->
    <a href="https://tecplay.inf.br" target="_blank" rel="noopener" class="support-card">
        <div class="support-icon" style="color: var(--hazard);">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="48" height="48">
                <circle cx="12" cy="12" r="10"/>
                <line x1="2" y1="12" x2="22" y2="12"/>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
        </div>
        <h3>tecplay.inf.br</h3>
        <p>Site institucional com base de conhecimento, novidades e contato comercial.</p>
        <span class="support-cta">Abrir site →</span>
    </a>

    <!-- E-mail direto -->
    <a href="mailto:suporte@tecplay.inf.br?subject=SUPORTE%20WEBSITE%20BY%20TECPLAY" class="support-card">
        <div class="support-icon" style="color: var(--moss);">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="48" height="48">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
        </div>
        <h3>E-mail</h3>
        <p style="font-family: var(--font-mono); font-size: 0.85rem;">suporte@tecplay.inf.br</p>
        <span class="support-cta">Abrir cliente de e-mail →</span>
    </a>

</div>

<div class="support-instructions">
    <h2>📋 Como abrir um chamado</h2>
    <ol class="support-steps">
        <li>
            <strong>Entra no Discord</strong> pelo botão acima (canal mais rápido)
        </li>
        <li>
            <strong>Abre um ticket</strong> com o assunto:
            <code class="support-tag">SUPORTE WEBSITE BY TECPLAY</code>
            <button class="copy-tag-btn" onclick="navigator.clipboard.writeText('SUPORTE WEBSITE BY TECPLAY').then(() => this.textContent = '✓ Copiado')">📋 Copiar</button>
        </li>
        <li>
            <strong>Descreve o problema</strong> com o máximo de detalhe:
            <ul>
                <li>O que estava tentando fazer</li>
                <li>O que aconteceu (mensagem de erro, comportamento estranho)</li>
                <li>Print ou link da página onde aconteceu</li>
                <li>Versão do site (encontra em <code>config/config.php</code> ou no README)</li>
            </ul>
        </li>
        <li>
            <strong>Aguarda resposta.</strong> Clientes Tecplay têm <span style="color: var(--moss); font-weight: 600;">prioridade</span> sobre tickets gerais.
        </li>
    </ol>
</div>

<div class="support-meta">
    <div class="meta-item">
        <span class="meta-label">SLA esperado</span>
        <span class="meta-value">≤ 24h úteis em casos normais</span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Urgência crítica</span>
        <span class="meta-value">site fora do ar ou pagamento quebrado: respondemos fora de horário</span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Idiomas</span>
        <span class="meta-value">🇧🇷 PT-BR · 🇺🇸 EN-US</span>
    </div>
</div>

<style>
.support-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.2rem;
    margin-bottom: 2.5rem;
}
.support-card {
    background: linear-gradient(180deg, var(--bg-2) 0%, var(--bg-1) 100%);
    border: 1px solid var(--border);
    padding: 1.5rem;
    text-decoration: none;
    color: var(--bone);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-radius: 2px;
    transition: transform .25s, border-color .2s, box-shadow .25s;
}
.support-card:hover {
    transform: translateY(-3px);
    border-color: var(--rust);
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}
.support-card-primary {
    border-color: #5865F2;
    background: linear-gradient(180deg, rgba(88,101,242,0.12) 0%, var(--bg-1) 100%);
}
.support-card-primary:hover { border-color: #7289DA; box-shadow: 0 8px 32px rgba(88,101,242,0.3); }
.support-icon { color: #5865F2; }
.support-card h3 {
    font-family: var(--font-display);
    font-size: 1.15rem;
    margin-top: 0.4rem;
}
.support-card p { color: var(--dim); font-size: 0.85rem; line-height: 1.5; flex: 1; }
.support-cta { color: var(--rust-2); font-family: var(--font-mono); font-size: 0.8rem; letter-spacing: 0.04em; margin-top: 0.6rem; }
.support-card-primary .support-cta { color: #b3bdfd; }

.support-instructions {
    background: var(--bg-1);
    border: 1px solid var(--border);
    border-left: 3px solid var(--hazard);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.support-instructions h2 {
    font-family: var(--font-display);
    font-size: 1.1rem;
    color: var(--bone);
    margin-bottom: 1rem;
}
.support-steps {
    margin: 0 0 0 1.5rem;
    color: var(--bone);
    font-size: 0.92rem;
    line-height: 1.7;
}
.support-steps li { margin-bottom: 0.8rem; }
.support-steps ul { margin: 0.4rem 0 0 1.2rem; color: var(--dim); font-size: 0.85rem; }
.support-tag {
    background: var(--bg-0);
    border: 1px solid var(--hazard);
    color: var(--hazard);
    padding: 0.25rem 0.6rem;
    font-family: var(--font-mono);
    font-size: 0.85rem;
    letter-spacing: 0.04em;
    user-select: all;
    margin: 0 0.3rem;
}
.copy-tag-btn {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--dim);
    padding: 0.2rem 0.6rem;
    font-size: 0.75rem;
    font-family: var(--font-mono);
    cursor: pointer;
    border-radius: 2px;
    margin-left: 0.2rem;
}
.copy-tag-btn:hover { color: var(--bone); border-color: var(--rust); }

.support-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.8rem;
    margin-top: 1.5rem;
}
.meta-item {
    background: var(--bg-1);
    border: 1px solid var(--border);
    padding: 0.8rem 1rem;
    border-radius: 2px;
}
.meta-label {
    display: block;
    font-size: 0.7rem;
    color: var(--dim);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 0.3rem;
}
.meta-value { color: var(--bone); font-size: 0.85rem; }
</style>

<?php \App\View::endSection(); ?>
