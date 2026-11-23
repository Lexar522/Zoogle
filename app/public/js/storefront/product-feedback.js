(function () {
    'use strict';

    function setup(root) {
        var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-feedback-tab]'));
        if (!tabs.length) {
            return;
        }

        function activate(name, focus) {
            tabs.forEach(function (tab) {
                var selected = tab.getAttribute('data-feedback-tab') === name;
                var panel = document.getElementById(tab.getAttribute('aria-controls'));
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
                if (panel) {
                    panel.hidden = !selected;
                }
                if (selected && focus) {
                    tab.focus();
                }
            });
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                var name = tab.getAttribute('data-feedback-tab');
                activate(name, false);
                try { history.replaceState(history.state, '', '#' + name); } catch (e) { /* ignore */ }
            });
            // Стрілки ←/→ між вкладками (як у стандартних вкладках)
            tab.addEventListener('keydown', function (event) {
                var step = event.key === 'ArrowRight' ? 1 : (event.key === 'ArrowLeft' ? -1 : 0);
                if (!step) {
                    return;
                }
                event.preventDefault();
                var next = tabs[(index + step + tabs.length) % tabs.length];
                activate(next.getAttribute('data-feedback-tab'), true);
            });
        });

        // Посилання з адмінки чи листа: /catalog/…#questions відкриває потрібну вкладку
        function fromHash() {
            var name = (window.location.hash || '').replace('#', '');
            if (name === 'reviews' || name === 'questions') {
                activate(name, false);
                var panel = document.getElementById(name);
                if (panel) {
                    window.requestAnimationFrame(function () {
                        root.scrollIntoView({ block: 'start' });
                    });
                }
            }
        }
        fromHash();
        window.addEventListener('hashchange', fromHash);
    }

    function init() {
        document.querySelectorAll('[data-feedback]').forEach(setup);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
