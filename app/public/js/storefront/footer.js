(function () {
    const duration = 260;
    const easing = 'cubic-bezier(0.16, 1, 0.3, 1)';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function clearAnimationState(item) {
        item.classList.remove('is-closing');
        item.style.height = '';
        item.style.overflow = '';
        item.dataset.animating = '';
    }

    function cancelCurrentAnimation(item) {
        if (item._footerFaqAnimation) {
            item._footerFaqAnimation.cancel();
            item._footerFaqAnimation = null;
        }
    }

    function summaryHeight(item) {
        const summary = item.querySelector('.site-footer__faq-question');

        return summary ? summary.offsetHeight : 0;
    }

    function closeItem(item, animated) {
        if (!item.open) {
            return;
        }

        cancelCurrentAnimation(item);

        if (!animated || reduceMotion.matches) {
            item.open = false;
            clearAnimationState(item);
            return;
        }

        const startHeight = item.offsetHeight;
        const endHeight = summaryHeight(item);
        item.classList.add('is-closing');
        item.style.height = startHeight + 'px';
        item.style.overflow = 'hidden';
        item.dataset.animating = 'closing';

        item._footerFaqAnimation = item.animate([
            { height: startHeight + 'px' },
            { height: endHeight + 'px' },
        ], { duration, easing });

        item._footerFaqAnimation.onfinish = function () {
            item.open = false;
            item._footerFaqAnimation = null;
            clearAnimationState(item);
        };
        item._footerFaqAnimation.oncancel = function () {
            item._footerFaqAnimation = null;
        };
    }

    function openItem(item) {
        const list = item.closest('.site-footer__faq-list');
        if (!list) {
            return;
        }

        list.querySelectorAll('.site-footer__faq-item[open]').forEach(function (openItem) {
            if (openItem !== item) {
                closeItem(openItem, true);
            }
        });

        cancelCurrentAnimation(item);
        item.classList.remove('is-closing');

        if (reduceMotion.matches) {
            item.open = true;
            clearAnimationState(item);
            return;
        }

        const startHeight = summaryHeight(item);
        item.style.height = startHeight + 'px';
        item.style.overflow = 'hidden';
        item.open = true;
        const endHeight = item.scrollHeight;
        item.dataset.animating = 'opening';

        item._footerFaqAnimation = item.animate([
            { height: startHeight + 'px' },
            { height: endHeight + 'px' },
        ], { duration, easing });

        item._footerFaqAnimation.onfinish = function () {
            item._footerFaqAnimation = null;
            clearAnimationState(item);
        };
        item._footerFaqAnimation.oncancel = function () {
            item._footerFaqAnimation = null;
        };
    }

    document.addEventListener('click', function (event) {
        const summary = event.target.closest('.site-footer__faq-question');
        if (!summary) {
            return;
        }

        const item = summary.closest('.site-footer__faq-item');
        if (!(item instanceof HTMLDetailsElement)) {
            return;
        }

        event.preventDefault();
        if (item.open) {
            closeItem(item, true);
        } else {
            openItem(item);
        }
    });

    document.querySelectorAll('[data-faq-search]').forEach(function (form) {
        const input = form.querySelector('.site-footer__faq-search-input');
        const clearBtn = form.querySelector('[data-faq-search-clear]');
        const section = form.closest('.site-footer__faq');
        const list = section ? section.querySelector('[data-faq-list]') : null;
        const empty = section ? section.querySelector('[data-faq-empty]') : null;
        if (!(input instanceof HTMLInputElement) || !(clearBtn instanceof HTMLButtonElement) || !list) {
            return;
        }

        const items = Array.prototype.slice.call(list.querySelectorAll('[data-faq-item]'));

        function focusWithoutScroll(el) {
            const y = window.scrollY || window.pageYOffset || 0;
            const x = window.scrollX || window.pageXOffset || 0;
            try {
                el.focus({ preventScroll: true });
            } catch (focusErr) {
                el.focus();
            }
            window.scrollTo(x, y);
        }

        function keepFaqSearchViewportPinned(run) {
            const beforeTop = input.getBoundingClientRect().top;

            run();

            function restore() {
                const afterTop = input.getBoundingClientRect().top;
                const delta = afterTop - beforeTop;
                if (Math.abs(delta) > 0.5) {
                    window.scrollBy(0, delta);
                }
            }

            restore();
            requestAnimationFrame(restore);
            requestAnimationFrame(function () {
                requestAnimationFrame(restore);
            });
        }

        // Клік/тап у FAQ-пошук не повинен «телепортувати» сторінку через scroll-padding sticky-хедера.
        form.addEventListener('mousedown', function (event) {
            if (event.target !== input && !input.contains(event.target)) {
                return;
            }
            if (document.activeElement === input) {
                return;
            }
            event.preventDefault();
            focusWithoutScroll(input);
        });
        form.addEventListener('touchstart', function (event) {
            if (event.target !== input && !input.contains(event.target)) {
                return;
            }
            if (document.activeElement === input) {
                return;
            }
            focusWithoutScroll(input);
        }, { passive: true });

        function normalize(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();
        }

        function tokens(value) {
            return normalize(value).split(' ').filter(Boolean);
        }

        function matches(item, queryTokens) {
            if (queryTokens.length === 0) {
                return true;
            }

            const haystack = normalize(item.getAttribute('data-faq-search-text') || '');
            return queryTokens.every(function (token) {
                return haystack.indexOf(token) !== -1;
            });
        }

        function applyFilter() {
            keepFaqSearchViewportPinned(function () {
                const query = input.value.trim();
                if (query !== '') {
                    if (!list.style.minHeight) {
                        list.style.minHeight = list.offsetHeight + 'px';
                    }
                } else {
                    list.style.minHeight = '';
                }

                const queryTokens = tokens(input.value);
                let visibleCount = 0;

                items.forEach(function (item) {
                    const show = matches(item, queryTokens);
                    item.hidden = !show;
                    if (!show && item.open) {
                        item.open = false;
                        clearAnimationState(item);
                    }
                    if (show) {
                        visibleCount += 1;
                    }
                });

                clearBtn.hidden = query === '';
                if (empty) {
                    empty.hidden = visibleCount > 0 || queryTokens.length === 0;
                }
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            applyFilter();
        });

        input.addEventListener('input', applyFilter);
        clearBtn.addEventListener('click', function () {
            input.value = '';
            applyFilter();
            focusWithoutScroll(input);
        });

        applyFilter();
    });
})();
