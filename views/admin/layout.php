<?php /** @var array $config */ ?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Admin') ?> — <?= e($config['site_name'] ?? 'DayZ') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Inter:wght@400;600;700&family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <?= theme_override_tag() ?>
</head>
<body class="admin-body">

<!-- Hamburger (mobile only — CSS esconde em desktop) -->
<button type="button" class="admin-hamburger" id="admin-hamburger" aria-label="Abrir menu" aria-controls="admin-shell" aria-expanded="false">☰</button>

<div class="admin-shell" id="admin-shell">

    <!-- Backdrop pra fechar drawer ao clicar fora -->
    <div class="admin-drawer-backdrop" id="admin-drawer-backdrop"></div>

    <aside class="admin-sidebar">
        <a href="/admin" class="admin-brand">
            <img src="<?= asset('img/logo_semfundo.png') ?>" alt="Logo">
            <div>
                <div class="admin-brand-name"><?= e($config['site_name'] ?? 'DAYZ') ?></div>
                <div class="admin-brand-sub">// ADMIN PANEL</div>
            </div>
        </a>

        <?php
        $current = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        // Nav itens filtrados por permissão. Se user não tem can(area), item some.
        // Cada tupla: [area, href, label, isActive bool]
        $navItems = [
            ['dashboard',           '/admin',                    '📊 Dashboard',           $current === '/admin'],
            ['players',             '/admin/players',            '👥 Jogadores',           str_starts_with($current, '/admin/players')],
            ['packages',            '/admin/packages',           '📦 Pacotes',             str_starts_with($current, '/admin/packages')],
            ['packages',            '/admin/shop',               '🛒 Loja in-game',        str_starts_with($current, '/admin/shop')],
            ['packages',            '/admin/caixas',             '🎁 Caixas',              str_starts_with($current, '/admin/caixas')],
            ['servers',             '/admin/servers',            '🛰 Integração Agent',    str_starts_with($current, '/admin/servers')],
            ['servers',             '/admin/sparda',             '🎮 Entrega Sparda',      str_starts_with($current, '/admin/sparda')],
            ['discord_integration', '/admin/discord-integration','🤖 Integração Discord',  str_starts_with($current, '/admin/discord-integration')],
            ['combos',              '/admin/combos',             '🎁 Combos',              str_starts_with($current, '/admin/combos')],
            ['settings',            '/admin/rewards',            '🏆 Recompensas',         str_starts_with($current, '/admin/rewards')],
            ['purchases',           '/admin/purchases',          '💰 Compras',             str_starts_with($current, '/admin/purchases')],
            ['pages',               '/admin/pages',              '📄 Páginas',             str_starts_with($current, '/admin/pages')],
            ['announcements',       '/admin/announcements',      '📢 Anúncios',            str_starts_with($current, '/admin/announcements')],
            ['coupons',             '/admin/coupons',            '🎟 Cupons',              str_starts_with($current, '/admin/coupons')],
            ['reviews',             '/admin/reviews',            '⭐ Avaliações',          str_starts_with($current, '/admin/reviews')],
            ['gallery',             '/admin/gallery',            '🖼 Galeria',             str_starts_with($current, '/admin/gallery')],
            ['team',                '/admin/team',               '🧑‍💼 Equipe',            str_starts_with($current, '/admin/team')],
            ['audit',               '/admin/audit',              '📋 Audit Log',           str_starts_with($current, '/admin/audit')],
            ['logs',                '/admin/logs',               '🐛 Logs PHP',            str_starts_with($current, '/admin/logs')],
            ['customize',           '/admin/customize',          '🎨 Visual',              str_starts_with($current, '/admin/customize')],
            ['settings',            '/admin/settings',           '⚙️ Config',              str_starts_with($current, '/admin/settings')],
        ];
        ?>
        <nav class="admin-nav">
            <?php foreach ($navItems as [$area, $href, $label, $active]):
                if (!\App\Auth::can($area)) continue; ?>
                <a href="<?= e($href) ?>" class="<?= $active ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <?php if (\App\Auth::can('support')): ?>
                <a href="/admin/support" class="<?= str_starts_with($current, '/admin/support') ? 'active' : '' ?>" style="margin-top: auto;">🛟 Suporte Tecplay</a>
            <?php endif; ?>
        </nav>

        <div class="admin-foot">
            <?php $u = \App\Auth::user(); ?>
            <div class="admin-user">
                <span class="admin-user-dot"></span>
                <span><?= e($u['username'] ?? '—') ?></span>
            </div>
            <a href="/admin/logout" class="admin-logout">Sair →</a>
            <a href="/" class="admin-back">↩ Ver site</a>
        </div>
    </aside>

    <main class="admin-main">
        <?= \App\View::yield('content') ?>
    </main>

