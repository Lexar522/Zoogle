(function () {
    function initHeaderBannerCarousel(root) {
        if (!root || root.dataset.headerBannerReady === '1') {
            return;
        }

        var wrap = root.closest('.catalog-header-banner-carousel-wrap') || root.parentElement;
        var viewport = root.querySelector('.catalog-header-banner-carousel__viewport');
        var track = root.querySelector('[data-header-banner-track]');
        var slides = root.querySelectorAll('[data-header-banner-slide]');
        if (!viewport || !track || slides.length < 2) {
            return;
        }

        root.dataset.headerBannerReady = '1';

        var index = 0;
        var timer = null;
        var autoplayMs = parseInt(root.getAttribute('data-header-banner-autoplay') || '5000', 10);
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var touchStartX = 0;
        var touchStartY = 0;
        var touchDeltaX = 0;
        var isSwiping = false;
        var pointerActive = false;
        var suppressClick = false;
        var swipeThreshold = 40;

        function dots() {
            return wrap ? wrap.querySelectorAll('[data-header-banner-dot]') : root.querySelectorAll('[data-header-banner-dot]');
        }

        function setActive(nextIndex) {
            index = (nextIndex + slides.length) % slides.length;
            track.style.transform = 'translateX(' + (-index * 100) + '%)';
            dots().forEach(function (dot, dotIndex) {
                var active = dotIndex === index;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function setActiveWithDragOffset(dragPx) {
            var width = viewport.offsetWidth || 1;
            var pct = (-index * 100) + (dragPx / width * 100);
            track.style.transform = 'translateX(' + pct + '%)';
        }

        function stopAutoplay() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function startAutoplay() {
            stopAutoplay();
            if (reducedMotion || autoplayMs < 1000) {
                return;
            }
            timer = window.setInterval(function () {
                setActive(index + 1);
            }, autoplayMs);
        }

        root.querySelector('[data-header-banner-prev]')?.addEventListener('click', function () {
            setActive(index - 1);
            startAutoplay();
        });

        root.querySelector('[data-header-banner-next]')?.addEventListener('click', function () {
            setActive(index + 1);
            startAutoplay();
        });

        dots().forEach(function (dot) {
            dot.addEventListener('click', function () {
                var target = parseInt(dot.getAttribute('data-header-banner-dot') || '0', 10);
                setActive(target);
                startAutoplay();
            });
        });

        root.addEventListener('mouseenter', stopAutoplay);
        root.addEventListener('mouseleave', startAutoplay);
        root.addEventListener('focusin', stopAutoplay);
        root.addEventListener('focusout', startAutoplay);
        if (wrap) {
            wrap.addEventListener('mouseenter', stopAutoplay);
            wrap.addEventListener('mouseleave', startAutoplay);
            wrap.addEventListener('focusin', stopAutoplay);
            wrap.addEventListener('focusout', startAutoplay);
        }

        slides.forEach(function (slide) {
            slide.addEventListener('click', function (event) {
                if (suppressClick) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });
        });

        function pointerClient(event) {
            if (event.touches && event.touches[0]) {
                return { x: event.touches[0].clientX, y: event.touches[0].clientY };
            }
            return { x: event.clientX, y: event.clientY };
        }

        function onSwipeStart(event) {
            if (event.touches && event.touches.length !== 1) {
                return;
            }
            var point = pointerClient(event);
            touchStartX = point.x;
            touchStartY = point.y;
            touchDeltaX = 0;
            isSwiping = false;
            pointerActive = true;
            stopAutoplay();
        }

        function onSwipeMove(event) {
            if (!pointerActive) {
                return;
            }
            if (event.pointerType === 'mouse' && event.buttons === 0) {
                return;
            }
            if (event.touches && event.touches.length !== 1) {
                return;
            }
            var point = pointerClient(event);
            var dx = point.x - touchStartX;
            var dy = point.y - touchStartY;

            if (!isSwiping && Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 8) {
                isSwiping = true;
            }

            if (!isSwiping) {
                return;
            }

            touchDeltaX = dx;
            if (event.cancelable) {
                event.preventDefault();
            }

            if (!reducedMotion) {
                track.style.transition = 'none';
                setActiveWithDragOffset(dx);
            }
        }

        function finishSwipeUi() {
            root.classList.remove('is-dragging');
            track.style.transition = '';
            pointerActive = false;
        }

        function onSwipeEnd() {
            var didSwipe = isSwiping;
            finishSwipeUi();

            if (!didSwipe) {
                startAutoplay();
                return;
            }

            suppressClick = true;
            window.setTimeout(function () {
                suppressClick = false;
            }, 300);

            if (Math.abs(touchDeltaX) >= swipeThreshold) {
                if (touchDeltaX < 0) {
                    setActive(index + 1);
                } else {
                    setActive(index - 1);
                }
            } else {
                setActive(index);
            }

            isSwiping = false;
            touchDeltaX = 0;
            startAutoplay();
        }

        function onSwipeCancel() {
            finishSwipeUi();
            isSwiping = false;
            touchDeltaX = 0;
            setActive(index);
            startAutoplay();
        }

        viewport.addEventListener('touchstart', onSwipeStart, { passive: true });
        viewport.addEventListener('touchmove', onSwipeMove, { passive: false });
        viewport.addEventListener('touchend', onSwipeEnd, { passive: true });
        viewport.addEventListener('touchcancel', onSwipeCancel, { passive: true });
        viewport.addEventListener('dragstart', function (event) {
            event.preventDefault();
        });

        viewport.addEventListener('pointerdown', function (event) {
            if (event.pointerType === 'touch') {
                return;
            }
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }
            if (event.target.closest('.catalog-header-banner-carousel__btn, .catalog-header-banner-carousel__dot')) {
                return;
            }
            try {
                viewport.setPointerCapture(event.pointerId);
            } catch (error) {
                /* capture is optional; move/up still work while the button is held */
            }
            root.classList.add('is-dragging');
            onSwipeStart(event);
        });
        viewport.addEventListener('pointermove', function (event) {
            if (event.pointerType === 'touch') {
                return;
            }
            onSwipeMove(event);
        });
        viewport.addEventListener('pointerup', function (event) {
            if (event.pointerType === 'touch') {
                return;
            }
            onSwipeEnd();
        });
        viewport.addEventListener('pointercancel', function (event) {
            if (event.pointerType === 'touch') {
                return;
            }
            onSwipeCancel();
        });

        setActive(0);
        startAutoplay();
    }

    function bootHeaderBannerCarousels() {
        document.querySelectorAll('[data-header-banner-carousel]').forEach(initHeaderBannerCarousel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootHeaderBannerCarousels);
    } else {
        bootHeaderBannerCarousels();
    }
})();
