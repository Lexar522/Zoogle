{{--
    Галерея товару: ряд мініатюр + зона додавання.
    FilePond у compact/hopper виставляє великий inline height і translateY — без скидання контент «падає» вниз у порожньому полі.
--}}
<style>
    .fi-listing-photos-layout.fi-fo-file-upload {
        --lp-thumb: 5.75rem;
        /* Ряд мініатюр + прев’ю з кнопками; зона дропа — під текст + кнопку «Обрати» */
        /* + зовнішні margin-block у .filepond--item */
        --lp-row: calc(7.75rem + var(--lp-item-outer-y) * 2);
        --lp-drop: 5.75rem;
        --lp-list-gap: 1.25rem;
        /* Зовнішній відступ картки мініатюри (від країв скролера по вертикалі) */
        --lp-item-outer-y: 0.25rem;
        --lp-surface: color-mix(in srgb, var(--gray-500, #6b7280) 6%, transparent);
        --lp-border: color-mix(in srgb, var(--gray-500, #6b7280) 20%, transparent);
        --lp-border-strong: color-mix(in srgb, var(--gray-500, #6b7280) 32%, transparent);
        min-width: 0;
        max-width: 100%;
        width: 100%;
        box-sizing: border-box;
        align-self: stretch;
    }

    .dark .fi-listing-photos-layout.fi-fo-file-upload {
        --lp-surface: color-mix(in srgb, var(--gray-400, #9ca3af) 8%, transparent);
        --lp-border: color-mix(in srgb, var(--gray-400, #9ca3af) 22%, transparent);
        --lp-border-strong: color-mix(in srgb, var(--gray-400, #9ca3af) 34%, transparent);
    }

    .fi-fo-repeater .fi-listing-photos-layout.fi-fo-file-upload {
        --lp-thumb: 4.875rem;
        --lp-row: calc(7rem + var(--lp-item-outer-y) * 2);
        --lp-drop: 5.25rem;
        --lp-list-gap: 1.125rem;
        --lp-item-outer-y: 0.1875rem;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .fi-fo-file-upload-input-ctn {
        min-width: 0;
        max-width: 100%;
        width: 100%;
        box-sizing: border-box;
        height: auto !important;
        min-height: 0;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root.filepond--hopper,
    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        justify-content: flex-start;
        align-content: flex-start;
        gap: 0.875rem;
        min-height: 0;
        max-width: 100%;
        min-width: 0;
        width: 100%;
        /* Поверх inline height від FilePond — інакше залишається «порожня вежа», а мініатюри внизу */
        height: fit-content !important;
        max-height: none !important;
        margin-bottom: 0 !important;
        padding: 0.875rem !important;
        box-sizing: border-box;
        border: 1px solid var(--lp-border) !important;
        border-radius: 0.75rem !important;
        background: var(--lp-surface) !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-shadow: 0 0 #0000 !important;
        overflow-x: hidden;
        overflow-y: hidden;
        position: relative;
    }

    /* Мінімальна висота = padding + ряд + gap + зона дропа, щоб filepond--browser (inset:0) покривав увесь інтерактив */
    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root:has(.filepond--item) {
        min-height: calc((0.875rem * 2) + var(--lp-row) + 0.875rem + var(--lp-drop));
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root:not(:has(.filepond--item)) {
        gap: 0;
        min-height: calc((0.875rem * 2) + 9.5rem);
    }

    /* Фонова панель FilePond на весь старий bounding-box — ховаємо, layout тримаємо на flex */
    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root > .filepond--panel-root {
        opacity: 0 !important;
        pointer-events: none !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--list-scroller {
        position: relative !important;
        inset: auto !important;
        transform: none !important;
        order: 1;
        flex: 0 1 auto !important;
        align-self: stretch;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        min-height: var(--lp-row);
        max-height: var(--lp-row) !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0.5rem 0.75rem !important;
        box-sizing: border-box;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        scrollbar-width: thin;
        z-index: 10;
        border-radius: 0.5rem;
        background: color-mix(in srgb, var(--gray-950, #030712) 4%, transparent);
        border: 1px solid var(--lp-border);
    }

    .dark .fi-listing-photos-layout.fi-fo-file-upload .filepond--list-scroller {
        background: color-mix(in srgb, #fff 5%, transparent);
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root[data-style-panel-layout~='compact'] .filepond--list-scroller {
        height: auto !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--list-scroller[data-state='overflow'] {
        -webkit-mask: none !important;
        mask: none !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--list-scroller::-webkit-scrollbar {
        height: 6px;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--list-scroller::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: color-mix(in srgb, var(--primary-500, #f59e0b) 35%, var(--gray-500, #6b7280) 40%, transparent);
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--list {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center;
        justify-content: flex-start;
        gap: var(--lp-list-gap) !important;
        width: max-content;
        min-width: 100%;
        min-height: 0;
        max-height: 100%;
        box-sizing: border-box;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--list:empty {
        min-height: 0 !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root:has(.filepond--item) .filepond--list {
        min-height: calc(var(--lp-thumb));
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--item {
        width: var(--lp-thumb) !important;
        max-width: var(--lp-thumb) !important;
        flex: 0 0 var(--lp-thumb) !important;
        margin-block: var(--lp-item-outer-y) !important;
        /* Не margin-inline:0 — інакше збивається margin-inline-end між фото */
        margin-inline-start: 0 !important;
        align-self: center;
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow:
            0 0 0 1px color-mix(in srgb, var(--gray-950, #030712) 6%, transparent),
            0 2px 6px color-mix(in srgb, var(--gray-950, #030712) 10%, transparent);
    }

    .dark .fi-listing-photos-layout.fi-fo-file-upload .filepond--item {
        box-shadow:
            0 0 0 1px color-mix(in srgb, #fff 10%, transparent),
            0 2px 8px color-mix(in srgb, #000 35%, transparent);
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--item > .filepond--panel-root {
        border-radius: 0.5rem;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--file-action-button {
        width: 1.625rem !important;
        height: 1.625rem !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--action-edit-item.filepond--file-action-button {
        width: 1.75rem !important;
        height: 1.75rem !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--drop-label {
        position: relative !important;
        inset: auto !important;
        transform: none !important;
        order: 2;
        z-index: 11;
        flex-shrink: 0;
        width: 100% !important;
        min-height: var(--lp-drop);
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        border-radius: 0.5rem !important;
        border: 2px dashed var(--lp-border-strong) !important;
        background: color-mix(in srgb, var(--primary-500, #f59e0b) 6%, var(--gray-500, #6b7280) 2%, transparent) !important;
        transition:
            border-color 0.18s ease,
            background-color 0.18s ease,
            box-shadow 0.18s ease;
    }

    .dark .fi-listing-photos-layout.fi-fo-file-upload .filepond--drop-label {
        background: color-mix(in srgb, var(--primary-400, #fbbf24) 8%, var(--gray-400, #9ca3af) 3%, transparent) !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--drop-label:hover {
        border-color: color-mix(in srgb, var(--primary-500, #f59e0b) 65%, var(--gray-500, #6b7280)) !important;
        background: color-mix(in srgb, var(--primary-500, #f59e0b) 12%, transparent) !important;
        box-shadow: 0 0 0 1px color-mix(in srgb, var(--primary-500, #f59e0b) 25%, transparent);
    }

    .dark .fi-listing-photos-layout.fi-fo-file-upload .filepond--drop-label:hover {
        border-color: color-mix(in srgb, var(--primary-400, #fbbf24) 70%, var(--gray-400, #9ca3af)) !important;
        background: color-mix(in srgb, var(--primary-400, #fbbf24) 14%, transparent) !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--drop-label:focus-within {
        outline: none;
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary-500, #f59e0b) 45%, transparent);
    }

    /* Порожня галерея: сховати ряд мініатюр без max-width:0 */
    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root:not(:has(.filepond--item)) .filepond--list-scroller {
        flex: none !important;
        min-height: 0 !important;
        max-height: 0 !important;
        min-width: 0 !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden !important;
        opacity: 0;
        pointer-events: none;
        border: none !important;
        background: transparent !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root:not(:has(.filepond--item)) .filepond--drop-label {
        flex: 1 1 auto !important;
        min-height: 9.5rem !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root:has(.filepond--item) .filepond--drop-label {
        flex: 0 0 auto !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--drop-label label {
        margin: 0 !important;
        transform: none !important;
        padding: 0.875rem 1rem !important;
        min-height: var(--lp-drop);
        font-size: 0.8125rem;
        line-height: 1.45;
        font-weight: 500;
        text-align: center;
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        box-sizing: border-box !important;
        white-space: normal !important;
        word-break: break-word !important;
        cursor: pointer;
        color: inherit;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root:not(:has(.filepond--item)) .filepond--drop-label label {
        min-height: 9.5rem;
        padding: 1.35rem 1rem !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--label-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-top: 0.125rem;
        padding: 0.2rem 0.65rem;
        border-radius: 0.375rem;
        font-weight: 600;
        text-decoration: none !important;
        color: var(--gray-950, #030712) !important;
        background: color-mix(in srgb, var(--primary-500, #f59e0b) 88%, transparent);
        box-shadow: 0 1px 2px color-mix(in srgb, var(--gray-950, #030712) 12%, transparent);
        transition: filter 0.15s ease, transform 0.12s ease;
    }

    .dark .fi-listing-photos-layout.fi-fo-file-upload .filepond--label-action {
        color: var(--gray-950, #030712) !important;
        background: color-mix(in srgb, var(--primary-400, #fbbf24) 92%, transparent);
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--drop-label:hover .filepond--label-action {
        filter: brightness(1.05);
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--label-action:hover {
        transform: translateY(-1px);
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--panel-root {
        z-index: 0;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--file-info {
        font-size: 0.5625rem !important;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--file-info-main {
        max-width: calc(var(--lp-thumb) - 1.25rem);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .fi-listing-photos-layout.fi-fo-file-upload .filepond--root [class*='filepond--browser'] {
        position: absolute !important;
        inset: 0 !important;
        width: 100% !important;
        height: 100% !important;
        min-height: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 0 !important;
        z-index: 9 !important;
        box-sizing: border-box !important;
        transform: none !important;
        cursor: pointer;
    }
</style>
