<?php /** @var array $config, $lines; @var string $log_path; @var int $log_size; @var ?string $error */ ?>
<?php $title = 'Logs'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Console de Logs</h1>
        <p>Últimas 500 linhas do <code>error_log</code> do PHP. Útil pra debug quando algo quebra.</p>
    </div>
    <div style="text-align: right; font-family: var(--font-mono); font-size: 0.75rem; color: var(--dim);">
        <?php if ($log_path): ?>
            <div>Path: <span style="color: var(--hazard); user-select: all;"><?= e($log_path) ?></span></div>
            <div>Tamanho: <?= number_format($log_size / 1024, 1, ',', '.') ?> KB</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div style="background:var(--danger-overlay); border-left:3px solid var(--rust-2); padding:1rem; color:var(--text-danger); margin-bottom: 1.5rem;">
        ⚠ <?= e($error) ?>
        <p style="margin-top:0.5rem; font-size:0.85rem;">
            Pra ativar, defina <code>error_log</code> no <code>php.ini</code> ou via <code>ini_set('error_log', '/path/to/log')</code> no bootstrap.
        </p>
    </div>
<?php endif; ?>

<div style="margin-bottom: 1rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
    <input type="search" id="log-filter" placeholder="Filtrar linhas (substring)…"
           style="flex: 1; min-width: 240px; padding: 0.55rem 0.8rem; background: var(--bg-0); border: 1px solid var(--border); color: var(--bone); font-family: var(--font-mono); font-size: 0.85rem;">
    <label style="display: inline-flex; align-items: center; gap: 0.3rem; color: var(--bone); font-size: 0.85rem; cursor: pointer;">
        <input type="checkbox" id="log-errors-only"> Só erros (PHP / Fatal / Warning)
    </label>
    <button onclick="location.reload()" class="btn-mini outline">↻ Recarregar</button>
</div>

<pre id="log-output" style="background: var(--bg-0); border: 1px solid var(--border); padding: 1rem; max-height: 70vh; overflow-y: auto; font-family: var(--font-mono); font-size: 0.78rem; color: var(--bone); line-height: 1.5; white-space: pre-wrap; word-break: break-all;">
<?php if (empty($lines)): ?><span style="color: var(--dim);">(log vazio)</span><?php else: ?>
<?php foreach ($lines as $l):
    if (trim($l) === '') continue;
    $cls = '';
    if (stripos($l, 'fatal') !== false || stripos($l, 'parse error') !== false) $cls = 'log-fatal';
    elseif (stripos($l, 'warning') !== false) $cls = 'log-warn';
    elseif (stripos($l, 'notice') !== false || stripos($l, 'deprecated') !== false) $cls = 'log-notice';
    elseif (stripos($l, 'tecplay') !== false || stripos($l, '[mp ') !== false) $cls = 'log-app';
?><span class="log-line <?= $cls ?>"><?= e($l) ?></span>
<?php endforeach; ?>
<?php endif; ?>
</pre>

<style>
.log-line { display: block; padding: 0.1rem 0.3rem; }
.log-line.log-fatal  { background: var(--danger-overlay); color: var(--text-danger); border-left: 3px solid var(--rust-2); padding-left: 0.5rem; }
.log-line.log-warn   { background: var(--hazard-overlay); color: var(--hazard); border-left: 3px solid var(--hazard); padding-left: 0.5rem; }
.log-line.log-notice { color: var(--dim); }
.log-line.log-app    { color: var(--moss); }
.log-line.hidden     { display: none; }
</style>

<script>
const filter = document.getElementById('log-filter');
const errorsOnly = document.getElementById('log-errors-only');
const output = document.getElementById('log-output');
function apply() {
    const q = filter.value.toLowerCase();
    const onlyErr = errorsOnly.checked;
    output.querySelectorAll('.log-line').forEach(l => {
        const text = l.textContent.toLowerCase();
        let show = true;
        if (q && !text.includes(q)) show = false;
        if (onlyErr && !(l.classList.contains('log-fatal') || l.classList.contains('log-warn'))) show = false;
        l.classList.toggle('hidden', !show);
    });
}
filter.addEventListener('input', apply);
errorsOnly.addEventListener('change', apply);
output.scrollTop = output.scrollHeight;
</script>

<?php \App\View::endSection(); ?>
