(function () {
    var form = document.querySelector('form[data-search-suggest]');
    if (!form) return;
    var input = form.querySelector('input[name="q"]');
    var panel = document.getElementById('site-search-suggest');
    var endpoint = form.getAttribute('data-search-suggest');
    var goLabel = form.getAttribute('data-search-go') || '';
    if (!input || !panel || !endpoint) return;

    var header = document.querySelector('.site-header');
    var activeIndex = -1;
    var requestToken = 0;
    var timer = 0;
    var open = false;
    var motionToken = 0;

    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function panelCap() {
        return Math.min(Math.round(window.innerHeight * 0.7), 460);
    }

    function finishClose(token) {
        if (token !== motionToken || open) return;
        panel.classList.remove('is-closing');
        panel.classList.remove('is-settled');
        panel.hidden = true;
        panel.style.maxHeight = '';
        form.classList.remove('is-open');
        if (header) header.classList.remove('is-search-open');
    }

    var icons = {
        search: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path></svg>',
        category: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"></path></svg>',
        article: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm1 14H8v-2h7v2zm0-4H8v-2h7v2zm-2-4V3.5L18.5 9H13z"></path></svg>',
        page: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"></path></svg>',
        product: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>'
    };

    function items() {
        return Array.prototype.slice.call(panel.querySelectorAll('[data-suggest-item]'));
    }

    function setOpen(next) {
        var wasOpen = open;
        open = next;
        input.setAttribute('aria-expanded', next ? 'true' : 'false');
        motionToken += 1;
        var token = motionToken;

        if (next) {
            panel.hidden = false;
            panel.classList.remove('is-closing');
            form.classList.add('is-open');
            if (header) header.classList.add('is-search-open');
            var full = Math.min(Math.ceil(panel.scrollHeight + 1), panelCap());
            var reduced = prefersReducedMotion();
            if (!wasOpen && !reduced) {
                panel.classList.remove('is-settled');
                panel.style.transition = 'none';
                panel.style.maxHeight = '0px';
                form.classList.remove('is-open');
                void panel.offsetHeight;
                form.classList.add('is-open');
                panel.style.transition = '';
                panel.style.maxHeight = full + 'px';
            } else {
                panel.style.maxHeight = full + 'px';
            }
            if (reduced) {
                panel.classList.add('is-settled');
            } else {
                window.setTimeout(function () {
                    if (token !== motionToken || !open) return;
                    panel.classList.add('is-settled');
                }, 460);
            }
            return;
        }

        setActive(-1);
        if (!wasOpen) {
            finishClose(token);
            return;
        }
        panel.classList.remove('is-settled');
        if (prefersReducedMotion()) {
            finishClose(token);
            return;
        }
        var current = Math.max(0, Math.ceil(panel.getBoundingClientRect().height));
        panel.style.transition = 'none';
        panel.style.maxHeight = current + 'px';
        void panel.offsetHeight;
        panel.style.transition = '';
        panel.classList.add('is-closing');
        panel.style.maxHeight = '0px';
        window.setTimeout(function () {
            finishClose(token);
        }, 460);
    }

    function scrollRowWithinPanel(row) {
        var panelRect = panel.getBoundingClientRect();
        var rowRect = row.getBoundingClientRect();
        if (rowRect.top < panelRect.top) {
            panel.scrollTop -= panelRect.top - rowRect.top;
        } else if (rowRect.bottom > panelRect.bottom) {
            panel.scrollTop += rowRect.bottom - panelRect.bottom;
        }
    }

    function setActive(index, options) {
        var rows = items();
        if (!rows.length) {
            activeIndex = -1;
            return;
        }
        if (index >= rows.length) index = 0;
        if (index < -1) index = rows.length - 1;
        activeIndex = index;
        rows.forEach(function (row, i) {
            var on = i === activeIndex;
            row.classList.toggle('is-active', on);
            if (on) {
                row.id = 'site-search-suggest-active';
                input.setAttribute('aria-activedescendant', row.id);
                if (options && options.scrollPanel) scrollRowWithinPanel(row);
            } else if (row.id === 'site-search-suggest-active') {
                row.removeAttribute('id');
            }
        });
        if (activeIndex < 0) input.removeAttribute('aria-activedescendant');
    }

    function highlight(el, text, query) {
        el.textContent = '';
        var source = text || '';
        var needle = (query || '').trim().toLocaleLowerCase();
        if (!needle) {
            el.textContent = source;
            return;
        }
        var lower = source.toLocaleLowerCase();
        var from = 0;
        var at = lower.indexOf(needle);
        if (at < 0) {
            el.textContent = source;
            return;
        }
        while (at !== -1) {
            if (at > from) el.appendChild(document.createTextNode(source.slice(from, at)));
            var mark = document.createElement('b');
            mark.textContent = source.slice(at, at + needle.length);
            el.appendChild(mark);
            from = at + needle.length;
            at = lower.indexOf(needle, from);
        }
        if (from < source.length) el.appendChild(document.createTextNode(source.slice(from)));
    }

    function markBox(type, image) {
        var box = document.createElement('span');
        box.className = 'site-search-suggest__mark';
        if (image) {
            var img = document.createElement('img');
            img.alt = '';
            img.src = image;
            box.appendChild(img);
            return box;
        }
        var kind = type === 'bundle' ? 'product' : type;
        box.innerHTML = icons[kind] || icons.product;
        return box;
    }

    function addRow(node) {
        node.setAttribute('data-suggest-item', '1');
        node.setAttribute('role', 'option');
        node.addEventListener('mousemove', function () {
            var rows = items();
            setActive(rows.indexOf(node));
        });
        panel.appendChild(node);
    }

    function applySuggestedWord(word) {
        var current = String(input.value || '').replace(/\s+$/u, '');
        var splitAt = current.search(/\s+\S*$/u);
        input.value = splitAt === -1 ? word : current.slice(0, splitAt).replace(/\s+$/u, '') + ' ' + word;
        var end = input.value.length;
        input.focus();
        if (typeof input.setSelectionRange === 'function') {
            try { input.setSelectionRange(end, end); } catch (err) {}
        }
        setOpen(false);
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    }

    function render(data) {
        panel.textContent = '';
        activeIndex = -1;
        var query = (data && data.query) || '';
        var searches = (data && data.searches) || [];
        if (!searches.length && data && data.search_label) {
            searches = [{ label: data.search_label, query: query }];
        }
        searches.forEach(function (item) {
            var searchRow = document.createElement('button');
            searchRow.type = 'button';
            searchRow.className = 'site-search-suggest__item site-search-suggest__item--query';
            searchRow.appendChild(markBox('search', null));
            var searchText = document.createElement('span');
            searchText.className = 'site-search-suggest__title';
            highlight(searchText, item.label || '', query);
            searchRow.appendChild(searchText);
            searchRow.addEventListener('click', function () {
                if (item.kind === 'word') {
                    applySuggestedWord(item.query || item.label || '');
                    return;
                }
                if (item.query) input.value = item.query;
                setOpen(false);
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            });
            addRow(searchRow);
        });

        var groups = (data && data.groups) || [];
        groups.forEach(function (group) {
            if (!group.items || !group.items.length) return;
            var label = document.createElement('div');
            label.className = 'site-search-suggest__label';
            label.textContent = group.label || '';
            panel.appendChild(label);
            group.items.forEach(function (item) {
                var link = document.createElement('a');
                link.className = 'site-search-suggest__item';
                link.href = item.url || '#';
                link.appendChild(markBox(item.type, item.image || null));
                var text = document.createElement('span');
                text.className = 'site-search-suggest__text';
                var title = document.createElement('span');
                title.className = 'site-search-suggest__title';
                highlight(title, item.title || '', query);
                text.appendChild(title);
                if (item.meta) {
                    var meta = document.createElement('span');
                    meta.className = 'site-search-suggest__meta';
                    meta.textContent = item.meta;
                    text.appendChild(meta);
                }
                link.appendChild(text);
                if (goLabel && (item.type === 'product' || item.type === 'bundle')) {
                    var go = document.createElement('span');
                    go.className = 'site-search-suggest__go';
                    go.textContent = goLabel;
                    link.appendChild(go);
                }
                if (item.price) {
                    var price = document.createElement('span');
                    price.className = 'site-search-suggest__price';
                    price.textContent = item.price;
                    link.appendChild(price);
                }
                addRow(link);
            });
        });

        if (!items().length) {
            var empty = document.createElement('p');
            empty.className = 'site-search-suggest__empty';
            empty.textContent = (data && data.empty) || '';
            panel.appendChild(empty);
        }
        setOpen(true);
    }

    function load() {
        var q = (input.value || '').trim();
        var token = ++requestToken;
        var url = new URL(endpoint, window.location.origin);
        if (q) url.searchParams.set('q', q);
        fetch(url.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) throw new Error('suggest');
            return response.json();
        }).then(function (data) {
            if (token !== requestToken || document.activeElement !== input) return;
            render(data);
        }).catch(function () {
            if (token === requestToken) setOpen(false);
        });
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(load, 160);
    }

    input.addEventListener('focus', function () {
        schedule();
    });
    input.addEventListener('input', function () {
        schedule();
    });
    input.addEventListener('keydown', function (event) {
        if (!open && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            schedule();
            return;
        }
        if (!open) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(activeIndex + 1, { scrollPanel: true });
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(activeIndex < 0 ? items().length - 1 : activeIndex - 1, { scrollPanel: true });
        } else if (event.key === 'Escape') {
            setOpen(false);
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            var row = items()[activeIndex];
            if (!row) return;
            event.preventDefault();
            if (row.tagName === 'A') {
                window.location.href = row.href;
            } else {
                row.click();
            }
        }
    });

    document.addEventListener('pointerdown', function (event) {
        if (!form.contains(event.target)) setOpen(false);
    });

    form.addEventListener('submit', function () {
        setOpen(false);
    });
})();
