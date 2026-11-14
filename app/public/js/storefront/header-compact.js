(function () {
    var header = document.querySelector('.site-header');
    if (!header) return;
    var heroEl = header.querySelector('[data-site-header-hero]');
    var compactBarEl = header.querySelector('[data-site-header-compact-bar]');
    var forceCompact = document.body && document.body.dataset.forceHeaderCompact === '1';

    var COMPACT_STICKY_FALLBACK_PX = 92;
    var MAX_COMPACT_STICKY_BAR_PX = 160;
    var resizeObserverInst = null;
    var roStickyPending = false;
    var lastStickyOffsetPx = -1;
    var lastCompactStickyHeightPx = -1;

    var headerMbCache = null;
    var heroEndY = 0;
    function refreshHeroEndY() {
        heroEndY = heroEl ? (heroEl.offsetTop + heroEl.offsetHeight) : 0;
    }
    function headerMarginBottom() {
        if (headerMbCache === null) {
            headerMbCache = parseFloat(getComputedStyle(header).marginBottom) || 0;
        }
        return headerMbCache;
    }

    function heroScrollEndY() {
        if (!heroEl) return 0;
        return heroEl.offsetTop + heroEl.offsetHeight;
    }

    function setCompactStickyHeight(px) {
        var next = Math.max(0, Math.ceil(px || 0));
        if (next === lastCompactStickyHeightPx) return;
        lastCompactStickyHeightPx = next;
        header.style.setProperty('--site-header-compact-sticky-height', next + 'px');
    }

    function updateCompactBarFromScroll() {
        if (forceCompact) {
            header.classList.add('site-header--compact-visible');
            if (compactBarEl) compactBarEl.setAttribute('aria-hidden', 'false');
            return;
        }
        var y = window.scrollY || 0;
        var end = heroScrollEndY();
        var show = y >= end - 1;
        if (show) {
            if (!header.classList.contains('site-header--compact-visible')) {
                header.classList.add('site-header--compact-visible');
                if (compactBarEl) compactBarEl.setAttribute('aria-hidden', 'false');
            }
        } else if (header.classList.contains('site-header--compact-visible')) {
            header.classList.remove('site-header--compact-visible');
            if (compactBarEl) compactBarEl.setAttribute('aria-hidden', 'true');
        }
    }

    function applyStickyOffsetFromHeader() {
        var compactBarOn = forceCompact || header.classList.contains('site-header--compact-visible');
        var headerRect = header.getBoundingClientRect();
        var mb = compactBarOn ? 0 : headerMarginBottom();

        if (forceCompact && compactBarEl) {
            var sr = compactBarEl.getBoundingClientRect();
            var ch = Math.ceil(sr.height);
            if (ch > 0) {
                if (ch <= MAX_COMPACT_STICKY_BAR_PX) {
                    setCompactStickyHeight(ch);
                } else {
                    setCompactStickyHeight(
                        lastCompactStickyHeightPx > 0 && lastCompactStickyHeightPx <= MAX_COMPACT_STICKY_BAR_PX
                            ? lastCompactStickyHeightPx
                            : COMPACT_STICKY_FALLBACK_PX
                    );
                }
            }
        } else if (!forceCompact) {
            header.style.removeProperty('--site-header-compact-sticky-height');
            lastCompactStickyHeightPx = -1;
        }

        var px = Math.ceil(headerRect.height + mb);
        if (px > 0) {
            if (!compactBarOn && px < 180) px = 200;
            if (px === lastStickyOffsetPx) return;
            lastStickyOffsetPx = px;
            document.documentElement.style.setProperty('--header-sticky-offset', px + 'px');
        }
    }

    function scheduleStickyFromResizeOrRo() {
        if (roStickyPending) return;
        roStickyPending = true;
        requestAnimationFrame(function () {
            roStickyPending = false;
            refreshHeroEndY();
            applyStickyOffsetFromHeader();
        });
    }

    function syncStickyOffsetNow() {
        refreshHeroEndY();
        applyStickyOffsetFromHeader();
        requestAnimationFrame(function () {
            refreshHeroEndY();
            applyStickyOffsetFromHeader();
        });
    }

    function onScrollHeaderAll() {
        if (forceCompact) {
            return;
        }
        var show = (window.scrollY || 0) >= heroEndY - 1;
        var shown = header.classList.contains('site-header--compact-visible');
        if (show === shown) {
            return;
        }
        header.classList.toggle('site-header--compact-visible', show);
        if (compactBarEl) {
            compactBarEl.setAttribute('aria-hidden', show ? 'false' : 'true');
        }
        applyStickyOffsetFromHeader();
    }

    if (forceCompact) {
        header.classList.add('site-header--compact-visible');
        if (compactBarEl) compactBarEl.setAttribute('aria-hidden', 'false');
    } else {
        updateCompactBarFromScroll();
    }
    syncStickyOffsetNow();

    if (typeof ResizeObserver !== 'undefined') {
        resizeObserverInst = new ResizeObserver(scheduleStickyFromResizeOrRo);
        resizeObserverInst.observe(header);
    } else {
        window.addEventListener('scroll', scheduleStickyFromResizeOrRo, { passive: true });
    }
    window.addEventListener('resize', function () {
        headerMbCache = null;
        scheduleStickyFromResizeOrRo();
    }, { passive: true });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', function () {
            headerMbCache = null;
            scheduleStickyFromResizeOrRo();
        }, { passive: true });
    }
    window.addEventListener('scroll', onScrollHeaderAll, { passive: true });
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) {
            return;
        }
        headerMbCache = null;
        lastStickyOffsetPx = -1;
        lastCompactStickyHeightPx = -1;
        if (forceCompact) {
            header.classList.add('site-header--compact-visible');
            if (compactBarEl) compactBarEl.setAttribute('aria-hidden', 'false');
        } else {
            updateCompactBarFromScroll();
        }
        syncStickyOffsetNow();
    });
})();

