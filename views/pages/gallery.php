<?php /** @var array $config; @var array $items */ ?>
<?php $title = 'Galeria'; ?>
<?php \App\View::extend('layouts.main'); ?>
<?php \App\View::section('content'); ?>

<section class="page-section">
    <div class="container">

        <div class="page-header">
            <span class="page-tag">// <?= e(__('gallery.kicker')) ?></span>
            <h1 class="page-title"><?= e(__('gallery.title')) ?></h1>
            <p class="page-subtitle"><?= e(__('gallery.subtitle')) ?></p>
        </div>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <span class="empty-icon" style="font-size: 4rem; opacity: 0.4;">📷</span>
                <p><?= e(__('gallery.empty')) ?></p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($items as $it): ?>
                    <a href="<?= asset('img/gallery/' . $it['filename']) ?>" class="gallery-item"
                       data-caption="<?= e($it['caption'] ?? '') ?>">
                        <img src="<?= asset('img/gallery/' . $it['filename']) ?>"
                             alt="<?= e($it['caption'] ?? 'Screenshot') ?>" loading="lazy">
                        <?php if (!empty($it['caption'])): ?>
                            <span class="gallery-caption"><?= e($it['caption']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<div id="gallery-lightbox" class="gallery-lightbox" hidden>
    <button class="gallery-lightbox-close" aria-label="Fechar (Esc)" title="Fechar (Esc)">×</button>
    <button class="gallery-lightbox-nav prev" aria-label="Anterior (←)" title="Anterior (←)">‹</button>
    <button class="gallery-lightbox-nav next" aria-label="Próxima (→)" title="Próxima (→)">›</button>
    <img id="gallery-lightbox-img" src="" alt="Imagem ampliada da galeria">
    <div class="gallery-lightbox-foot">
        <div id="gallery-lightbox-cap" class="gallery-lightbox-cap"></div>
        <div id="gallery-lightbox-count" class="gallery-lightbox-count"></div>
    </div>
</div>

<style>
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-top: 2rem;
}
.gallery-item {
    position: relative;
    aspect-ratio: 4/3;
    overflow: hidden;
    background: #000;
    border: 1px solid var(--border);
    display: block;
    cursor: zoom-in;
    transition: transform 0.2s, border-color 0.2s;
}
.gallery-item:hover { border-color: var(--hazard); transform: translateY(-2px); }
.gallery-item img {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
    transition: transform 0.4s;
}
.gallery-item:hover img { transform: scale(1.05); }
.gallery-caption {
    position: absolute; left: 0; right: 0; bottom: 0;
    padding: 0.7rem 0.9rem;
    background: linear-gradient(to top, rgba(0,0,0,0.85), transparent);
    color: var(--bone);
    font-family: var(--font-mono);
    font-size: 0.82rem;
}
.gallery-lightbox {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.92);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
    padding: 2rem;
    cursor: zoom-out;
}
/* [hidden] precisa override pq display:flex acima quebra o default do browser */
.gallery-lightbox[hidden] { display: none !important; }
.gallery-lightbox img {
    max-width: calc(100% - 120px); max-height: 82vh;
    object-fit: contain;
    border: 1px solid var(--border);
    transition: opacity 0.15s;
}
.gallery-lightbox img.loading { opacity: 0; }
.gallery-lightbox-foot {
    position: absolute; bottom: 1.2rem; left: 0; right: 0;
    display: flex; align-items: center; justify-content: center;
    gap: 1.5rem;
    color: var(--bone);
    font-family: var(--font-mono, monospace);
    padding: 0 4rem;
}
.gallery-lightbox-cap {
    text-align: center;
    font-size: 0.95rem;
}
.gallery-lightbox-count {
    color: var(--dim);
    font-size: 0.8rem;
    letter-spacing: 0.1em;
    white-space: nowrap;
}
.gallery-lightbox-close {
    position: absolute; top: 1.2rem; right: 1.5rem;
    background: rgba(0,0,0,0.45);
    border: 1px solid var(--border);
    color: var(--bone); font-size: 1.8rem;
    width: 44px; height: 44px;
    cursor: pointer; line-height: 1;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, border-color .15s, transform .1s;
    z-index: 2;
}
.gallery-lightbox-close:hover {
    background: var(--rust);
    border-color: var(--rust);
}
.gallery-lightbox-nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(0,0,0,0.5);
    border: 1px solid var(--border);
    color: var(--bone); font-size: 2.5rem;
    width: 52px; height: 72px;
    cursor: pointer; line-height: 1;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, border-color .15s, transform .1s;
    z-index: 2;
    font-family: system-ui, sans-serif;
}
.gallery-lightbox-nav:hover {
    background: var(--hazard);
    border-color: var(--hazard);
    color: var(--bg-0);
}
.gallery-lightbox-nav:active { transform: translateY(-50%) scale(0.95); }
.gallery-lightbox-nav.prev { left: 1.5rem; }
.gallery-lightbox-nav.next { right: 1.5rem; }
.gallery-lightbox-nav[disabled] { opacity: 0.3; cursor: not-allowed; }
@media (max-width: 640px) {
    .gallery-lightbox img { max-width: calc(100% - 20px); max-height: 75vh; }
    .gallery-lightbox-nav { width: 40px; height: 56px; font-size: 2rem; }
    .gallery-lightbox-nav.prev { left: 0.4rem; }
    .gallery-lightbox-nav.next { right: 0.4rem; }
    .gallery-lightbox-foot { padding: 0 1rem; flex-direction: column; gap: 0.4rem; }
}
.empty-state {
    text-align: center; padding: 4rem 1rem;
    color: var(--dim);
}
.empty-state .empty-icon {
    font-size: 3rem; display: block; margin-bottom: 1rem;
    color: var(--border);
}
</style>

