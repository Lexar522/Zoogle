(function () {
    'use strict';

    var TOAST_MS = 2000;

    function showToast(button, text) {
        var toast = button.querySelector('[data-product-share-toast]');
        if (!toast) {
            return;
        }
        toast.textContent = text;
        toast.hidden = false;
        window.clearTimeout(button._shareToastTimer);
        button._shareToastTimer = window.setTimeout(function () {
            toast.hidden = true;
        }, TOAST_MS);
    }

    function copyLink(button, url) {
        var done = function () { showToast(button, button.getAttribute('data-share-copied') || ''); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done, function () { window.prompt('', url); });
            return;
        }
        window.prompt('', url);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-product-share]');
        if (!button) {
            return;
        }
        var url = button.getAttribute('data-share-url') || window.location.href;
        var title = button.getAttribute('data-share-title') || document.title;

        // Телефон: системне меню «Поділитися» (Viber, Telegram, Instagram…); комп'ютер — копіюємо посилання
        if (navigator.share) {
            navigator.share({ title: title, url: url }).catch(function (err) {
                if (!err || err.name !== 'AbortError') {
                    copyLink(button, url);
                }
            });
            return;
        }
        copyLink(button, url);
    });
})();
