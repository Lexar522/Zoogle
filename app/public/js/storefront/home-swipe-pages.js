/**
 * Сторінки товарів зі стрілками й точками.
 * Телефон: свайп робить CSS scroll-snap, скрипт лише синхронізує точки.
 * Комп'ютер (лише --paged: хіти й рекомендовані): видно одну сторінку, стрілки й точки її перемикають без прокрутки.
 */
(function () {
    'use strict';

    var desktopQuery = window.matchMedia('(min-width: 769px)');

    function setup(root) {
        var viewport = root.querySelector('.home-product-carousel__viewport');
        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-page-dot]'));
        var pages = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-page]'));
        if (!viewport || dots.length < 2 || pages.length !== dots.length) return;

        var prev = root.querySelector('[data-carousel-page-prev]');
        var next = root.querySelector('[data-carousel-page-next]');
        var isPaged = root.classList.contains('home-product-carousel--paged');
        var active = 0;

        function showsOnePage() {
            return isPaged && desktopQuery.matches;
        }

        function setActive(index) {
            active = index;
            dots.forEach(function (dot, i) {
                if (i === index) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
            });
            if (prev) prev.disabled = index <= 0;
            if (next) next.disabled = index >= pages.length - 1;
        }

        function renderPages() {
            var onePage = showsOnePage();
            pages.forEach(function (page, i) {
                page.hidden = onePage && i !== active;
            });
        }

        function scrollToPage(index, behavior) {
            viewport.scrollTo({ left: pages[index].offsetLeft - pages[0].offsetLeft, behavior: behavior });
        }

        function goTo(index) {
            var target = Math.max(0, Math.min(pages.length - 1, index));
            if (showsOnePage()) {
                setActive(target);
                renderPages();
                return;
            }
            scrollToPage(target, 'smooth');
        }

        function currentIndex() {
            if (showsOnePage()) return active;
            var width = viewport.clientWidth || 1;
            return Math.max(0, Math.min(pages.length - 1, Math.round(viewport.scrollLeft / width)));
        }

        var ticking = false;
        viewport.addEventListener('scroll', function () {
            if (ticking || showsOnePage()) return;
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

        /* Телефон ↔ комп'ютер (поворот планшета, зміна вікна): лишаємось на тій самій сторінці */
        function onModeChange() {
            renderPages();
            if (!showsOnePage()) scrollToPage(active, 'auto');
            setActive(active);
        }
        if (typeof desktopQuery.addEventListener === 'function') {
            desktopQuery.addEventListener('change', onModeChange);
        } else if (typeof desktopQuery.addListener === 'function') {
            desktopQuery.addListener(onModeChange);
        }

        setActive(0);
        renderPages();
        root.setAttribute('data-pages-ready', '');
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
