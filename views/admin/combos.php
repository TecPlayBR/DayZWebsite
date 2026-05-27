<?php /** @var array $config, $combos, $packages */ ?>
<?php $title = 'Combos'; ?>
<?php \App\View::extend('admin.layout'); ?>
<?php \App\View::section('content'); ?>

<div class="admin-page-head">
    <div>
        <h1>Combo Packs</h1>
        <p>Junte 2+ pacotes num bundle com desconto. Jogador recebe a soma dos coins de todos os pacotes do combo.</p>
    </div>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert-toast">Salvo.</div><?php endif; ?>
<?php if (!empty($_GET['err'])): ?>
    <div style="background:rgba(231,76,60,0.12);border-left:3px solid var(--rust-2);padding:0.7rem 1rem;margin-bottom:1.5rem;color:#fca5a5;">
        <?= match($_GET['err']) {
            'duplicate' => 'Já existe combo com esse slug.',
            'invalid'   => 'Verifique: slug, nome, e pelo menos 2 pacotes.',
            default     => 'Erro.',
        } ?>
    </div>
<?php endif; ?>

<form method="POST" action="/admin/combos/create" class="stat-card" style="padding: 1.5rem; margin-bottom: 2rem;">
    <?= \App\Csrf::field() ?>
    <div class="label" style="margin-bottom: 1rem;">+ Novo combo</div>

    <div style="display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 0.8rem; margin-bottom: 0.8rem;">
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Slug</label>
            <input type="text" name="slug" required placeholder="combo-veterano"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);"
                   oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '')">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Nome do combo</label>
            <input type="text" name="name" required placeholder="Pacote do Veterano"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
        </div>
        <div>
            <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Preço total (R$)</label>
            <input type="number" name="custom_price" required min="0.01" step="0.01" placeholder="99.90"
                   style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone); font-family:var(--font-mono);">
        </div>
    </div>

    <div style="margin-bottom: 0.8rem;">
        <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.3rem; text-transform: uppercase;">Descrição (opcional)</label>
        <input type="text" name="description" placeholder="Combo turbo pra dominar o mapa"
               style="width:100%; padding:0.6rem; background:var(--bg-0); border:1px solid var(--border); color:var(--bone);">
    </div>

    <div style="margin-bottom: 1rem;">
        <label style="display:block; font-size:0.75rem; color:var(--dim); margin-bottom:0.5rem; text-transform: uppercase;">Pacotes inclusos <small>(mín. 2)</small></label>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem;">
            <?php foreach ($packages as $pkg): ?>
                <label style="background:var(--bg-0); padding:0.6rem 0.8rem; border:1px solid var(--border); cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;">
                    <input type="checkbox" name="package_ids[]" value="<?= e($pkg['id']) ?>">
                    <span>
                        <strong style="color: var(--bone);"><?= e($pkg['name']) ?></strong>
                        <small style="color: var(--dim); display: block;"><?= (int)$pkg['coins'] ?>+<?= (int)$pkg['bonus_coins'] ?> · R$ <?= number_format($pkg['price_brl'], 2, ',', '.') ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <button type="submit" class="btn-mini" style="padding: 0.6rem 1.5rem;">Criar combo</button>
</form>

<table class="admin-table">
    <thead>
        <tr><th>Slug / Nome</th><th>Pacotes</th><th>Coins (total)</th><th>Preço combo</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
        <?php if (empty($combos)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--dim);padding:2rem;">Nenhum combo ainda.</td></tr>
        <?php else:
            $pkgMap = []; foreach ($packages as $p) $pkgMap[$p['id']] = $p;
            foreach ($combos as $c):
                $ids = json_decode($c['package_ids'], true) ?: [];
                $totalCoins = 0; $totalOriginal = 0;
                $names = [];
                foreach ($ids as $pid) {
                    if (isset($pkgMap[$pid])) {
                        $totalCoins    += (int)$pkgMap[$pid]['coins'] + (int)$pkgMap[$pid]['bonus_coins'];
                        $totalOriginal += (float)$pkgMap[$pid]['price_brl'];
                        $names[] = $pkgMap[$pid]['name'];
                    }
                }
                $savedPct = $totalOriginal > 0 ? round((1 - $c['custom_price'] / $totalOriginal) * 100) : 0;
        ?>
            <tr>
                <td>
                    <strong><?= e($c['name']) ?></strong>
                    <div class="dim mono" style="font-size:0.75rem;"><?= e($c['slug']) ?></div>
                </td>
                <td class="dim" style="font-size:0.8rem;"><?= e(implode(' + ', $names)) ?></td>
                <td class="mono" style="font-family: var(--font-display); font-size: 1.1rem; color: var(--hazard);"><?= $totalCoins ?></td>
                <td>
                    <strong>R$ <?= number_format($c['custom_price'], 2, ',', '.') ?></strong>
                    <?php if ($savedPct > 0): ?>
                        <div class="dim" style="font-size: 0.75rem; text-decoration: line-through;">R$ <?= number_format($totalOriginal, 2, ',', '.') ?></div>
                        <div style="color: var(--moss); font-size: 0.75rem;">economiza <?= $savedPct ?>%</div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int)$c['enabled']): ?>
                        <span class="badge success">ativo</span>
                    <?php else: ?>
                        <span class="badge info">off</span>
                    <?php endif; ?>
                </td>
                <td style="white-space: nowrap;">
                    <form method="POST" action="/admin/combos/<?= (int)$c['id'] ?>/toggle" style="display:inline;">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline"><?= (int)$c['enabled'] ? 'Desativar' : 'Ativar' ?></button>
                    </form>
                    <form method="POST" action="/admin/combos/<?= (int)$c['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Apagar?');">
                        <?= \App\Csrf::field() ?>
                        <button type="submit" class="btn-mini outline" style="border-color: rgba(231,76,60,0.4); color: #fca5a5;">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php \App\View::endSection(); ?>
