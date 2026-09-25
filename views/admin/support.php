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


    <!-- Site institucional -->
    <a href="https://tecplay.inf.br" target="_blank" rel="noopener" class="support-card support-card-primary">
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
            <button class="copy-tag-btn" data-copiar="SUPORTE WEBSITE BY TECPLAY">📋 Copiar</button>
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
