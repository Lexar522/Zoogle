@extends('layouts.shop')

@section('title', 'Каталог — ZOOGLE')

@section('header_bottom')
    @include('catalog.partials.category-filters', ['inHeader' => true])
@endsection

@section('content')
    <div
        id="catalog-results"
        class="catalog-results"
        data-catalog-base="{{ route('catalog.index') }}"
        data-favorite-ids="{{ e(json_encode($favoriteProductIds ?? [])) }}"
    >
        @include('catalog.partials.results')
    </div>
@endsection

@push('scripts')
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
    var EXCERPT_EXPAND_DELAY_MS = 2000;

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

    function bindProductCardExcerptHover(scope) {
        if (!scope) return;
        scope.querySelectorAll('.product-card-shell--expandable').forEach(function (shell) {
            if (shell.dataset.excerptHoverBound === '1') return;
            shell.dataset.excerptHoverBound = '1';
            var card = shell.querySelector('.product-card');
            if (!card) return;
            var excerpt = card.querySelector('.product-card__excerpt');
            if (!excerpt) {
                shell.classList.remove('product-card-shell--expandable');
                return;
            }
            var collapseTimer = null;
            var hoverTimer = null;
            var shellMinTimer = null;
            var excerptCollapseCommitted = false;
            var isHoveringCard = false;

            function syncExcerptTruncationState() {
                var isTruncated = Math.ceil(excerpt.scrollHeight) > Math.ceil(excerpt.clientHeight) + 1;
                excerpt.classList.toggle('product-card__excerpt--truncated', isTruncated);
                shell.classList.toggle('product-card-shell--expandable', isTruncated);
                return isTruncated;
            }

            syncExcerptTruncationState();

            function cleanupShellMinAnim() {
                if (shellMinTimer) {
                    window.clearTimeout(shellMinTimer);
                    shellMinTimer = null;
                }
                shell.removeEventListener('transitionend', onShellTransitionEnd);
                shell.classList.remove('product-card-shell--minh-anim');
                shell.style.minHeight = '';
            }

            function onShellTransitionEnd(e) {
                if (!e || e.target !== shell) return;
                if (e.propertyName !== 'min-height' && e.propertyName !== 'minHeight') return;
                cleanupShellMinAnim();
            }

            function afterExcerptCollapsed() {
                if (excerptCollapseCommitted) return;
                excerptCollapseCommitted = true;
                if (collapseTimer) {
                    window.clearTimeout(collapseTimer);
                    collapseTimer = null;
                }
                if (excerpt) {
                    excerpt.removeEventListener('transitionend', onExcerptTransitionEnd);
                }

                var collapsedH = Math.ceil(card.getBoundingClientRect().height);
                var currentH = shell.offsetHeight;
                if (Math.abs(currentH - collapsedH) <= 1) {
                    cleanupShellMinAnim();
                    return;
                }

                shell.classList.add('product-card-shell--minh-anim');
                shell.style.minHeight = currentH + 'px';
                shell.offsetHeight;
                shell.style.minHeight = collapsedH + 'px';

                shell.addEventListener('transitionend', onShellTransitionEnd);
                shellMinTimer = window.setTimeout(cleanupShellMinAnim, 950);
            }

            function onExcerptTransitionEnd(e) {
                if (!e || e.target !== excerpt || e.propertyName !== 'max-height') return;
                afterExcerptCollapsed();
            }

            card.addEventListener('mouseenter', function () {
                isHoveringCard = true;
                excerptCollapseCommitted = false;
                cleanupShellMinAnim();
                if (hoverTimer) {
                    window.clearTimeout(hoverTimer);
                    hoverTimer = null;
                }
                if (collapseTimer) {
                    window.clearTimeout(collapseTimer);
                    collapseTimer = null;
                }
                if (excerpt) {
                    excerpt.removeEventListener('transitionend', onExcerptTransitionEnd);
                }
                if (!syncExcerptTruncationState()) return;
                hoverTimer = window.setTimeout(function () {
                    hoverTimer = null;
                    if (!isHoveringCard) return;
                    if (!syncExcerptTruncationState()) return;
                    shell.style.minHeight = shell.offsetHeight + 'px';
                    shell.classList.add('product-card-shell--hover');
                }, EXCERPT_EXPAND_DELAY_MS);
            });
            card.addEventListener('mouseleave', function () {
                isHoveringCard = false;
                if (hoverTimer) {
                    window.clearTimeout(hoverTimer);
                    hoverTimer = null;
                }
                if (!shell.classList.contains('product-card-shell--hover')) return;
                excerptCollapseCommitted = false;
                var expandedH = Math.ceil(card.getBoundingClientRect().height);
                shell.style.minHeight = Math.max(expandedH, shell.offsetHeight) + 'px';
                shell.classList.remove('product-card-shell--hover');
                if (!excerpt) {
                    shell.style.minHeight = '';
                    return;
                }
                excerpt.addEventListener('transitionend', onExcerptTransitionEnd);
                collapseTimer = window.setTimeout(afterExcerptCollapsed, 950);
            });
        });
    }

    var serverFavoriteIds = new Set();

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

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.product-card__favorite');
        if (!btn || !document.getElementById('catalog-results') || !document.getElementById('catalog-results').contains(btn)) return;
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
                });
            return;
        }
        var on = toggleFav(lid, vid);
        btn.classList.toggle('is-favorite', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });

    var resultsEl = document.getElementById('catalog-results');
    if (!resultsEl) return;

    try {
        JSON.parse(resultsEl.getAttribute('data-favorite-ids') || '[]').forEach(function (id) {
            serverFavoriteIds.add(Number(id));
        });
    } catch (err) { /* ignore */ }

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
                        syncFavoriteButtons(resultsEl);
                    });
            }
        } catch (e2) { /* ignore */ }
    }

    syncFavoriteButtons(resultsEl);
    bindProductCardExcerptHover(resultsEl);

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
        var form = document.querySelector('.catalog-search__form');
        if (!form) return;
        var base = resultsEl.getAttribute('data-catalog-base') || '/catalog';

        var hidden = form.querySelector('input[name="category"]');
        if (cat) {
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'category';
                form.insertBefore(hidden, form.firstChild);
            }
            hidden.value = cat;
        } else         if (hidden) {
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

        var qInput = form.querySelector('#q');
        if (qInput) qInput.value = qVal;

        var resetLink = form.querySelector('a.btn.secondary');
        if (resetLink) {
            var rp = new URLSearchParams();
            if (cat) rp.set('category', cat);
            if (u.searchParams.get('on_sale') === '1') rp.set('on_sale', '1');
            resetLink.href = rp.toString() ? base + '?' + rp.toString() : base;
        }
    }

    function fetchCatalog(url, pushState) {
        if (pushState === undefined) pushState = true;
        resultsEl.classList.add('catalog-results--loading');
        fetch(url, { headers: FRAGMENT_HEADERS, credentials: 'same-origin' })
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
                syncFavoriteButtons(resultsEl);
                bindProductCardExcerptHover(resultsEl);
                if (pushState) {
                    try { history.pushState({ catalog: true }, '', url); } catch (err) { window.location.href = url; }
                }
                var u = new URL(url, window.location.origin);
                var catParam = u.searchParams.get('category');
                syncCategoryFiltersActive(catParam && catParam !== '0' ? catParam : '0');
                syncOnSaleFilterActive(u.searchParams.get('on_sale') === '1');
                syncSearchFormFromUrl(url);
            })
            .catch(function () {
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
        var a = e.target.closest('a.shop-pagination__link');
        if (!a || !a.href) return;
        e.preventDefault();
        fetchCatalog(a.href);
    });

    var searchForm = document.querySelector('.catalog-search__form');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(searchForm);
            var params = new URLSearchParams(fd);
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
    }

    window.addEventListener('popstate', function () {
        fetchCatalog(window.location.href, false);
    });

})();
</script>
@endpush
