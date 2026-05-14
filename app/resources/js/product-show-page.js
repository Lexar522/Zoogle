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
    const catalogCategoryGroupId = Number(cfg.catalogCategoryGroupId || 0);
    const initialCategoryLabel = cfg.initialCategoryLabel || '';
    const initialPhotos = cfg.initialPhotos || [];
    const storageBase = cfg.storageBase || '';
    const listingTitle = cfg.listingTitle || '';

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

    function getMainImg() {
        return mainVisual ? mainVisual.querySelector('#product-main-img') : null;
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
    function setMainImageSrc(img, newSrc, { animate } = {}) {
        if (!img || !newSrc) {
            return;
        }
        if (urlsEqual(img.src, newSrc)) {
            return;
        }
        mainImageTransitionToken += 1;
        const token = mainImageTransitionToken;
        const shouldAnimate = Boolean(animate) && !prefersReducedMotion;

        if (!shouldAnimate) {
            img.src = newSrc;
            img.style.opacity = '';
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
        return thumbsWrap ? thumbsWrap.querySelectorAll('button[data-src]').length : 0;
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
        const buttons = thumbsWrap.querySelectorAll('button[data-src]');
        if (buttons.length <= 1) {
            return;
        }
        const active = thumbsWrap.querySelector('button[data-src].active');
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
        const src = button && button.dataset ? button.dataset.src : '';
        const img = getMainImg();
        if (!src || !thumbsWrap || !img) {
            return;
        }
        thumbsWrap.querySelectorAll('button').forEach((x) => x.classList.remove('active'));
        button.classList.add('active');
        setMainImageSrc(img, src, { animate: thumbButtonCount() > 1 });
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

    function calculateDisplayPrice(baseCurrent, baseCompare, selection) {
        const lines = splitSelectionToLineOptionValueIds(selection);
        let current = 0;
        let compare = baseCompare != null && !Number.isNaN(Number(baseCompare)) ? 0 : null;

        lines.forEach((lineIds) => {
            const addon = addonForOptionValueIds(lineIds);
            current += Number(baseCurrent) + addon;
            if (compare != null) {
                compare += Number(baseCompare) + addon;
            }
        });

        return {
            current,
            compare,
            lineCount: Math.max(1, lines.length),
        };
    }

    function toGalleryUrls(paths) {
        return (paths || []).map((p) => storageBase + '/' + String(p).replace(/^\/+/, ''));
    }

    function setGallery(photos) {
        if (thumbsWrap) {
            thumbsWrap.style.display = photos && photos.length > 1 ? '' : 'none';
        }
        if (!photos || !photos.length) {
            if (mainVisual) {
                mainVisual.classList.remove('product-gallery-main--multi-img');
            }
            const oldImg = getMainImg();
            if (oldImg) {
                oldImg.remove();
            }
            if (!mainVisual.querySelector('.placeholder')) {
                const ph = document.createElement('span');
                ph.className = 'placeholder';
                ph.textContent = 'Немає фото';
                mainVisual.appendChild(ph);
            }
            if (thumbsWrap) {
                thumbsWrap.innerHTML = '';
            }
            clearGalleryAutoplayTimer();
            return;
        }
        const first = photos[0];
        let img = mainVisual.querySelector('#product-main-img');
        if (!img) {
            mainVisual.querySelector('.placeholder')?.remove();
            img = document.createElement('img');
            img.id = 'product-main-img';
            img.alt = listingTitle;
            mainVisual.appendChild(img);
        }
        if (mainVisual) {
            mainVisual.classList.toggle('product-gallery-main--multi-img', photos.length > 1);
        }
        setMainImageSrc(img, first, { animate: false });

        if (thumbsWrap) {
            thumbsWrap.innerHTML = '';
            photos.forEach((src, i) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = i === 0 ? 'active' : '';
                b.dataset.src = src;
                const im = document.createElement('img');
                im.src = src;
                im.alt = '';
                b.appendChild(im);
                thumbsWrap.appendChild(b);
            });
        } else if (img) {
            setMainImageSrc(img, first, { animate: false });
        }
        restartGalleryAutoplay();
    }

    function updateBadges() {
        if (!badgesEl) {
            return;
        }
        let html = '';
        if (listingStockMode === 'preorder') {
            html = '<span class="badge pre">Передзамовлення</span>';
        } else if (listingStockMode === 'low') {
            html = '<span class="badge low">Закінчується</span>';
        } else if (listingStockMode === 'ok') {
            html = '<span class="badge ok">В наявності</span>';
        } else {
            html = '<span class="badge no">Немає в наявності</span>';
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
            if (sv == null || Array.isArray(sv)) {
                continue;
            }
            const row = (block.values || []).find((x) => Number(x.id) === Number(sv));
            const photos = (row?.gallery_photos || []).filter(Boolean);
            if (photos.length) {
                return toGalleryUrls(photos);
            }
        }

        return null;
    }

    function updateBreadcrumbSelectedLine(parts) {
        if (!breadcrumbSelectedEl) {
            return;
        }
        breadcrumbSelectedEl.textContent = parts.length ? 'Обрано: ' + parts.join(', ') : 'Обрано: —';
    }

    function updateBreadcrumbFromOptionSelection() {
        const parts = [];
        if (initialCategoryLabel) {
            parts.push('Категорія: ' + initialCategoryLabel);
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
        updateBreadcrumbSelectedLine(parts.filter((p) => !p.startsWith('Категорія: ')));
    }

    function refresh() {
        if (!mainVisual || !priceEl) {
            return;
        }

        const selection = getSelection();
        if (optionValuesInput) {
            optionValuesInput.value = JSON.stringify(selectedOptionValueIds(selection));
        }

        const price = calculateDisplayPrice(listingPrice, listingCompareAt, selection);
        setPriceDisplay(price.current, price.compare);
        setGallery(galleryPhotosForSelection(selection) || initialPhotos);
        updateBadges();

        if (addBtn) {
            addBtn.disabled = listingStockMode === 'none';
        }
        if (hintEl) {
            hintEl.textContent = listingStockMode === 'none' ? 'Цей товар зараз недоступний.' : '';
        }
        if (priceNoteEl) {
            priceNoteEl.textContent = price.lineCount > 1 ? 'Обрано позицій: ' + price.lineCount : '';
        }

        updateBreadcrumbFromOptionSelection();
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
            }
            refresh();
        });
    });

    if (thumbsWrap) {
        const onThumbInteract = (e) => {
            const b = e.target.closest('button[data-src]');
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

    if (cartForm && pdpDefersOnlinePayment && deferModal) {
        cartForm.addEventListener('submit', (e) => {
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
    }

    refresh();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runProductShowPage);
} else {
    runProductShowPage();
}
