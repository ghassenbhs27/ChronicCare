import './bootstrap.js';
import '@hotwired/turbo';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// Turbo Drive: all navigations become AJAX (no full page reload)

// Progress cursor during Turbo navigations
document.addEventListener('turbo:before-fetch-request', () => {
    document.body.style.cursor = 'progress';
});
document.addEventListener('turbo:before-render', () => {
    document.body.style.cursor = 'default';
});

// Re-initialize all vendor plugins after every Turbo navigation
document.addEventListener('turbo:load', () => {
    // AOS (Animate On Scroll)
    if (typeof AOS !== 'undefined') {
        AOS.init({ duration: 600, easing: 'ease-in-out', once: true, mirror: false });
    }

    // PureCounter
    if (typeof PureCounter !== 'undefined') {
        new PureCounter();
    }

    // Swiper sliders
    if (typeof Swiper !== 'undefined') {
        document.querySelectorAll('.init-swiper').forEach(function (el) {
            const cfg = el.querySelector('.swiper-config');
            if (cfg) {
                new Swiper(el, JSON.parse(cfg.innerHTML.trim()));
            }
        });
    }

    // GLightbox
    if (typeof GLightbox !== 'undefined') {
        GLightbox({ selector: '.glightbox' });
    }

    // Isotope layout
    if (typeof Isotope !== 'undefined' && typeof imagesLoaded !== 'undefined') {
        document.querySelectorAll('.isotope-layout').forEach(function (el) {
            const layout = el.getAttribute('data-layout') ?? 'masonry';
            const filter = el.getAttribute('data-default-filter') ?? '*';
            const sort   = el.getAttribute('data-sort') ?? 'original-order';
            imagesLoaded(el.querySelector('.isotope-container'), function () {
                const iso = new Isotope(el.querySelector('.isotope-container'), {
                    itemSelector: '.isotope-item', layoutMode: layout, filter: filter, sortBy: sort
                });
                el.querySelectorAll('.isotope-filters li').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        el.querySelector('.isotope-filters .filter-active')?.classList.remove('filter-active');
                        this.classList.add('filter-active');
                        iso.arrange({ filter: this.getAttribute('data-filter') });
                    });
                });
            });
        });
    }

    // Scroll-top button
    const scrollTop = document.querySelector('.scroll-top');
    if (scrollTop) {
        window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
    }
});
