@once
@push('styles')
<style>
:root {
    --shop-header-margin-bottom: 0px;
    --shop-shell-pad-top: 0px;
}

.catalog-filters {
    margin-top: 0;
    margin-bottom: 16px;
    padding: 0;
    position: -webkit-sticky;
    position: sticky;
    top: calc(var(--header-sticky-offset) - 1px);
    z-index: 40;
    overflow: visible;
    background: transparent;
    border: 0;
    box-shadow: none;
    transform: translateZ(0);
    backface-visibility: hidden;
    isolation: isolate;
}

.catalog-filters--in-header {
    margin-top: 0;
    margin-bottom: 0;
    margin-left: 0;
    margin-right: 0;
    padding-top: clamp(2px, 0.45vw, 6px);
    padding-bottom: clamp(2px, 0.45vw, 6px);
    padding-left: 0;
    padding-right: 0;
    position: static;
    top: auto;
    z-index: auto;
    background: transparent;
    border-radius: 20px;
    border: 0;
    box-shadow: none;
    transform: none;
    backface-visibility: visible;
}

.catalog-filters--in-header::before {
    display: none;
}

.catalog-filters--in-header .catalog-filters__scroll {
    padding-top: 0;
}

.catalog-filters--in-header .catalog-category-list {
    padding-top: 0;
    padding-bottom: 0;
    border-top: 0;
}

.catalog-filters.catalog-filters--in-header .catalog-category-list {
    justify-content: stretch;
    gap: 10px;
    padding-left: 5px;
    padding-right: 5px;
}

.catalog-filters.catalog-filters--in-header .catalog-category-list > li {
    flex: 1 1 0;
    min-width: 0;
}

.catalog-filters--in-header .catalog-category-list__chevron {
    font-size: 0.78rem;
}

.catalog-filters::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: -2px;
    height: 3px;
    background: transparent;
    pointer-events: none;
}

.catalog-filters:hover {
    box-shadow: none !important;
}

.site-main .alert ~ .catalog-filters {
    margin-top: 0;
}

.catalog-filters__scroll {
    position: relative;
    overflow: visible;
    display: flex;
    justify-content: center;
}

.catalog-filters .catalog-category-list {
    list-style: none;
    margin: 0;
    padding: 2px 0 6px;
    display: flex;
    width: 100%;
    flex-direction: row;
    flex-wrap: nowrap;
    justify-content: space-between;
    align-items: center;
    gap: 0;
    overflow: visible;
    white-space: nowrap;
    border-bottom: 0;
}

.catalog-filters .catalog-category-list > li {
    flex: 1 1 0;
    position: relative;
    padding-bottom: 0;
    margin-bottom: 0;
}

/* Міст для курсора: тримає hover між верхнім пунктом і випадаючою панеллю. */
.catalog-filters .catalog-category-list > li:has(> .catalog-subcategory-list)::before {
    content: '';
    position: absolute;
    left: 0;
    width: 100%;
    top: 100%;
    height: 14px;
    z-index: 88;
}

.catalog-filters .catalog-category-list__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 0 4px;
    min-height: 46px;
    height: 46px;
    border-radius: 0;
    border: 0;
    border-bottom: 3px solid transparent;
    text-decoration: none;
    color: #5f6368;
    background: transparent;
    line-height: 1.2;
    font-size: 1.36rem;
    white-space: nowrap;
    width: 100%;
    box-shadow: none;
    transition: color .16s ease, border-color .16s ease;
    position: relative;
    text-align: center;
}

.catalog-filters .catalog-category-list > li.catalog-category-list__item--promo {
    order: 999;
}

.catalog-filters .catalog-category-list__chevron {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #80868b;
    font-size: 1rem;
    line-height: 1;
    transform: translateY(-1px);
    transition: color .16s ease, transform .16s ease;
    position: absolute;
    right: 6px;
    top: 50%;
    translate: 0 -50%;
}

.catalog-category-list__row {
    display: flex;
    align-items: stretch;
    width: 100%;
    min-width: 0;
    gap: 0;
}

.catalog-category-list__row .catalog-category-list__link {
    flex: 1 1 auto;
    min-width: 0;
}

