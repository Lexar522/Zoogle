@php
    $optionBlocks = is_array($optionBlocks ?? null) ? $optionBlocks : [];
    $storefrontDefaultVariantId = $storefrontDefaultVariantId ?? null;
    $panelId = $panelId ?? null;
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
        .opt-colors { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
        .opt-swatch {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.95);
            padding: 0;
            box-sizing: border-box;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.18), 0 6px 14px rgba(15, 23, 42, 0.12);
        }
        .opt-swatch--has-img {
            overflow: hidden;
            background-color: #e8eaed;
        }
        .opt-swatch--has-img img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            pointer-events: none;
            border-radius: 50%;
        }
        .opt-group--readonly .opt-chip--readonly,
        .opt-group--readonly .opt-swatch--readonly {
            cursor: default;
            pointer-events: none;
        }
        .opt-group--readonly .opt-chip--readonly {
            display: inline-flex;
            align-items: center;
            border: 1px solid #e8eaed;
            background: #f8f9fb;
            color: #3c4043;
            font-weight: 500;
            padding: 10px 16px;
            border-radius: 20px;
            font-size: 14px;
        }
        .opt-group--readonly .opt-swatch--readonly {
            opacity: 0.95;
        }
        .opt-group--exclusive-hidden {
            display: none !important;
        }
    </style>
    @endpush
    @endonce

    <div
        class="product-options-readonly product-options-panel"
        @if ($panelId) id="{{ $panelId }}" @endif
        aria-label="{{ __('shop.pdp_options_aria') }}"
    >
        <h2 class="product-options-heading">{{ __('shop.pdp_options_heading') }}</h2>
        @foreach ($optionBlocks as $block)
            @php
                $exclusiveForVariantId = (int) ($block['exclusive_for_variant_id'] ?? 0);
                $exclusiveApplies = $exclusiveForVariantId <= 0
                    || ($storefrontDefaultVariantId !== null && $storefrontDefaultVariantId === $exclusiveForVariantId);
            @endphp
            <div class="opt-group opt-group--readonly {{ $exclusiveForVariantId > 0 && ! $exclusiveApplies ? 'opt-group--exclusive-hidden' : '' }}">
                <span class="opt-label">{{ $block['name'] }}</span>
                @if (($block['value_type'] ?? 'text') === 'color')
                    <div class="opt-colors" role="list">
                        @foreach ($block['values'] as $val)
                            @php
                                $swatchPath = isset($val['swatch_image']) && is_string($val['swatch_image']) ? trim($val['swatch_image']) : '';
                                $swatchHasImg = $swatchPath !== '' && ! filled($val['color_hex'] ?? null);
                            @endphp
                            <span
                                class="opt-swatch opt-swatch--readonly {{ $swatchHasImg ? 'opt-swatch--has-img' : '' }}"
                                @if (! $swatchHasImg)
                                    style="background-color: {{ $val['color_hex'] ?? '#666' }};"
                                @else
                                    style="background-color: {{ $val['color_hex'] ?? '#e8eaed' }};"
                                @endif
                                title="{{ $val['name'] }}"
                                role="listitem"
                                aria-label="{{ $val['name'] }}"
                            >
                                @if ($swatchHasImg)
                                    <img src="{{ asset('storage/' . ltrim($swatchPath, '/')) }}" alt="" aria-hidden="true">
                                @endif
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="opt-chips" role="list">
                        @foreach ($block['values'] as $val)
                            <span class="opt-chip opt-chip--readonly" role="listitem">
                                {{ $val['name'] }}
                                @if (($val['price'] ?? null) !== null && (float) $val['price'] > 0)
                                    <span style="opacity:.75"> +{{ number_format((float) $val['price'], 0, '', ' ') }} ₴</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
