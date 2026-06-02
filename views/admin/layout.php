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

<div class="admin-shell">

    <aside class="admin-sidebar">
        <a href="/admin" class="admin-brand">
            <img src="<?= asset('img/logo_semfundo.png') ?>" alt="Logo">
            <div>
                <div class="admin-brand-name"><?= e($config['site_name'] ?? 'DAYZ') ?></div>
                <div class="admin-brand-sub">// ADMIN PANEL</div>
            </div>
        </a>

        <?php $current = strtok($_SERVER['REQUEST_URI'] ?? '', '?'); ?>
        <nav class="admin-nav">
            <a href="/admin" class="<?= $current === '/admin' ? 'active' : '' ?>">▣ Dashboard</a>
            <a href="/admin/players" class="<?= str_starts_with($current, '/admin/players') ? 'active' : '' ?>">☣ Jogadores</a>
            <a href="/admin/packages" class="<?= str_starts_with($current, '/admin/packages') ? 'active' : '' ?>">⛁ Pacotes</a>
            <a href="/admin/servers" class="<?= str_starts_with($current, '/admin/servers') ? 'active' : '' ?>">▣ Servidores</a>
            <a href="/admin/combos" class="<?= str_starts_with($current, '/admin/combos') ? 'active' : '' ?>">⛓ Combos</a>
            <a href="/admin/purchases" class="<?= str_starts_with($current, '/admin/purchases') ? 'active' : '' ?>">⛏ Compras</a>
            <a href="/admin/pages" class="<?= str_starts_with($current, '/admin/pages') ? 'active' : '' ?>">▤ Páginas</a>
            <a href="/admin/announcements" class="<?= str_starts_with($current, '/admin/announcements') ? 'active' : '' ?>">⚑ Anúncios</a>
            <a href="/admin/coupons" class="<?= str_starts_with($current, '/admin/coupons') ? 'active' : '' ?>">◎ Cupons</a>
            <a href="/admin/reviews" class="<?= str_starts_with($current, '/admin/reviews') ? 'active' : '' ?>">★ Avaliações</a>
            <a href="/admin/gallery" class="<?= str_starts_with($current, '/admin/gallery') ? 'active' : '' ?>">▭ Galeria</a>
            <a href="/admin/team" class="<?= str_starts_with($current, '/admin/team') ? 'active' : '' ?>">◈ Equipe</a>
            <a href="/admin/audit" class="<?= str_starts_with($current, '/admin/audit') ? 'active' : '' ?>">⊟ Audit Log</a>
            <a href="/admin/logs" class="<?= str_starts_with($current, '/admin/logs') ? 'active' : '' ?>">⌨ Logs PHP</a>
            <a href="/admin/customize" class="<?= str_starts_with($current, '/admin/customize') ? 'active' : '' ?>">◐ Visual</a>
            <a href="/admin/settings" class="<?= str_starts_with($current, '/admin/settings') ? 'active' : '' ?>">⚙ Config</a>
            <a href="/admin/discord-integration" class="<?= str_starts_with($current, '/admin/discord-integration') ? 'active' : '' ?>">🤖 Integração Discord</a>
            <a href="/admin/support" class="<?= str_starts_with($current, '/admin/support') ? 'active' : '' ?>" style="margin-top: auto;">✉ Suporte Tecplay</a>
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
