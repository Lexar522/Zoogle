(function () {
    /** Після такого горизонтального зсуву (px) увімкнути capture + скрол; до цього click по товару лишається звичайним */
    var DRAG_COMMIT_PX = 6;

    function readTrackGapPx(track) {
        if (!track) {
            return 16;
        }
        try {
            var cs = window.getComputedStyle(track);
            var raw = cs.gap;
            if (!raw || raw === 'normal') {
                raw = cs.columnGap;
            }
            if (!raw || raw === 'normal') {
                raw = cs.getPropertyValue('--home-carousel-gap') || '16px';
            }
            var n = parseFloat(String(raw), 10);
            return isNaN(n) || n < 0 ? 16 : n;
        } catch (err) {
            return 16;
        }
    }

    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function initCarousel(root) {
        var viewport = root.querySelector('.home-product-carousel__viewport');
        var prev = root.querySelector('.home-product-carousel__btn--prev');
        var next = root.querySelector('.home-product-carousel__btn--next');
        if (!viewport || !prev || !next) return;

        var track = viewport.querySelector('.home-product-carousel__track');
        var isMobileFeedRoot = root.classList.contains('home-product-carousel--mobile-feed');
        var mqGrid = window.matchMedia('(max-width: 768px)');
        /** Ширина першого «ряду» карток; після клонів scrollLeft зміщуємо назад на це — без видимого стрибка */
        var firstSetWidth = 0;

        var autoTimer = null;
        var AUTO_INTERVAL_MS = 5000;
        var wasScrollable = false;

        function measureLoopWidth() {
            if (!track) return;
            var originals = track.querySelectorAll('.home-product-carousel__cell:not([data-home-carousel-clone])');
            if (!originals.length) {
                firstSetWidth = 0;
                return;
            }
            var n = originals.length;
            var gapPx = readTrackGapPx(track);
            var sum = 0;
            for (var i = 0; i < n; i++) {
                sum += originals[i].offsetWidth;
                if (i < n - 1) {
                    sum += gapPx;
                }
            }
            sum = Math.round(sum);
            var fc = track.querySelector('.home-product-carousel__cell[data-home-carousel-clone]');
            /* Межа першого сету = відстань до початку клонів. Без Math.round на геометрії — заниження межі давало зайве віднімання (стрибок на картку). */
            var fromGeom = 0;
            if (fc && track.getBoundingClientRect) {
                var tr = track.getBoundingClientRect();
                var cr = fc.getBoundingClientRect();
                fromGeom = cr.left - tr.left;
            }
            var fromOffset = fc ? fc.offsetLeft : 0;
            /* Узгоджено з CSS gap: сума з readTrackGapPx; геометрію клонів беремо першою — інакше старий фіксований gap ламав normalize на 12px мобільному відступі */
            if (fc && fromGeom > 0.5) {
                firstSetWidth = Math.round(fromGeom);
            } else if (fc && fromOffset > 0.5) {
                firstSetWidth = Math.round(fromOffset);
            } else {
                firstSetWidth = sum;
            }
        }

        /** Якщо мало товарів, один набір клонів не дає scrollWidth > viewport — безкінечний цикл не працює; дублюємо набір клонів. */
        function ensureTrackLongEnoughForLoop() {
            if (!track || !viewport) {
                return;
            }
            measureLoopWidth();
            if (firstSetWidth <= 0) {
                return;
            }
            var cw = viewport.clientWidth || 0;
            if (cw <= 0) {
                return;
            }
            var minTotal = firstSetWidth + cw + 32;
            var guard = 0;
            while (track.scrollWidth < minTotal && guard < 20) {
                var originals = track.querySelectorAll('.home-product-carousel__cell:not([data-home-carousel-clone])');
                if (!originals.length) {
                    return;
                }
                originals.forEach(function (cell) {
                    var c = cell.cloneNode(true);
                    c.setAttribute('data-home-carousel-clone', '1');
                    track.appendChild(c);
                });
                guard++;
                measureLoopWidth();
            }
            viewport.querySelectorAll('img').forEach(function (el) {
                el.setAttribute('draggable', 'false');
            });
        }

        function buildLoopSlides() {
            if (!track) return;
            if (track.querySelector('.home-product-carousel__cell[data-home-carousel-clone]')) {
                measureLoopWidth();
                return;
            }
            var originals = track.querySelectorAll('.home-product-carousel__cell');
            if (originals.length === 0) return;
            originals.forEach(function (cell) {
                var c = cell.cloneNode(true);
                c.setAttribute('data-home-carousel-clone', '1');
                track.appendChild(c);
            });
            measureLoopWidth();
            viewport.querySelectorAll('img').forEach(function (el) {
                el.setAttribute('draggable', 'false');
            });
        }

        function normalizeLoopPosition() {
            if (firstSetWidth <= 0) return;
            var guard = 0;
            while (viewport.scrollLeft >= firstSetWidth && guard < 10) {
                viewport.scrollLeft -= firstSetWidth;
                guard++;
            }
        }

        /** Після normalize вирівняти до сітки кроку — інакше scroll-snap: proximity після зняття is-suppress-snap тягне на попередню картку */
        function quantizeScrollToStep() {
            var step = getStep();
            if (step <= 0 || firstSetWidth <= 0) return;
            var sl = viewport.scrollLeft;
            var maxIdx = Math.max(0, Math.floor((firstSetWidth - 1) / step));
            var idx = Math.round(sl / step);
            if (idx > maxIdx) {
                idx = maxIdx;
            }
            if (idx < 0) {
                idx = 0;
            }
            var snapped = idx * step;
            var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
            if (snapped > maxScroll) {
                snapped = maxScroll;
            }
            if (Math.abs(snapped - sl) > 0.5) {
                viewport.scrollLeft = snapped;
            }
        }

        function applyLoopSync() {
            normalizeLoopPosition();
            quantizeScrollToStep();
        }

        function syncLoopScroll() {
            if (firstSetWidth <= 0) return;
            if (viewport.scrollLeft < firstSetWidth) return;
            viewport.classList.add('is-suppress-snap');
            applyLoopSync();
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    viewport.classList.remove('is-suppress-snap');
                });
            });
        }

        function getStep() {
            var cell = viewport.querySelector('.home-product-carousel__cell');
            if (!cell) return 0;
            return cell.offsetWidth + readTrackGapPx(track);
        }

        function setScrollable() {
            ensureTrackLongEnoughForLoop();
            measureLoopWidth();
            var overflow = viewport.scrollWidth > viewport.clientWidth + 2;
            root.classList.toggle('home-product-carousel--scrollable', overflow);
            if (!overflow) {
                stopAutoAdvance();
                wasScrollable = false;
                prev.setAttribute('disabled', 'disabled');
                next.setAttribute('disabled', 'disabled');
                prev.setAttribute('aria-disabled', 'true');
                next.setAttribute('aria-disabled', 'true');
                return;
            }
            updateEdges();
            if (!wasScrollable) {
                wasScrollable = true;
                startAutoAdvance();
            }
        }

        function updateEdges() {
            if (!root.classList.contains('home-product-carousel--scrollable')) return;
            /* Зациклення: кнопки завжди активні, якщо є що гортати */
            prev.disabled = false;
            next.disabled = false;
            prev.setAttribute('aria-disabled', 'false');
            next.setAttribute('aria-disabled', 'false');
        }

        /** Плавніша прокрутка за кнопками/стрілками, ніж один scroll-behavior: smooth у браузера */
        var SMOOTH_MS = 540;
        var smoothAnimRaf = null;

        function easeOutCubic(t) {
            return 1 - Math.pow(1 - t, 3);
        }

        function smoothScrollViewportTo(targetLeft) {
            if (smoothAnimRaf !== null) {
                cancelAnimationFrame(smoothAnimRaf);
                smoothAnimRaf = null;
                viewport.classList.remove('is-suppress-snap');
            }
            viewport.classList.add('is-suppress-snap');
            /* Початкова позиція в «першій половині» + вирівнювання до сітки — інакше snap після анімації зміщує на картку */
            applyLoopSync();
            var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
            targetLeft = Math.max(0, Math.min(maxScroll, targetLeft));
            if (prefersReducedMotion()) {
                viewport.scrollLeft = targetLeft;
                applyLoopSync();
                requestAnimationFrame(function () {
                    viewport.classList.remove('is-suppress-snap');
                });
                updateEdges();
                return;
            }
            var start = viewport.scrollLeft;
            var delta = targetLeft - start;
            if (Math.abs(delta) < 0.5) {
                applyLoopSync();
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        viewport.classList.remove('is-suppress-snap');
                    });
                });
                updateEdges();
                return;
            }
            var startTime = null;
            function step(timestamp) {
                if (!startTime) {
                    startTime = timestamp;
                }
                var elapsed = timestamp - startTime;
                var t = Math.min(1, elapsed / SMOOTH_MS);
                var newLeft = start + delta * easeOutCubic(t);
                viewport.scrollLeft = newLeft;
                /* Під час анімації sync не викликається з події scroll — нормалізуємо тут і зміщуємо базу, щоб не «їхати» по другому колу */
                if (firstSetWidth > 0) {
                    var guard = 0;
                    while (viewport.scrollLeft >= firstSetWidth && guard < 10) {
                        viewport.scrollLeft -= firstSetWidth;
                        start -= firstSetWidth;
                        targetLeft -= firstSetWidth;
                        delta = targetLeft - start;
                        guard++;
                    }
                }
                if (t < 1) {
                    smoothAnimRaf = requestAnimationFrame(step);
                } else {
                    smoothAnimRaf = null;
                    viewport.scrollLeft = start + delta;
                    applyLoopSync();
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            viewport.classList.remove('is-suppress-snap');
                            updateEdges();
                        });
                    });
                }
            }
            smoothAnimRaf = requestAnimationFrame(step);
        }

        /** Крок відносно поточної позиції після normalize (інакше біля maxScroll target рахують з «сирих» scrollLeft у зоні клонів) */
        function smoothScrollViewportDelta(delta) {
            if (smoothAnimRaf !== null) {
                cancelAnimationFrame(smoothAnimRaf);
                smoothAnimRaf = null;
                viewport.classList.remove('is-suppress-snap');
            }
            viewport.classList.add('is-suppress-snap');
            applyLoopSync();
            var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
            var targetLeft = Math.max(0, Math.min(maxScroll, viewport.scrollLeft + delta));
            smoothScrollViewportTo(targetLeft);
        }

        function scrollByStep(dir) {
            var step = getStep();
            if (step <= 0) return;
            var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
            if (maxScroll <= 1) return;
            if (dir > 0) {
                smoothScrollViewportDelta(step);
                return;
            }
            if (dir < 0 && viewport.scrollLeft <= 2) {
                if (firstSetWidth <= 0 || !track) {
                    smoothScrollViewportTo(maxScroll);
                    return;
                }
                var originals = track.querySelectorAll('.home-product-carousel__cell:not([data-home-carousel-clone])');
                var lastOrig = originals.length ? originals[originals.length - 1] : null;
                var target = lastOrig ? lastOrig.offsetLeft : maxScroll;
                smoothScrollViewportTo(Math.min(target, maxScroll));
                return;
            }
            smoothScrollViewportDelta(dir * step);
        }

        function stopAutoAdvance() {
            if (autoTimer !== null) {
                clearInterval(autoTimer);
                autoTimer = null;
            }
        }

        function tickAutoAdvance() {
            if (!root.classList.contains('home-product-carousel--scrollable')) return;
            if (prefersReducedMotion()) return;
            if (root.matches(':hover') || root.matches(':focus-within')) return;
            if (smoothAnimRaf !== null) return;
            var step = getStep();
            if (step <= 0) return;
            var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
            if (maxScroll <= 1) return;
            smoothScrollViewportDelta(step);
        }

        function startAutoAdvance() {
            stopAutoAdvance();
            if (prefersReducedMotion()) return;
            if (!root.classList.contains('home-product-carousel--scrollable')) return;
            autoTimer = setInterval(tickAutoAdvance, AUTO_INTERVAL_MS);
        }

        function isGridMode() {
            return isMobileFeedRoot && mqGrid.matches;
        }

        function removeCarouselClones() {
            if (!track) {
                return;
            }
            track.querySelectorAll('.home-product-carousel__cell[data-home-carousel-clone]').forEach(function (el) {
                el.remove();
            });
            firstSetWidth = 0;
        }

        function applyA11yMode() {
            if (!isMobileFeedRoot) {
                return;
            }
            var carouselDesc = root.getAttribute('data-carousel-roledescription');
            var listDesc = root.getAttribute('data-list-roledescription');
            if (isGridMode()) {
                if (listDesc) {
                    root.setAttribute('aria-roledescription', listDesc);
                } else {
                    root.removeAttribute('aria-roledescription');
                }
            } else if (carouselDesc) {
                root.setAttribute('aria-roledescription', carouselDesc);
            }
        }

        function enterGridMode() {
            stopAutoAdvance();
            if (smoothAnimRaf !== null) {
                cancelAnimationFrame(smoothAnimRaf);
                smoothAnimRaf = null;
            }
            removeCarouselClones();
            viewport.scrollLeft = 0;
            viewport.classList.remove('is-dragging', 'is-suppress-snap');
            root.classList.remove('home-product-carousel--scrollable');
            wasScrollable = false;
            prev.disabled = true;
            next.disabled = true;
            prev.setAttribute('aria-disabled', 'true');
            next.setAttribute('aria-disabled', 'true');
            applyA11yMode();
        }

        function enterCarouselMode() {
            buildLoopSlides();
            ensureTrackLongEnoughForLoop();
            measureLoopWidth();
            setScrollable();
            applyA11yMode();
        }

        function refreshMode() {
            if (isMobileFeedRoot) {
                enterGridMode();
                return;
            }
            if (isGridMode()) {
                enterGridMode();
                return;
            }
            enterCarouselMode();
        }

        if (typeof mqGrid.addEventListener === 'function') {
            mqGrid.addEventListener('change', refreshMode);
        } else if (typeof mqGrid.addListener === 'function') {
            mqGrid.addListener(refreshMode);
        }

        prev.addEventListener('click', function () {
            scrollByStep(-1);
        });
        next.addEventListener('click', function () {
            scrollByStep(1);
        });

        viewport.addEventListener('keydown', function (e) {
            if (isGridMode()) {
                return;
            }
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                scrollByStep(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                scrollByStep(1);
            }
        });

        var scrollEdgesRaf = null;
        viewport.addEventListener(
            'scroll',
            function () {
                if (scrollEdgesRaf) return;
                scrollEdgesRaf = requestAnimationFrame(function () {
                    scrollEdgesRaf = null;
                    if (smoothAnimRaf === null) {
                        syncLoopScroll();
                    }
                    updateEdges();
                });
            },
            { passive: true }
        );

        if (typeof ResizeObserver !== 'undefined') {
            var ro = new ResizeObserver(function () {
                refreshMode();
            });
            ro.observe(viewport);
        }
        window.addEventListener('resize', function () {
            refreshMode();
        });

        /* Drag-to-scroll: НЕ ставити setPointerCapture на pointerdown — інакше click по <a> не спрацьовує. Capture лише після DRAG_COMMIT_PX. */
        viewport.querySelectorAll('img').forEach(function (el) {
            el.setAttribute('draggable', 'false');
        });
        viewport.querySelectorAll('a.product-card__link-overlay').forEach(function (el) {
            el.setAttribute('draggable', 'false');
        });

        var dragStartX = 0;
        var dragStartScroll = 0;
        var dragging = false;
        var dragCommitted = false;
        var activePointerId = null;

        function isInteractiveTarget(t) {
            return t && t.closest && t.closest('button, input, select, textarea, [role="button"], label');
        }

        function detachDocListeners() {
            document.removeEventListener('pointermove', onPointerMove);
            document.removeEventListener('pointerup', onPointerUpDoc, true);
            document.removeEventListener('pointercancel', onPointerUpDoc, true);
        }

        function onPointerMove(e) {
            if (!dragging || e.pointerId !== activePointerId) return;
            var dx = e.clientX - dragStartX;
            if (!dragCommitted && Math.abs(dx) >= DRAG_COMMIT_PX) {
                dragCommitted = true;
                if (smoothAnimRaf !== null) {
                    cancelAnimationFrame(smoothAnimRaf);
                    smoothAnimRaf = null;
                }
                try {
                    viewport.setPointerCapture(e.pointerId);
                } catch (err) {}
                viewport.classList.add('is-dragging');
            }
            if (dragCommitted) {
                viewport.scrollLeft = dragStartScroll - dx;
            }
        }

        function endDrag() {
            if (!dragging) return;
            detachDocListeners();
            if (dragCommitted && activePointerId !== null) {
                try {
                    viewport.releasePointerCapture(activePointerId);
                } catch (err) {}
            }
            dragging = false;
            activePointerId = null;
            viewport.classList.remove('is-dragging');
            if (dragCommitted) {
                viewport.addEventListener(
                    'click',
                    function blockNav(ev) {
                        var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
                        if (a && viewport.contains(a)) {
                            ev.preventDefault();
                            ev.stopPropagation();
                        }
                        viewport.removeEventListener('click', blockNav, true);
                    },
                    true
                );
            }
            dragCommitted = false;
            updateEdges();
        }

        function onPointerUpDoc(e) {
            if (!dragging) return;
            if (e.pointerId !== activePointerId) return;
            endDrag();
        }

        function onPointerDown(e) {
            if (isGridMode()) {
                return;
            }
            if (e.button !== undefined && e.button !== 0) return;
            if (e.pointerType !== 'mouse' && e.pointerType !== 'pen') return;
            if (isInteractiveTarget(e.target)) return;
            if (!viewport.contains(e.target)) return;
            dragging = true;
            dragCommitted = false;
            dragStartX = e.clientX;
            dragStartScroll = viewport.scrollLeft;
            activePointerId = e.pointerId;
            document.addEventListener('pointermove', onPointerMove, { passive: true });
            document.addEventListener('pointerup', onPointerUpDoc, true);
            document.addEventListener('pointercancel', onPointerUpDoc, true);
        }

        root.addEventListener('pointerdown', onPointerDown, true);

        refreshMode();
    }

    function boot() {
        document.querySelectorAll('[data-home-carousel]').forEach(function (el) {
            initCarousel(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
