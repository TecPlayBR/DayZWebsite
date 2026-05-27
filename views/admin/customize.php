<?php /** @var array $config */ ?>
<?php $title = 'Personalização Visual'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Personalização Visual</h1>
        <p>Troque logo, favicon, backgrounds e paleta de cores via FTP. Não há upload pelo painel ainda.</p>
    </div>
</div>

<!-- Logos -->
<div class="customize-section">
    <h2>Logos</h2>
    <div class="customize-grid">
        <div class="customize-card">
            <div class="customize-preview customize-preview-dark">
                <img src="<?= asset('img/logo_semfundo.png') ?>" alt="logo atual" style="max-height: 100px;">
            </div>
            <div class="customize-info">
                <div class="customize-label">Logo principal</div>
                <div class="customize-path">public/assets/img/logo_semfundo.png</div>
                <p class="customize-help">
                    Aparece no header, footer e e-mails. <strong>PNG com fundo transparente</strong>,
                    recomendado 250×250px ou maior (proporcional).
                </p>
            </div>
        </div>

        <div class="customize-card">
            <div class="customize-preview customize-preview-light">
                <img src="<?= asset('img/logo.png') ?>" alt="logo com fundo" style="max-height: 100px;">
            </div>
            <div class="customize-info">
                <div class="customize-label">Favicon</div>
                <div class="customize-path">public/assets/img/logo.png</div>
                <p class="customize-help">
                    Ícone que aparece na aba do navegador. PNG quadrado (com ou sem fundo),
                    recomendado 64×64 ou 128×128.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Backgrounds -->
<div class="customize-section">
    <h2>Backgrounds</h2>
    <p style="color: var(--dim); font-size: 0.9rem; margin-bottom: 1rem;">
        São usados como pano de fundo em diferentes seções. Resolução recomendada: <strong>1920×1080</strong> ou maior.
        <span style="color: var(--hazard);">⚠ Otimize com TinyPNG/Squoosh antes de subir — backgrounds grandes deixam o site lento.</span>
    </p>
    <div class="customize-grid">
        <?php
        $bgs = [
            'background.png'  => 'Hero principal (home)',
            'background2.png' => 'Login admin',
            'background3.png' => 'Seções secundárias / loja',
            'background4.png' => 'Página 404',
            'background5.png' => 'Páginas estáticas (regras, FAQ, etc)',
        ];
        foreach ($bgs as $file => $purpose):
        ?>
            <div class="customize-card">
                <div class="customize-preview" style="background-image: url('<?= asset('img/' . $file) ?>'); background-size: cover; background-position: center;"></div>
                <div class="customize-info">
                    <div class="customize-label"><?= e($purpose) ?></div>
                    <div class="customize-path">public/assets/img/<?= $file ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Cores / paleta -->
<div class="customize-section">
    <h2>Paleta de Cores</h2>
    <p style="color: var(--dim); font-size: 0.9rem; margin-bottom: 1rem;">
        Edite as variáveis CSS no topo de <code>public/assets/css/theme.css</code>. Mudanças são imediatas (sem rebuild).
    </p>
    <div class="palette-grid">
        <?php
        $palette = [
            ['--rust',   '#c1440e', 'Cor principal (ferrugem/laranja)', '#c1440e'],
            ['--rust-2', '#e74c3c', 'Acento vivo (vermelho/sangue)',     '#e74c3c'],
            ['--hazard', '#d4a017', 'Destaque (amarelo lanterna)',      '#d4a017'],
            ['--moss',   '#5a6c4e', 'Sucesso (verde militar)',           '#5a6c4e'],
            ['--bone',   '#d4c5a9', 'Texto principal (osso/bege)',       '#d4c5a9'],
            ['--bg-0',   '#050608', 'Fundo principal',                   '#050608'],
            ['--bg-1',   '#0d1014', 'Fundo de cards',                    '#0d1014'],
            ['--bg-2',   '#161a20', 'Fundo de tabelas',                  '#161a20'],
            ['--dim',    '#6b7280', 'Texto secundário',                  '#6b7280'],
        ];
        foreach ($palette as [$var, $hex, $label, $color]):
        ?>
            <div class="palette-chip">
                <div class="palette-swatch" style="background: <?= $color ?>;"></div>
                <div class="palette-meta">
                    <code class="palette-var"><?= $var ?></code>
                    <span class="palette-hex"><?= $hex ?></span>
                    <span class="palette-label"><?= e($label) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Tutorial passo a passo -->
