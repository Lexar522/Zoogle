function runProductShowPage() {
    const cfgEl = document.getElementById('product-show-config');
    if (!cfgEl) {
        return;
    }

    let cfg;
    try {
        cfg = JSON.parse(cfgEl.textContent);
    } catch {
        return;
    }

    const listingPrice = Number(cfg.listingPrice || 0);
    const listingCompareAt = cfg.listingCompareAt != null ? Number(cfg.listingCompareAt) : null;
    const listingStockMode = cfg.listingStockMode || 'none';
    const optionBlocks = cfg.optionBlocks || [];
    const defaultOptionSelection = cfg.defaultOptionSelection && typeof cfg.defaultOptionSelection === 'object'
        ? cfg.defaultOptionSelection
        : {};
    const requiresOptionSelection = Boolean(cfg.requiresOptionSelection);
    const variants = Array.isArray(cfg.variants) ? cfg.variants : [];
    const needsBareVariantPicker = Boolean(cfg.needsBareVariantPicker);
    const variantIdInput = document.getElementById('input-variant-id');
    const variantPicker = document.getElementById('product-variant-picker');
    let selectedBareVariantId = Number(cfg.storefrontDefaultVariantId || variantPicker?.value || 0) || 0;
    const catalogCategoryGroupId = Number(cfg.catalogCategoryGroupId || 0);
    const initialCategoryLabel = cfg.initialCategoryLabel || '';
    const initialPhotos = cfg.initialPhotos || [];
    const initialGalleryItems = Array.isArray(cfg.initialGalleryItems) ? cfg.initialGalleryItems : [];
    const optimizeImages = cfg.optimizeImages !== false;
    const storageBase = cfg.storageBase || '';
    const listingTitle = cfg.listingTitle || '';
    const i18n = cfg.i18n && typeof cfg.i18n === 'object' ? cfg.i18n : {};
    const t = (key, fallback) => {
        const v = i18n[key];
        return v != null && String(v) !== '' ? String(v) : fallback;
    };
    const categoryPrefix = t('pdp_category_prefix', 'Категорія: ');

    const priceEl = document.getElementById('product-price');
    const priceNoteEl = document.getElementById('product-price-note');
    const hintEl = document.getElementById('product-variant-hint');
    const optionValuesInput = document.getElementById('input-option-value-ids');
    const addBtn = document.getElementById('product-add-btn');
    const mainVisual = document.getElementById('product-main-visual');
    const badgesEl = document.getElementById('product-stock-badges');
    const thumbsWrap = document.getElementById('product-thumbs');
    const galleryZone = document.getElementById('product-gallery-zone');
    const breadcrumbSelectedEl = document.getElementById('product-breadcrumb-selected');
    const prefersReducedMotion = typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const optionValuePriceById = new Map();
    optionBlocks.forEach((block) => {
        (block.values || []).forEach((val) => {
            const id = Number(val.id);
            if (Number.isFinite(id) && id > 0) {
                optionValuePriceById.set(id, Number(val.price || 0));
            }
        });
    });

    const priceAnimationState = {
        rafId: 0,
        classTimerId: 0,
        current: Number.isFinite(listingPrice) ? listingPrice : 0,
        compare: listingCompareAt != null && Number.isFinite(Number(listingCompareAt)) ? Number(listingCompareAt) : null,
        hasRendered: false,
    };

    function getMainMediaEl() {
        if (!mainVisual) {
            return null;
        }

        return mainVisual.querySelector('#product-main-img, #product-main-video');
    }

    function getMainImg() {
        return mainVisual ? mainVisual.querySelector('#product-main-img') : null;
    }

    function defaultObjectPosition(focusX, focusY) {
        return 'object-fit: cover; object-position: ' + focusX + '% ' + focusY + '%;';
    }

    function guessMediaTypeFromUrl(url) {
        const clean = String(url || '').split('?')[0].toLowerCase();
        if (clean.endsWith('.gif')) {
            return 'gif';
        }
        if (/\.(mp4|webm|mov)$/.test(clean)) {
            return 'video';
        }

        return 'image';
    }

    function normalizeGalleryItem(input) {
        if (input && typeof input === 'object' && input.url) {
            const focusX = Number(input.focus_x ?? 50);
            const focusY = Number(input.focus_y ?? 50);

            return {
                path: input.path || '',
                url: String(input.url),
                type: input.type || guessMediaTypeFromUrl(input.url),
                focus_x: Number.isFinite(focusX) ? focusX : 50,
                focus_y: Number.isFinite(focusY) ? focusY : 50,
                object_position: input.object_position || defaultObjectPosition(
                    Number.isFinite(focusX) ? focusX : 50,
                    Number.isFinite(focusY) ? focusY : 50
                ),
                poster_url: input.poster_url || '',
                card_url: input.card_url || '',
            };
        }

        const url = String(input || '');
        const type = guessMediaTypeFromUrl(url);

        return {
            path: '',
            url,
            type,
            focus_x: 50,
            focus_y: 50,
            object_position: defaultObjectPosition(50, 50),
            poster_url: '',
        };
    }

    function normalizeGalleryItems(list) {
        return (list || [])
            .map((entry) => normalizeGalleryItem(entry))
            .filter((item) => item.url);
    }

    // Незмінний знімок основної галереї товару — база, до якої додаються фото опцій.
    // Не можна підставляти galleryItemsState.items: setGallery його мутує.
    const initialGalleryItemsFrozen = normalizeGalleryItems(
        initialGalleryItems.length ? initialGalleryItems : initialPhotos
    );
    const galleryItemsState = {
        items: initialGalleryItemsFrozen.slice(),
    };

    function thumbPreviewUrl(item) {
        if (optimizeImages && item.card_url) {
            return item.card_url;
        }

        if (item.type === 'video' && item.poster_url) {
            return item.poster_url;
        }

        return item.url;
    }

    function appendThumbMedia(button, item) {
        if (!optimizeImages && item.type === 'video' && item.url) {
            const video = document.createElement('video');
            video.src = item.url;
            video.muted = true;
            video.defaultMuted = true;
            video.playsInline = true;
            video.loop = true;
            video.autoplay = true;
            video.preload = 'auto';
            video.setAttribute('muted', '');
            video.setAttribute('playsinline', '');
            video.setAttribute('loop', '');
            video.dataset.zoogleAnimatedMedia = 'video';
            video.style.cssText = item.object_position;
            button.appendChild(video);

            return;
        }

        const preview = thumbPreviewUrl(item);
        if (!preview) {
            return;
        }

        const im = document.createElement('img');
        im.src = preview;
        im.alt = '';
        im.style.cssText = item.object_position;
        if (!optimizeImages && item.type === 'gif') {
            im.dataset.zoogleAnimatedMedia = 'gif';
            im.dataset.zoogleAnimatedGif = '1';
            im.loading = 'eager';
            im.decoding = 'sync';
        } else {
            im.decoding = 'async';
        }
        button.appendChild(im);
    }

    function renderMainMediaItem(item, { animate } = {}) {
        if (!mainVisual || !item || !item.url) {
            return;
        }

        mainVisual.querySelector('.placeholder')?.remove();
        const existing = getMainMediaEl();
        const existingIsVideo = existing && existing.tagName === 'VIDEO';
        const nextIsVideo = item.type === 'video';

        if (existing && existingIsVideo === nextIsVideo) {
            if (nextIsVideo) {
                if (existing.src !== item.url) {
                    existing.src = item.url;
                }
                if (item.poster_url) {
                    existing.poster = item.poster_url;
                }
                existing.style.cssText = item.object_position;
                existing.play?.().catch(() => {});
            } else if (!urlsEqual(existing.src, item.url)) {
                setMainImageSrc(existing, item.url, { animate });
                existing.style.cssText = item.object_position;
            } else {
                existing.style.cssText = item.object_position;
            }

            return;
        }

        existing?.remove();

        if (nextIsVideo) {
            const video = document.createElement('video');
            video.id = 'product-main-video';
            video.src = item.url;
            if (item.poster_url) {
                video.poster = item.poster_url;
            }
            video.style.cssText = item.object_position;
            video.muted = true;
            video.playsInline = true;
            video.loop = true;
            video.autoplay = true;
            video.setAttribute('aria-label', listingTitle);
            mainVisual.appendChild(video);
            video.play?.().catch(() => {});

            return;
        }

        const img = document.createElement('img');
        img.id = 'product-main-img';
        img.alt = listingTitle;
        img.style.cssText = item.object_position;
        if (item.type === 'gif') {
            img.dataset.zoogleAnimatedMedia = 'gif';
            img.dataset.zoogleAnimatedGif = '1';
            img.loading = 'eager';
            img.decoding = 'sync';
        }
        mainVisual.appendChild(img);
        setMainImageSrc(img, item.url, { animate });
        if (item.type === 'gif' && typeof window.zoogleKickAnimatedGif === 'function') {
            window.zoogleKickAnimatedGif(img);
        }
    }

    let mainImageTransitionToken = 0;

    function urlsEqual(a, b) {
        try {
            return new URL(a, window.location.href).href === new URL(b, window.location.href).href;
        } catch {
            return String(a) === String(b);
        }
    }

    /**
     * Плавна зміна головного фото лише при animate (кілька фото в галереї) і без prefers-reduced-motion.
     */
    function isGifUrl(url) {
        return /\.gif(\?|#|$)/i.test(String(url || ''));
    }

    function setMainImageSrc(img, newSrc, { animate } = {}) {
        if (!img || !newSrc) {
            return;
        }
        if (urlsEqual(img.src, newSrc)) {
            if (isGifUrl(newSrc) && typeof window.zoogleKickAnimatedGif === 'function') {
                window.zoogleKickAnimatedGif(img);
            }
            return;
        }
        mainImageTransitionToken += 1;
        const token = mainImageTransitionToken;
        const shouldAnimate = Boolean(animate) && !prefersReducedMotion && !isGifUrl(newSrc);

        if (!shouldAnimate) {
            img.src = newSrc;
            img.style.opacity = '';
            if (isGifUrl(newSrc) && typeof window.zoogleKickAnimatedGif === 'function') {
                window.zoogleKickAnimatedGif(img);
            }
            return;
        }

        const fadeIn = () => {
            if (token !== mainImageTransitionToken) {
                return;
            }
            requestAnimationFrame(() => {
                if (token !== mainImageTransitionToken) {
                    return;
                }
                img.style.opacity = '1';
            });
        };

        const afterLoad = () => {
            img.removeEventListener('load', afterLoad);
            img.removeEventListener('error', afterErr);
            fadeIn();
        };

        const afterErr = () => {
            img.removeEventListener('load', afterLoad);
            img.removeEventListener('error', afterErr);
            fadeIn();
        };

        const swapSrcAfterFadeOut = () => {
            if (token !== mainImageTransitionToken) {
                return;
            }
            img.addEventListener('load', afterLoad);
            img.addEventListener('error', afterErr);
            img.src = newSrc;
            if (img.complete && img.naturalWidth > 0) {
                img.removeEventListener('load', afterLoad);
                img.removeEventListener('error', afterErr);
                fadeIn();
            }
        };

        let fadeFallbackId = 0;
        let fadeOutHandled = false;

        const finishFadeOut = () => {
            if (fadeOutHandled || token !== mainImageTransitionToken) {
                return;
            }
            fadeOutHandled = true;
            window.clearTimeout(fadeFallbackId);
            img.removeEventListener('transitionend', onFadeOutEnd);
            swapSrcAfterFadeOut();
        };

        const onFadeOutEnd = (e) => {
            if (e.propertyName !== 'opacity') {
                return;
            }
            if (token !== mainImageTransitionToken) {
                return;
            }
            finishFadeOut();
        };

        img.addEventListener('transitionend', onFadeOutEnd);
        fadeFallbackId = window.setTimeout(finishFadeOut, 420);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (token !== mainImageTransitionToken) {
                    return;
                }
                img.style.opacity = '0';
            });
        });
    }

    function thumbButtonCount() {
        return thumbsWrap ? thumbsWrap.querySelectorAll('button[data-gallery-index]').length : 0;
    }

    const GALLERY_AUTOPLAY_MS = 5000;
    let galleryAutoplayTimerId = null;
    let galleryPointerInside = false;
    let galleryFocusInside = false;

    function clearGalleryAutoplayTimer() {
        if (galleryAutoplayTimerId != null) {
            window.clearInterval(galleryAutoplayTimerId);
            galleryAutoplayTimerId = null;
        }
    }

    function tickGalleryAutoplay() {
        if (!thumbsWrap || prefersReducedMotion) {
            return;
        }
        const n = thumbButtonCount();
        if (n <= 1) {
            clearGalleryAutoplayTimer();
            return;
        }
        if (galleryPointerInside || galleryFocusInside) {
            return;
        }
        const buttons = thumbsWrap.querySelectorAll('button[data-gallery-index]');
        if (buttons.length <= 1) {
            return;
        }
        const active = thumbsWrap.querySelector('button[data-gallery-index].active');
        let idx = 0;
        if (active) {
            idx = Array.prototype.indexOf.call(buttons, active);
            if (idx < 0) {
                idx = 0;
            }
        }
        const next = buttons[(idx + 1) % buttons.length];
        applyThumbToMain(next, { fromAutoplay: true });
    }

    function restartGalleryAutoplay() {
        clearGalleryAutoplayTimer();
        if (prefersReducedMotion) {
            return;
        }
        if (thumbButtonCount() <= 1) {
            return;
        }
        if (galleryPointerInside || galleryFocusInside) {
            return;
        }
        galleryAutoplayTimerId = window.setInterval(tickGalleryAutoplay, GALLERY_AUTOPLAY_MS);
    }

    function applyThumbToMain(button, opts = {}) {
        const fromAutoplay = Boolean(opts.fromAutoplay);
        const index = Number(button?.dataset?.galleryIndex ?? -1);
        const item = galleryItemsState.items[index];
        if (!item || !thumbsWrap) {
            return;
        }
        thumbsWrap.querySelectorAll('button').forEach((x) => x.classList.remove('active'));
        button.classList.add('active');
        renderMainMediaItem(item, { animate: thumbButtonCount() > 1 });
        if (!fromAutoplay) {
            restartGalleryAutoplay();
        }
    }

    function formatMoney(n) {
        return new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 }).format(n) + ' ₴';
    }

    function formatPriceHtml(current, compare) {
        const curInner = '<span class="product-price__current">' + formatMoney(current) + '</span>';
        if (compare != null && Number(compare) > Number(current)) {
            return '<span class="product-price__old">' + formatMoney(compare) + '</span>' + curInner;
        }
        return curInner;
    }

    function normalizeMoney(value, fallback = 0) {
        const n = Number(value);
        return Number.isFinite(n) ? n : fallback;
    }

    function normalizeOptionalMoney(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : null;
    }

    function renderPrice(current, compare) {
        if (!priceEl) {
            return;
        }
        priceEl.innerHTML = formatPriceHtml(current, compare);
    }

    function stopPriceAnimation() {
        if (priceAnimationState.rafId) {
            cancelAnimationFrame(priceAnimationState.rafId);
            priceAnimationState.rafId = 0;
        }
    }

    function pulsePrice(direction) {
        if (!priceEl || !direction) {
            return;
        }
        priceEl.classList.remove('product-price--up', 'product-price--down');
        void priceEl.offsetWidth;
        priceEl.classList.add(direction === 'up' ? 'product-price--up' : 'product-price--down');
        if (priceAnimationState.classTimerId) {
            clearTimeout(priceAnimationState.classTimerId);
        }
        priceAnimationState.classTimerId = window.setTimeout(() => {
            priceEl?.classList.remove('product-price--up', 'product-price--down');
        }, 320);
    }

    function setPriceDisplay(current, compare, animate = true) {
        if (!priceEl) {
            return;
        }

        const targetCurrent = normalizeMoney(current, priceAnimationState.current);
        const targetCompare = compare == null ? null : normalizeOptionalMoney(compare);
        const currentChanged = Math.abs(priceAnimationState.current - targetCurrent) > 0.004;
        const compareChanged =
            (priceAnimationState.compare == null) !== (targetCompare == null)
            || (
                priceAnimationState.compare != null
                && targetCompare != null
                && Math.abs(priceAnimationState.compare - targetCompare) > 0.004
            );

        if (!priceAnimationState.hasRendered || !animate || prefersReducedMotion || (!currentChanged && !compareChanged)) {
            stopPriceAnimation();
            renderPrice(targetCurrent, targetCompare);
            priceAnimationState.current = targetCurrent;
            priceAnimationState.compare = targetCompare;
            priceAnimationState.hasRendered = true;
            return;
        }

        const startCurrent = priceAnimationState.current;
        const startCompare = priceAnimationState.compare;
        const canTweenCompare = startCompare != null && targetCompare != null;
        const duration = 280;
        const startedAt = performance.now();
        const direction = targetCurrent > startCurrent ? 'up' : (targetCurrent < startCurrent ? 'down' : '');

        stopPriceAnimation();
        pulsePrice(direction);

        const step = (timestamp) => {
            const progress = Math.min(1, (timestamp - startedAt) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            const frameCurrent = startCurrent + (targetCurrent - startCurrent) * eased;
            const frameCompare = canTweenCompare
                ? startCompare + (targetCompare - startCompare) * eased
                : targetCompare;

            renderPrice(frameCurrent, frameCompare);
            priceAnimationState.current = frameCurrent;
            priceAnimationState.compare = frameCompare;

            if (progress < 1) {
                priceAnimationState.rafId = requestAnimationFrame(step);
                return;
            }

            renderPrice(targetCurrent, targetCompare);
            priceAnimationState.current = targetCurrent;
            priceAnimationState.compare = targetCompare;
            priceAnimationState.rafId = 0;
        };

        priceAnimationState.rafId = requestAnimationFrame(step);
    }

    function getSelection() {
        const sel = {};
        document.querySelectorAll('#product-options .opt-group').forEach((g) => {
            const gid = parseInt(g.dataset.groupId, 10);
            if (!Number.isFinite(gid)) {
                return;
            }
            const mode = g.dataset.mode || 'single';
            if (mode === 'multiple') {
                const ids = [];
                g.querySelectorAll('.opt-chip.active, .opt-swatch.active').forEach((el) => {
                    const id = parseInt(el.dataset.valueId, 10);
                    if (Number.isFinite(id)) {
                        ids.push(id);
                    }
                });
                ids.sort((a, b) => a - b);
                if (ids.length) {
                    sel[gid] = ids;
                }
            } else {
                const active = g.querySelector('.opt-chip.active, .opt-swatch.active');
                if (active) {
                    const id = parseInt(active.dataset.valueId, 10);
                    if (Number.isFinite(id)) {
                        sel[gid] = id;
                    }
                }
            }
        });
        return sel;
    }

    function getVisibleOptionGroups() {
        return Array.from(document.querySelectorAll('#product-options .opt-group'))
            .filter((group) => !group.classList.contains('opt-group--exclusive-hidden'));
    }

    function isVisibleGroupSelectionComplete(group) {
        const chips = group.querySelectorAll('.opt-chip, .opt-swatch');
        if (chips.length <= 1) {
            return true;
        }
        const mode = group.dataset.mode || 'single';
        const actives = group.querySelectorAll('.opt-chip.active, .opt-swatch.active');
        if (mode === 'multiple') {
            return actives.length >= 1;
        }

        return actives.length === 1;
    }

    function missingVisibleOptionGroupLabels() {
        const groups = getVisibleOptionGroups();
        const handledExclusivityKeys = new Set();
        const missing = [];

        groups.forEach((group) => {
            const exclusivityKey = (group.dataset.exclusivityKey || '').trim();
            if (exclusivityKey) {
                if (handledExclusivityKeys.has(exclusivityKey)) {
                    return;
                }
                handledExclusivityKeys.add(exclusivityKey);
                const family = groups.filter((g) => (g.dataset.exclusivityKey || '').trim() === exclusivityKey);
                const choosable = family.filter((g) => g.querySelectorAll('.opt-chip, .opt-swatch').length > 1);
                if (!choosable.length) {
                    return;
                }
                const familyComplete = choosable.some((g) => {
                    const mode = g.dataset.mode || 'single';
                    const actives = g.querySelectorAll('.opt-chip.active, .opt-swatch.active');
                    return mode === 'multiple' ? actives.length >= 1 : actives.length === 1;
                });
                if (!familyComplete) {
                    const label = choosable[0].querySelector('.opt-label');
                    const text = label ? label.textContent.trim() : '';
                    if (text) {
                        missing.push(text);
                    }
                }
                return;
            }

            if (!isVisibleGroupSelectionComplete(group)) {
                const label = group.querySelector('.opt-label');
                const text = label ? label.textContent.trim() : '';
                if (text) {
                    missing.push(text);
                }
            }
        });

        return missing;
    }

    function clearExclusiveSiblingSelections(group) {
        const exclusivityKey = (group.dataset.exclusivityKey || '').trim();
        if (!exclusivityKey) {
            return;
        }
        document.querySelectorAll('#product-options .opt-group').forEach((other) => {
            if (other === group) {
                return;
            }
            if ((other.dataset.exclusivityKey || '').trim() !== exclusivityKey) {
                return;
            }
            other.querySelectorAll('.opt-chip.active, .opt-swatch.active').forEach((node) => {
                node.classList.remove('active');
            });
        });
    }

    function updateOptionGroupSelectionState() {
        getVisibleOptionGroups().forEach((group) => {
            const exclusivityKey = (group.dataset.exclusivityKey || '').trim();
            if (exclusivityKey) {
                const family = getVisibleOptionGroups().filter(
                    (g) => (g.dataset.exclusivityKey || '').trim() === exclusivityKey,
                );
                const choosable = family.filter((g) => g.querySelectorAll('.opt-chip, .opt-swatch').length > 1);
                const familyComplete = choosable.some((g) => {
                    const mode = g.dataset.mode || 'single';
                    const actives = g.querySelectorAll('.opt-chip.active, .opt-swatch.active');
                    return mode === 'multiple' ? actives.length >= 1 : actives.length === 1;
                });
                family.forEach((g) => {
                    g.classList.toggle('opt-group--needs-selection', choosable.length > 0 && !familyComplete);
                });
                return;
            }
            group.classList.toggle('opt-group--needs-selection', !isVisibleGroupSelectionComplete(group));
        });
    }

    function isOptionSelectionComplete() {
        if (!requiresOptionSelection) {
            return true;
        }

        return missingVisibleOptionGroupLabels().length === 0;
    }

    function normalizeDefaultSelectionValue(raw) {
        if (Array.isArray(raw)) {
            return raw.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0);
        }
        const single = Number(raw);
        return Number.isFinite(single) && single > 0 ? [single] : [];
    }

    function ensureDefaultOptionSelection() {
        document.querySelectorAll('#product-options .opt-group').forEach((group) => {
            const gid = parseInt(group.dataset.groupId, 10);
            if (!Number.isFinite(gid)) {
                return;
            }
            const mode = group.dataset.mode || 'single';
            const hasActive = group.querySelector('.opt-chip.active, .opt-swatch.active');
            if (hasActive) {
                return;
            }
            const defaults = normalizeDefaultSelectionValue(
                defaultOptionSelection[gid] ?? defaultOptionSelection[String(gid)] ?? null,
            );
            if (!defaults.length) {
                return;
            }
            group.querySelectorAll('.opt-chip, .opt-swatch').forEach((el) => {
                el.classList.remove('active');
            });
            defaults.forEach((valueId) => {
                const target = group.querySelector(`[data-value-id="${valueId}"]`);
                if (target) {
                    target.classList.add('active');
                }
            });
            if (mode !== 'multiple' && defaults[0]) {
                const target = group.querySelector(`[data-value-id="${defaults[0]}"]`);
                if (target) {
                    group.querySelectorAll('.opt-chip, .opt-swatch').forEach((el) => el.classList.remove('active'));
                    target.classList.add('active');
                }
            }
        });
    }

    function selectedOptionLabelsForPriceNote(selection) {
        const labels = [];
        for (const block of optionBlocks) {
            const gid = Number(block.id);
            if (!Number.isFinite(gid) || gid === catalogCategoryGroupId) {
                continue;
            }
            const values = block.values || [];
            if (values.length < 2) {
                continue;
            }
            const hasPaid = values.some((val) => Number(val.price || 0) > 0);
            if (!hasPaid) {
                continue;
            }
            const sv = selectionValue(selection, gid);
            if (sv == null) {
                continue;
            }
            labels.push(...valueNamesForBlock(block, sv));
        }
        return labels;
    }

    function selectionValue(selection, gid) {
        const g = Number(gid);
        if (selection[g] != null) {
            return selection[g];
        }
        const k = String(g);
        if (selection[k] != null) {
            return selection[k];
        }
        return null;
    }

    function selectedOptionValueIds(selection) {
        const out = [];
        for (const block of optionBlocks) {
            const gid = Number(block.id);
            if (!Number.isFinite(gid) || gid === catalogCategoryGroupId) {
                continue;
            }
            const sv = selectionValue(selection, gid);
            if (sv == null) {
                continue;
            }
            if (Array.isArray(sv)) {
                sv.forEach((id) => {
                    const n = Number(id);
                    if (Number.isFinite(n) && n > 0) {
                        out.push(n);
                    }
                });
            } else {
                const n = Number(sv);
                if (Number.isFinite(n) && n > 0) {
                    out.push(n);
                }
            }
        }
        return [...new Set(out)].sort((a, b) => a - b);
    }

    function splitSelectionToLineOptionValueIds(selection) {
        const groups = [];
        for (const block of optionBlocks) {
            const gid = Number(block.id);
            if (!Number.isFinite(gid) || gid === catalogCategoryGroupId) {
                continue;
            }
            const sv = selectionValue(selection, gid);
            if (sv == null) {
                continue;
            }
            const ids = Array.isArray(sv) ? sv.map(Number).filter(Number.isFinite) : [Number(sv)].filter(Number.isFinite);
            if (!ids.length) {
                continue;
            }
            groups.push({
                selection_mode: block.selection_mode || 'single',
                ids: [...new Set(ids)].sort((a, b) => a - b),
            });
        }

        if (!groups.length) {
            return [[]];
        }

        let lineSets = [[]];
        groups.forEach((group) => {
            const shouldSplit = group.selection_mode === 'multiple' && group.ids.length > 1;
            const choices = shouldSplit ? group.ids.map((id) => [id]) : [group.ids];
            const next = [];
            lineSets.forEach((prefix) => {
                choices.forEach((choice) => {
                    next.push([...new Set([...prefix, ...choice])].sort((a, b) => a - b));
                });
            });
            lineSets = next;
        });

        const unique = [];
        const seen = new Set();
        lineSets.forEach((row) => {
            const key = row.join(',');
            if (seen.has(key)) {
                return;
            }
            seen.add(key);
            unique.push(row);
        });

        return unique.length ? unique : [[]];
    }

    function addonForOptionValueIds(valueIds) {
        let add = 0;
        valueIds.forEach((id) => {
            const p = Number(optionValuePriceById.get(Number(id)) || 0);
            if (Number.isFinite(p)) {
                add += p;
            }
        });
        return add;
    }

    function optionValueFromVariantOptions(opts, gid) {
        if (!opts || typeof opts !== 'object') {
            return undefined;
        }
        if (Object.prototype.hasOwnProperty.call(opts, gid)) {
            return opts[gid];
        }
        if (Object.prototype.hasOwnProperty.call(opts, String(gid))) {
            return opts[String(gid)];
        }
        return undefined;
    }

    function variantMatchSignature(row) {
        const opts = row && row.options && typeof row.options === 'object' ? row.options : {};
        const parts = [];
        optionBlocks.forEach((block) => {
            if (block.affects_variant_matching === false) {
                return;
            }
            const gid = Number(block.id);
            if (!Number.isFinite(gid) || gid <= 0) {
                return;
            }
            const val = optionValueFromVariantOptions(opts, gid);
            if (val === undefined) {
                return;
            }
            if (Array.isArray(val)) {
                const norm = val.map(Number).filter((n) => Number.isFinite(n) && n > 0);
                norm.sort((a, b) => a - b);
                parts.push(gid + ':' + norm.join(','));
            } else {
                parts.push(gid + ':' + Number(val));
            }
        });
        parts.sort();
        return parts.join('|');
    }

    function selectionMatchSignature(selection) {
        const parts = [];
        optionBlocks.forEach((block) => {
            if (block.affects_variant_matching === false) {
                return;
            }
            const gid = Number(block.id);
            if (!Number.isFinite(gid) || gid <= 0) {
                return;
            }
            const sv = selectionValue(selection, gid);
            if (sv == null) {
                return;
            }
            if (Array.isArray(sv)) {
                const norm = sv.map(Number).filter((n) => Number.isFinite(n) && n > 0);
                norm.sort((a, b) => a - b);
                if (!norm.length) {
                    return;
                }
                parts.push(gid + ':' + norm.join(','));
            } else {
                const n = Number(sv);
                if (!Number.isFinite(n) || n <= 0) {
                    return;
                }
                parts.push(gid + ':' + n);
            }
        });
        parts.sort();
        return parts.join('|');
    }

    const variantById = new Map();
    const variantBySignature = new Map();
    variants.forEach((row) => {
        const id = Number(row.id);
        if (Number.isFinite(id) && id > 0) {
            variantById.set(id, row);
        }
        const sig = variantMatchSignature(row);
        if (sig && (!variantBySignature.has(sig) || Number(row.id) < Number(variantBySignature.get(sig).id))) {
            variantBySignature.set(sig, row);
        }
    });
    const hasMatchingAxis = optionBlocks.some((block) => block.affects_variant_matching !== false);

    function selectionFromLineIds(lineIds) {
        const sel = {};
        const idSet = new Set(lineIds.map(Number));
        optionBlocks.forEach((block) => {
            const gid = Number(block.id);
            const hits = (block.values || [])
                .map((val) => Number(val.id))
                .filter((id) => idSet.has(id));
            if (!hits.length) {
                return;
            }
            sel[gid] = (block.selection_mode || 'single') === 'multiple'
                ? hits.sort((a, b) => a - b)
                : hits[0];
        });
        return sel;
    }

    function matchVariantForSelection(selection) {
        if (needsBareVariantPicker && selectedBareVariantId > 0) {
            return variantById.get(selectedBareVariantId) || null;
        }
        const sig = selectionMatchSignature(selection);
        if (sig && variantBySignature.has(sig)) {
            return variantBySignature.get(sig);
        }
        if (!sig && variants.length === 1) {
            const only = variants[0];
            const opts = only.options && typeof only.options === 'object' ? only.options : {};
            if (Object.keys(opts).length === 0) {
                return only;
            }
        }
        return null;
    }

    function applyExclusiveVisibility(variantId) {
        const vid = Number(variantId || 0);
        document.querySelectorAll('#product-options .opt-group').forEach((group) => {
            const exclusive = Number(group.dataset.exclusiveForVariantId || 0);
            const hide = exclusive > 0 && exclusive !== vid;
            group.classList.toggle('opt-group--exclusive-hidden', hide);
            if (hide) {
                group.querySelectorAll('.opt-chip.active, .opt-swatch.active').forEach((node) => {
                    node.classList.remove('active');
                });
            }
        });
    }

    function calculateDisplayPrice(selection) {
        const lines = splitSelectionToLineOptionValueIds(selection);
        let current = 0;
        let compare = null;
        let hasCompare = false;

        lines.forEach((lineIds) => {
            const lineSel = selectionFromLineIds(lineIds);
            const variant = matchVariantForSelection(lineSel);
            const base = variant ? Number(variant.price) : listingPrice;
            const baseCompare = variant && variant.compare_at != null
                ? Number(variant.compare_at)
                : listingCompareAt;
            const addon = addonForOptionValueIds(lineIds);
            current += Number(base) + addon;
            if (baseCompare != null && !Number.isNaN(Number(baseCompare))) {
                hasCompare = true;
                compare = (compare == null ? 0 : compare) + Number(baseCompare) + addon;
            }
        });

        return {
            current,
            compare: hasCompare ? compare : null,
            lineCount: Math.max(1, lines.length),
        };
    }

    function toGalleryUrls(paths) {
        return (paths || []).map((p) => storageBase + '/' + String(p).replace(/^\/+/, ''));
    }

    function pathsToGalleryItems(paths) {
        return normalizeGalleryItems(
            (paths || []).map((p) => {
                const raw = String(p || '').trim();
                if (!raw) {
                    return '';
                }
                if (/^https?:\/\//i.test(raw) || raw.startsWith('//') || raw.startsWith('data:')) {
                    return raw;
                }
                const cleaned = raw.replace(/^\/+/, '').replace(/^storage\//, '');

                return storageBase + '/' + cleaned;
            })
        );
    }

    function setGallery(items) {
        const normalized = normalizeGalleryItems(items);
        galleryItemsState.items = normalized;

        if (thumbsWrap) {
            thumbsWrap.style.display = normalized.length > 1 ? '' : 'none';
        }
        if (!normalized.length) {
            if (mainVisual) {
                mainVisual.classList.remove('product-gallery-main--multi-img');
            }
            getMainMediaEl()?.remove();
            if (mainVisual && !mainVisual.querySelector('.placeholder')) {
                const ph = document.createElement('span');
                ph.className = 'placeholder';
                ph.textContent = t('pdp_no_photo', 'Немає фото');
                mainVisual.appendChild(ph);
            }
            if (thumbsWrap) {
                thumbsWrap.innerHTML = '';
            }
            clearGalleryAutoplayTimer();
            return;
        }

        if (mainVisual) {
            mainVisual.classList.toggle('product-gallery-main--multi-img', normalized.length > 1);
        }
        renderMainMediaItem(normalized[0], { animate: false });

        if (thumbsWrap) {
            thumbsWrap.innerHTML = '';
            normalized.forEach((item, i) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = i === 0 ? 'active' : '';
                b.dataset.galleryIndex = String(i);
                appendThumbMedia(b, item);
                thumbsWrap.appendChild(b);
            });
        }
        if (typeof window.zoogleSyncCatalogCardMedia === 'function') {
            window.zoogleSyncCatalogCardMedia(galleryZone || mainVisual || document);
        }
        restartGalleryAutoplay();
    }

    function updateBadges(stockMode) {
        if (!badgesEl) {
            return;
        }
        const mode = stockMode || listingStockMode;
        let html = '';
        if (mode === 'preorder') {
            html = '<span class="badge pre">' + t('pdp_badge_preorder', 'Передзамовлення') + '</span>';
        } else if (mode === 'low') {
            html = '<span class="badge low">' + t('pdp_badge_low', 'Закінчується') + '</span>';
        } else if (mode === 'ok') {
            html = '<span class="badge ok">' + t('pdp_badge_in_stock', 'В наявності') + '</span>';
        } else {
            html = '<span class="badge no">' + t('pdp_badge_out_of_stock', 'Немає в наявності') + '</span>';
        }
        badgesEl.innerHTML = html;
    }

    function valueNamesForBlock(block, selVal) {
        const vals = block.values || [];
        const out = [];
        if (Array.isArray(selVal)) {
            selVal.forEach((id) => {
                const row = vals.find((x) => Number(x.id) === Number(id));
                if (row && row.name) {
                    out.push(row.name);
                }
            });
        } else if (selVal != null) {
            const row = vals.find((x) => Number(x.id) === Number(selVal));
            if (row && row.name) {
                out.push(row.name);
            }
        }
        return out;
    }

    function galleryPhotosForSelection(selection) {
        for (const block of optionBlocks) {
            if ((block.value_type || 'text') !== 'color') {
                continue;
            }
            const gid = Number(block.id);
            if (!Number.isFinite(gid)) {
                continue;
            }
            const sv = selectionValue(selection, gid);
            if (sv == null) {
                continue;
            }
            // Multiple: беремо перше активне значення з привʼязаними фото.
            const valueIds = Array.isArray(sv) ? sv : [sv];
            for (const valueId of valueIds) {
                const row = (block.values || []).find((x) => Number(x.id) === Number(valueId));
                const photos = (row?.gallery_photos || []).filter(Boolean);
                if (photos.length) {
                    const items = pathsToGalleryItems(photos);
                    if (items.length) {
                        return items;
                    }
                }
            }
        }

        return null;
    }

    function updateBreadcrumbSelectedLine(parts) {
        if (!breadcrumbSelectedEl) {
            return;
        }
        breadcrumbSelectedEl.textContent = parts.length
            ? t('pdp_selected_prefix', 'Обрано: ') + parts.join(', ')
            : t('pdp_selected_empty', 'Обрано: —');
    }

    function updateBreadcrumbFromOptionSelection() {
        const parts = [];
        if (initialCategoryLabel) {
            parts.push(categoryPrefix + initialCategoryLabel);
        }
        const sel = getSelection();
        for (const block of optionBlocks) {
            const gid = Number(block.id);
            if (!Number.isFinite(gid) || gid === catalogCategoryGroupId) {
                continue;
            }
            const sv = selectionValue(sel, gid);
            if (sv == null) {
                continue;
            }
            const names = valueNamesForBlock(block, sv);
            if (names.length) {
                parts.push(block.name + ': ' + names.join(', '));
            }
        }
        updateBreadcrumbSelectedLine(parts.filter((p) => !p.startsWith(categoryPrefix)));
    }

    let galleryRendered = false;

    function galleryItemKey(item) {
        try {
            return new URL(item.url, window.location.href).href;
        } catch {
            return String(item.url);
        }
    }

    function mergeGalleryItems(...lists) {
        const seen = new Set();
        const out = [];
        lists.forEach((list) => {
            normalizeGalleryItems(list).forEach((item) => {
                const key = galleryItemKey(item);
                if (!seen.has(key)) {
                    seen.add(key);
                    out.push(item);
                }
            });
        });

        return out;
    }

    function sameGalleryItems(a, b) {
        return a.length === b.length && a.every((item, i) => galleryItemKey(item) === galleryItemKey(b[i]));
    }

    function focusGalleryItem(item) {
        const key = galleryItemKey(item);
        const index = galleryItemsState.items.findIndex((x) => galleryItemKey(x) === key);
        if (index < 0) {
            return;
        }
        const button = thumbsWrap
            ? thumbsWrap.querySelector('button[data-gallery-index="' + index + '"]')
            : null;
        if (button) {
            applyThumbToMain(button);
            if (typeof button.scrollIntoView === 'function') {
                button.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'nearest', inline: 'nearest' });
            }
        } else {
            renderMainMediaItem(galleryItemsState.items[index], { animate: false });
        }
    }

    function refresh({ focusOptionPhoto = false } = {}) {
        if (!mainVisual || !priceEl) {
            return;
        }

        let selection = getSelection();
        let variant = matchVariantForSelection(selection);
        applyExclusiveVisibility(variant ? variant.id : 0);
        selection = getSelection();
        variant = matchVariantForSelection(selection);
        if (optionValuesInput) {
            optionValuesInput.value = JSON.stringify(selectedOptionValueIds(selection));
        }
        if (variantIdInput) {
            variantIdInput.value = variant ? String(variant.id) : '';
        }

        const price = calculateDisplayPrice(selection);
        setPriceDisplay(price.current, price.compare);
        // Єдина галерея: сервер уже віддає фото товару разом із фото всіх кольорів.
        // Фото варіанта без кольору додаються в кінець; при кліку по опції галерея
        // лише перемикається на перше її фото.
        const colorGalleryItems = galleryPhotosForSelection(selection) || [];
        const variantPhotos = variant && Array.isArray(variant.photos) ? variant.photos.filter(Boolean) : [];
        const variantGalleryItems = pathsToGalleryItems(variantPhotos);
        const nextGalleryItems = mergeGalleryItems(initialGalleryItemsFrozen, colorGalleryItems, variantGalleryItems);
        if (!galleryRendered || !sameGalleryItems(galleryItemsState.items, nextGalleryItems)) {
            setGallery(nextGalleryItems);
            galleryRendered = true;
        }
        if (focusOptionPhoto) {
            const focusItem = colorGalleryItems[0] || variantGalleryItems[0] || null;
            if (focusItem) {
                focusGalleryItem(focusItem);
            }
        }

        updateBreadcrumbFromOptionSelection();
        updateOptionGroupSelectionState();

        const missingGroups = missingVisibleOptionGroupLabels();
        const optionsIncomplete = requiresOptionSelection && missingGroups.length > 0;
        const matchingComplete = !optionsIncomplete;
        let stockMode = listingStockMode;
        if (variant) {
            stockMode = variant.stock_mode || 'none';
        } else if (variants.length && matchingComplete && hasMatchingAxis) {
            stockMode = 'none';
        }
        updateBadges(stockMode);

        if (addBtn) {
            addBtn.disabled = stockMode === 'none' || optionsIncomplete;
            addBtn.setAttribute('aria-disabled', addBtn.disabled ? 'true' : 'false');
        }
        if (hintEl) {
            if (optionsIncomplete) {
                hintEl.textContent = t('pdp_select_options_hint', 'Оберіть опцію: ') + missingGroups.join(', ');
            } else if (stockMode === 'none') {
                hintEl.textContent = t('pdp_unavailable_hint', 'Цей товар зараз недоступний.');
            } else if (stockMode === 'preorder') {
                hintEl.textContent = t('pdp_preorder_hint', 'Товар доступний під замовлення. Онлайн-оплата буде доступна після підтвердження менеджером.');
            } else {
                hintEl.textContent = '';
            }
        }
        if (priceNoteEl) {
            if (price.lineCount > 1) {
                priceNoteEl.textContent = t('pdp_lines_selected_prefix', 'Обрано позицій: ') + price.lineCount;
            } else {
                const addon = addonForOptionValueIds(selectedOptionValueIds(selection));
                const labels = selectedOptionLabelsForPriceNote(selection);
                priceNoteEl.textContent = addon <= 0 && labels.length
                    ? t('pdp_price_for_option', 'Ціна за: ') + labels.join(', ')
                    : '';
            }
        }
    }

    document.querySelectorAll('#product-options .opt-chip, #product-options .opt-swatch').forEach((el) => {
        el.addEventListener('click', () => {
            const group = el.closest('.opt-group');
            if (!group) {
                return;
            }
            const mode = group.dataset.mode || 'single';
            if (mode === 'multiple') {
                el.classList.toggle('active');
            } else {
                group.querySelectorAll('.opt-chip, .opt-swatch').forEach((x) => x.classList.remove('active'));
                el.classList.add('active');
                clearExclusiveSiblingSelections(group);
            }
            refresh({ focusOptionPhoto: true });
        });
    });

    if (thumbsWrap) {
        const onThumbInteract = (e) => {
            const b = e.target.closest('button[data-gallery-index]');
            if (!b || !thumbsWrap.contains(b)) {
                return;
            }
            applyThumbToMain(b);
        };
        thumbsWrap.addEventListener('click', onThumbInteract);
        thumbsWrap.addEventListener('mouseover', onThumbInteract);
        thumbsWrap.addEventListener('focusin', onThumbInteract);
    }

    if (galleryZone) {
        galleryZone.addEventListener(
            'mouseover',
            (e) => {
                if (!galleryZone.contains(e.target)) {
                    return;
                }
                const from = e.relatedTarget;
                if (from && galleryZone.contains(from)) {
                    return;
                }
                galleryPointerInside = true;
                clearGalleryAutoplayTimer();
            },
            true
        );
        galleryZone.addEventListener(
            'mouseout',
            (e) => {
                if (!galleryZone.contains(e.target)) {
                    return;
                }
                const to = e.relatedTarget;
                if (to && galleryZone.contains(to)) {
                    return;
                }
                galleryPointerInside = false;
                restartGalleryAutoplay();
            },
            true
        );
        galleryZone.addEventListener('focusin', () => {
            galleryFocusInside = true;
            clearGalleryAutoplayTimer();
        });
        galleryZone.addEventListener('focusout', (e) => {
            if (e.relatedTarget && galleryZone.contains(e.relatedTarget)) {
                return;
            }
            galleryFocusInside = false;
            restartGalleryAutoplay();
        });
    }

    const pdpDefersOnlinePayment = Boolean(cfg.pdpDefersOnlinePayment);
    const cartForm = document.getElementById('product-cart-form');
    const deferModal = document.getElementById('pdp-defer-payment-modal');
    let allowDeferModalBypass = false;
    let deferModalClosing = false;

    /** Уникнути прив’язки fixed до колонки PDP з overflow (або інших предків). */
    if (deferModal && deferModal.parentElement !== document.body) {
        document.body.appendChild(deferModal);
    }

    function setDeferModalOpen(open) {
        if (!deferModal) {
            return;
        }
        const panel = deferModal.querySelector('.pdp-defer-modal__panel');
        if (open) {
            deferModalClosing = false;
            deferModal.hidden = false;
            deferModal.setAttribute('aria-hidden', 'false');
            deferModal.classList.remove('is-visible');
            requestAnimationFrame(() => {
                deferModal.classList.add('is-visible');
            });
            const toFocus = deferModal.querySelector('.pdp-defer-modal__panel a[href], .pdp-defer-modal__panel button');
            if (toFocus && typeof toFocus.focus === 'function') {
                toFocus.focus();
            }
            return;
        }

        if (deferModal.hidden || deferModalClosing) {
            return;
        }

        deferModalClosing = true;
        deferModal.classList.remove('is-visible');
        const finishClose = () => {
            deferModalClosing = false;
            deferModal.hidden = true;
            deferModal.setAttribute('aria-hidden', 'true');
        };

        if (!panel) {
            finishClose();
            return;
        }

        let closed = false;
        const onEnd = (e) => {
            if (e.target !== panel || (e.propertyName !== 'opacity' && e.propertyName !== 'transform')) {
                return;
            }
            closed = true;
            panel.removeEventListener('transitionend', onEnd);
            finishClose();
        };
        panel.addEventListener('transitionend', onEnd);
        window.setTimeout(() => {
            if (!closed) {
                panel.removeEventListener('transitionend', onEnd);
                finishClose();
            }
        }, 350);
    }

    function handleCartFormSubmit(e) {
        if (addBtn && addBtn.disabled) {
            e.preventDefault();
            e.stopPropagation();
            refresh();
            return false;
        }
        if (!isOptionSelectionComplete()) {
            e.preventDefault();
            e.stopPropagation();
            refresh();
            const firstIncomplete = getVisibleOptionGroups().find((group) => !isVisibleGroupSelectionComplete(group));
            if (firstIncomplete && typeof firstIncomplete.scrollIntoView === 'function') {
                firstIncomplete.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'center' });
            }
            return false;
        }

        return true;
    }

    if (cartForm && pdpDefersOnlinePayment && deferModal) {
        cartForm.addEventListener('submit', (e) => {
            if (!handleCartFormSubmit(e)) {
                return;
            }
            if (allowDeferModalBypass) {
                allowDeferModalBypass = false;
                return;
            }
            e.preventDefault();
            // Інакше спливаючий submit дійде до document у layout і submitCartForm додасть позицію без згоди з модалкою (подвійне додавання після «Продовжити»).
            e.stopPropagation();
            setDeferModalOpen(true);
        });

        deferModal.querySelectorAll('[data-pdp-defer-close]').forEach((el) => {
            el.addEventListener('click', () => setDeferModalOpen(false));
        });

        const continueBtn = deferModal.querySelector('[data-pdp-defer-continue]');
        if (continueBtn) {
            continueBtn.addEventListener('click', () => {
                setDeferModalOpen(false);
                allowDeferModalBypass = true;
                if (typeof cartForm.requestSubmit === 'function') {
                    cartForm.requestSubmit();
                } else {
                    cartForm.submit();
                }
            });
        }

        document.addEventListener(
            'pointerdown',
            (e) => {
                if (deferModal.hidden || !deferModal.classList.contains('is-visible')) {
                    return;
                }
                const panel = deferModal.querySelector('.pdp-defer-modal__panel');
                if (panel && panel.contains(e.target)) {
                    return;
                }
                setDeferModalOpen(false);
            },
            true
        );

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && deferModal && !deferModal.hidden) {
                setDeferModalOpen(false);
            }
        });
    } else if (cartForm && requiresOptionSelection) {
        cartForm.addEventListener('submit', (e) => {
            handleCartFormSubmit(e);
        });
    }

    if (variantPicker) {
        variantPicker.addEventListener('change', () => {
            selectedBareVariantId = Number(variantPicker.value) || 0;
            refresh({ focusOptionPhoto: true });
        });
    }

    ensureDefaultOptionSelection();
    refresh();

    if (typeof window.zoogleSyncCatalogCardMedia === 'function') {
        window.zoogleSyncCatalogCardMedia(galleryZone || mainVisual || document);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runProductShowPage);
} else {
    runProductShowPage();
}
