(function () {
    const REDUCED_MOTION =
        typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function isGifUrl(url) {
        return /\.gif(\?|#|$)/i.test(String(url || ''));
    }

    function restartGif(img) {
        if (!img || !img.parentNode) {
            return img;
        }

        const replacement = img.cloneNode(true);
        replacement.dataset.zoogleAnimatedMediaBound = '';
        img.parentNode.replaceChild(replacement, img);

        return replacement;
    }

    function bindVideo(video) {
        if (!video || video.dataset.zoogleAnimatedMediaBound === '1') {
            return;
        }

        video.dataset.zoogleAnimatedMediaBound = '1';
        video.muted = true;
        video.defaultMuted = true;
        video.playsInline = true;
        video.loop = true;
        video.autoplay = true;
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('loop', '');

        if (REDUCED_MOTION) {
            video.pause();
            video.removeAttribute('autoplay');

            return;
        }

        const playIfVisible = () => {
            if (video.getBoundingClientRect().height <= 0) {
                return;
            }

            video.play().catch(() => {});
        };

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        playIfVisible();
                    } else {
                        video.pause();
                    }
                });
            },
            { root: null, rootMargin: '0px 0px 12% 0px', threshold: 0.12 }
        );

        observer.observe(video);
        playIfVisible();
    }

    function bindGif(img) {
        if (!img || img.dataset.zoogleAnimatedMediaBound === '1') {
            return;
        }

        if (!isGifUrl(img.currentSrc || img.src) && img.dataset.zoogleAnimatedMedia !== 'gif') {
            return;
        }

        img.dataset.zoogleAnimatedMediaBound = '1';
        img.loading = 'eager';
        img.decoding = 'sync';

        if (REDUCED_MOTION) {
            return;
        }

        let activeImg = img;

        const kick = () => {
            if (!activeImg.isConnected) {
                return;
            }

            activeImg = restartGif(activeImg);
            activeImg.dataset.zoogleAnimatedMediaBound = '1';
            activeImg.dataset.zoogleAnimatedMedia = 'gif';
            activeImg.loading = 'eager';
            activeImg.decoding = 'sync';
        };

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        kick();
                    }
                });
            },
            { root: null, rootMargin: '0px 0px 12% 0px', threshold: 0.12 }
        );

        observer.observe(activeImg);
        kick();
    }

    function animatedMediaSelectors() {
        return [
            '.product-card__media video[data-zoogle-animated-media="video"]',
            '.product-card__media img[data-zoogle-animated-media="gif"]',
            '.product-card__media img[data-zoogle-animated-gif="1"]',
            '#product-gallery-zone video[data-zoogle-animated-media="video"]',
            '#product-gallery-zone img[data-zoogle-animated-media="gif"]',
            '#product-gallery-zone img[data-zoogle-animated-gif="1"]',
            '#product-main-visual img[data-zoogle-animated-media="gif"]',
            '#product-main-visual img[data-zoogle-animated-gif="1"]',
        ].join(', ');
    }

    function revealCardPhoto(img) {
        img.classList.add('is-loaded');
        img.classList.remove('product-card__photo--pending');
    }

    /** srcset раніше за src: інакше браузер встигне почати вантажити копію 640px навіть на телефоні */
    function loadDeferredSource(img, dataSrc) {
        const dataSrcset = img.getAttribute('data-srcset');
        if (dataSrcset) {
            img.setAttribute('srcset', dataSrcset);
        }
        img.setAttribute('src', dataSrc);
    }

    function bindDeferredCardPhoto(img) {
        if (!img) {
            return;
        }

        if (img.dataset.cardPhotoBound === '1') {
            if (!img.classList.contains('product-card__photo--pending')) {
                return;
            }

            if (img.getAttribute('src') && img.complete && img.naturalWidth > 0) {
                revealCardPhoto(img);

                return;
            }

            if (img.getAttribute('src')) {
                return;
            }

            delete img.dataset.cardPhotoBound;
        }

        const dataSrc = img.getAttribute('data-src');
        if (!dataSrc) {
            return;
        }

        img.dataset.cardPhotoBound = '1';

        const start = () => {
            if (img.getAttribute('src')) {
                return;
            }

            img.addEventListener('load', () => revealCardPhoto(img), { once: true });
            loadDeferredSource(img, dataSrc);
        };

        if (!('IntersectionObserver' in window)) {
            start();

            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    observer.disconnect();
                    start();
                });
            },
            { root: null, rootMargin: '400px 0px', threshold: 0.01 }
        );

        observer.observe(img);
    }

    function syncDeferredCardPhotos(scope) {
        const root = scope && scope.querySelectorAll ? scope : document;

        root.querySelectorAll('.product-card__media img[data-src]').forEach(bindDeferredCardPhoto);
    }

    function syncCatalogCardMedia(scope) {
        const root = scope && scope.querySelectorAll ? scope : document;

        syncDeferredCardPhotos(root);

        root.querySelectorAll('.product-card__media video[data-zoogle-animated-media="video"], #product-gallery-zone video[data-zoogle-animated-media="video"], #product-main-visual video[data-zoogle-animated-media="video"]').forEach(bindVideo);

        root.querySelectorAll('.product-card__media img[data-zoogle-animated-media="gif"], .product-card__media img[data-zoogle-animated-gif="1"], #product-gallery-zone img[data-zoogle-animated-media="gif"], #product-gallery-zone img[data-zoogle-animated-gif="1"], #product-main-visual img[data-zoogle-animated-media="gif"], #product-main-visual img[data-zoogle-animated-gif="1"]').forEach(bindGif);

        root.querySelectorAll('.product-card__media img[src], #product-gallery-zone img[src], #product-main-visual img[src]').forEach((img) => {
            if (isGifUrl(img.currentSrc || img.src)) {
                bindGif(img);
            }
        });
    }

    window.zoogleSyncCatalogCardMedia = syncCatalogCardMedia;
    window.zoogleKickAnimatedGif = function (img) {
        if (img) {
            bindGif(img);
        }
    };

    function nudgeUnloadedImage(img) {
        if (!img || !img.getAttribute) {
            return;
        }
        if (img.complete && img.naturalWidth > 0) {
            if (img.classList.contains('product-card__photo--pending')) {
                revealCardPhoto(img);
            }

            return;
        }
        const src = img.getAttribute('src');
        if (!src) {
            return;
        }
        img.loading = 'eager';
        img.removeAttribute('src');
        img.setAttribute('src', src);
    }

    function restoreDeferredCardPhotos() {
        document.querySelectorAll('.product-card__media img.product-card__photo--pending, .product-card__media img[data-src]').forEach((img) => {
            const dataSrc = img.getAttribute('data-src');
            if (img.getAttribute('src')) {
                if (img.complete && img.naturalWidth > 0) {
                    revealCardPhoto(img);
                } else {
                    nudgeUnloadedImage(img);
                }

                return;
            }
            if (!dataSrc) {
                return;
            }
            img.loading = 'eager';
            img.addEventListener('load', () => revealCardPhoto(img), { once: true });
            loadDeferredSource(img, dataSrc);
        });
    }

    function restorePageVisuals() {
        if (typeof window.zoogleReapplyShopStyles === 'function') {
            window.zoogleReapplyShopStyles();
        }

        document.querySelectorAll('.catalog-results--loading').forEach((el) => {
            el.classList.remove('catalog-results--loading');
        });

        restoreDeferredCardPhotos();
        document.querySelectorAll('img[src]').forEach(nudgeUnloadedImage);

        document.querySelectorAll('video').forEach((video) => {
            if (video.dataset.zoogleAnimatedMedia !== 'video' && !video.hasAttribute('autoplay')) {
                return;
            }
            video.play().catch(() => {});
        });

        syncCatalogCardMedia(document);
    }

    window.zoogleRestorePageVisuals = restorePageVisuals;

    // Кружечки кольорів: наведення (або тап на телефоні) показує фото цього кольору,
    // вихід курсора з картки повертає основне фото. Делегування — працює й для карток «Показати ще».
    function cardPhotoOf(card) {
        const img = card.querySelector('.product-card__media img');
        if (!img || img.classList.contains('product-card__photo--pending') || !img.getAttribute('src')) {
            return null;
        }

        return img;
    }

    function showColorPhoto(dot) {
        const card = dot.closest('.product-card');
        const img = card ? cardPhotoOf(card) : null;
        if (!img) {
            return;
        }
        if (!img.dataset.cardOriginalSrc) {
            img.dataset.cardOriginalSrc = img.getAttribute('src');
            // Поки є srcset, браузер ігнорує новий src — знімаємо його на час показу кольору
            const srcset = img.getAttribute('srcset');
            if (srcset) {
                img.dataset.cardOriginalSrcset = srcset;
                img.removeAttribute('srcset');
            }
        }
        card.querySelectorAll('.product-card__color-dot.is-active').forEach((el) => el.classList.remove('is-active'));
        dot.classList.add('is-active');
        img.setAttribute('src', dot.dataset.cardPhoto);
    }

    function restoreCardPhoto(card) {
        const img = card.querySelector('.product-card__media img');
        if (img && img.dataset.cardOriginalSrc) {
            if (img.dataset.cardOriginalSrcset) {
                img.setAttribute('srcset', img.dataset.cardOriginalSrcset);
                delete img.dataset.cardOriginalSrcset;
            }
            img.setAttribute('src', img.dataset.cardOriginalSrc);
            delete img.dataset.cardOriginalSrc;
        }
        card.querySelectorAll('.product-card__color-dot.is-active').forEach((el) => el.classList.remove('is-active'));
    }

    document.addEventListener('pointerover', (event) => {
        const dot = event.target.closest?.('.product-card__color-dot[data-card-photo]');
        if (dot && event.pointerType === 'mouse') {
            showColorPhoto(dot);
        }
    });

    document.addEventListener('pointerout', (event) => {
        const card = event.target.closest?.('.product-card');
        if (card && !card.contains(event.relatedTarget)) {
            restoreCardPhoto(card);
        }
    });

    document.addEventListener('click', (event) => {
        const dot = event.target.closest?.('.product-card__color-dot');
        if (!dot) {
            return;
        }
        event.preventDefault();
        if (dot.dataset.cardPhoto) {
            showColorPhoto(dot);
        }
    });

    function isBackForwardShow(event) {
        if (event && event.persisted) {
            return true;
        }
        try {
            const nav = performance.getEntriesByType('navigation')[0];

            return Boolean(nav && nav.type === 'back_forward');
        } catch {
            return false;
        }
    }

    window.addEventListener('pageshow', (event) => {
        if (isBackForwardShow(event)) {
            restorePageVisuals();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => syncCatalogCardMedia(document), { once: true });
    } else {
        syncCatalogCardMedia(document);
    }
})();
