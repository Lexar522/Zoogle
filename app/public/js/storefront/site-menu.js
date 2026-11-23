/**
 * Бургер-меню (телефон) і кнопка контактів у правому нижньому куті.
 */
(function () {
    'use strict';

    var DESKTOP_QUERY = '(min-width: 769px)';
    var ANIMATION_MS = 280;

    function setupMobileMenu() {
        var menu = document.querySelector('[data-mobile-menu]');
        if (!menu) return;

        var panel = menu.querySelector('.mobile-menu__panel');
        var openers = Array.prototype.slice.call(document.querySelectorAll('[data-mobile-menu-open]'));
        var closers = Array.prototype.slice.call(menu.querySelectorAll('[data-mobile-menu-close]'));
        var lastFocus = null;
        var hideTimer = null;

        function setExpanded(value) {
            openers.forEach(function (btn) { btn.setAttribute('aria-expanded', value ? 'true' : 'false'); });
        }

        function open() {
            window.clearTimeout(hideTimer);
            lastFocus = document.activeElement;
            menu.hidden = false;
            document.documentElement.classList.add('mobile-menu-open');
            setExpanded(true);
            window.requestAnimationFrame(function () {
                menu.classList.add('is-open');
                panel.focus({ preventScroll: true });
            });
        }

        function close() {
            if (menu.hidden) return;
            menu.classList.remove('is-open');
            document.documentElement.classList.remove('mobile-menu-open');
            setExpanded(false);
            hideTimer = window.setTimeout(function () { menu.hidden = true; }, ANIMATION_MS);
            if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus({ preventScroll: true });
        }

        openers.forEach(function (btn) { btn.addEventListener('click', open); });
        closers.forEach(function (btn) { btn.addEventListener('click', close); });
        menu.addEventListener('click', function (event) {
            if (event.target.closest('a[href]')) close();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') close();
        });

        var desktop = window.matchMedia(DESKTOP_QUERY);
        var onChange = function (event) { if (event.matches) close(); };
        if (desktop.addEventListener) desktop.addEventListener('change', onChange);
    }

    function setupFloatingContacts() {
        var root = document.querySelector('[data-floating-contacts]');
        if (!root) return;

        var toggle = root.querySelector('[data-floating-contacts-toggle]');
        var panel = root.querySelector('[data-floating-contacts-panel]');

        function setOpen(value) {
            panel.hidden = !value;
            root.classList.toggle('is-open', value);
            toggle.setAttribute('aria-expanded', value ? 'true' : 'false');
            toggle.setAttribute('aria-label', value ? toggle.dataset.labelClose : toggle.dataset.labelOpen);
        }

        toggle.addEventListener('click', function () { setOpen(panel.hidden); });
        document.addEventListener('click', function (event) {
            if (!panel.hidden && !root.contains(event.target)) setOpen(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) {
                setOpen(false);
                toggle.focus();
            }
        });
    }

    function init() {
        setupMobileMenu();
        setupFloatingContacts();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
