@php
    $optionBlocks = is_array($optionBlocks ?? null) ? $optionBlocks : [];
    $panelId = $panelId ?? 'storefront-options';
@endphp

@if (count($optionBlocks))
    @once
    @push('styles')
    <style>
        .product-options-panel { margin-top: 8px; padding-top: 20px; border-top: 1px solid #e8eaed; }
        .product-options-heading { font-size: 15px; font-weight: 600; color: #202124; margin: 0 0 16px; letter-spacing: 0.01em; }
        .opt-group { margin-bottom: 22px; }
        .opt-group:last-child { margin-bottom: 0; }
        .opt-label { font-size: 13px; font-weight: 600; color: #5f6368; margin-bottom: 10px; display: block; }
        .opt-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .opt-chip {
            border: 1px solid #dadce0;
            background: #fff;
            color: #202124;
            padding: 10px 16px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
            transition: transform .26s cubic-bezier(0.22, 1, 0.36, 1), border-color .18s ease, background .18s ease, color .18s ease, box-shadow .26s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .opt-chip:hover,
        .opt-chip:focus-visible {
            border-color: rgba(26, 115, 232, 0.55);
            transform: translateY(-3px) scale(1.035);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14), 0 0 0 4px rgba(26, 115, 232, 0.08);
            outline: none;
        }
        .opt-chip.active {
            background: #1a73e8;
            color: #fff;
            border-color: #1a73e8;
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 14px 30px rgba(26, 115, 232, 0.26), 0 0 0 4px rgba(26, 115, 232, 0.14);
        }
        .opt-colors { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
        .opt-swatch {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.95);
            cursor: pointer;
            padding: 0;
            box-sizing: border-box;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.18), 0 6px 14px rgba(15, 23, 42, 0.12);
            transition: transform .26s cubic-bezier(0.22, 1, 0.36, 1), box-shadow .26s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .opt-swatch:hover,
        .opt-swatch:focus-visible {
            transform: translateY(-3px) scale(1.08);
            box-shadow: 0 0 0 2px rgba(54, 125, 241, 0.24), 0 13px 26px rgba(15, 23, 42, 0.22);
            outline: none;
        }
        .opt-swatch.active {
            border-color: #fff;
            box-shadow: 0 0 0 3px #1a73e8, 0 0 0 7px rgba(26, 115, 232, 0.16), 0 14px 30px rgba(26, 115, 232, 0.24);
            transform: translateY(-2px) scale(1.06);
        }
        .opt-swatch span { display: none; }
        .opt-swatch--has-img { overflow: hidden; background-color: #e8eaed; }
        .opt-swatch--has-img img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            pointer-events: none;
            border-radius: 50%;
        }
    </style>
    @endpush
    @endonce

    <div id="{{ $panelId }}" class="product-options-panel" aria-label="{{ __('shop.pdp_options_aria') }}">
        <h2 class="product-options-heading">{{ __('shop.pdp_options_heading') }}</h2>
        @php
            $claimedExclusivityKeys = [];
        @endphp
        @foreach ($optionBlocks as $block)
            @php
                $isMultipleBlock = ($block['selection_mode'] ?? 'single') === 'multiple';
                $exclusivityKey = trim((string) ($block['exclusivity_key'] ?? ''));
                $claimPreselect = true;
                if ($exclusivityKey !== '') {
                    if (isset($claimedExclusivityKeys[$exclusivityKey])) {
                        $claimPreselect = false;
                    } else {
                        $claimedExclusivityKeys[$exclusivityKey] = true;
                    }
                }
            @endphp
            <div
                class="opt-group"
                data-group-id="{{ $block['id'] }}"
                data-mode="{{ $block['selection_mode'] ?? 'single' }}"
                data-value-type="{{ $block['value_type'] ?? 'text' }}"
                @if ($exclusivityKey !== '') data-exclusivity-key="{{ $exclusivityKey }}" @endif
            >
                <span class="opt-label">{{ $block['name'] }}</span>
                @if (($block['value_type'] ?? 'text') === 'color')
                    <div class="opt-colors" role="group">
                        @foreach ($block['values'] as $val)
                            @php
                                $isOptActive = ! $isMultipleBlock && $claimPreselect && $loop->first;
                                $swatchPath = isset($val['swatch_image']) && is_string($val['swatch_image']) ? trim($val['swatch_image']) : '';
                                $swatchHasImg = $swatchPath !== '' && ! filled($val['color_hex'] ?? null);
                            @endphp
                            <button
                                type="button"
                                class="opt-swatch {{ $isOptActive ? 'active' : '' }} {{ $swatchHasImg ? 'opt-swatch--has-img' : '' }}"
                                data-value-id="{{ $val['id'] }}"
                                @if ($swatchHasImg)
                                    style="background-color: {{ $val['color_hex'] ?? '#e8eaed' }};"
                                @else
                                    style="background-color: {{ $val['color_hex'] ?? '#666' }};"
                                @endif
                                title="{{ $val['name'] }}"
                                aria-label="{{ $val['name'] }}"
                                aria-pressed="{{ $isOptActive ? 'true' : 'false' }}"
                            >
                                <span>{{ $val['name'] }}</span>
                                @if ($swatchHasImg)
                                    <img src="{{ asset('storage/' . ltrim($swatchPath, '/')) }}" alt="" aria-hidden="true">
                                @endif
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="opt-chips" role="group">
                        @foreach ($block['values'] as $val)
                            @php
                                $isOptActive = ! $isMultipleBlock && $claimPreselect && $loop->first;
                            @endphp
                            <button
                                type="button"
                                class="opt-chip {{ $isOptActive ? 'active' : '' }}"
                                data-value-id="{{ $val['id'] }}"
                                aria-pressed="{{ $isOptActive ? 'true' : 'false' }}"
                            >
                                {{ $val['name'] }}
                                @if (($val['price'] ?? null) !== null && (float) $val['price'] > 0)
                                    <span style="opacity:.75"> +{{ number_format((float) $val['price'], 0, '', ' ') }} ₴</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
