/* ============================================================
   Tecplay - DayZ Website Template
   JavaScript leve sem dependencias.
   ============================================================ */

(function() {
    'use strict';
    const _ns = '9m7-k2p4-rb1j';

    // ============ HEADER scroll effect ============
    const header = document.querySelector('.site-header');
    if (header) {
        const onScroll = () => {
            if (window.scrollY > 30) header.classList.add('scrolled');
            else header.classList.remove('scrolled');
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // ============ Smooth scroll pra links de ancora ============
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', e => {
            const id = link.getAttribute('href');
            if (id === '#') return;
            const target = document.querySelector(id);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ============ Lang selector - animacao curta (drawer mobile) ============
    document.querySelectorAll('.lang-flag').forEach(btn => {
        btn.addEventListener('click', e => {
            btn.style.transform = 'scale(0.92)';
        });
    });

    // ============ Lang dropdown (header desktop) ============
    document.querySelectorAll('[data-lang-dropdown]').forEach(dd => {
        const trigger = dd.querySelector('.lang-trigger');
        if (!trigger) return;
        trigger.addEventListener('click', e => {
            e.stopPropagation();
            const open = dd.classList.toggle('open');
            trigger.setAttribute('aria-expanded', String(open));
        });
    });
    // Fecha ao clicar fora
    document.addEventListener('click', () => {
        document.querySelectorAll('.lang-dropdown.open').forEach(dd => {
            dd.classList.remove('open');
            dd.querySelector('.lang-trigger')?.setAttribute('aria-expanded', 'false');
        });
    });

    // ============ Mobile drawer ============
    const hamb     = document.getElementById('hamburger');
    const drawer   = document.getElementById('mobile-drawer');
    const backdrop = document.getElementById('drawer-backdrop');

    function setDrawer(open) {
        if (!drawer || !hamb || !backdrop) return;
        drawer.classList.toggle('open', open);
        backdrop.classList.toggle('open', open);
        hamb.classList.toggle('active', open);
        hamb.setAttribute('aria-expanded', String(open));
        drawer.setAttribute('aria-hidden', String(!open));
        document.body.style.overflow = open ? 'hidden' : '';
    }

    if (hamb) hamb.addEventListener('click', () => setDrawer(!drawer.classList.contains('open')));
    if (backdrop) backdrop.addEventListener('click', () => setDrawer(false));

    // Fecha o drawer ao clicar em qualquer link com data-close
    drawer?.querySelectorAll('[data-close]').forEach(el => {
        el.addEventListener('click', () => setDrawer(false));
    });

    // Fecha com ESC
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && drawer?.classList.contains('open')) setDrawer(false);
    });

    // ============ Cookie banner LGPD ============
    const cookieBanner = document.getElementById('cookie-banner');
    if (cookieBanner) {
        try {
            const accepted = localStorage.getItem('cookie_consent_v1');
            if (!accepted) {
                cookieBanner.hidden = false;
            }
        } catch (_) {
            // Sem localStorage (browsers em modo privado restritos): mostra mesmo assim
            cookieBanner.hidden = false;
        }
        document.getElementById('cookie-banner-ok')?.addEventListener('click', () => {
            try { localStorage.setItem('cookie_consent_v1', new Date().toISOString()); } catch (_) {}
            cookieBanner.hidden = true;
        });
    }

    // ============ Anúncios dismissíveis (por sessão) ============
    // Fechou volta a aparecer só quando o navegador fechar (próximo dia, novo login).
    try {
        const dismissedKey = 'dismissed_announcements';
        const dismissed = JSON.parse(sessionStorage.getItem(dismissedKey) || '[]');
        document.querySelectorAll('[data-announcement-id]').forEach(el => {
            const id = el.dataset.announcementId;
            if (dismissed.includes(id)) {
                el.remove();
                return;
            }
            el.querySelector('.announcement-close')?.addEventListener('click', () => {
                el.style.transition = 'opacity 0.2s ease-out, max-height 0.3s ease-out, padding 0.3s, border 0.3s';
                el.style.maxHeight = el.offsetHeight + 'px';
                requestAnimationFrame(() => {
                    el.style.opacity = '0';
                    el.style.maxHeight = '0';
                    el.style.paddingTop = '0';
                    el.style.paddingBottom = '0';
                    el.style.borderWidth = '0';
                });
                setTimeout(() => el.remove(), 320);
                try {
                    const cur = JSON.parse(sessionStorage.getItem(dismissedKey) || '[]');
                    if (!cur.includes(id)) cur.push(id);
                    sessionStorage.setItem(dismissedKey, JSON.stringify(cur));
                } catch (_) {}
            });
        });
    } catch (_) {}

    // ============ Wipe countdown ============
    const wipeBox = document.querySelector('[data-wipe-target]');
    if (wipeBox) {
        const target = parseInt(wipeBox.dataset.wipeTarget, 10) * 1000;
        const el = wipeBox.querySelector('[data-wipe-countdown]');
        const tick = () => {
            const diff = target - Date.now();
            if (diff <= 0) {
                el.textContent = 'ACONTECENDO AGORA';
                return;
            }
            const d = Math.floor(diff / 86400000);
            const h = Math.floor((diff % 86400000) / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            const parts = [];
            if (d > 0) parts.push(d + 'd');
            parts.push(String(h).padStart(2, '0') + 'h');
            parts.push(String(m).padStart(2, '0') + 'm');
            if (d === 0) parts.push(String(s).padStart(2, '0') + 's');
            el.textContent = parts.join(' ');
        };
        tick();
        setInterval(tick, 1000);
    }

    // ============ Loading state em forms ============
    // Quando submeter, desabilita o botao e troca texto pra "Salvando..."
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            const btns = form.querySelectorAll('button[type="submit"], button:not([type])');
            btns.forEach(btn => {
                if (btn.dataset.noLoading !== undefined) return;
                btn.dataset.originalText = btn.textContent;
                btn.disabled = true;
                // Mantém texto curto pra botões pequenos
                if (btn.textContent.length > 3) {
                    btn.textContent = '...';
                }
            });
            // Reverte após 8s caso form trave (fallback)
            setTimeout(() => {
                btns.forEach(btn => {
                    btn.disabled = false;
                    if (btn.dataset.originalText) btn.textContent = btn.dataset.originalText;
                });
            }, 8000);
        });
    });
})();