<div class="customize-section">
    <h2>Como subir arquivos novos</h2>
    <ol style="color: var(--bone); line-height: 1.8; margin-left: 1.5rem;">
        <li>Conecte ao servidor via <strong>FTP</strong> (FileZilla recomendado) ou Gerenciador de Arquivos do cPanel</li>
        <li>Navegue até a pasta <code>public_html/assets/img/</code></li>
        <li>Faça <strong>upload do arquivo novo com o MESMO nome</strong> do antigo (substitui por cima)</li>
        <li>No navegador, abra o site e dê <strong>Ctrl+F5</strong> (hard refresh) — cache de imagens é agressivo</li>
        <li>Pronto. Não precisa mexer em código nem reiniciar nada.</li>
    </ol>
</div>

<!-- Aviso de cache -->
<div class="stat-card" style="border-left-color: var(--hazard); padding: 1.2rem;">
    <div class="label" style="color: var(--hazard);">⚠ Cache do navegador</div>
    <p style="color: var(--bone); margin-top: 0.5rem; font-size: 0.9rem;">
        O <code>.htaccess</code> diz pros navegadores cachearem imagens por <strong>1 ano</strong> (boa pra performance, ruim pra ver mudanças na hora).
        Após substituir, peça pros jogadores darem <strong>Ctrl+F5</strong>. Pra forçar invalidação global, mude o nome do arquivo
        (ex: <code>logo-v2.png</code>) e atualize as referências em <code>views/partials/header.php</code> e <code>footer.php</code>.
    </p>
</div>

<style>
.customize-section {
    margin-bottom: 3rem;
}
.customize-section h2 {
    font-family: var(--font-display);
    color: var(--bone);
    font-size: 1.2rem;
    margin-bottom: 1rem;
    letter-spacing: 0.04em;
    border-bottom: 2px solid var(--rust);
    padding-bottom: 0.5rem;
}
.customize-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}
.customize-card {
    background: var(--bg-1);
    border: 1px solid var(--border);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.customize-preview {
    height: 140px;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-0);
}
.customize-preview-dark  { background: linear-gradient(135deg, #0a0c10, #161a20); }
.customize-preview-light { background: #f5f5f0; }
.customize-info { padding: 1rem; }
.customize-label {
    font-family: var(--font-display);
    color: var(--bone);
    font-size: 0.95rem;
    letter-spacing: 0.03em;
    margin-bottom: 0.3rem;
}
.customize-path {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: var(--hazard);
    user-select: all;
    margin-bottom: 0.4rem;
    word-break: break-all;
}
.customize-help {
    color: var(--dim);
    font-size: 0.8rem;
    line-height: 1.5;
    margin: 0;
}

.palette-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 0.6rem;
}
.palette-chip {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    background: var(--bg-1);
    border: 1px solid var(--border);
    padding: 0.6rem 0.8rem;
}
.palette-swatch {
    width: 36px; height: 36px;
    border: 1px solid var(--border);
    border-radius: 2px;
    flex-shrink: 0;
}
.palette-meta { display: flex; flex-direction: column; gap: 0.15rem; }
.palette-var {
    font-family: var(--font-mono);
    color: var(--hazard);
    font-size: 0.8rem;
}
.palette-hex {
    font-family: var(--font-mono);
    color: var(--bone);
    font-size: 0.75rem;
}
.palette-label {
    color: var(--dim);
    font-size: 0.75rem;
}
</style>

<?php \App\View::endSection(); ?>
