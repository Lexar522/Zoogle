(function () {
    var MOBILE_PANEL_MQ = window.matchMedia('(max-width: 1199px)');
    var FLYOUT_PORTAL_CLASS = 'catalog-subcategory-list--flyout-portal';
    var HEADER_FILTERS_SELECTOR = '.catalog-filters.catalog-filters--in-header';

    function isMobileCategoryPanel() {
        return MOBILE_PANEL_MQ.matches;
    }

    function isInHeaderFilters(el) {
        return !!(el && el.closest(HEADER_FILTERS_SELECTOR));
    }

    /** Flyout у body: перевіряємо хост-панель, якщо елемент уже не під .catalog-filters */
    function isInHeaderCategoryNav(el) {
        if (!el) {
            return false;
        }
        if (isInHeaderFilters(el)) {
            return true;
        }
        var portalRoot = el.closest('.catalog-subcategory-list--flyout-portal');
        if (portalRoot && portalRoot._zooglePortalHost) {
            return isInHeaderFilters(portalRoot._zooglePortalHost);
        }
        return false;
    }

    function getCategoryPanelAnchor(li) {
        return li.querySelector('.catalog-category-list__row--top')
            || li.querySelector('.catalog-category-list__row')
            || li.querySelector('.catalog-category-list__link');
    }

    function getMobileCategoryListRoot() {
        if (!isMobileCategoryPanel()) {
            return null;
        }
        return document.querySelector('.catalog-filters__scroll.catalog-category-hub__mobile > .catalog-category-list');
    }

    function isTopLevelCategoryLi(li) {
        return !!(li && li.parentElement && li.parentElement.classList.contains('catalog-category-list'));
    }

    function shouldPortalTopLi(li) {
        if (!isInHeaderCategoryNav(li)) {
            return false;
        }
        if (isMobileCategoryPanel()) {
            return false;
        }
        return true;
    }

    function shouldPortalNestedLi(li) {
        return isInHeaderCategoryNav(li) && !isMobileCategoryPanel();
    }

    function shouldPortalLi(li) {
        if (!li) {
            return false;
        }
        return isTopLevelCategoryLi(li) ? shouldPortalTopLi(li) : shouldPortalNestedLi(li);
    }

    function isFlyoutOpenLi(li) {
        return li.classList.contains('is-panel-open') || li.classList.contains('is-expanded');
    }

    function linkLabelIsTruncated(link) {
        var label = link.querySelector('.catalog-category-list__label');
        if (!label) {
            return false;
        }
        var isVisible = !!(label.offsetWidth || label.offsetHeight || label.getClientRects().length);
        return isVisible && (
            label.scrollWidth > label.clientWidth + 1
            || label.scrollHeight > label.clientHeight + 1
        );
    }

    function syncCategoryOverflowTooltips() {
        document.querySelectorAll('.catalog-category-list__link[data-category-tooltip]').forEach(function (link) {
            var label = link.querySelector('.catalog-category-list__label');
            if (!label) {
                link.classList.remove('has-truncated-label');
                return;
            }

            link.classList.toggle('has-truncated-label', linkLabelIsTruncated(link));
        });
    }

    var categoryTipTimer = null;
    var categoryTipLink = null;
    var categoryTipZone = null;

    function hideCategoryTip() {
        if (categoryTipTimer) {
            clearTimeout(categoryTipTimer);
            categoryTipTimer = null;
        }
        categoryTipLink = null;
        categoryTipZone = null;
        var tip = document.querySelector('.catalog-category-tip');
        if (tip) {
            tip.classList.remove('is-visible');
        }
    }

    function placeCategoryTip(link) {
        var text = (link.getAttribute('data-category-tooltip') || '').trim();
        if (text === '' || !link.classList.contains('has-truncated-label')) {
            return;
        }
        var tip = document.querySelector('.catalog-category-tip');
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'catalog-category-tip';
            tip.setAttribute('role', 'tooltip');
            document.body.appendChild(tip);
        }
        tip.textContent = text;
        tip.classList.add('is-visible');
        tip.style.left = '0px';
        tip.style.top = '0px';
        var rect = link.getBoundingClientRect();
        var tipRect = tip.getBoundingClientRect();
        var left = Math.round(rect.left + (rect.width - tipRect.width) / 2);
        left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));
        var top = Math.round(rect.bottom + 6);
        if (top + tipRect.height > window.innerHeight - 8) {
            top = Math.max(8, Math.round(rect.top - tipRect.height - 6));
        }
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
    }

    function armCategoryTip(link) {
        var zone = link.closest('.catalog-category-list__row') || link;
        if (categoryTipZone === zone) {
            return;
        }
        hideCategoryTip();
        link.classList.toggle('has-truncated-label', linkLabelIsTruncated(link));
        if (!link.classList.contains('has-truncated-label')) {
            return;
        }
        categoryTipLink = link;
        categoryTipZone = zone;
        categoryTipTimer = setTimeout(function () {
            categoryTipTimer = null;
            if (categoryTipLink === link && document.contains(link)) {
                placeCategoryTip(link);
            }
        }, 500);
    }

    function cleanupOrphanFlyoutPortals() {
        document.querySelectorAll('body > .catalog-subcategory-list--flyout-portal').forEach(function (panel) {
            var host = panel._zooglePortalHost;
            panel.classList.remove(FLYOUT_PORTAL_CLASS);
            panel.style.removeProperty('top');
            panel.style.removeProperty('left');
            panel.style.removeProperty('right');
            panel.style.removeProperty('width');
            panel.style.removeProperty('min-width');
            panel.style.removeProperty('max-width');
            panel.style.removeProperty('max-height');
            if (host && document.contains(host)) {
                host.appendChild(panel);
                panel._zooglePortaled = false;
                delete panel._zooglePortalHost;
                delete host._zoogleFlyoutPanelEl;
            } else {
                panel.remove();
            }
        });
    }

    function getFlyoutPanel(li) {
        if (li._zoogleFlyoutPanelEl) {
            return li._zoogleFlyoutPanelEl;
        }
        return li.querySelector(':scope > .catalog-subcategory-list');
    }

    function syncMobilePanelOpenClass() {
        var hasOpen = isMobileCategoryPanel()
            && document.querySelector('.catalog-category-list > li.is-panel-open');
        document.querySelectorAll(HEADER_FILTERS_SELECTOR).forEach(function (root) {
            root.classList.toggle('catalog-filters--mobile-panel-open', !!hasOpen);
        });
    }

    function positionFlyoutPanel(li) {
        if (!li || !isFlyoutOpenLi(li)) {
            return;
        }
        var panel = getFlyoutPanel(li);
        var anchor = getCategoryPanelAnchor(li);
        if (!panel || !anchor || !panel.classList.contains(FLYOUT_PORTAL_CLASS)) {
            return;
        }

        var rect = anchor.getBoundingClientRect();
        var gap = isTopLevelCategoryLi(li) ? 8 : 10;
        var safeLeft = 12;
        var safeRight = 12;
        var safeBottom = 12;

        if (isMobileCategoryPanel()) {
            panel.style.left = 'max(' + safeLeft + 'px, env(safe-area-inset-left, 0px))';
            panel.style.right = 'max(' + safeRight + 'px, env(safe-area-inset-right, 0px))';
            panel.style.width = 'auto';
            panel.style.minWidth = '0';
            panel.style.maxWidth = 'none';
            var top = Math.round(rect.bottom + gap);
            var viewportBottomInset = window.visualViewport
                ? Math.max(0, window.innerHeight - window.visualViewport.height - window.visualViewport.offsetTop)
                : 0;
            var available = Math.max(120, window.innerHeight - top - safeBottom - viewportBottomInset);
            panel.style.top = top + 'px';
            panel.style.maxHeight = Math.min(available, Math.round(window.innerHeight * 0.58)) + 'px';
            return;
        }

        var top = Math.round(rect.bottom + gap);
        var left = Math.round(rect.left);
        panel.style.right = 'auto';
        panel.style.width = 'max-content';
        panel.style.minWidth = 'min(320px, calc(100vw - 32px))';
        panel.style.maxWidth = 'min(calc(100vw - 24px), 720px)';

        var panelWidth = panel.offsetWidth || 320;
        var maxLeft = Math.max(safeLeft, window.innerWidth - panelWidth - safeRight);
        left = Math.max(safeLeft, Math.min(left, maxLeft));

        var maxHeight = Math.min(560, Math.round(window.innerHeight * 0.68));
        var availableBelow = window.innerHeight - top - safeBottom;
        if (availableBelow < 140 && rect.top > maxHeight) {
            top = Math.max(safeBottom, Math.round(rect.top - gap - Math.min(maxHeight, panel.offsetHeight || maxHeight)));
        }

        panel.style.top = top + 'px';
        panel.style.left = left + 'px';
        panel.style.maxHeight = Math.min(maxHeight, window.innerHeight - top - safeBottom) + 'px';
    }

    function mountFlyoutPanel(li) {
        if (!li || !isFlyoutOpenLi(li) || !shouldPortalLi(li)) {
            return;
        }
        var panel = li.querySelector(':scope > .catalog-subcategory-list');
        if (!panel) {
            return;
        }
        if (!panel._zooglePortaled) {
            li._zoogleFlyoutPanelEl = panel;
            panel._zooglePortalHost = li;
            // Стартова позиція за межами екрана, щоб уникнути спалаху в куті (0,0)
            // до синхронного перерахунку нижче.
            panel.style.top = '-9999px';
            panel.style.left = '-9999px';
            panel.classList.add(FLYOUT_PORTAL_CLASS);
            document.body.appendChild(panel);
            panel._zooglePortaled = true;
        }
        syncMobilePanelOpenClass();
        // Синхронно (до відмалювання кадру) — без requestAnimationFrame,
        // інакше блок встигає блимнути у верхньому лівому куті.
        positionFlyoutPanel(li);
    }

    function unmountFlyoutPanel(li) {
        if (!li) {
            return;
        }
        var panel = li._zoogleFlyoutPanelEl || li.querySelector(':scope > .catalog-subcategory-list');
        if (!panel) {
            return;
        }
        panel.classList.remove(FLYOUT_PORTAL_CLASS);
        panel.style.removeProperty('top');
        panel.style.removeProperty('left');
        panel.style.removeProperty('right');
        panel.style.removeProperty('width');
        panel.style.removeProperty('min-width');
        panel.style.removeProperty('max-width');
        panel.style.removeProperty('max-height');
        if (panel._zooglePortaled && panel._zooglePortalHost) {
            panel._zooglePortalHost.appendChild(panel);
            panel._zooglePortaled = false;
            delete panel._zooglePortalHost;
        }
        delete li._zoogleFlyoutPanelEl;
    }

    function clearFlyoutPanel(li) {
        unmountFlyoutPanel(li);
        syncMobilePanelOpenClass();
    }

    function syncFlyoutPanels() {
        var portaled = document.querySelector('body > .catalog-subcategory-list--flyout-portal');
        var open = document.querySelector(
            HEADER_FILTERS_SELECTOR + ' .catalog-category-list > li.is-panel-open, '
            + HEADER_FILTERS_SELECTOR + ' li.is-expanded'
        );
        if (!portaled && !open) {
            return;
        }

        cleanupOrphanFlyoutPortals();

        document.querySelectorAll(HEADER_FILTERS_SELECTOR + ' .catalog-category-list > li.has-children').forEach(function (li) {
            if (li.classList.contains('is-panel-open') && shouldPortalTopLi(li)) {
                mountFlyoutPanel(li);
            } else if (isTopLevelCategoryLi(li)) {
                clearFlyoutPanel(li);
            }
        });

        document.querySelectorAll('body > .catalog-subcategory-list--flyout-portal').forEach(function (panel) {
            var host = panel._zooglePortalHost;
            if (host && isFlyoutOpenLi(host) && shouldPortalLi(host)) {
                positionFlyoutPanel(host);
            } else if (host) {
                clearFlyoutPanel(host);
            }
        });

        syncMobilePanelOpenClass();
    }

    function onFiltersViewportChange() {
        syncFlyoutPanels();
        syncCategoryOverflowTooltips();
    }

    function setExpandButtonState(btn, expanded) {
        if (!btn) {
            return;
        }
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        var label = expanded ? btn.getAttribute('data-collapse-label') : btn.getAttribute('data-expand-label');
        if (label) {
            btn.setAttribute('aria-label', label);
        }
    }

    function resetNestedExpandsInSubtree(rootLi) {
        if (!rootLi) {
            return;
        }
        rootLi.querySelectorAll('.catalog-subcategory-list li.is-expanded').forEach(function (li) {
            li.classList.remove('is-expanded');
            clearFlyoutPanel(li);
            setExpandButtonState(li.querySelector('[data-category-expand]'), false);
        });
    }

    function closeTopCategoryPanels(exceptLi) {
        cleanupOrphanFlyoutPortals();
        document.querySelectorAll('.catalog-category-list > li.is-panel-open').forEach(function (li) {
            if (exceptLi && li === exceptLi) {
                return;
            }
            li.classList.remove('is-panel-open');
            resetNestedExpandsInSubtree(li);
            clearFlyoutPanel(li);
            setExpandButtonState(li.querySelector('[data-category-expand]'), false);
        });
    }

    function closeSiblingNestedExpands(exceptLi) {
        if (!exceptLi || !exceptLi.parentElement) {
            return;
        }
        exceptLi.parentElement.querySelectorAll(':scope > li.is-expanded').forEach(function (li) {
            if (li === exceptLi) {
                return;
            }
            li.classList.remove('is-expanded');
            clearFlyoutPanel(li);
            setExpandButtonState(li.querySelector('[data-category-expand]'), false);
        });
    }

    function closeNestedCategoryExpands() {
        document.querySelectorAll('.catalog-subcategory-list > li.is-expanded').forEach(function (li) {
            li.classList.remove('is-expanded');
            clearFlyoutPanel(li);
            setExpandButtonState(li.querySelector('[data-category-expand]'), false);
        });
    }

    function resetAllCategoryExpandState() {
        closeTopCategoryPanels();
        closeNestedCategoryExpands();
    }

    function openHubPanel(hubPanel) {
        if (!hubPanel) {
            return;
        }
        hubPanel.classList.remove('catalog-category-hub__panel--subs-collapsed');
        var expandBtn = hubPanel.querySelector('[data-hub-category-expand]');
        if (expandBtn) {
            setExpandButtonState(expandBtn, true);
        }
    }

    function expandActiveCategoryPath(skipReset) {
        if (!skipReset) {
            resetAllCategoryExpandState();
        }

        if (isMobileCategoryPanel()) {
            closeTopCategoryPanels();
            closeNestedCategoryExpands();
            syncFlyoutPanels();
            return;
        }

        document.querySelectorAll(
            '.catalog-category-list__link.is-active, '
            + '.catalog-category-hub__panel-head .catalog-category-list__link.is-active, '
            + '.catalog-category-hub__subs-list .catalog-category-list__link.is-active'
        ).forEach(function (a) {
            var hubPanel = a.closest('.catalog-category-hub__panel');
            if (hubPanel) {
                openHubPanel(hubPanel);
            }

            // У шапці на десктопі не авто-відкриваємо flyout-вікна (інакше вони
            // портуються в body і стають у кут). Вибране показуємо лише підсвіткою
            // .is-active та відкритою бічною панеллю хабу. Flyout — лише по кліку.
            if (isInHeaderFilters(a)) {
                return;
            }

            var activeLi = a.closest('li');
            if (!activeLi) {
                return;
            }
            if (activeLi.classList.contains('has-children')) {
                var activeParentList = activeLi.parentElement;
                if (activeParentList && activeParentList.classList.contains('catalog-category-list')) {
                    activeLi.classList.add('is-panel-open');
                    setExpandButtonState(activeLi.querySelector('[data-category-expand]'), true);
                }
            }
            var directParentBranch = activeLi.parentElement ? activeLi.parentElement.closest('li.has-children') : null;
            var parentLi = directParentBranch;
            while (parentLi) {
                var parentList = parentLi.parentElement;
                if (parentList && parentList.classList.contains('catalog-category-list')) {
                    parentLi.classList.add('is-panel-open');
                    setExpandButtonState(parentLi.querySelector('[data-category-expand]'), true);
                } else if (!(parentLi === directParentBranch && !activeLi.classList.contains('has-children'))) {
                    parentLi.classList.add('is-expanded');
                    setExpandButtonState(parentLi.querySelector('[data-category-expand]'), true);
                }
                parentLi = parentLi.parentElement ? parentLi.parentElement.closest('li.has-children') : null;
            }
        });
        syncFlyoutPanels();
    }

    document.addEventListener('click', function (e) {
        var hubExpandBtn = e.target.closest('[data-hub-category-expand]');
        if (hubExpandBtn) {
            e.preventDefault();
            e.stopPropagation();
            var hubPanel = hubExpandBtn.closest('.catalog-category-hub__panel');
            if (!hubPanel) {
                return;
            }
            var willOpen = hubPanel.classList.contains('catalog-category-hub__panel--subs-collapsed');
            if (willOpen) {
                closeTopCategoryPanels();
            }
            hubPanel.classList.toggle('catalog-category-hub__panel--subs-collapsed', !willOpen);
            setExpandButtonState(hubExpandBtn, willOpen);
            return;
        }

        var expandBtn = e.target.closest('[data-category-expand]');
        if (expandBtn) {
            e.preventDefault();
            e.stopPropagation();
            if (isMobileCategoryPanel() && isInHeaderFilters(expandBtn)) {
                return;
            }
            var li = expandBtn.closest('li.has-children');
            if (!li) {
                return;
            }
            var isTop = li.parentElement && li.parentElement.classList.contains('catalog-category-list');
            var willOpen = isTop ? !li.classList.contains('is-panel-open') : !li.classList.contains('is-expanded');
            if (isTop) {
                var mobileRoot = getMobileCategoryListRoot();
                if (isMobileCategoryPanel() && mobileRoot && !mobileRoot.contains(li)) {
                    return;
                }
                closeTopCategoryPanels(willOpen ? li : null);
                if (willOpen) {
                    resetNestedExpandsInSubtree(li);
                }
                li.classList.toggle('is-panel-open', willOpen);
                if (willOpen) {
                    requestAnimationFrame(function () {
                        mountFlyoutPanel(li);
                    });
                } else {
                    clearFlyoutPanel(li);
                }
            } else {
                if (willOpen) {
                    closeSiblingNestedExpands(li);
                }
                li.classList.toggle('is-expanded', willOpen);
                if (willOpen && shouldPortalNestedLi(li)) {
                    requestAnimationFrame(function () {
                        mountFlyoutPanel(li);
                    });
                } else {
                    clearFlyoutPanel(li);
                }
            }
            setExpandButtonState(expandBtn, willOpen);
            hideCategoryTip();
            requestAnimationFrame(syncCategoryOverflowTooltips);
            return;
        }

        if (!e.target.closest('.catalog-category-list')
            && !e.target.closest('.catalog-category-hub__panel')
            && !e.target.closest('.catalog-subcategory-list--flyout-portal')) {
            closeTopCategoryPanels();
            closeNestedCategoryExpands();
        }

        if (e.target.closest('[data-catalog-subcategory-filter]')) {
            closeTopCategoryPanels();
        }
    });

    document.addEventListener('pointerover', function (event) {
        var link = event.target.closest('.catalog-category-list__link[data-category-tooltip]');
        if (!link) {
            return;
        }
        armCategoryTip(link);
    });

    document.addEventListener('pointerout', function (event) {
        if (!categoryTipZone) {
            return;
        }
        var next = event.relatedTarget;
        if (next && (next === categoryTipZone || categoryTipZone.contains(next))) {
            return;
        }
        hideCategoryTip();
    });

    document.addEventListener('focusin', function (event) {
        var link = event.target.closest('.catalog-category-list__link[data-category-tooltip]');
        if (link) {
            armCategoryTip(link);
        }
    });

    document.addEventListener('focusout', function (event) {
        var link = event.target.closest('.catalog-category-list__link[data-category-tooltip]');
        if (link && categoryTipLink === link) {
            hideCategoryTip();
        }
    });

    document.addEventListener('scroll', function () {
        if (categoryTipTimer || !categoryTipLink) {
            return;
        }
        var tip = document.querySelector('.catalog-category-tip');
        if (!tip || !tip.classList.contains('is-visible')) {
            return;
        }
        placeCategoryTip(categoryTipLink);
    }, true);

    function boot() {
        expandActiveCategoryPath(true);
        syncCategoryOverflowTooltips();
        window.addEventListener('resize', onFiltersViewportChange);
        window.addEventListener('scroll', syncFlyoutPanels, { passive: true });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', onFiltersViewportChange);
            window.visualViewport.addEventListener('scroll', syncFlyoutPanels);
        }
        if (typeof MOBILE_PANEL_MQ.addEventListener === 'function') {
            MOBILE_PANEL_MQ.addEventListener('change', onFiltersViewportChange);
        } else if (typeof MOBILE_PANEL_MQ.addListener === 'function') {
            MOBILE_PANEL_MQ.addListener(onFiltersViewportChange);
        }
    }

    window.zoogleExpandActiveCategoryPath = expandActiveCategoryPath;
    window.zoogleResetAllCategoryExpandState = resetAllCategoryExpandState;

    window.addEventListener('pagehide', function () {
        hideCategoryTip();
        resetAllCategoryExpandState();
    });

    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) {
            return;
        }
        resetAllCategoryExpandState();
        expandActiveCategoryPath(true);
        syncCategoryOverflowTooltips();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
