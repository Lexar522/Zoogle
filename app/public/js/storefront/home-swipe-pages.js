/**
 * Сторінки товарів зі стрілками й точками.
 * Телефон: сторінка — 4 товари, свайп робить CSS scroll-snap, скрипт лише синхронізує точки.
 * Комп'ютер (лише --paged: хіти й рекомендовані): видно data-desktop-group телефонних сторінок разом (8 товарів),
 * стрілки й точки перемикають їх з анімацією, без прокрутки.
 */
(function () {
    'use strict';

    var desktopQuery = window.matchMedia('(min-width: 769px)');
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    /** Тривалості збігаються з CSS-анімаціями .is-page-out / .is-page-in у home-carousel.css */
    var PAGE_OUT_MS = 160;
    var PAGE_IN_MS = 480;

    function setup(root) {
        var viewport = root.querySelector('.home-product-carousel__viewport');
        var dotsBar = root.querySelector('[data-carousel-dots]');
        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-page-dot]'));
        var pages = Array.prototype.slice.call(root.querySelectorAll('[data-carousel-page]'));
        if (!viewport || dots.length < 2 || pages.length !== dots.length) return;

        var prev = root.querySelector('[data-carousel-page-prev]');
        var next = root.querySelector('[data-carousel-page-next]');
        var isPaged = root.classList.contains('home-product-carousel--paged');
        var group = Math.max(1, parseInt(viewport.getAttribute('data-desktop-group') || '1', 10) || 1);
        var labelTemplate = dotsBar ? dotsBar.getAttribute('data-page-label') : null;
        /** Індекс телефонної сторінки — спільна точка відліку для обох режимів */
        var phonePage = 0;
        var timers = [];

        function showsGroups() {
            return isPaged && desktopQuery.matches;
        }

        function pageCount() {
            return showsGroups() ? Math.ceil(pages.length / group) : pages.length;
        }

        /** Поточна сторінка в одиницях режиму: на комп'ютері — група, на телефоні — сама сторінка */
        function currentIndex() {
            if (showsGroups()) return Math.floor(phonePage / group);
            var width = viewport.clientWidth || 1;
            return Math.max(0, Math.min(pages.length - 1, Math.round(viewport.scrollLeft / width)));
        }

        function setActive(index) {
            var total = pageCount();
            dots.forEach(function (dot, i) {
                if (i === index) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
                if (labelTemplate) {
                    dot.setAttribute('aria-label', labelTemplate.replace(':page', String(i + 1)).replace(':total', String(total)));
                }
            });
            if (prev) prev.disabled = index <= 0;
            if (next) next.disabled = index >= total - 1;
        }

        function renderPages() {
            var grouped = showsGroups();
            var visibleGroup = Math.floor(phonePage / group);
            pages.forEach(function (page, i) {
                page.hidden = grouped && Math.floor(i / group) !== visibleGroup;
            });
        }

        function scrollToPage(index, behavior) {
            viewport.scrollTo({ left: pages[index].offsetLeft - pages[0].offsetLeft, behavior: behavior });
        }

        function finishAnimation() {
            timers.forEach(clearTimeout);
            timers = [];
            root.classList.remove('is-page-out', 'is-page-in');
        }

        function showGroup(index) {
            phonePage = index * group;
            renderPages();
            setActive(index);
        }

        function goTo(index) {
            var target = Math.max(0, Math.min(pageCount() - 1, index));
            if (!showsGroups()) {
                scrollToPage(target, 'smooth');
                return;
            }
            var from = currentIndex();
            if (target === from) return;

            finishAnimation();
            if (reducedMotion.matches) {
                showGroup(target);
                return;
            }
            /* Стара сторінка їде в бік гортання й зникає, нова в'їжджає з протилежного боку */
            root.style.setProperty('--page-dir', target > from ? '1' : '-1');
            root.classList.add('is-page-out');
            timers.push(setTimeout(function () {
                root.classList.remove('is-page-out');
                showGroup(target);
                root.classList.add('is-page-in');
                timers.push(setTimeout(finishAnimation, PAGE_IN_MS));
            }, PAGE_OUT_MS));
        }

        var ticking = false;
        viewport.addEventListener('scroll', function () {
            if (ticking || showsGroups()) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                phonePage = currentIndex();
                setActive(phonePage);
                ticking = false;
            });
        }, { passive: true });

        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { goTo(i); });
        });
        if (prev) prev.addEventListener('click', function () { goTo(currentIndex() - 1); });
        if (next) next.addEventListener('click', function () { goTo(currentIndex() + 1); });

        /* Телефон ↔ комп'ютер (поворот планшета, зміна вікна): лишаємось на тих самих товарах */
        function onModeChange() {
            finishAnimation();
            if (showsGroups()) {
                phonePage = Math.floor(phonePage / group) * group;
            }
            renderPages();
            if (!showsGroups()) scrollToPage(phonePage, 'auto');
            setActive(currentIndex());
        }
        if (typeof desktopQuery.addEventListener === 'function') {
            desktopQuery.addEventListener('change', onModeChange);
        } else if (typeof desktopQuery.addListener === 'function') {
            desktopQuery.addListener(onModeChange);
        }

        renderPages();
        setActive(0);
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
