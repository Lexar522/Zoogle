(function () {
    var shopAuth = window.zoogleShopAuth || {};
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
            // «0» біля сердечка не показуємо — лише коли товар уже хтось уподобав.
            el.hidden = v <= 0;
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
    });

    mergeFavoriteIdsFromElement(document.getElementById('catalog-results'));
    mergeFavoriteIdsFromElement(document.querySelector('.product-page'));

    var resultsEl = document.getElementById('catalog-results');
    var catalogFetchSearch = null;

    function isCatalogListingPageFor(root) {
        var catalogRoot = root || resultsEl;
        if (!catalogRoot) {
            return false;
        }
        var catalogPath = catalogRoot.getAttribute('data-catalog-base') || '/catalog';
        try {
            var catalogUrl = new URL(catalogPath, window.location.origin);
            return window.location.pathname.replace(/\/$/, '') === catalogUrl.pathname.replace(/\/$/, '');
        } catch (pathErr) {
            return window.location.pathname.replace(/\/$/, '') === '/catalog';
        }
    }

    if (resultsEl && isCatalogListingPageFor(resultsEl)) {
        try {
            var shuffleUrl = new URL(window.location.href);
            if (shuffleUrl.searchParams.has('mix')) {
                shuffleUrl.searchParams.delete('mix');
                history.replaceState(history.state || { catalog: true }, '', shuffleUrl.toString());
            }
        } catch (shuffleErr) { /* ignore */ }
    }

    function buildCatalogSearchUrl(searchForm) {
        var fd = new FormData(searchForm);
        var params = new URLSearchParams(fd);
        var qTrim = (params.get('q') || '').trim();
        if (qTrim !== '') {
            params.delete('category');
            var subKeysToDelete = [];
            params.forEach(function (_value, key) {
                if (key === 'subcategories[]' || /^subcategories(\[\d*\])?$/.test(key)) {
                    subKeysToDelete.push(key);
                }
            });
            subKeysToDelete.forEach(function (key) {
                params.delete(key);
            });
        }
        var root = document.getElementById('catalog-results');
        var base = (root && root.getAttribute('data-catalog-base')) || searchForm.getAttribute('action') || '/catalog';
        var qs = params.toString();
        return {
            url: qs ? base + '?' + qs : base,
            qTrim: qTrim,
        };
    }

    function handleCatalogSearchSubmit(searchForm, e) {
        if (e) {
            e.preventDefault();
        }
        var built = buildCatalogSearchUrl(searchForm);
        var root = document.getElementById('catalog-results');
        if (!root || !isCatalogListingPageFor(root)) {
            window.location.href = built.url;
            return;
        }
        if (typeof catalogFetchSearch === 'function') {
            var searchInput = searchForm.querySelector('input[name="q"]');
            if (searchInput) {
                try {
                    searchInput.blur();
                } catch (blurErr) { /* ignore */ }
            }
            catalogFetchSearch(built.url, built.qTrim !== '');
            return;
        }
        window.location.href = built.url;
    }

    document.querySelectorAll('.catalog-search__form').forEach(function (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            handleCatalogSearchSubmit(searchForm, e);
        });
    });

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
    if (typeof window.zoogleSyncCatalogCardMedia === 'function') {
        window.zoogleSyncCatalogCardMedia(document);
    }

    function catalogUrlWithoutSearch(url) {
        try {
            var u = new URL(url, window.location.origin);
            u.searchParams.delete('q');
            var qs = u.searchParams.toString();
            return u.pathname + (qs ? '?' + qs : '') + u.hash;
        } catch (urlErr) {
            return url;
        }
    }

    function clearCatalogSearchInputs() {
        document.querySelectorAll('.catalog-search__form input[name="q"]').forEach(function (input) {
            input.value = '';
        });
    }

    function findCategoryNavAnchor(target) {
        if (!target || !target.closest) {
            return null;
        }
        var inNav = target.closest('.catalog-filters')
            || target.closest('.catalog-subcategory-list--flyout-portal');
        if (!inNav || target.closest('[data-category-expand]')) {
            return null;
        }
        var a = target.closest('a.catalog-category-list__link');
        if (!a) {
            a = target.closest('.catalog-category-hub__panel-head a.catalog-category-list__link');
        }
        if (!a) {
            var row = target.closest('.catalog-category-list__row');
            if (row) {
                a = row.querySelector(':scope > a.catalog-category-list__link');
            }
        }
        return a && a.href ? a : null;
    }

    if (!resultsEl) {
        document.addEventListener('click', function (e) {
            var a = findCategoryNavAnchor(e.target);
            if (!a) {
                return;
            }
            e.preventDefault();
            clearCatalogSearchInputs();
            window.location.href = catalogUrlWithoutSearch(a.href);
        });
        return;
    }

    var catalogFetchAbort = null;
    var catalogRestoredFromCache = false;

    function syncCategoryFiltersActive(categoryId) {
        var id = String(categoryId);
        document.querySelectorAll('.catalog-category-list__link[data-category-id], .catalog-category-hub__panel-head .catalog-category-list__link[data-category-id]').forEach(function (a) {
            var cid = a.getAttribute('data-category-id');
            if (cid === null || cid === '') cid = '0';
            a.classList.toggle('is-active', cid === id);
        });
    }

    function appendCatalogListingParams(params, searchParams) {
        var sort = searchParams.get('sort');
        if (sort && sort !== 'random') {
            params.set('sort', sort);
        }
        var perPage = searchParams.get('per_page');
        if (perPage && perPage !== '24') {
            params.set('per_page', perPage);
        }
        if (searchParams.get('all') === '1') {
            params.set('all', '1');
        }
    }

    function appendSubcategoryParams(params, searchParams) {
        var ids = [];
        searchParams.forEach(function (value, key) {
            if (key === 'subcategories[]' || /^subcategories(\[\d*\])?$/.test(key)) {
                var id = parseInt(value, 10);
                if (!Number.isNaN(id) && id > 0 && ids.indexOf(String(id)) < 0) {
                    ids.push(String(id));
                }
            }
        });
        ids.sort(function (a, b) { return Number(a) - Number(b); });
        ids.forEach(function (id) {
            params.append('subcategories[]', id);
        });
    }

    function catalogContextParamsFromUrl(url) {
        var u = new URL(url, window.location.origin);
        var params = new URLSearchParams();
        // Не переносимо q: навігація категоріями / акціями виходить з режиму пошуку
        var bundlesOnly = u.searchParams.get('bundles') === '1';
        var onSale = u.searchParams.get('on_sale') === '1';
        if (bundlesOnly) {
            params.set('bundles', '1');
        } else if (onSale) {
            params.set('on_sale', '1');
        }
        appendCatalogListingParams(params, u.searchParams);
        return params;
    }

    function catalogPromoContextParamsFromUrl(url) {
        var u = new URL(url, window.location.origin);
        var params = catalogContextParamsFromUrl(url);
        var cat = u.searchParams.get('category');
        if (cat && cat !== '0') {
            params.set('category', cat);
        }
        appendSubcategoryParams(params, u.searchParams);
        return params;
    }

    function catalogHrefFromParams(params) {
        var base = resultsEl.getAttribute('data-catalog-base') || '/catalog';
        var qs = params.toString();
        return qs ? base + '?' + qs : base;
    }

    function syncSpecialFilterLinks(url) {
        var u = new URL(url, window.location.origin);
        var onSale = u.searchParams.get('on_sale') === '1';
        var bundlesOnly = u.searchParams.get('bundles') === '1';

        document.querySelectorAll('[data-on-sale-link]').forEach(function (a) {
            // «Акції» не прив'язуються до категорії: показуємо всі акційні товари
            var params = catalogContextParamsFromUrl(url);
            params.delete('bundles');
            if (onSale) {
                params.delete('on_sale');
            } else {
                params.set('on_sale', '1');
            }
            a.href = catalogHrefFromParams(params);
        });

        document.querySelectorAll('[data-bundles-link]').forEach(function (a) {
            var params = catalogPromoContextParamsFromUrl(url);
            if (bundlesOnly) {
                params.delete('bundles');
            } else {
                params.delete('on_sale');
                params.set('bundles', '1');
            }
            a.href = catalogHrefFromParams(params);
        });
    }

    function syncCategoryLinkHrefs(url) {
        document.querySelectorAll('.catalog-category-list__link[data-category-id], .catalog-category-hub__panel-head .catalog-category-list__link[data-category-id]').forEach(function (a) {
            var id = a.getAttribute('data-category-id');
            if (!id) {
                return;
            }
            var params = catalogContextParamsFromUrl(url);
            // Вибір категорії виходить з режиму «Акції» чи «Комплекти»
            params.delete('on_sale');
            params.delete('bundles');
            params.set('category', id);
            a.href = catalogHrefFromParams(params);
        });
    }

    function syncOnSaleFilterActive(onSale) {
        var on = onSale === true || onSale === 1 || onSale === '1';
        document.querySelectorAll('[data-on-sale-link]').forEach(function (a) {
            a.classList.toggle('is-active', on);
        });
    }

    function syncBundlesFilterActive(bundlesOnly) {
        var on = bundlesOnly === true || bundlesOnly === 1 || bundlesOnly === '1';
        document.querySelectorAll('[data-bundles-link]').forEach(function (a) {
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

            var bundlesOnly = u.searchParams.get('bundles');
            var onSale = u.searchParams.get('on_sale');
            var hiddenSale = form.querySelector('input[name="on_sale"]');
            if (onSale === '1' && bundlesOnly !== '1') {
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

            var hiddenBundles = form.querySelector('input[name="bundles"]');
            if (bundlesOnly === '1') {
                if (!hiddenBundles) {
                    hiddenBundles = document.createElement('input');
                    hiddenBundles.type = 'hidden';
                    hiddenBundles.name = 'bundles';
                    hiddenBundles.value = '1';
                    form.insertBefore(hiddenBundles, form.firstChild);
                }
            } else if (hiddenBundles) {
                hiddenBundles.remove();
            }

            var sortHidden = form.querySelector('input[name="sort"]');
            var sortVal = u.searchParams.get('sort');
            if (sortVal && sortVal !== 'random') {
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

            var allHidden = form.querySelector('input[name="all"]');
            if (u.searchParams.get('all') === '1') {
                if (!allHidden) {
                    allHidden = document.createElement('input');
                    allHidden.type = 'hidden';
                    allHidden.name = 'all';
                    allHidden.value = '1';
                    form.insertBefore(allHidden, form.firstChild);
                }
            } else if (allHidden) {
                allHidden.remove();
            }

            var qInput = form.querySelector('input[name="q"]');
            if (qInput) qInput.value = qVal;

            var resetLink = form.querySelector('a.btn.secondary');
            if (resetLink) {
                var rp = new URLSearchParams();
                if (cat) rp.set('category', cat);
                if (u.searchParams.get('bundles') === '1') {
                    rp.set('bundles', '1');
                } else if (u.searchParams.get('on_sale') === '1') {
                    rp.set('on_sale', '1');
                }
                if (sortVal && sortVal !== 'random') rp.set('sort', sortVal);
                if (perVal && perVal !== '24') rp.set('per_page', perVal);
                if (u.searchParams.get('all') === '1') rp.set('all', '1');
                appendSubcategoryParams(rp, u.searchParams);
                resetLink.href = rp.toString() ? base + '?' + rp.toString() : base;
            }
        });
    }

    function isCatalogListingPage() {
        var catalogPath = resultsEl.getAttribute('data-catalog-base') || '/catalog';
        try {
            var catalogUrl = new URL(catalogPath, window.location.origin);
            return window.location.pathname.replace(/\/$/, '') === catalogUrl.pathname.replace(/\/$/, '');
        } catch (pathErr) {
            return window.location.pathname.replace(/\/$/, '') === '/catalog';
        }
    }

    function syncCatalogUrlState(url, options) {
        options = options || {};
        var u = new URL(url, window.location.origin);
        var catParam = u.searchParams.get('category');
        var bundlesOnly = u.searchParams.get('bundles') === '1';
        var onSale = u.searchParams.get('on_sale') === '1' && ! bundlesOnly;
        syncCategoryFiltersActive(catParam && catParam !== '0' ? catParam : '0');
        syncOnSaleFilterActive(onSale);
        syncBundlesFilterActive(bundlesOnly);
        syncSpecialFilterLinks(url);
        syncCategoryLinkHrefs(url);
        syncSearchFormFromUrl(url);
        if (options.skipExpandPath) {
            closeCategoryPanels();
            return;
        }
        if (typeof window.zoogleExpandActiveCategoryPath === 'function') {
            window.zoogleExpandActiveCategoryPath();
        }
    }

    function catalogListingFooter() {
        return resultsEl ? resultsEl.querySelector('.catalog-listing-footer') : null;
    }

    function appendCatalogProductCards(fromGrid, toGrid) {
        if (!fromGrid || !toGrid) return 0;
        var appended = 0;
        fromGrid.querySelectorAll('.product-card-shell').forEach(function (card) {
            toGrid.appendChild(card);
            appended += 1;
        });
        return appended;
    }

    function syncCatalogFooterPageState(page) {
        var footer = catalogListingFooter();
        if (!footer || !page) return;
        footer.setAttribute('data-catalog-current-page', String(page));
    }

    function updateCatalogShownInfo() {
        var footer = catalogListingFooter();
        if (!footer) return;
        var info = resultsEl.querySelector('.shop-pagination__info');
        if (!info) return;
        var total = parseInt(footer.getAttribute('data-catalog-total') || '0', 10);
        var count = resultsEl.querySelectorAll('[data-catalog-product-grid] .product-card-shell').length;
        if (!total || count < 1) return;
        var shown = footer.getAttribute('data-pagination-shown') || '';
        var ofWord = footer.getAttribute('data-pagination-of') || '';
        var from = 1;
        var to = count;
        var through = 0;
        try {
            through = parseInt(new URL(window.location.href).searchParams.get('through') || '0', 10);
        } catch (throughErr) { /* ignore */ }
        if (!(through > 1)) {
            var perPage = parseInt(footer.getAttribute('data-catalog-per-page') || '0', 10);
            var page = parseInt(footer.getAttribute('data-catalog-current-page') || '1', 10);
            if (perPage > 0 && page > 1) {
                from = ((page - 1) * perPage) + 1;
                to = Math.min(total, from + count - 1);
            }
        }
        info.innerHTML = shown + ' <strong>' + from + '</strong>–<strong>' + to + '</strong> ' + ofWord + ' <strong>' + total + '</strong>';
    }

    function ensureCatalogSubcategoryFilterStyles() {
        var source = document.getElementById('zoogle-catalog-subcategory-filter-styles');
        if (!source || !source.textContent) {
            return;
        }
        var headStyle = document.getElementById('zoogle-catalog-subcategory-filter-styles-head');
        if (!headStyle) {
            headStyle = document.createElement('style');
            headStyle.id = 'zoogle-catalog-subcategory-filter-styles-head';
            document.head.appendChild(headStyle);
        }
        if (headStyle.textContent !== source.textContent) {
            headStyle.textContent = source.textContent;
        }
    }

    function catalogListingPanelScrollTarget() {
        return document.getElementById('catalog-listing-panel')
            || (resultsEl ? resultsEl.querySelector('.home-shop-panel.catalog-listing-panel') : null)
            || (resultsEl ? resultsEl.querySelector('.catalog-listing-panel') : null);
    }

    function catalogSubcategoryFilterScrollTarget() {
        return resultsEl ? resultsEl.querySelector('[data-catalog-subcategory-filter]') : null;
    }

    function isMobileCatalogViewport() {
        try {
            return window.matchMedia('(max-width: 768px)').matches;
        } catch (mqErr) {
            return false;
        }
    }

    function catalogNavScrollTarget(preferSubcategoryFilter) {
        if (preferSubcategoryFilter) {
            var subFilter = catalogSubcategoryFilterScrollTarget();
            if (subFilter) {
                return subFilter;
            }
        }
        return catalogListingPanelScrollTarget();
    }

    function catalogSubcategoryStickyOffsetPx() {
        var compactBar = document.querySelector('[data-site-header-compact-bar]');
        var height = compactBar ? compactBar.offsetHeight : 0;
        if (!(height > 0)) {
            height = catalogCompactScrollOffsetPx();
        }
        return Math.ceil(height) + 12;
    }

    function catalogHeroScrollEndY() {
        var hero = document.querySelector('[data-site-header-hero]');
        if (!hero) {
            return 0;
        }
        return hero.offsetTop + hero.offsetHeight;
    }

    function catalogCompactScrollOffsetPx() {
        var compactBar = document.querySelector('[data-site-header-compact-bar]');
        if (compactBar) {
            var barHeight = compactBar.offsetHeight;
            if (barHeight > 0) {
                return Math.ceil(barHeight);
            }
        }
        var header = document.querySelector('.site-header');
        if (header) {
            var stickyHeight = parseFloat(getComputedStyle(header).getPropertyValue('--site-header-compact-sticky-height'));
            if (!isNaN(stickyHeight) && stickyHeight > 0) {
                return Math.ceil(stickyHeight);
            }
        }
        return 92;
    }

    /** Верх #catalog-listing-panel під липким хедером (--header-sticky-offset або rect хедера). */
    function catalogStickyScrollOffsetPx() {
        var header = document.querySelector('.site-header');
        if (header && header.classList.contains('site-header--compact-visible')) {
            return catalogCompactScrollOffsetPx();
        }
        var hero = document.querySelector('[data-site-header-hero]');
        if (hero) {
            return Math.max(0, Math.ceil(hero.getBoundingClientRect().bottom));
        }
        var parsed = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-sticky-offset'));
        if (!isNaN(parsed) && parsed > 0) {
            return Math.min(Math.ceil(parsed), 320);
        }
        return 200;
    }

    function catalogListingPanelScrollOffsetPx(panelDocTop) {
        var heroEnd = catalogHeroScrollEndY();
        if (heroEnd > 0 && panelDocTop >= heroEnd - 8) {
            return catalogCompactScrollOffsetPx();
        }
        return catalogStickyScrollOffsetPx();
    }

    function catalogScrollYForElement(target) {
        if (!target) {
            return null;
        }
        var scrollY = window.scrollY || 0;
        var rect = target.getBoundingClientRect();
        var docTop = scrollY + rect.top;
        var offset = isMobileCatalogViewport() && target.hasAttribute && target.hasAttribute('data-catalog-subcategory-filter')
            ? catalogSubcategoryStickyOffsetPx()
            : catalogListingPanelScrollOffsetPx(docTop);
        return Math.max(0, Math.round(docTop - offset));
    }

    function catalogListingPanelScrollY() {
        return catalogScrollYForElement(catalogListingPanelScrollTarget());
    }

    function applyCatalogNavScroll(target, behavior) {
        if (!target) {
            return null;
        }
        var top = catalogScrollYForElement(target);
        if (top == null) {
            return null;
        }
        var currentY = window.scrollY || 0;
        if (Math.abs(currentY - top) < 2) {
            return top;
        }
        try {
            window.scrollTo({ top: top, left: 0, behavior: behavior || 'auto' });
        } catch (scrollErr) {
            window.scrollTo(0, top);
        }
        return top;
    }

    function applyCatalogListingPanelScroll(behavior) {
        return applyCatalogNavScroll(catalogListingPanelScrollTarget(), behavior);
    }

    function catalogScrollBehavior() {
        try {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return 'auto';
            }
        } catch (motionErr) { /* ignore */ }
        return 'smooth';
    }

    var catalogScrollGeneration = 0;
    var catalogScrollTimer = null;

    function scheduleScrollToCatalogResults(preferSubcategoryFilter) {
        catalogScrollGeneration += 1;
        var generation = catalogScrollGeneration;

        if (catalogScrollTimer) {
            window.clearTimeout(catalogScrollTimer);
            catalogScrollTimer = null;
        }

        function attemptScroll() {
            if (generation !== catalogScrollGeneration) {
                return;
            }
            var target = catalogNavScrollTarget(preferSubcategoryFilter === true);
            if (!target) {
                return;
            }
            applyCatalogNavScroll(target, catalogScrollBehavior());
            if (isMobileCatalogViewport() && target.hasAttribute && target.hasAttribute('data-catalog-subcategory-filter')) {
                window.setTimeout(function () {
                    if (generation !== catalogScrollGeneration) {
                        return;
                    }
                    var settled = catalogNavScrollTarget(true);
                    if (settled) {
                        applyCatalogNavScroll(settled, 'auto');
                    }
                }, 280);
            }
        }

        function waitForTarget(triesLeft) {
            if (generation !== catalogScrollGeneration) {
                return;
            }
            if (catalogNavScrollTarget(preferSubcategoryFilter === true)) {
                requestAnimationFrame(function () {
                    requestAnimationFrame(attemptScroll);
                });
                return;
            }
            if (triesLeft <= 0) {
                return;
            }
            requestAnimationFrame(function () {
                waitForTarget(triesLeft - 1);
            });
        }

        catalogScrollTimer = window.setTimeout(function () {
            catalogScrollTimer = null;
            waitForTarget(40);
        }, 32);
    }

    function scrollToCatalogResults(preferSubcategoryFilter) {
        scheduleScrollToCatalogResults(preferSubcategoryFilter);
    }

    function markCatalogResultsScrollIntent(shouldScroll) {
        if (!resultsEl) {
            return;
        }
        resultsEl.setAttribute('data-scroll-to-listing', shouldScroll ? '1' : '0');
    }

    function preserveCatalogScrollY(scrollY) {
        var y = typeof scrollY === 'number' ? scrollY : (window.scrollY || 0);
        window.scrollTo({ top: y, left: 0, behavior: 'instant' in window ? 'instant' : 'auto' });
        requestAnimationFrame(function () {
            window.scrollTo({ top: y, left: 0, behavior: 'instant' in window ? 'instant' : 'auto' });
            requestAnimationFrame(function () {
                window.scrollTo({ top: y, left: 0, behavior: 'instant' in window ? 'instant' : 'auto' });
            });
        });
    }

    function fetchCatalog(url, pushState, fetchOptions) {
        if (pushState === undefined) pushState = true;
        fetchOptions = fetchOptions || {};
        if (catalogFetchAbort) {
            try {
                catalogFetchAbort.abort();
            } catch (abortErr) { /* ignore */ }
        }
        catalogFetchAbort = new AbortController();
        var signal = catalogFetchAbort.signal;
        var scrollYBeforeFetch = window.scrollY || 0;
        resultsEl.classList.add('catalog-results--loading');
        fetch(url, { headers: FRAGMENT_HEADERS, credentials: 'same-origin', signal: signal })
            .then(function (r) {
                if (!r.ok) throw new Error('Network');
                return r.text();
            })
            .then(function (html) {
                var scrollToCatalog = fetchOptions.scrollToCatalog === true;
                var active = document.activeElement;
                if (active && resultsEl.contains(active)) {
                    active.blur();
                }
                resultsEl.innerHTML = html;
                ensureCatalogSubcategoryFilterStyles();
                markCatalogResultsScrollIntent(scrollToCatalog);
                resultsEl.classList.remove('catalog-results--loading');
                resultsEl.classList.add('catalog-results--updated');
                window.setTimeout(function () {
                    resultsEl.classList.remove('catalog-results--updated');
                }, 450);
                syncFavoriteButtons(document.body);
                if (typeof window.zoogleSyncCatalogCardMedia === 'function') {
                    window.zoogleSyncCatalogCardMedia(resultsEl);
                }
                if (pushState) {
                    try { history.pushState({ catalog: true }, '', url); } catch (err) { window.location.href = url; }
                }
                syncCatalogUrlState(url, fetchOptions);
                if (scrollToCatalog) {
                    scrollToCatalogResults(fetchOptions.preferSubcategoryFilter === true);
                } else {
                    preserveCatalogScrollY(scrollYBeforeFetch);
                }
                var pageFromUrl = 1;
                try {
                    pageFromUrl = Math.max(1, parseInt(new URL(url, window.location.origin).searchParams.get('page') || '1', 10));
                } catch (pageParseErr) { /* ignore */ }
                syncCatalogFooterPageState(pageFromUrl);
                updateCatalogShownInfo();
            })
            .catch(function (err) {
                if (err && (err.name === 'AbortError' || err.code === 20)) {
                    resultsEl.classList.remove('catalog-results--loading');
                    return;
                }
                resultsEl.classList.remove('catalog-results--loading');
                window.location.href = url;
            });
    }

    catalogFetchSearch = function (url, scrollToCatalog) {
        fetchCatalog(url, true, { scrollToCatalog: scrollToCatalog, skipExpandPath: true });
    };

    function closeCategoryPanels() {
        if (typeof window.zoogleResetAllCategoryExpandState === 'function') {
            window.zoogleResetAllCategoryExpandState();
            return;
        }
        document.querySelectorAll('.catalog-category-list > li.is-panel-open').forEach(function (li) {
            li.classList.remove('is-panel-open');
            var btn = li.querySelector('[data-category-expand]');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
                var expandLabel = btn.getAttribute('data-expand-label');
                if (expandLabel) btn.setAttribute('aria-label', expandLabel);
            }
        });
        document.querySelectorAll('.catalog-subcategory-list > li.is-expanded').forEach(function (li) {
            li.classList.remove('is-expanded');
            var btn = li.querySelector('[data-category-expand]');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
                var expandLabel = btn.getAttribute('data-expand-label');
                if (expandLabel) btn.setAttribute('aria-label', expandLabel);
            }
        });
    }

    document.addEventListener('click', handleCategoryNavClick);

    function handleCategoryNavClick(e) {
        if (e.target.closest('[data-category-expand]')) return;

        var onSaleLink = e.target.closest('[data-on-sale-link]');
        if (onSaleLink && onSaleLink.href) {
            e.preventDefault();
            closeCategoryPanels();
            clearCatalogSearchInputs();
            var onSaleHref = catalogUrlWithoutSearch(onSaleLink.href);
            if (!isCatalogListingPage()) {
                window.location.href = onSaleHref;
                return;
            }
            fetchCatalog(onSaleHref, true, { scrollToCatalog: true });
            return;
        }

        var bundlesLink = e.target.closest('[data-bundles-link]');
        if (bundlesLink && bundlesLink.href) {
            e.preventDefault();
            closeCategoryPanels();
            clearCatalogSearchInputs();
            var bundlesHref = catalogUrlWithoutSearch(bundlesLink.href);
            if (!isCatalogListingPage()) {
                window.location.href = bundlesHref;
                return;
            }
            fetchCatalog(bundlesHref, true, { scrollToCatalog: true });
            return;
        }

        var a = findCategoryNavAnchor(e.target);
        if (!a || !a.href) return;
        e.preventDefault();
        closeCategoryPanels();
        clearCatalogSearchInputs();
        var categoryHref = catalogUrlWithoutSearch(a.href);
        if (!isCatalogListingPage()) {
            window.location.href = categoryHref;
            return;
        }
        fetchCatalog(categoryHref, true, { scrollToCatalog: true, preferSubcategoryFilter: true });
    }

    resultsEl.addEventListener('click', function (e) {
        var showAllChip = e.target.closest('a.catalog-toolbar__chip--show-all');
        if (showAllChip && showAllChip.href && resultsEl.contains(showAllChip)) {
            e.preventDefault();
            fetchCatalog(showAllChip.href, true, { scrollToCatalog: false });
            return;
        }
        var filterChip = e.target.closest('a.catalog-toolbar__chip--filter');
        if (filterChip && filterChip.href && resultsEl.contains(filterChip)) {
            e.preventDefault();
            // Зняли фільтр (×) — повертаємо до блоку фільтрів, як після вибору категорії
            fetchCatalog(filterChip.href, true, { scrollToCatalog: true, preferSubcategoryFilter: true });
            return;
        }
        var chip = e.target.closest('[data-catalog-sort-preset]');
        if (chip && resultsEl.contains(chip)) {
            e.preventDefault();
            var v = chip.getAttribute('data-catalog-sort-preset');
            if (!v) return;
            var u = new URL(window.location.href);
            var cur = u.searchParams.get('sort') || 'random';
            if (cur === v) return;
            u.searchParams.delete('mix');
            if (v === 'random') {
                u.searchParams.delete('sort');
            } else {
                u.searchParams.set('sort', v);
            }
            u.searchParams.delete('page');
            u.searchParams.delete('through');
            fetchCatalog(u.toString());
            return;
        }
        var a = e.target.closest('a.shop-pagination__link');
        if (a && a.href && resultsEl.contains(a)) {
            e.preventDefault();
            // Нова сторінка — на початок каталогу (не лишатись унизу попередньої)
            fetchCatalog(a.href, true, { scrollToCatalog: true });
            return;
        }
        var loadMore = e.target.closest('[data-catalog-load-more]');
        if (loadMore && resultsEl.contains(loadMore)) {
            e.preventDefault();
            var nextUrl = loadMore.getAttribute('data-catalog-next-url');
            if (!nextUrl) return;
            // Фіксуємо viewport: довантаження не має «тягнути» камеру вниз за кнопкою
            var loadMoreScrollY = window.scrollY || 0;
            try { loadMore.blur(); } catch (blurErr) { /* ignore */ }
            loadMore.disabled = true;
            resultsEl.classList.add('catalog-results--loading');
            fetch(nextUrl, { headers: FRAGMENT_HEADERS, credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) throw new Error('Network');
                    return r.text();
                })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var newGrid = doc.querySelector('[data-catalog-product-grid]');
                    var curGrid = resultsEl.querySelector('[data-catalog-product-grid]');
                    var newFooter = doc.querySelector('.catalog-listing-footer');
                    var curFooter = catalogListingFooter();
                    var appended = appendCatalogProductCards(newGrid, curGrid);
                    if (appended < 1) {
                        throw new Error('No products');
                    }
                    if (newFooter && curFooter) {
                        var newPages = newFooter.querySelector('.catalog-listing-footer__pages');
                        var curPages = curFooter.querySelector('.catalog-listing-footer__pages');
                        if (newPages && curPages) {
                            curPages.innerHTML = newPages.innerHTML;
                        }
                        var newMore = newFooter.querySelector('.catalog-results-more');
                        var curMore = curFooter.querySelector('.catalog-results-more');
                        if (newMore && curMore) {
                            curMore.replaceWith(newMore);
                        } else if (curMore && !newMore) {
                            curMore.remove();
                        } else if (!curMore && newMore) {
                            curFooter.appendChild(newMore);
                        }
                        var nextTotal = newFooter.getAttribute('data-catalog-total');
                        if (nextTotal) {
                            curFooter.setAttribute('data-catalog-total', nextTotal);
                        }
                    }
                    var historyUrl = nextUrl;
                    try {
                        var accumulated = new URL(window.location.href);
                        accumulated.searchParams.delete('page');
                        accumulated.searchParams.set('through', String(Math.max(1, parseInt(new URL(nextUrl, window.location.origin).searchParams.get('page') || '1', 10))));
                        historyUrl = accumulated.pathname + accumulated.search + accumulated.hash;
                    } catch (throughErr) { /* ignore */ }
                    try { history.replaceState({ catalog: true }, '', historyUrl); } catch (urlErr) { /* ignore */ }
                    syncCatalogUrlState(historyUrl);
                    var loadedPage = 1;
                    try {
                        loadedPage = Math.max(1, parseInt(new URL(nextUrl, window.location.origin).searchParams.get('page') || '1', 10));
                    } catch (loadedPageErr) { /* ignore */ }
                    syncCatalogFooterPageState(loadedPage);
                    updateCatalogShownInfo();
                    resultsEl.classList.remove('catalog-results--loading');
                    resultsEl.classList.add('catalog-results--updated');
                    window.setTimeout(function () {
                        resultsEl.classList.remove('catalog-results--updated');
                    }, 450);
                    syncFavoriteButtons(document.body);
                    if (typeof window.zoogleSyncCatalogCardMedia === 'function') {
                        window.zoogleSyncCatalogCardMedia(resultsEl);
                    }
                    // Повернути scroll після append (scroll anchoring / focus інакше їдуть униз)
                    window.scrollTo({ top: loadMoreScrollY, left: 0, behavior: 'auto' });
                    requestAnimationFrame(function () {
                        window.scrollTo({ top: loadMoreScrollY, left: 0, behavior: 'auto' });
                        requestAnimationFrame(function () {
                            window.scrollTo({ top: loadMoreScrollY, left: 0, behavior: 'auto' });
                        });
                    });
                })
                .catch(function () {
                    resultsEl.classList.remove('catalog-results--loading');
                    loadMore.disabled = false;
                    window.location.href = nextUrl;
                });
        }
    });

    document.querySelectorAll('.catalog-search__form').forEach(function (searchForm) {
        searchForm.addEventListener('click', function (e) {
            var a = e.target.closest('a.btn.secondary');
            if (!a || !a.getAttribute('href')) return;
            e.preventDefault();
            fetchCatalog(a.href);
        });
    });

    function findSubcategoryFilterCheckbox(target) {
        if (!target || !target.closest) {
            return null;
        }
        var form = target.closest('[data-catalog-subcategory-filter]');
        if (!form || !resultsEl.contains(form)) {
            return null;
        }
        var checkbox = target.closest('.catalog-subcategory-filter__checkbox');
        if (checkbox) {
            return checkbox;
        }
        var label = target.closest('.catalog-subcategory-filter__label');
        if (!label) {
            return null;
        }
        return label.querySelector('.catalog-subcategory-filter__checkbox');
    }

    function restoreSubcategoryFilterScrollY(scrollY) {
        var y = typeof scrollY === 'number' ? scrollY : (window.scrollY || 0);
        function restoreScroll() {
            window.scrollTo({ top: y, left: 0, behavior: 'auto' });
        }
        restoreScroll();
        requestAnimationFrame(restoreScroll);
        requestAnimationFrame(function () {
            requestAnimationFrame(restoreScroll);
        });
    }

    var subcategoryFilterScrollY = null;

    resultsEl.addEventListener('mousedown', function (e) {
        var checkbox = findSubcategoryFilterCheckbox(e.target);
        if (!checkbox) {
            return;
        }
        /* Capture до фокусу на input — інакше браузер прокручує до блоку «Фільтр» зверху. */
        subcategoryFilterScrollY = window.scrollY || 0;
        e.preventDefault();
    }, true);

    resultsEl.addEventListener('pointerdown', function (e) {
        var checkbox = findSubcategoryFilterCheckbox(e.target);
        if (!checkbox) {
            return;
        }
        subcategoryFilterScrollY = window.scrollY || 0;
        e.preventDefault();
    }, true);

    resultsEl.addEventListener('click', function (e) {
        var checkbox = findSubcategoryFilterCheckbox(e.target);
        if (!checkbox) {
            return;
        }
        e.preventDefault();
        checkbox.checked = !checkbox.checked;
        checkbox.blur();
        restoreSubcategoryFilterScrollY(subcategoryFilterScrollY);
        subcategoryFilterScrollY = null;
    }, true);

    resultsEl.addEventListener('focusin', function (e) {
        if (!e.target || !e.target.matches || !e.target.matches('.catalog-subcategory-filter__checkbox')) {
            return;
        }
        if (!resultsEl.contains(e.target) || subcategoryFilterScrollY === null) {
            return;
        }
        restoreSubcategoryFilterScrollY(subcategoryFilterScrollY);
    }, true);

    resultsEl.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-catalog-subcategory-filter]');
        if (!form || !resultsEl.contains(form)) {
            return;
        }
        e.preventDefault();
        var fd = new FormData(form);
        var params = new URLSearchParams();
        fd.forEach(function (value, key) {
            if (value === '') {
                return;
            }
            params.append(key, value);
        });
        var base = resultsEl.getAttribute('data-catalog-base') || '/catalog';
        var qs = params.toString();
        // Застосування фільтрів лишає екран на місці — не перекидає до товарів
        fetchCatalog(qs ? base + '?' + qs : base, true, { scrollToCatalog: false });
    });

    function syncCatalogFromCurrentUrl() {
        syncCatalogUrlState(window.location.href, {});
        if (catalogListingFooter()) {
            updateCatalogShownInfo();
        }
    }

    window.addEventListener('pageshow', function (event) {
        if (resultsEl) {
            resultsEl.classList.remove('catalog-results--loading');
        }
        if (!event.persisted) {
            return;
        }
        catalogRestoredFromCache = true;
        window.zoogleBackNavigation = true;
        if (catalogFetchAbort) {
            try {
                catalogFetchAbort.abort();
            } catch (backAbortErr) { /* ignore */ }
            catalogFetchAbort = null;
        }
        if (typeof window.zoogleReapplyShopStyles === 'function') {
            window.zoogleReapplyShopStyles();
        }
        if (isCatalogListingPage()) {
            syncCatalogFromCurrentUrl();
        }
        if (typeof window.zoogleRestorePageVisuals === 'function') {
            window.zoogleRestorePageVisuals();
        }
        window.setTimeout(function () {
            catalogRestoredFromCache = false;
            window.zoogleBackNavigation = false;
        }, 800);
    });

    window.addEventListener('pagehide', function () {
        if (catalogFetchAbort) {
            try {
                catalogFetchAbort.abort();
            } catch (hideAbortErr) { /* ignore */ }
            catalogFetchAbort = null;
        }
        if (resultsEl) {
            resultsEl.classList.remove('catalog-results--loading');
        }
    });

    window.addEventListener('popstate', function (event) {
        var state = event.state;
        /* Chrome кидає popstate раніше за pageshow. Чекаємо, чи це повернення з пам’яті, і тоді сітку не перезапитуємо. */
        window.setTimeout(function () {
            if (catalogRestoredFromCache || window.zoogleBackNavigation) {
                catalogRestoredFromCache = false;
                window.zoogleBackNavigation = false;
                if (resultsEl) {
                    resultsEl.classList.remove('catalog-results--loading');
                }
                if (typeof window.zoogleReapplyShopStyles === 'function') {
                    window.zoogleReapplyShopStyles();
                }
                if (isCatalogListingPage()) {
                    syncCatalogFromCurrentUrl();
                }
                if (typeof window.zoogleResetAllCategoryExpandState === 'function') {
                    window.zoogleResetAllCategoryExpandState();
                }
                if (typeof window.zoogleExpandActiveCategoryPath === 'function') {
                    window.zoogleExpandActiveCategoryPath(true);
                }
                if (typeof window.zoogleRevealPendingPhotos === 'function') {
                    window.zoogleRevealPendingPhotos();
                }
                if (typeof window.zoogleRestorePageVisuals === 'function') {
                    window.zoogleRestorePageVisuals();
                }
                return;
            }
            if (!isCatalogListingPage()) {
                /* Не reload() на головній після «Назад»: це ламає bfcache і інколи лишає порожній візуал. */
                if (resultsEl.classList.contains('catalog-results--listing')) {
                    window.location.reload();
                }
                return;
            }
            if (!state || !state.catalog) {
                syncCatalogFromCurrentUrl();
                return;
            }
            fetchCatalog(window.location.href, false);
        }, 200);
    });

    if (catalogListingFooter()) {
        updateCatalogShownInfo();
    }

    ensureCatalogSubcategoryFilterStyles();

    (function scrollToListingPanelOnLoad() {
        if (!isCatalogListingPage() || !resultsEl) {
            return;
        }
        if (resultsEl.getAttribute('data-scroll-to-listing') !== '1') {
            return;
        }
        var preferSubcategoryFilter = false;
        try {
            var categoryParam = new URL(window.location.href).searchParams.get('category');
            preferSubcategoryFilter = categoryParam !== null && categoryParam !== '' && categoryParam !== '0';
        } catch (categoryParseErr) { /* ignore */ }
        scheduleScrollToCatalogResults(preferSubcategoryFilter);
        if (document.readyState !== 'complete') {
            window.addEventListener('load', function () {
                scheduleScrollToCatalogResults(preferSubcategoryFilter);
            }, { once: true });
        }
    })();

})();