.catalog-category-list__expand {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    padding: 0;
    border: 0;
    background: transparent;
    color: #80868b;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.catalog-category-list__expand .catalog-category-list__chevron {
    position: static;
    right: auto;
    top: auto;
    translate: none;
    margin: 0;
    transform: rotate(90deg);
    font-size: 0.82rem;
}

.catalog-filters.catalog-filters--in-header .catalog-category-list__row--top {
    --category-chip-height: 62px;
    --category-chip-expand-size: 38px;
    position: relative;
    display: inline-flex;
    align-items: stretch;
    width: auto;
    min-width: 0;
    height: var(--category-chip-height);
    min-height: var(--category-chip-height);
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}

.catalog-filters.catalog-filters--in-header .catalog-category-list__row--top .catalog-category-list__link {
    flex: 1 1 auto;
    width: auto;
    max-width: none;
    min-width: 0;
    height: 100%;
    min-height: 0;
    margin: 0;
    padding-inline: calc(var(--category-chip-expand-size) + 4px) calc(var(--category-chip-expand-size) + 2px);
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    transform: none;
}

.catalog-filters.catalog-filters--in-header .catalog-category-list__row--top .catalog-category-list__expand {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: var(--category-chip-expand-size);
    min-width: var(--category-chip-expand-size);
}

.catalog-filters.catalog-filters--in-header .catalog-category-list > li.is-panel-open > .catalog-category-list__row--top {
    box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.35);
}

.catalog-filters .catalog-category-list__link:hover,
.catalog-filters .catalog-category-list__link:focus-visible {
    color: #202124;
    border-color: #d2e3fc;
}

.catalog-filters .catalog-category-list__link.is-active {
    background: transparent;
    border-color: #1a73e8;
    color: #1a73e8;
    font-weight: 600;
}

/* Хедер: білі «пігулки», радіус 20px, 5px між пунктами та від країв рядка (перебиває базові link). */
.catalog-filters.catalog-filters--in-header .catalog-category-list > li:hover,
.catalog-filters.catalog-filters--in-header .catalog-category-list > li:focus-within {
    z-index: 6;
}

/* У шапці li широкі (flex): міст на 100% ширини li ловить hover над контентом каталогу (чіпи тулбару) — звужуємо під пігулку. */
.catalog-filters.catalog-filters--in-header .catalog-category-list > li:has(> .catalog-subcategory-list)::before {
    left: 50%;
    right: auto;
    width: min(220px, 78%);
    transform: translateX(-50%);
    height: 10px;
    z-index: 1;
}

.catalog-filters.catalog-filters--in-header .catalog-category-list__link {
    min-height: 62px;
    height: auto;
    font-size: 1.08rem;
    font-weight: 500;
    gap: 6px;
    padding: 12px 14px;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    background: #ffffff;
    border-radius: 20px;
    border-bottom: 0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    transition:
        color .22s ease,
        border-color .22s ease,
        box-shadow .22s ease,
        transform .22s cubic-bezier(0.4, 0, 0.2, 1);
}

.catalog-filters.catalog-filters--in-header .catalog-category-list__link:hover,
.catalog-filters.catalog-filters--in-header .catalog-category-list__link:focus-visible {
    color: #202124;
    border-color: transparent;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
}

.catalog-filters.catalog-filters--in-header .catalog-category-list__link.is-active {
    background: #ffffff;
    border-color: transparent;
    color: #1a73e8;
    font-weight: 600;
    box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.35);
}

