<?php /** @var array $config */ ?>
<?php $title = 'Personalização Visual'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Personalização Visual</h1>
        <p>Troque logo, favicon e backgrounds direto por aqui - sem FTP. Suas imagens ficam isoladas e <strong>não são perdidas quando você atualiza o template</strong>.</p>
    </div>
</div>

<?php
$czOk  = $_GET['ok']  ?? '';
$czErr = $_GET['err'] ?? '';
$czOkMsg = [
    'upload'      => '✓ Imagem atualizada. Se não mudar na hora, dê Ctrl+F5 (cache do navegador).',
    'reset'       => '✓ Voltou pra imagem padrão do template.',
    'theme'       => '✓ Cores salvas e aplicadas. Dê Ctrl+F5 se não atualizar na hora.',
    'theme_reset' => '✓ Cores voltaram pro padrão do template.',
];
$czErrMsg = [
    'slot'        => 'Item inválido.',
    'upload'      => 'Falha no envio do arquivo. Tente de novo.',
    'size'        => 'Arquivo grande demais pro limite desse item.',
    'type'        => 'Formato não suportado. Use PNG, JPG, WEBP ou GIF.',
    'move'        => 'Não consegui salvar - dê permissão de escrita à pasta public/assets/img/custom/ (chmod 755; se o seu host roda o PHP em usuário separado, use 775).',
    'theme'       => 'Nenhuma cor válida pra salvar.',
    'theme_write' => 'Não consegui gravar o tema - dê permissão de escrita à pasta public/assets/css/ (chmod 755; se não resolver, 775).',
];
?>
<?php if (isset($czOkMsg[$czOk])): ?>
    <div class="cz-banner cz-ok"><?= e($czOkMsg[$czOk]) ?></div>
<?php elseif ($czErr): ?>
    <div class="cz-banner cz-err">✕ <?= e($czErrMsg[$czErr] ?? 'Erro ao processar.') ?></div>
<?php endif; ?>