<script>
(function(){
    const lb    = document.getElementById('gallery-lightbox');
    const img   = document.getElementById('gallery-lightbox-img');
    const cap   = document.getElementById('gallery-lightbox-cap');
    const count = document.getElementById('gallery-lightbox-count');
    const btnPrev = lb.querySelector('.gallery-lightbox-nav.prev');
    const btnNext = lb.querySelector('.gallery-lightbox-nav.next');
    const btnClose = lb.querySelector('.gallery-lightbox-close');
    const items = Array.from(document.querySelectorAll('.gallery-item'));
    let idx = 0;

    function show(i) {
        if (i < 0 || i >= items.length) return;
        idx = i;
        const a = items[i];
        img.classList.add('loading');
        const newImg = new Image();
        newImg.onload = () => { img.src = a.href; img.classList.remove('loading'); };
        newImg.onerror = () => img.classList.remove('loading');
        newImg.src = a.href;
        cap.textContent = a.dataset.caption || '';
        count.textContent = (i + 1) + ' / ' + items.length;
        btnPrev.disabled = (i === 0);
        btnNext.disabled = (i === items.length - 1);
    }
    function open(i) { show(i); lb.hidden = false; document.body.style.overflow = 'hidden'; }
    function close() { lb.hidden = true; img.src = ''; document.body.style.overflow = ''; }
    function next() { if (idx < items.length - 1) show(idx + 1); }
    function prev() { if (idx > 0) show(idx - 1); }

    items.forEach((a, i) => a.addEventListener('click', e => { e.preventDefault(); open(i); }));
    btnClose.addEventListener('click', e => { e.stopPropagation(); close(); });
    btnPrev.addEventListener('click',  e => { e.stopPropagation(); prev();  });
    btnNext.addEventListener('click',  e => { e.stopPropagation(); next();  });
    // Click no fundo (não na imagem nem nos controles) fecha
    lb.addEventListener('click', e => { if (e.target === lb) close(); });

    document.addEventListener('keydown', e => {
        if (lb.hidden) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowRight') next();
        else if (e.key === 'ArrowLeft')  prev();
    });

    // Swipe touch (mobile)
    let touchX = null;
    lb.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend',   e => {
        if (touchX === null) return;
        const dx = e.changedTouches[0].clientX - touchX;
        if (Math.abs(dx) > 50) { dx < 0 ? next() : prev(); }
        touchX = null;
    });
})();
</script>

<?php \App\View::endSection(); ?>
