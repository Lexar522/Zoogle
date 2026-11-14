(function () {
    var drawer = document.querySelector('[data-cart-drawer]');
    if (!drawer) return;

    var panel = drawer.querySelector('.cart-drawer__panel');
    var content = drawer.querySelector('[data-cart-drawer-content]');
    var pageContent = document.querySelector('[data-cart-page-content]');
    var toastStack = document.querySelector('[data-cart-toast-stack]');
    var openButtons = Array.prototype.slice.call(document.querySelectorAll('[data-cart-open]'));
    var closeButtons = Array.prototype.slice.call(document.querySelectorAll('[data-cart-close]'));
    var panelCountEls = Array.prototype.slice.call(document.querySelectorAll('[data-cart-panel-count]'));
    var badgeEls = Array.prototype.slice.call(document.querySelectorAll('[data-cart-badge]'));
    var lastActiveElement = null;
    var closeTimer = null;
    var toastHideTimer = null;

    function getFocusableElements() {
        return Array.prototype.slice.call(
            drawer.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
        ).filter(function (el) {
            return !el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true';
        });
    }

    function setExpanded(isOpen) {
        openButtons.forEach(function (btn) {
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function syncSummary(summary) {
        var itemsCount = summary && typeof summary.items_count === 'number' ? summary.items_count : 0;

        var prevBadgeCount = null;
        if (badgeEls.length) {
            var parsed = parseInt(badgeEls[0].textContent || '0', 10);
            prevBadgeCount = isNaN(parsed) ? 0 : parsed;
        }

        panelCountEls.forEach(function (el) {
            el.textContent = String(itemsCount);
        });

        badgeEls.forEach(function (badge) {
            badge.textContent = String(itemsCount);
        });

        if (prevBadgeCount !== null && prevBadgeCount !== itemsCount) {
            badgeEls.forEach(function (badge) {
                pulseNumberClass(badge, 'cart-pill__count-num--pulse');
            });
        }

        openButtons.forEach(function (btn) {
            btn.classList.toggle('cart-pill--has-items', itemsCount > 0);
        });
    }

    function currentRenderedSummaryTotal() {
        var totalEl = (content && content.querySelector('[data-cart-summary-total]'))
            || (pageContent && pageContent.querySelector('[data-cart-summary-total]'));
        if (!(totalEl instanceof HTMLElement)) {
            return null;
        }

        var raw = parseFloat(totalEl.getAttribute('data-cart-summary-total') || '');

        return isNaN(raw) ? null : raw;
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('uk-UA', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + ' ₴';
    }

    function animateNumericValue(from, to, duration, onFrame, onDone) {
        if (typeof onFrame !== 'function') return;

        var start = null;
        var diff = to - from;

        if (Math.abs(diff) < 0.009) {
            onFrame(to);
            if (typeof onDone === 'function') onDone();
            return;
        }

        function tick(ts) {
            if (start === null) start = ts;
            var progress = Math.min(1, (ts - start) / duration);
            var eased = 1 - Math.pow(1 - progress, 3);
            onFrame(from + diff * eased);

            if (progress < 1) {
                requestAnimationFrame(tick);
            } else if (typeof onDone === 'function') {
                onDone();
            }
        }

        requestAnimationFrame(tick);
    }

    function pulseNumberClass(el, className) {
        if (!(el instanceof HTMLElement)) return;
        el.classList.remove(className);
        void el.offsetWidth;
        el.classList.add(className);
    }

    function captureCartVisualState() {
        var state = {
            summaryTotal: currentRenderedSummaryTotal(),
            lines: {},
        };

        document.querySelectorAll('[data-cart-line-key]').forEach(function (lineEl) {
            if (!(lineEl instanceof HTMLElement)) return;
            var key = lineEl.getAttribute('data-cart-line-key');
            if (!key) return;

            var qtyInput = lineEl.querySelector('[data-cart-qty-input]');
            var totalEl = lineEl.querySelector('[data-cart-line-total]');
            var qty = qtyInput instanceof HTMLInputElement ? parseInt(qtyInput.value || '0', 10) : NaN;
            var total = totalEl instanceof HTMLElement ? parseFloat(totalEl.getAttribute('data-cart-line-total') || '') : NaN;

            state.lines[key] = {
                qty: isNaN(qty) ? null : qty,
                lineTotal: isNaN(total) ? null : total,
            };
        });

        return state;
    }

    function animateCartState(prevState, summary) {
        var nextSummary = summary && typeof summary.total === 'number' ? summary.total : null;
        if (prevState && prevState.summaryTotal !== null && nextSummary !== null) {
            document.querySelectorAll('[data-cart-summary-total]').forEach(function (el) {
                if (!(el instanceof HTMLElement)) return;
                animateNumericValue(prevState.summaryTotal, nextSummary, 420, function (value) {
                    el.textContent = formatMoney(value);
                }, function () {
                    el.textContent = formatMoney(nextSummary);
                });
                pulseNumberClass(el, 'cart-drawer__summary-value--pulse');
            });
        }

        document.querySelectorAll('[data-cart-line-key]').forEach(function (lineEl) {
            if (!(lineEl instanceof HTMLElement)) return;
            var key = lineEl.getAttribute('data-cart-line-key');
            if (!key || !prevState || !prevState.lines[key]) return;

            var prevLine = prevState.lines[key];
            var qtyInput = lineEl.querySelector('[data-cart-qty-input]');
            if (qtyInput instanceof HTMLInputElement) {
                var nextQty = parseInt(qtyInput.value || '0', 10);
                if (prevLine.qty !== null && !isNaN(nextQty) && prevLine.qty !== nextQty) {
                    animateNumericValue(prevLine.qty, nextQty, 260, function (value) {
                        qtyInput.value = String(Math.round(value));
                    }, function () {
                        qtyInput.value = String(nextQty);
                    });
                    pulseNumberClass(qtyInput, 'cart-drawer__qty-input--pulse');
                }
            }

            var totalEl = lineEl.querySelector('[data-cart-line-total]');
            var nextLineTotal = totalEl instanceof HTMLElement ? parseFloat(totalEl.getAttribute('data-cart-line-total') || '') : NaN;
            if (totalEl instanceof HTMLElement && prevLine.lineTotal !== null && !isNaN(nextLineTotal) && Math.abs(prevLine.lineTotal - nextLineTotal) > 0.009) {
                animateNumericValue(prevLine.lineTotal, nextLineTotal, 420, function (value) {
                    totalEl.textContent = formatMoney(value);
                }, function () {
                    totalEl.textContent = formatMoney(nextLineTotal);
                });
                pulseNumberClass(totalEl, 'cart-drawer__line-total--pulse');
            }
        });
    }

    function showCartToast(message, type) {
        if (!toastStack || typeof message !== 'string' || message.trim() === '') {
            return;
        }

        if (toastHideTimer) {
            window.clearTimeout(toastHideTimer);
            toastHideTimer = null;
        }

        toastStack.innerHTML = '';

        var toast = document.createElement('div');
        toast.className = 'cart-toast cart-toast--' + (type === 'error' ? 'error' : 'success');
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.textContent = message;
        toastStack.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        toastHideTimer = window.setTimeout(function () {
            toast.classList.remove('is-visible');
            window.setTimeout(function () {
                if (toast.parentNode === toastStack) {
                    toast.remove();
                }
            }, 400);
            toastHideTimer = null;
        }, 2200);
    }

    function replaceContent(html, summary) {
        var prevState = captureCartVisualState();

        if (typeof html === 'string') {
            if (content) {
                content.innerHTML = html;
            }
            if (pageContent) {
                pageContent.innerHTML = html;
            }
        }

        if (summary) {
            syncSummary(summary);
            requestAnimationFrame(function () {
                animateCartState(prevState, summary);
            });
        }
    }

    function setFormBusy(form, isBusy) {
        form.dataset.cartBusy = isBusy ? '1' : '0';
        form.setAttribute('aria-busy', isBusy ? 'true' : 'false');

        Array.prototype.slice.call(form.querySelectorAll('button, input')).forEach(function (el) {
            if (el instanceof HTMLButtonElement || (el instanceof HTMLInputElement && el.type !== 'hidden')) {
                el.disabled = isBusy;
            }
        });
    }

    function defaultErrorMessage(payload) {
        if (payload && typeof payload.statusMessage === 'string' && payload.statusMessage !== '') {
            return payload.statusMessage;
        }

        if (payload && typeof payload.message === 'string' && payload.message !== '') {
            return payload.message;
        }

        if (payload && payload.errors) {
            var firstField = Object.keys(payload.errors)[0];
            if (firstField && Array.isArray(payload.errors[firstField]) && payload.errors[firstField][0]) {
                return String(payload.errors[firstField][0]);
            }
        }

        return 'Не вдалося оновити кошик.';
    }

    function applyPayload(payload) {
        if (!payload) return;
        replaceContent(payload.html || '', payload.summary || null);
        if (payload.statusMessage) {
            showCartToast(payload.statusMessage, payload.statusType || 'success');
        }
    }

    function submitCartForm(form) {
        if (!(form instanceof HTMLFormElement) || form.dataset.cartBusy === '1') {
            return Promise.resolve(null);
        }

        var formData = new FormData(form);
        setFormBusy(form, true);

        return fetch(form.action, {
            method: (form.getAttribute('method') || 'POST').toUpperCase(),
            body: formData,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Cart-Fragment': '1',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var payload = null;

                    try {
                        payload = text ? JSON.parse(text) : null;
                    } catch (error) {
                        payload = null;
                    }

                    if (!response.ok) {
                        throw payload || { message: response.statusText || 'Request failed.' };
                    }

                    return payload;
                });
            })
            .finally(function () {
                setFormBusy(form, false);
            });
    }

    function cleanupClose() {
        if (closeTimer) {
            window.clearTimeout(closeTimer);
            closeTimer = null;
        }
    }

    function openDrawer() {
        cleanupClose();
        lastActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        drawer.classList.remove('is-open');
        drawer.hidden = false;
        /* Два rAF: перший кадр з панеллю за межами екрана, другий — is-open, щоб спрацював CSS transition */
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                drawer.classList.add('is-open');
                drawer.setAttribute('aria-hidden', 'false');
                document.body.classList.add('cart-drawer-open');
                setExpanded(true);
                var focusTarget = drawer.querySelector('[data-cart-close]');
                if (focusTarget instanceof HTMLElement) {
                    focusTarget.focus();
                } else if (panel instanceof HTMLElement) {
                    panel.focus();
                }
            });
        });
    }

    function closeDrawer() {
        cleanupClose();
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('cart-drawer-open');
        setExpanded(false);

        closeTimer = window.setTimeout(function () {
            drawer.hidden = true;
        }, 300);

        if (lastActiveElement instanceof HTMLElement) {
            lastActiveElement.focus();
        }
    }

    openButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            openDrawer();
        });
    });

    closeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeDrawer();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (!drawer.hidden && event.key === 'Tab') {
            var focusable = getFocusableElements();
            if (focusable.length) {
                var first = focusable[0];
                var last = focusable[focusable.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                    return;
                }

                if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                    return;
                }
            }
        }

        if (event.key === 'Escape' && !drawer.hidden) {
            closeDrawer();
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target instanceof Element
            ? event.target.closest('[data-cart-add-form], [data-cart-update-form], [data-cart-remove-form]')
            : null;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        event.preventDefault();

        submitCartForm(form)
            .then(function (payload) {
                if (!payload) return;
                applyPayload(payload);
            })
            .catch(function (payload) {
                if (payload && payload.html) {
                    applyPayload(payload);
                    if (payload.statusMessage) {
                        return;
                    }
                    if (!drawer.hidden) {
                        return;
                    }
                }

                window.alert(defaultErrorMessage(payload));
            });
    });

    document.addEventListener('click', function (event) {
        var stepBtn = event.target instanceof Element ? event.target.closest('[data-cart-qty-step]') : null;
        if (!(stepBtn instanceof HTMLButtonElement)) {
            return;
        }

        var form = stepBtn.closest('[data-cart-update-form]');
        var input = form ? form.querySelector('[data-cart-qty-input]') : null;
        if (!(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement)) {
            return;
        }

        event.preventDefault();

        var step = parseInt(stepBtn.getAttribute('data-cart-qty-step') || '0', 10);
        var current = parseInt(input.value || '1', 10);
        var next = isNaN(current) ? 1 : current + step;

        if (next < 1) next = 1;
        if (next > 999) next = 999;

        input.value = String(next);

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    });

    document.addEventListener('change', function (event) {
        var input = event.target instanceof Element ? event.target.closest('[data-cart-qty-input]') : null;
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        var form = input.closest('[data-cart-update-form]');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        var next = parseInt(input.value || '1', 10);
        if (isNaN(next) || next < 1) next = 1;
        if (next > 999) next = 999;
        input.value = String(next);

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    });

    window.ZoogleCartDrawer = {
        open: openDrawer,
        close: closeDrawer,
        replaceContent: replaceContent,
        syncSummary: syncSummary,
        submitForm: submitCartForm,
    };
})();