/* Десктоп: легке збільшення чіпа при наведенні (без зміни font-size). */
@media (hover: hover) and (pointer: fine) and (min-width: 901px) {
    .catalog-filters.catalog-filters--in-header .catalog-category-list__row--top {
        transition: transform .22s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:hover > .catalog-category-list__row--top,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:focus-within > .catalog-category-list__row--top {
        transform: scale(1.04);
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:not(.has-children):hover > .catalog-category-list__link--parent,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:not(.has-children):focus-within > .catalog-category-list__link--parent,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li.catalog-category-list__item--promo:hover > .catalog-category-list__link,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li.catalog-category-list__item--promo:focus-within > .catalog-category-list__link {
        transform: scale(1.04);
    }
}

@media (prefers-reduced-motion: reduce) {
    .catalog-filters.catalog-filters--in-header .catalog-category-list__link {
        transition: color .16s ease, border-color .16s ease, box-shadow .16s ease;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__row--top,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:hover > .catalog-category-list__row--top,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:focus-within > .catalog-category-list__row--top,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:not(.has-children):hover > .catalog-category-list__link--parent,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:not(.has-children):focus-within > .catalog-category-list__link--parent,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li.catalog-category-list__item--promo:hover > .catalog-category-list__link,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li.catalog-category-list__item--promo:focus-within > .catalog-category-list__link {
        transform: none;
    }
}

.catalog-filters.catalog-filters--in-header .catalog-category-list__label {
    font-size: inherit;
    font-weight: inherit;
    line-height: inherit;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.catalog-filters .catalog-category-list__link--sub {
    opacity: .95;
}

.catalog-filters .catalog-category-list > li > .catalog-subcategory-list {
    list-style: none;
    margin: 0;
    padding: 12px;
    position: absolute;
    left: 0;
    top: calc(100% + 10px);
    /* Ширина під найширший вміст (вкладені рівні), обмеження — лише вікно */
    width: max-content;
    min-width: min(320px, calc(100vw - 32px));
    max-width: min(calc(100vw - 24px), 720px);
    max-height: min(68vh, 560px);
    overflow-x: clip;
    overflow-y: auto;
    scrollbar-gutter: stable;
    box-sizing: border-box;
    display: block;
    border: 1px solid #e6e9ef;
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 20px 54px rgba(15, 23, 42, 0.14), 0 6px 18px rgba(15, 23, 42, 0.08);
    z-index: 90;
    opacity: 0;
    pointer-events: none;
    visibility: hidden;
    transform: translateY(8px) scale(.985);
    transition:
        opacity .16s ease,
        transform .16s ease,
        visibility 0s linear .16s;
}

.catalog-filters .catalog-category-list > li:nth-last-child(-n+3) > .catalog-subcategory-list {
    left: auto;
    right: 0;
}

.catalog-filters .catalog-category-list > li:hover > .catalog-subcategory-list,
.catalog-filters .catalog-category-list > li:focus-within > .catalog-subcategory-list {
    opacity: 1;
    pointer-events: auto;
    visibility: visible;
    transform: translateY(0) scale(1);
    transition:
        opacity .16s ease,
        transform .16s ease,
        visibility 0s linear 0s;
}

/* Вкладені рівні в панелі: за замовчуванням згорнуті, розгортаються при hover / focus-within.
   Не задавати visibility: visible на вкладених ul — інакше при глибокій категорії (:has(.is-active)) дочірній
   блок може стати видимим/клікабельним поверх сторінки, коли кореневе меню ще закрите (visibility: hidden). */
.catalog-filters .catalog-subcategory-list > li.has-children > .catalog-subcategory-list {
    position: static;
    width: auto;
    min-width: 0;
    max-width: none;
    max-height: 0;
    overflow: hidden;
    margin: 0;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    opacity: 0;
    pointer-events: none;
    transform: none;
    display: block;
    transition:
        max-height 0.22s cubic-bezier(0.32, 0.94, 0.34, 1),
        opacity 0.16s ease,
        margin 0.2s ease,
        padding 0.2s ease;
}

.catalog-filters .catalog-subcategory-list > li.has-children:hover > .catalog-subcategory-list,
.catalog-filters .catalog-subcategory-list > li.has-children:focus-within > .catalog-subcategory-list,
.catalog-filters .catalog-subcategory-list > li.has-children:has(.is-active) > .catalog-subcategory-list {
    max-height: min(2000px, 80vh);
    overflow-x: hidden;
    overflow-y: auto;
    opacity: 1;
    pointer-events: auto;
    margin: 6px 0 10px 16px;
    padding: 2px 0 0 12px;
    border-left: 1px solid #e4e8f0;
    transition:
        max-height 0.22s cubic-bezier(0.32, 0.94, 0.34, 1),
        opacity 0.16s ease,
        margin 0.2s ease,
        padding 0.2s ease;
}

.catalog-filters .catalog-subcategory-list > li {
    position: relative;
    padding: 2px 0;
}

.catalog-filters .catalog-category-list > li > .catalog-subcategory-list > li + li {
    margin-top: 8px;
    padding-top: 0;
    border-top: none;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__link {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    gap: 10px;
    width: 100%;
    min-height: 38px;
    height: auto;
    border-radius: 20px;
    border: 0;
    background: transparent;
    padding: .5rem .75rem;
    white-space: normal;
    line-height: 1.35;
    cursor: pointer;
    color: #425466;
    font-size: .97rem;
    font-weight: 500;
    transition: background-color .14s ease, color .14s ease, transform .14s ease;
    text-align: left;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__label {
    flex: 1 1 auto;
    min-width: 0;
    overflow-wrap: break-word;
    word-break: break-word;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__item.has-children > .catalog-category-list__link {
    color: #202124;
    font-weight: 600;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__link:hover,
.catalog-filters .catalog-subcategory-list .catalog-category-list__link:focus-visible {
    background: #eef4ff;
    color: #1f3f75;
    border-color: transparent;
    transform: translateX(1px);
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__link.is-active {
    background: #eaf1ff;
    border-color: transparent;
    color: #2f5eb5;
    font-weight: 600;
    box-shadow: inset 2px 0 0 #1a73e8;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__link.is-active:hover,
.catalog-filters .catalog-subcategory-list .catalog-category-list__link.is-active:focus-visible {
    background: #d2e3fc;
    border-color: transparent;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__item--depth-1 > .catalog-category-list__link {
    font-size: 1rem;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__item--depth-2 > .catalog-category-list__link {
    font-size: .95rem;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__item--depth-3 > .catalog-category-list__link,
.catalog-filters .catalog-subcategory-list .catalog-category-list__item--depth-4 > .catalog-category-list__link,
.catalog-filters .catalog-subcategory-list .catalog-category-list__item--depth-5 > .catalog-category-list__link {
    font-size: .9rem;
    color: #51606f;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__chevron {
    position: static;
    right: auto;
    top: auto;
    translate: none;
    margin-left: auto;
    padding-top: .15rem;
    color: #98a2b3;
    font-size: .82rem;
    transform: rotate(90deg);
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__item.has-children > .catalog-category-list__link .catalog-category-list__chevron {
    color: #7b8794;
}

.catalog-filters .catalog-subcategory-list .catalog-category-list__item.has-children > .catalog-category-list__link:hover .catalog-category-list__chevron,
.catalog-filters .catalog-subcategory-list .catalog-category-list__item.has-children > .catalog-category-list__link:focus-visible .catalog-category-list__chevron {
    color: #1a73e8;
    transform: rotate(90deg) translateX(1px);
}

.catalog-filters .catalog-category-list > li:hover > .catalog-category-list__link--parent,
.catalog-filters .catalog-category-list > li:focus-within > .catalog-category-list__link--parent {
    background: transparent;
    border-color: #d2e3fc;
    color: #202124;
}

.catalog-filters .catalog-category-list > li:hover > .catalog-category-list__link--parent .catalog-category-list__chevron,
.catalog-filters .catalog-category-list > li:focus-within > .catalog-category-list__link--parent .catalog-category-list__chevron {
    color: #1a73e8;
}

.catalog-filters.catalog-filters--in-header .catalog-category-list > li:hover > .catalog-category-list__link--parent,
.catalog-filters.catalog-filters--in-header .catalog-category-list > li:focus-within > .catalog-category-list__link--parent {
    background: #ffffff;
    border-color: transparent;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
}

.catalog-filters .catalog-category-list > li > .catalog-subcategory-list::-webkit-scrollbar {
    width: 10px;
}

.catalog-filters .catalog-category-list > li > .catalog-subcategory-list::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.45);
    border-radius: 999px;
    border: 2px solid #ffffff;
}

@media (max-width: 900px) {
    /* У шапці — один ряд із горизонтальним скролом; перенос лише для категорій поза header. */
    .catalog-filters:not(.catalog-filters--in-header) .catalog-filters__scroll {
        overflow: visible;
    }

    .catalog-filters:not(.catalog-filters--in-header) .catalog-category-list {
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px 14px;
    }

    .catalog-filters:not(.catalog-filters--in-header) .catalog-category-list > li {
        flex: 0 0 auto;
    }

    .catalog-filters:not(.catalog-filters--in-header) .catalog-category-list__link {
        width: auto;
    }

    .catalog-filters .catalog-category-list > li > .catalog-subcategory-list {
        width: max-content;
        min-width: min(280px, calc(100vw - 24px));
        max-width: min(calc(100vw - 16px), 640px);
    }

    .catalog-filters .catalog-subcategory-list > li.has-children:hover > .catalog-subcategory-list,
    .catalog-filters .catalog-subcategory-list > li.has-children:focus-within > .catalog-subcategory-list,
    .catalog-filters .catalog-subcategory-list > li.has-children:has(.is-active) > .catalog-subcategory-list {
        margin-left: 12px;
        padding-left: 10px;
    }

    .catalog-filters.catalog-filters--in-header .catalog-filters__scroll {
        justify-content: flex-start;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        width: 100%;
        max-width: 100%;
    }

    .catalog-filters.catalog-filters--in-header .catalog-filters__scroll::-webkit-scrollbar {
        display: none;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list {
        flex-wrap: nowrap;
        justify-content: flex-start;
        width: max-content;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list > li {
        flex: 0 0 auto;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list {
        gap: 6px;
    }

    .catalog-filters.catalog-filters--in-header {
        --category-chip-font-size: 0.84rem;
        --category-chip-font-weight: 500;
        --category-chip-line-height: 1.2;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__link,
    .catalog-filters.catalog-filters--in-header .catalog-category-list__link--promo {
        width: auto;
        min-width: auto;
        max-width: none;
        min-height: 38px;
        height: 38px;
        max-height: 38px;
        font-size: var(--category-chip-font-size);
        font-weight: var(--category-chip-font-weight);
        line-height: var(--category-chip-line-height);
        padding: 0 12px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__link.is-active {
        font-weight: 600;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__link:hover,
    .catalog-filters.catalog-filters--in-header .catalog-category-list__link:focus-visible,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:hover > .catalog-category-list__link--parent,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:focus-within > .catalog-category-list__link--parent {
        font-size: var(--category-chip-font-size);
        font-weight: var(--category-chip-font-weight);
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.1);
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:hover > .catalog-category-list__link--parent.is-active,
    .catalog-filters.catalog-filters--in-header .catalog-category-list > li:focus-within > .catalog-category-list__link--parent.is-active {
        font-weight: 600;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__label {
        overflow: visible;
        text-overflow: clip;
        white-space: nowrap;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__row--top {
        --category-chip-height: 38px;
        --category-chip-expand-size: 32px;
        height: var(--category-chip-height);
        min-height: var(--category-chip-height);
        max-height: var(--category-chip-height);
        border-radius: 12px;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__row--top .catalog-category-list__link {
        height: 100%;
        min-height: 0;
        max-height: none;
        padding-block: 0;
        padding-inline: calc(var(--category-chip-expand-size) + 2px) calc(var(--category-chip-expand-size) + 2px);
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__row--top .catalog-category-list__expand {
        width: var(--category-chip-expand-size);
        min-width: var(--category-chip-expand-size);
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__expand .catalog-category-list__chevron {
        font-size: 0.7rem;
    }

    .catalog-filters .catalog-category-list > li:not(.is-panel-open):hover > .catalog-subcategory-list,
    .catalog-filters .catalog-category-list > li:not(.is-panel-open):focus-within > .catalog-subcategory-list {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .catalog-filters .catalog-category-list > li.is-panel-open > .catalog-subcategory-list {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: translateY(0) scale(1);
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list > li.is-panel-open {
        z-index: 120;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list > li.is-panel-open > .catalog-subcategory-list {
        position: fixed;
        left: max(12px, env(safe-area-inset-left, 0px));
        right: max(12px, env(safe-area-inset-right, 0px));
        width: auto;
        min-width: 0;
        max-width: none;
        max-height: min(42vh, 260px);
        z-index: 130;
        transform: none;
        padding: 6px 10px;
        border-radius: 14px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12), 0 4px 12px rgba(15, 23, 42, 0.06);
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list > li.is-panel-open > .catalog-subcategory-list .catalog-category-list__link {
        justify-content: flex-start;
        text-align: left;
        min-height: 36px;
        height: auto;
        padding: 0.4rem 0.6rem;
        font-size: 0.9rem;
        border-radius: 12px;
        width: 100%;
        background: transparent;
        box-shadow: none;
    }
}

@media (max-width: 768px) {
    .catalog-filters.catalog-filters--in-header {
        overflow: hidden;
        border-radius: 12px;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list {
        gap: 6px;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__link,
    .catalog-filters.catalog-filters--in-header .catalog-category-list__link--promo {
        min-height: 36px;
        height: 36px;
        max-height: 36px;
        padding: 0 11px;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__row--top {
        --category-chip-height: 36px;
        --category-chip-expand-size: 30px;
        border-radius: 12px;
    }
}

@media (max-width: 640px) {
    .catalog-filters.catalog-filters--in-header .catalog-category-list {
        gap: 5px;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__link,
    .catalog-filters.catalog-filters--in-header .catalog-category-list__link--promo {
        min-height: 34px;
        height: 34px;
        max-height: 34px;
        padding: 0 10px;
    }

    .catalog-filters.catalog-filters--in-header .catalog-category-list__row--top {
        --category-chip-height: 34px;
        --category-chip-expand-size: 28px;
    }
}

@media (max-width: 640px) {
    .catalog-filters--in-header {
        border-radius: 12px;
    }
    .catalog-filters:not(.catalog-filters--in-header) {
        margin-bottom: 12px;
    }
    .catalog-filters:not(.catalog-filters--in-header) .catalog-category-list {
        gap: 10px 14px;
        row-gap: 6px;
    }
    .catalog-filters:not(.catalog-filters--in-header) .catalog-category-list__link {
        min-height: 42px;
        height: 42px;
        font-size: 0.92rem;
    }
}

@media (max-width: 400px) {
    .catalog-filters .catalog-category-list {
        gap: 8px 10px;
    }
    .catalog-filters:not(.catalog-filters--in-header) .catalog-category-list__link {
        font-size: 0.85rem;
    }
}
</style>
@endpush
@endonce

@php
    $filters = $filters ?? ['q' => '', 'category' => 0, 'on_sale' => false];
    $categoryTree = $categoryTree ?? [];
@endphp
@php
    $inHeader = (bool) ($inHeader ?? false);
@endphp
<div class="catalog-filters @if($inHeader) catalog-filters--in-header @endif">
    @php
        $catQuery = array_filter([
            'q' => ($filters['q'] ?? '') !== '' ? $filters['q'] : null,
            'on_sale' => ! empty($filters['on_sale']) ? 1 : null,
        ]);
        $promoOn = ! empty($filters['on_sale']);
        $promoHref = route('catalog.index', array_filter(array_merge($catQuery, $promoOn ? [] : ['on_sale' => 1])));
        $promoOffHref = route('catalog.index', array_filter([
            'q' => ($filters['q'] ?? '') !== '' ? $filters['q'] : null,
            'category' => ($filters['category'] ?? 0) > 0 ? (int) $filters['category'] : null,
        ]));
    @endphp
    <div class="catalog-filters__scroll">
        <ul class="catalog-category-list" role="list">
            <li class="catalog-category-list__item--promo">
                <a
                    href="{{ $promoOn ? $promoOffHref : $promoHref }}"
                    class="catalog-category-list__link catalog-category-list__link--promo @if ($promoOn) is-active @endif"
                    data-on-sale-link="1"
                ><span class="catalog-category-list__label">{{ __('shop.catalog_promo_sales') }}</span></a>
            </li>
            @foreach ($categoryTree as $cat)
                @include('catalog.partials.category-node', [
                    'node' => $cat,
                    'catQuery' => $catQuery,
                    'filters' => $filters,
                    'depth' => 0,
                ])
            @endforeach
        </ul>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    var MOBILE_PANEL_MQ = window.matchMedia('(max-width: 900px)');

    function isMobileCategoryPanel() {
        return MOBILE_PANEL_MQ.matches;
    }

    function getCategoryPanelAnchor(li) {
        return li.querySelector('.catalog-category-list__row--top') || li.querySelector('.catalog-category-list__link');
    }

    function positionMobileTopPanel(li) {
        if (!li || !li.classList.contains('is-panel-open') || !isMobileCategoryPanel()) {
            return;
        }
        var panel = li.querySelector(':scope > .catalog-subcategory-list');
        var anchor = getCategoryPanelAnchor(li);
        if (!panel || !anchor) {
            return;
        }
        var rect = anchor.getBoundingClientRect();
        var top = Math.round(rect.bottom + 8);
        var maxTop = Math.max(8, window.innerHeight - 96);
        if (top > maxTop) {
            top = maxTop;
        }
        panel.style.top = top + 'px';
    }

    function clearMobileTopPanel(li) {
        if (!li) {
            return;
        }
        var panel = li.querySelector(':scope > .catalog-subcategory-list');
        if (panel) {
            panel.style.removeProperty('top');
        }
    }

    function syncMobileTopPanels() {
        document.querySelectorAll('.catalog-category-list > li.is-panel-open').forEach(positionMobileTopPanel);
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

    function closeTopCategoryPanels(exceptLi) {
        document.querySelectorAll('.catalog-category-list > li.is-panel-open').forEach(function (li) {
            if (exceptLi && li === exceptLi) {
                return;
            }
            li.classList.remove('is-panel-open');
            clearMobileTopPanel(li);
            setExpandButtonState(li.querySelector('[data-category-expand]'), false);
        });
    }

    function expandActiveCategoryPath() {
        document.querySelectorAll('.catalog-category-list__link.is-active').forEach(function (a) {
            var li = a.closest('li');
            if (!li) {
                return;
            }
            var parentLi = li.parentElement ? li.parentElement.closest('li.has-children') : null;
            while (parentLi) {
                var parentList = parentLi.parentElement;
                if (parentList && parentList.classList.contains('catalog-category-list')) {
                    parentLi.classList.add('is-panel-open');
                } else {
                    parentLi.classList.add('is-expanded');
                }
                setExpandButtonState(parentLi.querySelector('[data-category-expand]'), true);
                parentLi = parentLi.parentElement ? parentLi.parentElement.closest('li.has-children') : null;
            }
        });
        syncMobileTopPanels();
    }

    document.addEventListener('click', function (e) {
        var expandBtn = e.target.closest('[data-category-expand]');
        if (expandBtn) {
            e.preventDefault();
            e.stopPropagation();
            var li = expandBtn.closest('li.has-children');
            if (!li) {
                return;
            }
            var isTop = li.parentElement && li.parentElement.classList.contains('catalog-category-list');
            var willOpen = isTop ? !li.classList.contains('is-panel-open') : !li.classList.contains('is-expanded');
            if (isTop) {
                closeTopCategoryPanels(willOpen ? li : null);
                li.classList.toggle('is-panel-open', willOpen);
                if (willOpen) {
                    requestAnimationFrame(function () {
                        positionMobileTopPanel(li);
                    });
                } else {
                    clearMobileTopPanel(li);
                }
            } else {
                li.classList.toggle('is-expanded', willOpen);
            }
            setExpandButtonState(expandBtn, willOpen);
            return;
        }

        if (!e.target.closest('.catalog-category-list')) {
            closeTopCategoryPanels();
        }
    });

    function boot() {
        expandActiveCategoryPath();
        window.addEventListener('resize', syncMobileTopPanels);
        window.addEventListener('scroll', syncMobileTopPanels, { passive: true });
        if (typeof MOBILE_PANEL_MQ.addEventListener === 'function') {
            MOBILE_PANEL_MQ.addEventListener('change', syncMobileTopPanels);
        } else if (typeof MOBILE_PANEL_MQ.addListener === 'function') {
            MOBILE_PANEL_MQ.addListener(syncMobileTopPanels);
        }
    }

    window.zoogleExpandActiveCategoryPath = expandActiveCategoryPath;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endpush
@endonce
