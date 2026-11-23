(function () {
    'use strict';

    /** Згортаємо лише тоді, коли прихована частина помітна — інакше кнопка заради пари рядків. */
    var MIN_HIDDEN_PX = 120;

    function toggleFor(box) {
        return box.parentElement ? box.parentElement.querySelector('[data-desc-toggle]') : null;
    }

    /** Вмикає згортання, якщо опис достатньо довгий. Повертає true, коли згорнуто. */
    function measure(box) {
        var toggle = toggleFor(box);
        if (!toggle || box.classList.contains('is-expanded')) {
            return true;
        }
        box.classList.add('is-collapsible');
        var tooShort = box.scrollHeight - box.clientHeight < MIN_HIDDEN_PX;
        box.classList.toggle('is-collapsible', !tooShort);
        toggle.hidden = tooShort;

        return !tooShort;
    }

    function setup(box) {
        var toggle = toggleFor(box);
        if (!toggle || box.dataset.descReady === '1') {
            return;
        }
        box.dataset.descReady = '1';

        toggle.addEventListener('click', function () {
            var expanded = box.classList.toggle('is-expanded');
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.textContent = toggle.getAttribute(expanded ? 'data-label-less' : 'data-label-more');
            if (!expanded) {
                var top = box.getBoundingClientRect().top + window.scrollY - 120;
                if (window.scrollY > top) {
                    window.scrollTo({ top: top, behavior: 'smooth' });
                }
            }
        });

        // Фото й шрифти в описі довантажуються пізніше — перевіряємо висоту ще раз після завантаження
        if (!measure(box) && document.readyState !== 'complete') {
            window.addEventListener('load', function () { measure(box); }, { once: true });
        }
    }

    function init() {
        document.querySelectorAll('[data-desc-collapse]').forEach(setup);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
