@once('catalog-subcategory-filter-styles')
<style id="zoogle-catalog-subcategory-filter-styles">
.catalog-subcategory-filter {
    scroll-margin-top: var(--header-sticky-offset, 0px);
    --subfilter-chip-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    --subfilter-chip-shadow-hover:
        0 12px 24px rgba(15, 23, 42, 0.08),
        0 4px 10px rgba(15, 23, 42, 0.04);
    --subfilter-chip-accent: #3c4043;
    --subfilter-chip-tint: rgba(60, 64, 67, 0.08);
    --subfilter-chip-color-1: #3b82f2;
    --subfilter-chip-tint-1: rgba(59, 130, 242, 0.12);
    --subfilter-chip-color-2: #ed3626;
    --subfilter-chip-tint-2: rgba(237, 54, 38, 0.12);
    --subfilter-chip-color-3: #fcb501;
    --subfilter-chip-tint-3: rgba(252, 181, 1, 0.18);
    --subfilter-chip-color-4: #397dea;
    --subfilter-chip-tint-4: rgba(57, 125, 234, 0.12);
    --subfilter-chip-color-5: #33983b;
    --subfilter-chip-tint-5: rgba(51, 152, 59, 0.12);
    --subfilter-chip-color-6: #ee3c2b;
    --subfilter-chip-tint-6: rgba(238, 60, 43, 0.12);
    --subfilter-active-bg: #1a73e8;
    --subfilter-active-text: #ffffff;
    margin: 0 0 clamp(12px, 1.8vw, 18px);
    padding: clamp(14px, 2vw, 18px) clamp(16px, 2.2vw, 22px);
    border-radius: var(--shop-radius-catalog, 20px);
    background: var(--surface, #ffffff);
    border: 1px solid #e8eaed;
    box-shadow:
        0 12px 30px rgba(15, 23, 42, 0.1),
        0 2px 10px rgba(15, 23, 42, 0.05);
    box-sizing: border-box;
    overflow-anchor: none;
}

.catalog-subcategory-filter__head {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f7;
}

.catalog-subcategory-filter__context {
    margin: 0;
    color: #5f6368;
    font-size: 0.875rem;
    line-height: 1.4;
}

.catalog-subcategory-filter__applied {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 0;
}

.catalog-subcategory-filter__applied-label {
    color: #5f6368;
    font-size: 0.8125rem;
    font-weight: 500;
    line-height: 1.3;
}

.catalog-subcategory-filter__applied-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.catalog-subcategory-filter__applied-item {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e8f0fe;
    color: #1557b0;
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.3;
}

.catalog-subcategory-filter__body {
    display: flex;
    flex-direction: column;
    gap: 0;
    min-width: 0;
}

.catalog-subcategory-filter__groups {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
}

.catalog-subcategory-filter__group {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
    max-width: 100%;
    width: 100%;
    padding-left: calc(var(--subfilter-depth, 0) * 14px);
    box-sizing: border-box;
}

.catalog-subcategory-filter__group-title {
    margin: 0;
    color: var(--text, #367df1);
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.3;
}

.catalog-subcategory-filter__items {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: flex-start;
    align-content: flex-start;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.catalog-subcategory-filter__item {
    margin: 0;
    min-width: 0;
    max-width: 100%;
    width: auto;
}

/* Палітра як у catalog-toolbar__chips--sort: циклічно 1–6 для необраних чіпів */
.catalog-subcategory-filter__items > .catalog-subcategory-filter__item:nth-of-type(6n + 1) .catalog-subcategory-filter__label {
    --subfilter-chip-accent: var(--subfilter-chip-color-1);
    --subfilter-chip-tint: var(--subfilter-chip-tint-1);
}

.catalog-subcategory-filter__items > .catalog-subcategory-filter__item:nth-of-type(6n + 2) .catalog-subcategory-filter__label {
    --subfilter-chip-accent: var(--subfilter-chip-color-2);
    --subfilter-chip-tint: var(--subfilter-chip-tint-2);
}

.catalog-subcategory-filter__items > .catalog-subcategory-filter__item:nth-of-type(6n + 3) .catalog-subcategory-filter__label {
    --subfilter-chip-accent: var(--subfilter-chip-color-3);
    --subfilter-chip-tint: var(--subfilter-chip-tint-3);
}

.catalog-subcategory-filter__items > .catalog-subcategory-filter__item:nth-of-type(6n + 4) .catalog-subcategory-filter__label {
    --subfilter-chip-accent: var(--subfilter-chip-color-4);
    --subfilter-chip-tint: var(--subfilter-chip-tint-4);
}

.catalog-subcategory-filter__items > .catalog-subcategory-filter__item:nth-of-type(6n + 5) .catalog-subcategory-filter__label {
    --subfilter-chip-accent: var(--subfilter-chip-color-5);
    --subfilter-chip-tint: var(--subfilter-chip-tint-5);
}

.catalog-subcategory-filter__items > .catalog-subcategory-filter__item:nth-of-type(6n + 6) .catalog-subcategory-filter__label {
    --subfilter-chip-accent: var(--subfilter-chip-color-6);
    --subfilter-chip-tint: var(--subfilter-chip-tint-6);
}

/* Кнопка-чіп: без видимої галочки */
.catalog-subcategory-filter__label {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: auto;
    max-width: 100%;
    box-sizing: border-box;
    min-height: 40px;
    padding: 8px 16px;
    border-radius: var(--shop-radius-catalog, 20px);
    border: 2px solid var(--subfilter-chip-accent);
    background: #ffffff;
    box-shadow: var(--subfilter-chip-shadow);
    color: var(--subfilter-chip-accent);
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1.2;
    cursor: pointer;
    user-select: none;
    transition:
        color 0.22s var(--ease-hover, ease),
        border-color 0.22s var(--ease-hover, ease),
        box-shadow 0.22s cubic-bezier(0.4, 0, 0.2, 1),
        background-color 0.22s var(--ease-hover, ease),
        transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

.catalog-subcategory-filter__checkbox {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.catalog-subcategory-filter__text {
    display: block;
    text-align: center;
}

.catalog-subcategory-filter__label:hover {
    color: var(--subfilter-chip-accent);
    border-color: var(--subfilter-chip-accent);
    background: #ffffff;
    box-shadow: var(--subfilter-chip-shadow-hover);
    transform: translateY(-2px);
    outline: none;
}

.catalog-subcategory-filter__label:focus-within {
    color: var(--subfilter-chip-accent);
    border-color: var(--subfilter-chip-accent);
    background: #ffffff;
    box-shadow: var(--subfilter-chip-shadow);
    outline: none;
}

.catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:focus-visible) {
    outline: 2px solid var(--subfilter-chip-accent);
    outline-offset: 2px;
}

.catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:checked) {
    border-color: var(--subfilter-active-bg);
    background: var(--subfilter-active-bg);
    box-shadow: 0 4px 14px rgba(26, 115, 232, 0.28);
    color: var(--subfilter-active-text);
    outline: none;
}

.catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:checked) .catalog-subcategory-filter__text {
    color: var(--subfilter-active-text);
    font-weight: 600;
}

.catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:checked):hover,
.catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:checked):focus-within {
    border-color: var(--subfilter-active-bg);
    background: var(--subfilter-active-bg);
    box-shadow: 0 4px 14px rgba(26, 115, 232, 0.32);
    color: var(--subfilter-active-text);
    transform: translateY(-2px);
}

.catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:checked):focus-within {
    outline: none;
}

.catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:checked:focus-visible) {
    outline: 2px solid var(--subfilter-active-bg);
    outline-offset: 2px;
}

.catalog-subcategory-filter__actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #f1f3f4;
}

.catalog-subcategory-filter__submit.btn {
    min-height: 40px;
    padding: 10px 20px;
    border-radius: var(--shop-radius-catalog, 20px);
    font-size: 0.875rem;
    box-shadow:
        0 12px 24px rgba(15, 23, 42, 0.08),
        0 4px 10px rgba(15, 23, 42, 0.04);
}

.catalog-subcategory-filter__submit.btn:hover,
.catalog-subcategory-filter__submit.btn:focus-visible {
    transform: translateY(-2px);
    box-shadow: var(--shop-product-btn-shadow-hover-cta, 0 0 0 4px rgba(239, 56, 41, 0.22), 0 16px 24px rgba(15, 23, 42, 0.16));
}

@media (min-width: 992px) {
    .catalog-subcategory-filter {
        --subfilter-chip-shadow:
            0 2px 4px rgba(15, 23, 42, 0.08),
            0 1px 2px rgba(15, 23, 42, 0.04);
        --subfilter-chip-shadow-hover:
            0 16px 28px rgba(15, 23, 42, 0.12),
            0 6px 14px rgba(15, 23, 42, 0.06);
        margin-bottom: clamp(18px, 2.4vw, 26px);
        padding: clamp(18px, 2.2vw, 24px) clamp(20px, 2.4vw, 28px);
        background: linear-gradient(180deg, #f8fbff 0%, #ffffff 42%);
        border: 1px solid #d2e3fc;
        box-shadow:
            0 18px 36px rgba(26, 115, 232, 0.1),
            0 6px 16px rgba(15, 23, 42, 0.06),
            inset 0 1px 0 rgba(255, 255, 255, 0.92);
    }

    .catalog-subcategory-filter__head {
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid #dbeafe;
    }

    .catalog-subcategory-filter__context {
        color: #1a73e8;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .catalog-subcategory-filter__items {
        gap: 8px;
    }

    .catalog-subcategory-filter__label {
        min-height: 44px;
        padding: 10px 18px;
        font-size: 0.9375rem;
        background: var(--subfilter-chip-tint);
        box-shadow: var(--subfilter-chip-shadow);
    }

    .catalog-subcategory-filter__label:hover {
        background: #ffffff;
    }

    .catalog-subcategory-filter__actions {
        margin-top: 16px;
        padding-top: 14px;
    }

    .catalog-subcategory-filter__submit.btn {
        min-height: 44px;
        padding: 11px 24px;
        font-size: 0.9375rem;
    }
}

@media (max-width: 991px) {
    .catalog-subcategory-filter {
        padding: 12px 14px max(16px, env(safe-area-inset-bottom, 0px));
        margin-bottom: 12px;
    }

    .catalog-subcategory-filter__head {
        margin-bottom: 10px;
        padding-bottom: 8px;
    }

    .catalog-subcategory-filter__groups {
        display: flex;
        flex-direction: column;
        flex-wrap: nowrap;
        gap: 0;
    }

    .catalog-subcategory-filter__group {
        width: 100%;
        max-width: 100%;
        gap: 8px;
    }

    .catalog-subcategory-filter__group + .catalog-subcategory-filter__group {
        padding-left: 0;
        border-left: 0;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #eef2f7;
    }

    .catalog-subcategory-filter__group-title {
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.25;
        margin-bottom: 2px;
    }

    .catalog-subcategory-filter__items {
        flex-direction: row;
        flex-wrap: wrap;
        align-items: flex-start;
        align-content: flex-start;
        gap: 6px;
    }

    .catalog-subcategory-filter__item {
        width: auto;
        max-width: 100%;
        flex: 0 0 auto;
    }

    .catalog-subcategory-filter__label {
        min-height: 40px;
        padding: 10px 14px;
        font-size: 0.875rem;
        border-radius: var(--shop-radius-catalog, 12px);
    }

    .catalog-subcategory-filter__text {
        white-space: normal;
        overflow: visible;
        text-overflow: unset;
        line-height: 1.3;
        word-break: break-word;
    }

    .catalog-subcategory-filter__label:hover,
    .catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:checked):hover {
        transform: none;
    }

    .catalog-subcategory-filter__actions {
        justify-content: stretch;
        margin-top: 12px;
        padding-top: 10px;
    }

    .catalog-subcategory-filter__submit.btn {
        width: 100%;
        min-height: 40px;
        padding: 10px 16px;
        font-size: 0.875rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .catalog-subcategory-filter__label,
    .catalog-subcategory-filter__label:hover,
    .catalog-subcategory-filter__label:focus-within,
    .catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:checked):hover,
    .catalog-subcategory-filter__label:has(.catalog-subcategory-filter__checkbox:checked):focus-within,
    .catalog-subcategory-filter__submit.btn:hover,
    .catalog-subcategory-filter__submit.btn:focus-visible {
        transform: none;
        transition: color 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease, background-color 0.16s ease, opacity 0.16s ease;
    }
}
</style>
@endonce
