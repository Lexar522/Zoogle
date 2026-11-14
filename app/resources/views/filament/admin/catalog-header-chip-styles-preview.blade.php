@php
    use App\Support\ShopHeaderChipStyles;

    $chipStyles = is_array($chipStyles ?? null) ? $chipStyles : [];
    $merged = ShopHeaderChipStyles::mergeForForm($chipStyles);
    $previewCss = ShopHeaderChipStyles::toInlineCss([
        'custom_palette_enabled' => ! empty($merged['custom_palette_enabled']),
        'center' => $merged['center'] ?? [],
        'side_parent' => $merged['side_parent'] ?? [],
        'side_sub' => $merged['side_sub'] ?? [],
        'side_nested' => $merged['side_nested'] ?? [],
        'palette' => $merged['palette'] ?? [],
    ]);
    $useSubPalette = ! empty($merged['side_sub']['use_palette']);
    $useNestedPalette = ! empty($merged['side_nested']['use_palette']);
    $previewKey = md5($previewCss.'|'.($useSubPalette ? '1' : '0').'|'.($useNestedPalette ? '1' : '0').'|'.json_encode($merged));
@endphp
<div
    wire:key="chip-styles-preview-{{ $previewKey }}"
    class="chip-styles-preview catalog-filters catalog-filters--in-header @if($useSubPalette) catalog-filters--chip-sub-palette @endif @if($useNestedPalette) catalog-filters--chip-nested-palette @endif"
    style="{{ $previewCss }}"
