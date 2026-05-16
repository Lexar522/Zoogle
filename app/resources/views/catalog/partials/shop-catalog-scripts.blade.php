@php
    $shopAuthPayload = [
        'loggedIn' => auth()->check(),
        'csrf' => csrf_token(),
        'toggleUrl' => auth()->check() ? route('favorites.toggle') : '',
        'syncUrl' => auth()->check() ? route('favorites.sync') : '',
    ];
@endphp
<script>
(function () {
    var shopAuth = @json($shopAuthPayload);
    var STORAGE_KEY = 'favorite_products';
    var FRAGMENT_HEADERS = { 'X-Catalog-Fragment': '1', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' };

    function key(lid, vid) { return String(lid) + ':' + String(vid); }
    function loadFavs() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) { return []; }
    }
    function saveFavs(list) { localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); }
    function toggleFav(lid, vid) {
        var k = key(lid, vid);
        var list = loadFavs();
        var i = list.indexOf(k);
        if (i >= 0) list.splice(i, 1); else list.push(k);
        saveFavs(list);
        return list.indexOf(k) >= 0;
    }
    function isFav(lid, vid) { return loadFavs().indexOf(key(lid, vid)) >= 0; }

    var serverFavoriteIds = new Set();

    function setProductLikeCount(btn, n) {
        var wrap = btn.closest('.product-card__like-react');
        if (!wrap) return;
        var el = wrap.querySelector('[data-product-like-count]');
        if (el) {
            var v = typeof n === 'number' && !Number.isNaN(n) ? n : parseInt(String(n), 10);
            if (Number.isNaN(v)) return;
            el.textContent = String(Math.max(0, v));
        }
    }

    function syncFavoriteButtons(scope) {
        if (!scope) return;
        scope.querySelectorAll('.product-card__favorite').forEach(function (btn) {
            var lid = btn.getAttribute('data-product-id');
            var vid = btn.getAttribute('data-favorite-key');
            var on;
            if (shopAuth.loggedIn) {
                on = serverFavoriteIds.has(Number(lid));
            } else {
                on = isFav(lid, vid);
            }
            if (on) {
                btn.classList.add('is-favorite');
                btn.setAttribute('aria-pressed', 'true');
            } else {
                btn.classList.remove('is-favorite');
                btn.setAttribute('aria-pressed', 'false');
            }
        });
    }

    function mergeFavoriteIdsFromElement(el) {
        if (!el) {
            return;
        }
        try {
            JSON.parse(el.getAttribute('data-favorite-ids') || '[]').forEach(function (id) {
                serverFavoriteIds.add(Number(id));
            });
        } catch (err) { /* ignore */ }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.product-card__favorite');
        var catalogRoot = document.getElementById('catalog-results');
        var productPageRoot = document.querySelector('.product-page');
        var inCatalog = catalogRoot && catalogRoot.contains(btn);
        var inPdp = productPageRoot && productPageRoot.contains(btn);
        if (!btn || (!inCatalog && !inPdp)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var lid = btn.getAttribute('data-product-id');
        var vid = btn.getAttribute('data-favorite-key');
        if (shopAuth.loggedIn && shopAuth.toggleUrl) {
            fetch(shopAuth.toggleUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': shopAuth.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ product_id: Number(lid) }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (typeof data.favorited !== 'boolean') return;
                    var on = data.favorited;
                    if (on) {
                        serverFavoriteIds.add(Number(lid));
                    } else {
                        serverFavoriteIds.delete(Number(lid));
                    }
                    btn.classList.toggle('is-favorite', on);
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    if (typeof data.favorites_count === 'number') {
                        setProductLikeCount(btn, data.favorites_count);
                    }
                });
            return;
        }
        var on = toggleFav(lid, vid);
        btn.classList.toggle('is-favorite', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        var wrap = btn.closest('.product-card__like-react');
        var countEl = wrap && wrap.querySelector('[data-product-like-count]');
        if (countEl) {
            var cur = parseInt(countEl.textContent, 10);
            if (Number.isNaN(cur)) cur = 0;
            countEl.textContent = String(Math.max(0, on ? cur + 1 : cur - 1));
        }
    });

    mergeFavoriteIdsFromElement(document.getElementById('catalog-results'));
    mergeFavoriteIdsFromElement(document.querySelector('.product-page'));

    var resultsEl = document.getElementById('catalog-results');

    if (shopAuth.loggedIn && shopAuth.syncUrl) {
        try {
            var raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            var fromLs = [];
            if (Array.isArray(raw)) {
                raw.forEach(function (entry) {
                    if (typeof entry !== 'string') return;
                    var parts = entry.split(':');
                    if (parts.length >= 2 && parts[1] === 'catalog') {
                        var pid = Number(parts[0]);
                        if (pid > 0) {
                            fromLs.push(pid);
                        }
                    }
                });
            }
            if (fromLs.length) {
                fetch(shopAuth.syncUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': shopAuth.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ product_ids: fromLs }),
                })
                    .then(function () {
                        fromLs.forEach(function (id) {
                            serverFavoriteIds.add(Number(id));
                        });
                        syncFavoriteButtons(document.body);
                    });
            }
        } catch (e2) { /* ignore */ }
    }

    syncFavoriteButtons(document.body);

    if (!resultsEl) {
        return;
    }

    var catalogFetchAbort = null;

    function syncCategoryFiltersActive(categoryId) {
        var id = String(categoryId);
        document.querySelectorAll('.catalog-category-list__link[data-category-id]').forEach(function (a) {
            var cid = a.getAttribute('data-category-id');
            if (cid === null || cid === '') cid = '0';
            a.classList.toggle('is-active', cid === id);
        });
    }

    function syncOnSaleFilterActive(onSale) {
        var on = onSale === true || onSale === 1 || onSale === '1';
        document.querySelectorAll('[data-on-sale-link]').forEach(function (a) {
            a.classList.toggle('is-active', on);
        });
    }

    function syncSearchFormFromUrl(url) {
        var u = new URL(url, window.location.origin);
        var cat = u.searchParams.get('category') || '';
        var qVal = u.searchParams.get('q') || '';
        var base = resultsEl.getAttribute('data-catalog-base') || '/catalog';

        document.querySelectorAll('.catalog-search__form').forEach(function (form) {
            var hidden = form.querySelector('input[name="category"]');
            if (cat) {
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'category';
                    form.insertBefore(hidden, form.firstChild);
                }
                hidden.value = cat;
            } else if (hidden) {
                hidden.remove();
            }

            var onSale = u.searchParams.get('on_sale');
            var hiddenSale = form.querySelector('input[name="on_sale"]');
            if (onSale === '1') {
                if (!hiddenSale) {
                    hiddenSale = document.createElement('input');
                    hiddenSale.type = 'hidden';
                    hiddenSale.name = 'on_sale';
                    hiddenSale.value = '1';
                    form.insertBefore(hiddenSale, form.firstChild);
                }
            } else if (hiddenSale) {
                hiddenSale.remove();
            }

            var sortHidden = form.querySelector('input[name="sort"]');
            var sortVal = u.searchParams.get('sort');
            if (sortVal && sortVal !== 'newest') {
                if (!sortHidden) {
                    sortHidden = document.createElement('input');
                    sortHidden.type = 'hidden';
                    sortHidden.name = 'sort';
                    form.insertBefore(sortHidden, form.firstChild);
                }
                sortHidden.value = sortVal;
            } else if (sortHidden) {
                sortHidden.remove();
            }

            var perHidden = form.querySelector('input[name="per_page"]');
            var perVal = u.searchParams.get('per_page');
            if (perVal && perVal !== '24') {
                if (!perHidden) {
                    perHidden = document.createElement('input');
                    perHidden.type = 'hidden';
                    perHidden.name = 'per_page';
                    form.insertBefore(perHidden, form.firstChild);
                }
                perHidden.value = perVal;
            } else if (perHidden) {
                perHidden.remove();
            }

            var qInput = form.querySelector('input[name="q"]');
            if (qInput) qInput.value = qVal;

            var resetLink = form.querySelector('a.btn.secondary');
            if (resetLink) {
                var rp = new URLSearchParams();
                if (cat) rp.set('category', cat);
                if (u.searchParams.get('on_sale') === '1') rp.set('on_sale', '1');
                if (sortVal && sortVal !== 'newest') rp.set('sort', sortVal);
                if (perVal && perVal !== '24') rp.set('per_page', perVal);
                resetLink.href = rp.toString() ? base + '?' + rp.toString() : base;
            }
        });
    }

    function fetchCatalog(url, pushState) {
        if (pushState === undefined) pushState = true;
        if (catalogFetchAbort) {
            try {
                catalogFetchAbort.abort();
            } catch (abortErr) { /* ignore */ }
        }
        catalogFetchAbort = new AbortController();
        var signal = catalogFetchAbort.signal;
        resultsEl.classList.add('catalog-results--loading');
        fetch(url, { headers: FRAGMENT_HEADERS, credentials: 'same-origin', signal: signal })
            .then(function (r) {
                if (!r.ok) throw new Error('Network');
                return r.text();
            })
            .then(function (html) {
                resultsEl.innerHTML = html;
                resultsEl.classList.remove('catalog-results--loading');
                resultsEl.classList.add('catalog-results--updated');
                window.setTimeout(function () {
                    resultsEl.classList.remove('catalog-results--updated');
                }, 450);
                syncFavoriteButtons(document.body);
                if (pushState) {
                    try { history.pushState({ catalog: true }, '', url); } catch (err) { window.location.href = url; }
                }
                var u = new URL(url, window.location.origin);
                var catParam = u.searchParams.get('category');
                syncCategoryFiltersActive(catParam && catParam !== '0' ? catParam : '0');
                syncOnSaleFilterActive(u.searchParams.get('on_sale') === '1');
                syncSearchFormFromUrl(url);
            })
            .catch(function (err) {
                if (err && (err.name === 'AbortError' || err.code === 20)) {
                    return;
                }
                resultsEl.classList.remove('catalog-results--loading');
                window.location.href = url;
            });
    }

    document.querySelector('.catalog-category-list')?.addEventListener('click', function (e) {
        var a = e.target.closest('a.catalog-category-list__link');
        if (!a) return;
        e.preventDefault();
        fetchCatalog(a.href);
    });

    resultsEl.addEventListener('click', function (e) {
        var chip = e.target.closest('[data-catalog-sort-preset]');
        if (chip && resultsEl.contains(chip)) {
            e.preventDefault();
            var v = chip.getAttribute('data-catalog-sort-preset');
            if (!v) return;
            var u = new URL(window.location.href);
            var cur = u.searchParams.get('sort') || 'newest';
            if (cur === v) return;
            if (v === 'newest') {
                u.searchParams.delete('sort');
            } else {
                u.searchParams.set('sort', v);
            }
            u.searchParams.delete('page');
            fetchCatalog(u.toString());
            return;
        }
        var a = e.target.closest('a.shop-pagination__link');
        if (a && a.href && resultsEl.contains(a)) {
            e.preventDefault();
            fetchCatalog(a.href);
            return;
        }
        var pageNav = e.target.closest('a[data-catalog-page-nav]');
        if (pageNav && resultsEl.contains(pageNav) && pageNav.href) {
            e.preventDefault();
            fetchCatalog(pageNav.href);
            return;
        }
        var loadMore = e.target.closest('[data-catalog-load-more]');
        if (loadMore && resultsEl.contains(loadMore)) {
            e.preventDefault();
            var nextUrl = loadMore.getAttribute('data-catalog-next-url');
            if (!nextUrl) return;
            loadMore.disabled = true;
            resultsEl.classList.add('catalog-results--loading');
            fetch(nextUrl, { headers: FRAGMENT_HEADERS, credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) throw new Error('Network');
                    return r.text();
                })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var newGrid = doc.querySelector('.catalog-results__grid');
                    var curGrid = resultsEl.querySelector('.catalog-results__grid');
                    var newMore = doc.querySelector('.catalog-results-more');
                    var curMore = resultsEl.querySelector('.catalog-results-more');
                    var newPag = doc.querySelector('.pagination-wrap');
                    var curPag = resultsEl.querySelector('.pagination-wrap');
                    if (newGrid && curGrid) {
                        while (newGrid.firstChild) {
                            curGrid.appendChild(newGrid.firstChild);
                        }
                    }
                    if (newMore && curMore) {
                        curMore.replaceWith(newMore);
                    } else if (curMore && !newMore) {
                        curMore.remove();
                    }
                    if (newPag && curPag) {
                        curPag.innerHTML = newPag.innerHTML;
                    }
                    resultsEl.classList.remove('catalog-results--loading');
                    resultsEl.classList.add('catalog-results--updated');
                    window.setTimeout(function () {
                        resultsEl.classList.remove('catalog-results--updated');
                    }, 450);
                    syncFavoriteButtons(document.body);
                })
                .catch(function () {
                    resultsEl.classList.remove('catalog-results--loading');
                    loadMore.disabled = false;
                    window.location.href = nextUrl;
                });
        }
    });

    document.querySelectorAll('.catalog-search__form').forEach(function (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(searchForm);
            var params = new URLSearchParams(fd);
            /* Текстовий пошук у шапці має шукати по всьому каталогу: інакше залишається прихований
               category після кліку по чипу — запит «па» не знаходить «Папуга1» в іншій категорії. */
            var qTrim = (params.get('q') || '').trim();
            if (qTrim !== '') {
                params.delete('category');
            }
            var base = resultsEl.getAttribute('data-catalog-base') || '/catalog';
            var qs = params.toString();
            fetchCatalog(qs ? base + '?' + qs : base);
        });
        searchForm.addEventListener('click', function (e) {
            var a = e.target.closest('a.btn.secondary');
            if (!a || !a.getAttribute('href')) return;
            e.preventDefault();
            fetchCatalog(a.href);
        });
    });

    window.addEventListener('popstate', function () {
        fetchCatalog(window.location.href, false);
    });

})();
</script>