</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
// ============ MOBILE DRAWER ============
(function() {
    const shell = document.getElementById('admin-shell');
    const btn   = document.getElementById('admin-hamburger');
    const bd    = document.getElementById('admin-drawer-backdrop');
    if (!shell || !btn) return;
    let savedScrollY = 0;
    function setOpen(open) {
        shell.classList.toggle('drawer-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? '×' : '☰';
        // Scroll lock: salva posição, fixa body pra não rolar atrás do drawer.
        // Restaura ao fechar. Funciona no Edge 60 (não usa body.overflow:hidden
        // direto que quebrou no patchG).
        if (open) {
            savedScrollY = window.scrollY;
            document.body.style.position = 'fixed';
            document.body.style.top = '-' + savedScrollY + 'px';
            document.body.style.left = '0';
            document.body.style.right = '0';
        } else {
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            window.scrollTo(0, savedScrollY);
        }
    }
    btn.addEventListener('click', () => setOpen(!shell.classList.contains('drawer-open')));
    bd && bd.addEventListener('click', () => setOpen(false));
    // Fecha ao clicar em qualquer link da sidebar (mobile nav UX)
    shell.querySelector('.admin-nav')?.addEventListener('click', e => {
        if (e.target.closest('a')) setOpen(false);
    });
    // Fecha com ESC
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });

    // ============ AUTO-WRAP TABELAS pra scroll horizontal ============
    // Toda .admin-table que NÃO está dentro de .admin-table-wrap ganha um wrapper.
    // Cobre TODAS as pages admin (Pacotes, Compras, Cupons, Equipe, Audit, etc).
    const mainEl = document.querySelector('.admin-main');
    function wrapAdminTables(root) {
        (root || document).querySelectorAll('.admin-table').forEach(t => {
            if (t.parentElement?.classList.contains('admin-table-wrap')) return;
            const wrap = document.createElement('div');
            wrap.className = 'admin-table-wrap';
            t.parentNode.insertBefore(wrap, t);
            wrap.appendChild(t);
        });
    }
    wrapAdminTables();
    // Re-aplica quando PJAX troca o conteúdo do main
    if (mainEl) new MutationObserver(() => wrapAdminTables(mainEl)).observe(mainEl, { childList: true, subtree: false });

    // ============ HIDE-ON-SCROLL DO HAMBÚRGUER ============
    // Quando user rola pra baixo, esconde (libera viewport). Volta ao rolar pra cima.
    // Não esconde nos primeiros 100px (área do título — hambúrguer ainda relevante).
    let lastY = 0, ticking = false;
    window.addEventListener('scroll', () => {
        if (shell.classList.contains('drawer-open')) return; // não esconde com drawer aberto
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
            const y = window.scrollY;
            if (y > lastY && y > 100)      btn.classList.add('hidden');  // rolando pra baixo
            else if (y < lastY - 5)         btn.classList.remove('hidden'); // pra cima (threshold pra não tremer)
            lastY = y;
            ticking = false;
        });
    }, { passive: true });
})();
</script>
<script>
// ============ PJAX-lite admin ============
// Intercepta clicks na sidebar e troca só o <main> via fetch — sem refresh.
// Mantém scroll por path no sessionStorage, restaura ao revisitar.
(function() {
    const main = document.querySelector('.admin-main');
    const nav  = document.querySelector('.admin-nav');
    if (!main || !nav || !window.fetch) return;

    const scrollKey = (path) => 'pjax-scroll:' + path;
    const stashScroll = () => sessionStorage.setItem(scrollKey(location.pathname), String(window.scrollY));
    const restoreScroll = () => {
        const v = sessionStorage.getItem(scrollKey(location.pathname));
        window.scrollTo(0, v ? parseInt(v, 10) : 0);
    };

    function reExecuteScripts(container) {
        container.querySelectorAll('script').forEach(old => {
            const s = document.createElement('script');
            for (const a of old.attributes) s.setAttribute(a.name, a.value);
            s.textContent = old.textContent;
            old.parentNode.replaceChild(s, old);
        });
    }

    function setActive(href) {
        nav.querySelectorAll('a').forEach(a => {
            const h = a.getAttribute('href') || '';
            // Mesma regra do PHP: str_starts_with($current, h) — exato pra /admin
            const active = (h === '/admin') ? (href === '/admin') : href.startsWith(h);
            a.classList.toggle('active', active);
        });
    }

    let loading = false;
    async function navigate(url, push = true, fromPop = false) {
        if (loading) return;
        loading = true;
        main.classList.add('pjax-loading');
        try {
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'admin-pjax', 'Accept': 'text/html' },
                redirect: 'follow', credentials: 'same-origin',
            });
            // Caiu pra login (302 → /admin/login fora de /admin)? full reload.
            if (!res.ok || !res.url.includes('/admin') || res.url.includes('/admin/login')) {
                location.href = url; return;
            }
            const html = await res.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newMain = doc.querySelector('.admin-main');
            if (!newMain) { location.href = url; return; }

            if (push) {
                stashScroll();
                history.pushState({ pjax: true }, '', url);
            }
            main.innerHTML = newMain.innerHTML;
            reExecuteScripts(main);
            const t = doc.querySelector('title'); if (t) document.title = t.textContent;
            setActive(location.pathname);
            if (fromPop) restoreScroll(); else window.scrollTo(0, 0);
        } catch (e) {
            location.href = url;
        } finally {
            loading = false;
            main.classList.remove('pjax-loading');
        }
    }

    nav.addEventListener('click', e => {
        const a = e.target.closest('a');
        if (!a) return;
        const href = a.getAttribute('href') || '';
        if (!href.startsWith('/admin') || href === '/admin/logout') return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || a.target === '_blank') return;
        e.preventDefault();
        if (href === location.pathname + location.search) return;
        navigate(href);
    });

    window.addEventListener('popstate', () => navigate(location.href, false, true));
    window.addEventListener('beforeunload', stashScroll);
})();
</script>
<style>
.admin-main { transition: opacity .15s; }
.admin-main.pjax-loading { opacity: 0.55; pointer-events: none; }
@media (prefers-reduced-motion: no-preference) {
    @view-transition { navigation: auto; }
}
</style>
</body>
</html>