<?php
/** @var callable $isCustom */
// Renderiza um card de upload pra um slot de marca (logo ou background).
$brandCard = function(string $slot, string $label, string $help, string $type = 'logo', string $previewMod = '') use ($isCustom) {
    $active = $isCustom($slot);
    ?>
    <div class="customize-card">
        <?php if ($type === 'logo'): ?>
            <div class="customize-preview <?= $previewMod ?>">
                <img src="<?= asset('img/' . $slot) ?>" alt="<?= e($label) ?>" style="max-height: 100px;">
            </div>
        <?php else: ?>
            <div class="customize-preview" style="background-image: url('<?= asset('img/' . $slot) ?>'); background-size: cover; background-position: center;"></div>
        <?php endif; ?>
        <div class="customize-info">
            <div class="customize-label">
                <?= e($label) ?>
                <?php if ($active): ?>
                    <span class="cz-badge">customizado</span>
                <?php else: ?>
                    <span class="cz-badge cz-badge-default">padrão</span>
                <?php endif; ?>
            </div>
            <p class="customize-help"><?= $help ?></p>
            <form method="POST" action="/admin/customize/upload" enctype="multipart/form-data" class="cz-form">
                <?= \App\Csrf::field() ?>
                <input type="hidden" name="slot" value="<?= e($slot) ?>">
                <input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/gif" required class="cz-file">
                <button type="submit" class="btn-mini">Enviar</button>
            </form>
            <?php if ($active): ?>
                <form method="POST" action="/admin/customize/reset" class="cz-form-reset"
                      onsubmit="return confirm('Voltar pra imagem padrão do template?');">
                    <?= \App\Csrf::field() ?>
                    <input type="hidden" name="slot" value="<?= e($slot) ?>">
                    <button type="submit" class="btn-mini btn-mini-ghost">Voltar ao padrão</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>

<!-- Logos -->
<div class="customize-section">
    <h2>Logos & Favicon</h2>
    <div class="customize-grid">
        <?php $brandCard('logo_semfundo.png', 'Logo principal', 'Aparece no header, rodapé e e-mails. <strong>PNG transparente</strong>, ~250×250px ou maior.', 'logo', 'customize-preview-dark'); ?>
        <?php $brandCard('logo_semfundo_small.png', 'Logo pequeno', 'Versão compacta no header/rodapé. PNG transparente, ~120×120px.', 'logo', 'customize-preview-dark'); ?>
        <?php $brandCard('logo.png', 'Favicon', 'Ícone na aba do navegador. PNG quadrado, 64×64 ou 128×128.', 'logo', 'customize-preview-light'); ?>
    </div>
</div>

<!-- Backgrounds -->
<div class="customize-section">
    <h2>Backgrounds</h2>
    <p style="color: var(--dim); font-size: 0.9rem; margin-bottom: 1rem;">
        Pano de fundo das seções. Resolução recomendada: <strong>1920×1080</strong> ou maior.
        <span style="color: var(--hazard);">⚠ Otimize com TinyPNG/Squoosh antes de subir - backgrounds grandes deixam o site lento.</span>
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
        foreach ($bgs as $file => $purpose) {
            $brandCard($file, $purpose, 'PNG ou JPG, 1920×1080 ou maior.', 'bg');
        }
        ?>
    </div>
</div>

<!-- Cores / paleta -->
<div class="customize-section">
    <h2>Paleta de Cores
        <?php if (!empty($themeActive)): ?><span class="cz-badge">customizada</span><?php else: ?><span class="cz-badge cz-badge-default">padrão</span><?php endif; ?>
    </h2>
    <p style="color: var(--dim); font-size: 0.9rem; margin-bottom: 1rem;">
        Escolha as cores e clique em <strong>Salvar cores</strong>. Aplica na hora, no site inteiro e no painel.
        Fica guardado isolado (<code>theme.override.css</code>) e <strong>não é perdido em update</strong>.
    </p>
    <?php
    /** @var array $themeColors */
    $palette = [
        ['--rust',   'Cor principal (brand)'],
        ['--rust-2', 'Acento vivo'],
        ['--hazard', 'Destaque (accent)'],
        ['--moss',   'Sucesso (verde/online)'],
        ['--bone',   'Texto principal'],
        ['--dim',    'Texto secundário'],
        ['--bg-0',   'Fundo principal'],
        ['--bg-1',   'Fundo de cards'],
        ['--bg-2',   'Fundo de tabelas'],
        ['--bg-3',   'Fundo elevado'],
    ];
    ?>
    <form method="POST" action="/admin/customize/theme" id="theme-form">
        <?= \App\Csrf::field() ?>
        <div class="palette-grid">
            <?php foreach ($palette as [$var, $label]): $val = $themeColors[$var] ?? '#000000'; ?>
                <label class="palette-chip">
                    <input type="color" class="palette-input" name="c_<?= e(ltrim($var, '-')) ?>" value="<?= e($val) ?>">
                    <div class="palette-meta">
                        <span class="palette-label"><?= e($label) ?></span>
                        <code class="palette-var"><?= e($var) ?></code>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="cz-theme-actions">
            <button type="submit" class="btn-mini">Salvar cores</button>
            <?php if (!empty($themeActive)): ?>
                <button type="submit" name="reset_theme" value="1" class="btn-mini btn-mini-ghost"
                        onclick="return confirm('Voltar as cores pro padrão do template?');">Voltar ao padrão</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Nota sobre persistência -->
<div class="stat-card" style="border-left-color: var(--moss); padding: 1.2rem;">
    <div class="label" style="color: var(--moss);">✓ Suas imagens são à prova de update</div>
    <p style="color: var(--bone); margin-top: 0.5rem; font-size: 0.9rem;">
        O que você envia aqui fica guardado em <code>assets/img/custom/</code> - uma pasta isolada que
        <strong>não é tocada quando você atualiza o template</strong>. O site usa a sua imagem no lugar da padrão
        automaticamente, e o cache é invalidado sozinho a cada novo envio (sem precisar renomear nada).
        Quiser voltar atrás, é só clicar em <strong>"Voltar ao padrão"</strong>.
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
.customize-preview-dark  { background: linear-gradient(135deg, #0a0c10, var(--bg-2)); }
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
    margin: 0 0 0.8rem;
}

.cz-banner {
    padding: 0.8rem 1.1rem;
    border-radius: 3px;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}
.cz-ok  { background: rgba(34,197,94,0.12);  border-left: 3px solid var(--moss); color: var(--bone); }
.cz-err { background: var(--danger-overlay); border-left: 3px solid var(--rust-2); color: var(--bone); }

.cz-badge {
    display: inline-block;
    font-family: var(--font-mono);
    font-size: 0.62rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.1rem 0.45rem;
    border-radius: 2px;
    margin-left: 0.4rem;
    vertical-align: middle;
    background: var(--hazard-overlay, rgba(217,164,65,0.15));
    color: var(--hazard);
    border: 1px solid var(--hazard-border, rgba(217,164,65,0.3));
}
.cz-badge-default { background: transparent; color: var(--dim); border-color: var(--border); }

.cz-form { display: flex; gap: 0.4rem; align-items: center; }
.cz-file {
    flex: 1; min-width: 0;
    font-size: 0.75rem; color: var(--dim);
}
.cz-file::file-selector-button {
    background: var(--bg-2); color: var(--bone);
    border: 1px solid var(--border); border-radius: 2px;
    padding: 0.3rem 0.6rem; font-size: 0.75rem; cursor: pointer; margin-right: 0.5rem;
}
.cz-form-reset { margin-top: 0.5rem; }
.btn-mini {
    background: var(--rust); color: var(--bone); border: none; cursor: pointer;
    padding: 0.4rem 0.9rem; font-size: 0.78rem; border-radius: 2px;
    font-family: var(--font-display); letter-spacing: 0.03em; white-space: nowrap;
}
.btn-mini:hover { background: var(--rust-2); }
.btn-mini-ghost {
    background: transparent; color: var(--dim); border: 1px solid var(--border);
    font-family: inherit; letter-spacing: 0;
}
.btn-mini-ghost:hover { background: var(--bg-2); color: var(--bone); }

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
    cursor: pointer;
}
.palette-chip:hover { border-color: var(--rust); }
.palette-input {
    width: 42px; height: 42px;
    padding: 0; border: 1px solid var(--border); border-radius: 3px;
    background: none; cursor: pointer; flex-shrink: 0;
}
.palette-input::-webkit-color-swatch-wrapper { padding: 2px; }
.palette-input::-webkit-color-swatch { border: none; border-radius: 2px; }
.palette-input::-moz-color-swatch { border: none; border-radius: 2px; }
.palette-meta { display: flex; flex-direction: column; gap: 0.2rem; }
.palette-label {
    color: var(--bone);
    font-size: 0.82rem;
}
.palette-var {
    font-family: var(--font-mono);
    color: var(--hazard);
    font-size: 0.72rem;
}
.cz-theme-actions {
    display: flex; gap: 0.6rem; margin-top: 1.2rem;
}
</style>

<?php \App\View::endSection(); ?>