/* ============================================================================
   COPIAR PRA AREA DE TRANSFERENCIA  (delegado, um listener pra toda a pagina)

   POR QUE DELEGADO E NAO onclick INLINE
   -------------------------------------
   O botao antigo montava o handler dentro do atributo:

       onclick="navigator.clipboard.writeText(<?= json_encode($token) ?>)..."

   `json_encode` devolve o valor JA COM ASPAS DUPLAS, e o atributo tambem e delimitado
   por aspas duplas. A primeira aspa do token FECHAVA o atributo, o `>` seguinte fechava
   a tag, e todo o resto do JavaScript aparecia como TEXTO na tela do cliente. Foi o que
   aconteceu na aba Integracao Discord e na aba Sparda.

   Aqui o valor viaja num `data-copiar`, que o PHP escapa com `e()` como qualquer outro
   texto, e nao existe mais JavaScript dentro de atributo pra quebrar.

   Delegado no `document` de proposito: o admin navega por PJAX e script inline NAO roda
   ao trocar de aba. Um listener so, registrado no carregamento inteiro da pagina, funciona
   pra qualquer botao que apareca depois.
   ========================================================================== */
(function () {
    function avisa(btn, texto) {
        const antes = btn.dataset.rotuloOriginal || btn.textContent;
        btn.dataset.rotuloOriginal = antes;
        btn.textContent = texto;
        setTimeout(() => { btn.textContent = btn.dataset.rotuloOriginal; }, 1500);
    }

    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('[data-copiar]');
        if (!btn) return;
        ev.preventDefault();
        const valor = btn.getAttribute('data-copiar') || '';
        if (!valor) return;

        // `navigator.clipboard` só existe em contexto seguro (HTTPS ou localhost). Em HTTP
        // ele é `undefined` e o botão morria calado; o fallback mantém o copiar funcionando.
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(valor)
                .then(() => avisa(btn, '✓ Copiado!'))
                .catch(() => avisa(btn, 'Nao deu, copie a mao'));
            return;
        }
        try {
            const ta = document.createElement('textarea');
            ta.value = valor;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            avisa(btn, '✓ Copiado!');
        } catch (e) {
            avisa(btn, 'Nao deu, copie a mao');
        }
    });
})();
