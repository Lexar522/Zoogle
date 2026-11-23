/**
 * Хіти / рекомендовані на телефоні: стрілки й крапки під сторінками по 4 товари (свайп робить CSS scroll-snap).
 */
(function () {
    'use strict';

    function setup(root) {
        var viewport = root.querySelector('.home-product-carousel__viewport');
        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-page-dot]'));
        var pages = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-page]'));
        if (!viewport || dots.length < 2 || pages.length !== dots.length) return;

        var prev = root.querySelector('[data-carousel-page-prev]');
        var next = root.querySelector('[data-carousel-page-next]');

        function setActive(index) {
            dots.forEach(function (dot, i) {
                if (i === index) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
            });
            if (prev) prev.disabled = index <= 0;
            if (next) next.disabled = index >= pages.length - 1;
        }

        function goTo(index) {
            var target = Math.max(0, Math.min(pages.length - 1, index));
            viewport.scrollTo({ left: pages[target].offsetLeft - pages[0].offsetLeft, behavior: 'smooth' });
        }

        function currentIndex() {
            var width = viewport.clientWidth || 1;
            return Math.max(0, Math.min(pages.length - 1, Math.round(viewport.scrollLeft / width)));
        }

        var ticking = false;
        viewport.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                setActive(currentIndex());
                ticking = false;
            });
        }, { passive: true });

        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { goTo(i); });
        });
        if (prev) prev.addEventListener('click', function () { goTo(currentIndex() - 1); });
        if (next) next.addEventListener('click', function () { goTo(currentIndex() + 1); });
    }

    function init() {
        document.querySelectorAll('[data-carousel-dots]').forEach(function (dotsEl) {
            setup(dotsEl.closest('.home-product-carousel'));
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