>
    <style>
        .chip-styles-preview {
            --hub-chip-color-1: #3b82f2;
            --hub-chip-bg-1: rgba(59, 130, 242, 0.12);
            --hub-chip-color-2: #ed3626;
            --hub-chip-bg-2: rgba(237, 54, 38, 0.12);
            --hub-chip-color-3: #fcb501;
            --hub-chip-bg-3: rgba(252, 181, 1, 0.18);
            --hub-chip-color-4: #397dea;
            --hub-chip-bg-4: rgba(57, 125, 234, 0.12);
            --hub-chip-color-5: #33983b;
            --hub-chip-bg-5: rgba(51, 152, 59, 0.12);
            --hub-chip-color-6: #ee3c2b;
            --hub-chip-bg-6: rgba(238, 60, 43, 0.12);
            --hub-chip-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            --hub-chip-shadow-sort: 0 12px 24px rgba(15, 23, 42, 0.08), 0 4px 10px rgba(15, 23, 42, 0.04);
            --hub-chip-shadow-sort-hover: 0 18px 36px rgba(15, 23, 42, 0.16), 0 8px 16px rgba(15, 23, 42, 0.1);
            --category-chip-radius: 16px;
            --category-chip-padding-inline: 10px;
            --category-chip-font-size: 0.9rem;
            --category-chip-font-weight: 500;
            --category-chip-gap: 4px;
            --hub-main-chip-font-size: 1.15rem;
            --hub-main-chip-font-weight: 800;
            --catalog-hover-text: #1a73e8;
            --catalog-hover-border: #bfdbfe;
            --catalog-active-bg: #1a73e8;
            --catalog-active-text: #ffffff;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 12px;
        }
        .chip-styles-preview [data-hub-chip-color="1"] {
            --hub-chip-accent: var(--hub-chip-color-1);
            --hub-chip-tint: var(--hub-chip-bg-1);
            --hub-chip-text: var(--hub-chip-text-1, var(--hub-chip-color-1));
            --hub-chip-hover-bg: var(--hub-chip-hover-bg-1, var(--hub-chip-color-1));
            --hub-chip-hover-text: var(--hub-chip-hover-text-1, #ffffff);
            --hub-chip-active-bg: var(--hub-chip-active-bg-1, var(--hub-chip-color-1));
            --hub-chip-active-text: var(--hub-chip-active-text-1, #ffffff);
        }
        .chip-styles-preview [data-hub-chip-color="2"] {
            --hub-chip-accent: var(--hub-chip-color-2);
            --hub-chip-tint: var(--hub-chip-bg-2);
            --hub-chip-text: var(--hub-chip-text-2, var(--hub-chip-color-2));
            --hub-chip-hover-bg: var(--hub-chip-hover-bg-2, var(--hub-chip-color-2));
            --hub-chip-hover-text: var(--hub-chip-hover-text-2, #ffffff);
            --hub-chip-active-bg: var(--hub-chip-active-bg-2, var(--hub-chip-color-2));
            --hub-chip-active-text: var(--hub-chip-active-text-2, #ffffff);
        }
        .chip-styles-preview [data-hub-chip-color="3"] {
            --hub-chip-accent: var(--hub-chip-color-3);
            --hub-chip-tint: var(--hub-chip-bg-3);
            --hub-chip-text: var(--hub-chip-text-3, var(--hub-chip-color-3));
            --hub-chip-hover-bg: var(--hub-chip-hover-bg-3, var(--hub-chip-color-3));
            --hub-chip-hover-text: var(--hub-chip-hover-text-3, #ffffff);
            --hub-chip-active-bg: var(--hub-chip-active-bg-3, var(--hub-chip-color-3));
            --hub-chip-active-text: var(--hub-chip-active-text-3, #ffffff);
        }
        .chip-styles-preview__grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 10px;
            align-items: start;
        }
        .chip-styles-preview__panel {
            background: #fff;
            border: 1px solid #e8edf3;
            border-radius: 16px;
            padding: 8px;
            box-shadow: var(--hub-chip-shadow);
        }
        .chip-styles-preview__parent {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border-radius: var(--chip-side-parent-radius, var(--category-chip-radius));
            background: var(--hub-chip-tint);
            color: var(--hub-chip-text, var(--hub-chip-accent));
            font-size: var(--chip-side-parent-font-size, var(--hub-main-chip-font-size));
            font-weight: var(--chip-side-parent-font-weight, var(--hub-main-chip-font-weight));
            font-style: var(--chip-side-parent-font-style, normal);
            padding-inline: var(--chip-side-parent-padding-x, var(--category-chip-padding-inline));
            padding-block: var(--chip-side-parent-padding-y, 8px);
            box-shadow: var(--hub-chip-shadow-sort);
            cursor: default;
            transition: background-color .15s ease, color .15s ease, transform .25s ease, box-shadow .25s ease;
        }
        .chip-styles-preview__parent:hover {
            background: var(--hub-chip-hover-bg, var(--hub-chip-accent));
            color: var(--hub-chip-hover-text, #fff);
            transform: translateY(-3px);
            box-shadow: var(--hub-chip-shadow-sort-hover);
        }
        .chip-styles-preview__subs {
            display: flex;
            flex-direction: column;
            gap: var(--chip-side-sub-gap, var(--category-chip-gap));
            margin-top: 8px;
        }
        .chip-styles-preview__sub {
            display: flex;
            align-items: center;
            min-height: 34px;
            border: 1px solid var(--chip-side-sub-border, #e3e8f0);
            border-radius: var(--chip-side-sub-radius, var(--category-chip-radius));
            background: var(--chip-side-sub-bg, #fff);
            color: var(--chip-side-sub-text, #334155);
            font-size: var(--chip-side-sub-font-size, var(--category-chip-font-size));
            font-weight: var(--chip-side-sub-font-weight, var(--category-chip-font-weight));
            font-style: var(--chip-side-sub-font-style, normal);
            padding-inline: var(--chip-side-sub-padding-x, var(--category-chip-padding-inline));
            padding-block: var(--chip-side-sub-padding-y, 6px);
            transition: background-color .15s ease, color .15s ease, border-color .15s ease;
        }
        .chip-styles-preview.catalog-filters--chip-sub-palette .chip-styles-preview__sub {
            background: var(--hub-chip-tint);
            border-color: transparent;
            color: var(--hub-chip-text, var(--hub-chip-accent));
        }
        .chip-styles-preview__sub:hover {
            background: var(--chip-side-sub-hover-bg, #eff6ff);
            border-color: #1a73e8;
            color: var(--chip-side-sub-hover-text, var(--catalog-hover-text));
        }
        .chip-styles-preview.catalog-filters--chip-sub-palette .chip-styles-preview__sub:hover {
            background: var(--hub-chip-hover-bg, var(--hub-chip-accent));
            color: var(--hub-chip-hover-text, #fff);
            border-color: #1a73e8;
        }
        .chip-styles-preview__sub.is-active {
            background: var(--chip-side-sub-active-bg, var(--catalog-active-bg));
            border-color: transparent;
            color: var(--chip-side-sub-active-text, #fff);
        }
        .chip-styles-preview.catalog-filters--chip-sub-palette .chip-styles-preview__sub.is-active {
            background: var(--hub-chip-active-bg, var(--hub-chip-accent));
            color: var(--hub-chip-active-text, #fff);
        }
        .chip-styles-preview__nested {
            display: flex;
            flex-direction: column;
            gap: var(--chip-side-nested-gap, 6px);
            margin: 6px 0 0 10px;
            padding-left: 10px;
            border-left: 2px solid #aecbfa;
        }
        .chip-styles-preview__nested-item {
            display: flex;
            align-items: center;
            min-height: 30px;
            border: 1px solid var(--chip-side-nested-border, var(--chip-side-sub-border, #e3e8f0));
            border-radius: var(--chip-side-nested-radius, var(--chip-side-sub-radius, var(--category-chip-radius)));
            background: var(--chip-side-nested-bg, var(--chip-side-sub-bg, #fff));
            color: var(--chip-side-nested-text, var(--chip-side-sub-text, #334155));
            font-size: var(--chip-side-nested-font-size, var(--chip-side-sub-font-size, var(--category-chip-font-size)));
            font-weight: var(--chip-side-nested-font-weight, var(--chip-side-sub-font-weight, var(--category-chip-font-weight)));
            font-style: var(--chip-side-nested-font-style, var(--chip-side-sub-font-style, normal));
            padding-inline: var(--chip-side-nested-padding-x, var(--chip-side-sub-padding-x, var(--category-chip-padding-inline)));
            padding-block: var(--chip-side-nested-padding-y, var(--chip-side-sub-padding-y, 5px));
            transition: background-color .15s ease, color .15s ease, border-color .15s ease;
        }
        .chip-styles-preview.catalog-filters--chip-nested-palette .chip-styles-preview__nested-item {
            background: var(--hub-chip-tint);
            border-color: transparent;
            color: var(--hub-chip-text, var(--hub-chip-accent));
        }
        .chip-styles-preview__nested-item:hover {
            background: var(--chip-side-nested-hover-bg, var(--chip-side-sub-hover-bg, #eff6ff));
            color: var(--chip-side-nested-hover-text, var(--chip-side-sub-hover-text, var(--catalog-hover-text)));
            border-color: #1a73e8;
        }
        .chip-styles-preview.catalog-filters--chip-nested-palette .chip-styles-preview__nested-item:hover {
            background: var(--hub-chip-hover-bg, var(--hub-chip-accent));
            color: var(--hub-chip-hover-text, #fff);
            border-color: #1a73e8;
        }
        .chip-styles-preview__nested-item.is-active {
            background: var(--chip-side-nested-active-bg, var(--chip-side-sub-active-bg, var(--catalog-active-bg)));
            color: var(--chip-side-nested-active-text, var(--chip-side-sub-active-text, #fff));
            border-color: transparent;
        }
        .chip-styles-preview.catalog-filters--chip-nested-palette .chip-styles-preview__nested-item.is-active {
            background: var(--hub-chip-active-bg, var(--hub-chip-accent));
            color: var(--hub-chip-active-text, #fff);
        }
        .chip-styles-preview__center {
            display: flex;
            flex-wrap: wrap;
            gap: var(--chip-center-gap, var(--category-chip-gap));
            align-content: flex-start;
        }
        .chip-styles-preview__chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border-radius: var(--chip-center-radius, var(--category-chip-radius));
            background: var(--hub-chip-tint);
            color: var(--hub-chip-text, var(--hub-chip-accent));
            font-size: var(--chip-center-font-size, var(--hub-main-chip-font-size));
            font-weight: var(--chip-center-font-weight, var(--hub-main-chip-font-weight));
            font-style: var(--chip-center-font-style, normal);
            padding-inline: var(--chip-center-padding-x, var(--category-chip-padding-inline));
            padding-block: var(--chip-center-padding-y, 8px);
            box-shadow: var(--hub-chip-shadow-sort);
            transition: background-color .15s ease, color .15s ease, transform .25s ease, box-shadow .25s ease;
        }
        .chip-styles-preview__chip:hover {
            background: var(--hub-chip-hover-bg, var(--hub-chip-accent));
            color: var(--hub-chip-hover-text, #fff);
            transform: translateY(-3px);
            box-shadow: var(--hub-chip-shadow-sort-hover);
        }
        .chip-styles-preview__chip.is-active {
            background: var(--hub-chip-active-bg, var(--hub-chip-accent));
            color: var(--hub-chip-active-text, #fff);
        }
        .chip-styles-preview__hint {
            margin: 10px 0 0;
            font-size: 12px;
            color: #64748b;
            line-height: 1.35;
        }
        .chip-styles-preview__live-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #22c55e;
            margin-right: 6px;
            vertical-align: middle;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
        }
    </style>

    <div class="chip-styles-preview__grid">
        <div class="chip-styles-preview__panel" data-hub-chip-color="1">
            <div class="chip-styles-preview__parent">Товар</div>
            <div class="chip-styles-preview__subs">
                <div class="chip-styles-preview__sub">Підкатегорія</div>
                <div class="chip-styles-preview__nested">
                    <div class="chip-styles-preview__nested-item">Вкладений рівень</div>
                    <div class="chip-styles-preview__nested-item is-active">Обраний вкладений</div>
                </div>
                <div class="chip-styles-preview__sub is-active">Обрана</div>
            </div>
        </div>
        <div class="chip-styles-preview__center">
            <span class="chip-styles-preview__chip" data-hub-chip-color="1">Каталог</span>
            <span class="chip-styles-preview__chip" data-hub-chip-color="2">Аксесуар</span>
            <span class="chip-styles-preview__chip is-active" data-hub-chip-color="3">Корм</span>
            <span class="chip-styles-preview__chip" data-hub-chip-color="1">Акції</span>
        </div>
    </div>
    <p class="chip-styles-preview__hint">
        <span class="chip-styles-preview__live-dot" aria-hidden="true"></span>
        Превʼю оновлюється одразу під час зміни параметрів. Наведіть на кнопки для hover.
    </p>
</div>
