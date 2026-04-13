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
        img.src = first;

        function previewThumb(buttonEl, src) {
            if (!thumbsWrap || !img) {
                return;
            }
            thumbsWrap.querySelectorAll('button').forEach((x) => x.classList.remove('active'));
            buttonEl.classList.add('active');
            img.src = src;
        }

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
                b.addEventListener('click', () => previewThumb(b, src));
                b.addEventListener('mouseenter', () => previewThumb(b, src));
                b.addEventListener('focus', () => previewThumb(b, src));
                thumbsWrap.appendChild(b);
            });
        } else if (img) {
            img.src = first;
        }
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
        thumbsWrap.addEventListener('click', (e) => {
            const b = e.target.closest('button[data-src]');
            if (!b || !thumbsWrap.contains(b)) {
                return;
            }
            const img = getMainImg();
            if (!img) {
                return;
            }
            thumbsWrap.querySelectorAll('button').forEach((x) => x.classList.remove('active'));
            b.classList.add('active');
            img.src = b.dataset.src;
        });
        thumbsWrap.addEventListener('mouseover', (e) => {
            const b = e.target.closest('button[data-src]');
            if (!b || !thumbsWrap.contains(b)) {
                return;
            }
            const img = getMainImg();
            if (!img) {
                return;
            }
            thumbsWrap.querySelectorAll('button').forEach((x) => x.classList.remove('active'));
            b.classList.add('active');
            img.src = b.dataset.src;
        });
        thumbsWrap.addEventListener('focusin', (e) => {
            const b = e.target.closest('button[data-src]');
            if (!b || !thumbsWrap.contains(b)) {
                return;
            }
            const img = getMainImg();
            if (!img) {
                return;
            }
            thumbsWrap.querySelectorAll('button').forEach((x) => x.classList.remove('active'));
            b.classList.add('active');
            img.src = b.dataset.src;
        });
    }

    refresh();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runProductShowPage);
} else {
    runProductShowPage();
}
