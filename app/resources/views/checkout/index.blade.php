@extends('layouts.shop')

@section('title', __('shop.checkout_page_title'))
@section('robots', 'noindex,follow')

@php
    $checkoutPrefill = $checkoutPrefill ?? ['customer_name' => '', 'customer_email' => '', 'customer_phone' => ''];
    $novaPoshtaConfigured = $novaPoshtaConfigured ?? false;
    $liqPayConfigured = $liqPayConfigured ?? false;
    $checkoutGoogleMapsKey = $checkoutGoogleMapsKey ?? '';
    $paymentSplit = $paymentSplit ?? [
        'immediate_subtotal' => 0.0,
        'deferred_subtotal' => 0.0,
        'is_mixed' => false,
        'defers_any' => false,
    ];
    $checkoutPaymentIsMixed = (bool) ($paymentSplit['is_mixed'] ?? false);
    $allowsOnlineAtCheckout = $liqPayConfigured && ((! $categoryDefersOnlinePayment) || $checkoutPaymentIsMixed);
    $pmOld = old('payment_method', 'cod');
    if ($categoryDefersOnlinePayment && ! $checkoutPaymentIsMixed) {
        $pmOld = 'cod';
    }
    /** Карти (відділення, кур’єр, самовивіз): Google, якщо є ключ у базі або .env — незалежно від ключа Нової Пошти. */
    $checkoutUseGoogleMaps = $checkoutGoogleMapsKey !== '';
    $pickupDisplay = $pickupDisplay ?? ['address' => null, 'lat' => null, 'lng' => null];
    $pickupEnabled = $pickupEnabled ?? true;
    $categoryRequiresPickupOnly = $categoryRequiresPickupOnly ?? false;
    $categoryDefersOnlinePayment = $categoryDefersOnlinePayment ?? false;
    $pickupHasMap = $pickupEnabled
        && isset($pickupDisplay['lat'], $pickupDisplay['lng'])
        && is_numeric($pickupDisplay['lat'])
        && is_numeric($pickupDisplay['lng']);
    $deliveryTypes = $deliveryTypes ?? [\App\Models\Order::DELIVERY_PICKUP];
    $deliveryTypeLabels = [
        \App\Models\Order::DELIVERY_PICKUP => __('shop.checkout_delivery_pickup'),
        \App\Models\Order::DELIVERY_TAXI => __('shop.checkout_delivery_taxi'),
        \App\Models\Order::DELIVERY_NOVA_POSHTA_WAREHOUSE => __('shop.checkout_delivery_np_wh'),
        \App\Models\Order::DELIVERY_NOVA_POSHTA_COURIER => __('shop.checkout_delivery_np_courier'),
    ];
    $deliveryTypeCurrent = old('delivery_type', $deliveryTypes[0] ?? '');
    if (! in_array($deliveryTypeCurrent, $deliveryTypes, true)) {
        $deliveryTypeCurrent = $deliveryTypes[0] ?? '';
    }
    $deliveryTypeLocked = count($deliveryTypes) === 1;
    $taxiEnabled = in_array(\App\Models\Order::DELIVERY_TAXI, $deliveryTypes, true);
    $showTaxiMap = $deliveryTypeCurrent === \App\Models\Order::DELIVERY_TAXI;
    $shopHasCoords = is_numeric($pickupDisplay['lat'] ?? null) && is_numeric($pickupDisplay['lng'] ?? null);
    $showPickupMapPanel = $pickupHasMap && $deliveryTypeCurrent === \App\Models\Order::DELIVERY_PICKUP;
    $showNpWarehouseMap = $novaPoshtaConfigured && $deliveryTypeCurrent === \App\Models\Order::DELIVERY_NOVA_POSHTA_WAREHOUSE;
    $showNpCourierMapApi = $novaPoshtaConfigured && $deliveryTypeCurrent === \App\Models\Order::DELIVERY_NOVA_POSHTA_COURIER;
    $showNpCourierMapManual = ! $novaPoshtaConfigured && $deliveryTypeCurrent === \App\Models\Order::DELIVERY_NOVA_POSHTA_COURIER;
@endphp

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    @vite('resources/css/storefront/checkout.css')
@endpush

@section('content')
    <div class="checkout-page">
        <header class="checkout-page__toolbar">
            <h1 class="checkout-page__title">{{ __('shop.checkout_title') }}</h1>
            <p class="checkout-page__lead">{{ __('shop.checkout_lead') }}</p>
        </header>

        <section class="card" aria-labelledby="checkout-items-heading">
            <h2 id="checkout-items-heading" class="checkout-page__section-heading">{{ __('shop.checkout_your_order') }}</h2>
            <div class="cart-drawer__lines">
                @foreach ($items as $item)
                    @include('cart.partials.line-checkout', ['item' => $item])
                @endforeach
            </div>
            <p class="checkout-page__total">{{ __('shop.checkout_total') }} {{ number_format((float) $total, 2, ',', ' ') }} ₴</p>
        </section>

        <form class="checkout-page__form checkout-page__form--blocks" method="POST" action="{{ route('checkout.store') }}" id="checkout-form">
                @csrf
                <input type="hidden" name="checkout_token" value="{{ \Illuminate\Support\Str::random(40) }}">

        <section class="card" aria-labelledby="checkout-contact-heading">
            <h2 id="checkout-contact-heading" class="checkout-page__section-heading">{{ __('shop.checkout_contact') }}</h2>
                <div class="checkout-contact-grid">
                    <div>
                        <label for="customer_name">{{ __('shop.checkout_name_label') }}</label>
                        <input id="customer_name" name="customer_name" value="{{ old('customer_name', $checkoutPrefill['customer_name'] ?? '') }}" required autocomplete="name">
                        @error('customer_name') <p class="alert error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="customer_phone">{{ __('shop.checkout_phone_label') }}</label>
                        <input id="customer_phone" name="customer_phone" value="{{ old('customer_phone', $checkoutPrefill['customer_phone'] ?? '') }}" required autocomplete="tel">
                        @error('customer_phone') <p class="alert error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="customer_email">Email</label>
                        <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email', $checkoutPrefill['customer_email'] ?? '') }}" autocomplete="email">
                        @error('customer_email') <p class="alert error">{{ $message }}</p> @enderror
                    </div>
                </div>
        </section>

        <div class="checkout-delivery-section">
            <h2 id="checkout-delivery-heading" class="checkout-page__section-heading">{{ __('shop.checkout_delivery') }}</h2>
            @if ($categoryRequiresPickupOnly)
                <p class="muted" style="margin:0 0 0.75rem;line-height:1.5;font-size:0.95rem;">
                    {!! $taxiEnabled ? __('shop.checkout_delivery_no_nova_poshta') : __('shop.checkout_delivery_pickup_only') !!}
                </p>
            @endif

            <div class="checkout-delivery-row" id="checkout-delivery-row">
                <section class="card checkout-delivery__card checkout-delivery__card--info" aria-label="{{ __('shop.checkout_delivery_card_aria') }}">
                <div class="checkout-delivery__main">
                    <div>
                        <label for="delivery_type">{{ __('shop.checkout_delivery_type') }}</label>
                        @php($dt = $deliveryTypeCurrent)
                        <select id="delivery_type" name="delivery_type" required @if($deliveryTypeLocked) class="checkout-delivery-type--locked" aria-readonly="true" @endif>
                            @foreach ($deliveryTypes as $deliveryTypeOption)
                                <option value="{{ $deliveryTypeOption }}" @selected($dt === $deliveryTypeOption)>{{ $deliveryTypeLabels[$deliveryTypeOption] ?? $deliveryTypeOption }}</option>
                            @endforeach
                        </select>
                        @error('delivery_type') <p class="alert error">{{ $message }}</p> @enderror
                    </div>

                    <div id="pickup-block" class="pickup-delivery" hidden>
                        <div class="np-field">
                            <p style="margin:0 0 0.35rem;font-weight:700;">{{ __('shop.checkout_pickup_title') }}</p>
                            @if (filled($pickupDisplay['address'] ?? null))
                                <p style="margin:0;line-height:1.5;">{{ $pickupDisplay['address'] }}</p>
                            @else
                                <p class="muted" style="margin:0;line-height:1.5;">{{ __('shop.checkout_pickup_hint') }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($taxiEnabled)
                        <div id="taxi-block" class="np-field" hidden>
                            <label for="delivery_address_taxi">{!! __('shop.checkout_address_taxi') !!}</label>
                            <textarea id="delivery_address_taxi" name="delivery_address" rows="3" placeholder="{{ __('shop.checkout_address_taxi_placeholder') }}" autocomplete="street-address">{{ old('delivery_address') }}</textarea>
                            <button type="button" class="btn secondary np-courier-geocode-btn" id="taxi-geocode-btn">{{ __('shop.checkout_geocode_btn') }}</button>
                            {{-- contain: довга підказка не розширює картку й не стискає карту поруч --}}
                            <p class="muted" style="margin:0.6rem 0 0;font-size:0.9rem;line-height:1.5;contain:inline-size;">{{ __('shop.checkout_taxi_hint') }}</p>
                            @error('delivery_address') <p class="alert error">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div id="np-block" class="np-delivery" hidden>
                        @unless ($novaPoshtaConfigured)
                            <p class="muted" style="margin:0 0 1rem;font-size:0.92rem;line-height:1.5;">
                                {{ __('shop.checkout_np_api_hint') }}
                                {!! __('shop.checkout_np_manual_hint') !!}
                            </p>
                        @endunless

                        @if ($novaPoshtaConfigured)
                            <div class="np-checkout-form-col">
                                <div class="np-field">
                                    <label for="np_region_select">{{ __('shop.checkout_region') }}</label>
                                    <select id="np_region_select" autocomplete="address-level1">
                                        <option value="">{{ __('shop.checkout_loading') }}</option>
                                    </select>
                                </div>
                                <div class="np-field">
                                    <label for="np_city_select">{{ __('shop.checkout_city') }}</label>
                                    <select id="np_city_select" autocomplete="address-level2" disabled>
                                        <option value="">{{ __('shop.checkout_region_first') }}</option>
                                    </select>
                                    <input type="hidden" name="delivery_city" id="delivery_city" value="{{ old('delivery_city') }}">
                                    <input type="hidden" name="delivery_city_ref" id="delivery_city_ref" value="{{ old('delivery_city_ref') }}">
                                    @error('delivery_city') <p class="alert error">{{ $message }}</p> @enderror
                                    @error('delivery_city_ref') <p class="alert error">{{ $message }}</p> @enderror
                                </div>

                                <div id="np-wh-block" class="np-field">
                                    <label for="np_warehouse_select">{{ __('shop.checkout_warehouse') }}</label>
                                    <select id="np_warehouse_select">
                                        <option value="">{{ __('shop.checkout_city_first') }}</option>
                                    </select>
                                    <input type="hidden" name="delivery_branch" id="delivery_branch" value="{{ old('delivery_branch') }}">
                                    <input type="hidden" name="delivery_warehouse_ref" id="delivery_warehouse_ref" value="{{ old('delivery_warehouse_ref') }}">
                                    @error('delivery_branch') <p class="alert error">{{ $message }}</p> @enderror
                                    @error('delivery_warehouse_ref') <p class="alert error">{{ $message }}</p> @enderror
                                </div>

                                <div id="np-courier-address-block" class="np-field" hidden>
                                    <label for="delivery_address_courier">{!! __('shop.checkout_address_courier') !!}</label>
                                    <textarea id="delivery_address_courier" name="delivery_address" rows="3" placeholder="{{ __('shop.checkout_address_placeholder') }}">{{ old('delivery_address') }}</textarea>
                                    <button type="button" class="btn secondary np-courier-geocode-btn" id="courier-geocode-btn">{{ __('shop.checkout_geocode_btn') }}</button>
                                    @error('delivery_address') <p class="alert error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @else
                            <div id="np-manual-city-block" class="np-field" hidden>
                                <label for="delivery_city_manual">{!! __('shop.checkout_city_required') !!}</label>
                                <input type="text" name="delivery_city" id="delivery_city_manual" value="{{ old('delivery_city') }}" placeholder="{{ __('shop.checkout_city_placeholder') }}">
                                <input type="hidden" name="delivery_city_ref" id="delivery_city_ref_manual" value="{{ old('delivery_city_ref') }}">
                                @error('delivery_city') <p class="alert error">{{ $message }}</p> @enderror
                                @error('delivery_city_ref') <p class="alert error">{{ $message }}</p> @enderror
                            </div>

                            <div id="np-manual-warehouse-block" class="np-field" hidden>
                                <label for="delivery_branch_manual">{!! __('shop.checkout_branch_required') !!}</label>
                                <input type="text" name="delivery_branch" id="delivery_branch_manual" value="{{ old('delivery_branch') }}" placeholder="{{ __('shop.checkout_branch_placeholder') }}">
                                <input type="hidden" name="delivery_warehouse_ref" id="delivery_warehouse_ref_manual" value="{{ old('delivery_warehouse_ref') }}">
                                @error('delivery_branch') <p class="alert error">{{ $message }}</p> @enderror
                                @error('delivery_warehouse_ref') <p class="alert error">{{ $message }}</p> @enderror
                            </div>

                            <div id="np-manual-courier-block" hidden>
                                <div class="np-field">
                                    <label for="delivery_address_manual">{!! __('shop.checkout_address_manual') !!}</label>
                                    <textarea name="delivery_address" id="delivery_address_manual" rows="3" placeholder="{{ __('shop.checkout_address_manual_placeholder') }}">{{ old('delivery_address') }}</textarea>
                                    <button type="button" class="btn secondary np-courier-geocode-btn" id="courier-geocode-btn-manual">{{ __('shop.checkout_geocode_btn') }}</button>
                                    @error('delivery_address') <p class="alert error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <input type="hidden" name="delivery_lat" id="delivery_lat" value="{{ old('delivery_lat') }}">
                <input type="hidden" name="delivery_lng" id="delivery_lng" value="{{ old('delivery_lng') }}">
                @error('delivery_lat') <p class="alert error">{{ $message }}</p> @enderror
                @error('delivery_lng') <p class="alert error">{{ $message }}</p> @enderror

                <div>
                    <label for="customer_notes">{{ __('shop.checkout_notes_label') }}</label>
                    <textarea id="customer_notes" name="customer_notes" rows="2">{{ old('customer_notes') }}</textarea>
                    @error('customer_notes') <p class="alert error">{{ $message }}</p> @enderror
                </div>
                </section>

                <section id="checkout-delivery-map-card" class="card checkout-delivery__card checkout-delivery__card--map" aria-labelledby="checkout-delivery-map-title" hidden>
                    <h3 id="checkout-delivery-map-title" class="checkout-delivery__map-heading">{{ __('shop.checkout_map_delivery') }}</h3>
                    <div id="checkout-delivery-map-column" class="checkout-delivery__maps-inner">
                    @if ($pickupHasMap)
                        <div id="pickup-map-panel" class="checkout-delivery__map-panel np-checkout-map-col" @unless($showPickupMapPanel) hidden @endunless>
                            <p class="np-map-caption muted">{{ __('shop.checkout_map_how_find') }}</p>
                            <div id="pickup-map" class="np-map" role="region" aria-label="{{ __('shop.checkout_map_pickup_aria') }}"></div>
                        </div>
                    @endif

                    @if ($novaPoshtaConfigured)
                        <div id="np-map-column" class="np-checkout-map-col" @unless($showNpWarehouseMap) hidden @endunless>
                            <p class="np-map-caption muted">{{ __('shop.checkout_caption_np_map') }}</p>
                            <div id="np-map" class="np-map" role="region" aria-label="{{ __('shop.checkout_map_np_aria') }}"></div>
                        </div>
                        <div id="np-courier-map-column" class="np-checkout-map-col" @unless($showNpCourierMapApi) hidden @endunless>
                            <p class="np-map-caption muted">{{ __('shop.checkout_caption_courier_map') }}</p>
                            <div id="courier-map" class="np-map" role="region" aria-label="{{ __('shop.checkout_map_courier_aria') }}"></div>
                            <p class="muted" style="font-size:0.86rem;line-height:1.45;margin:0.5rem 0 0;">{{ __('shop.checkout_map_drag_hint') }}</p>
                        </div>
                    @endif
                    @if ($taxiEnabled)
                        <div id="taxi-map-column" class="np-checkout-map-col" @unless($showTaxiMap) hidden @endunless>
                            <p class="np-map-caption muted">{{ __('shop.checkout_caption_taxi_map') }}</p>
                            <div id="taxi-map" class="np-map" role="region" aria-label="{{ __('shop.checkout_map_address_aria') }}"></div>
                            <p class="muted" style="font-size:0.86rem;line-height:1.45;margin:0.5rem 0 0;">{{ __('shop.checkout_map_drag_hint') }}</p>
                        </div>
                    @endif
                    @unless ($novaPoshtaConfigured)
                        <div id="np-courier-map-column-manual" class="np-checkout-map-col" @unless($showNpCourierMapManual) hidden @endunless>
                            <p class="np-map-caption muted">{{ __('shop.checkout_caption_address_map') }}</p>
                            <div id="courier-map-manual" class="np-map" role="region" aria-label="{{ __('shop.checkout_map_address_aria') }}"></div>
                            <p class="muted" style="font-size:0.86rem;line-height:1.45;margin:0.5rem 0 0;">{{ __('shop.checkout_map_drag_hint') }}</p>
                        </div>
                    @endunless
                    </div>
                </section>
            </div>
        </div>

        <section class="card" aria-labelledby="checkout-payment-heading">
            <h2 id="checkout-payment-heading" class="checkout-page__section-heading">{{ __('shop.checkout_payment') }}</h2>
            @if ($checkoutPaymentIsMixed)
                <p class="muted" style="margin:0 0 0.75rem;line-height:1.5;font-size:0.95rem;">
                    {!! __('shop.checkout_payment_split') !!}
                    {!! __('shop.checkout_payment_split_2') !!}
                </p>
            @elseif ($categoryDefersOnlinePayment)
                <p class="muted" style="margin:0 0 0.75rem;line-height:1.5;font-size:0.95rem;">
                    {!! __('shop.checkout_payment_defer') !!}
                </p>
            @endif
                @if ($allowsOnlineAtCheckout)
                    <div class="checkout-payment-buttons" role="radiogroup" aria-labelledby="checkout-payment-heading">
                        <label class="checkout-payment-btn">
                            <input class="visually-hidden" type="radio" name="payment_method" value="cod" @checked($pmOld === 'cod') required>
                            <span class="checkout-payment-btn__inner">{{ __('shop.checkout_pay_cod') }}</span>
                        </label>
                        <label class="checkout-payment-btn">
                            <input class="visually-hidden" type="radio" name="payment_method" value="online" @checked($pmOld === 'online')>
                            <span class="checkout-payment-btn__inner">{{ __('shop.checkout_pay_card') }}</span>
                        </label>
                    </div>
                @elseif ($liqPayConfigured && $categoryDefersOnlinePayment && ! $checkoutPaymentIsMixed)
                    <input type="hidden" name="payment_method" value="cod">
                    <div class="checkout-payment-buttons" aria-labelledby="checkout-payment-heading">
                        <div class="checkout-payment-btn checkout-payment-btn--readonly" aria-current="true">
                            <span class="checkout-payment-btn__inner">{{ __('shop.checkout_pay_cod') }}</span>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="payment_method" value="cod">
                    <div class="checkout-payment-buttons" aria-labelledby="checkout-payment-heading">
                        <div class="checkout-payment-btn checkout-payment-btn--readonly" aria-current="true">
                            <span class="checkout-payment-btn__inner">{{ __('shop.checkout_pay_cod') }}</span>
                        </div>
                    </div>
                    <p class="muted" style="margin:0.75rem 0 0;line-height:1.5;font-size:0.92rem;">{{ __('shop.checkout_pay_online_hint') }}</p>
                @endif
                @error('payment_method') <p class="alert error">{{ $message }}</p> @enderror
        </section>

        <section class="card" aria-labelledby="checkout-comment-heading">
            <h2 id="checkout-comment-heading" class="checkout-page__section-heading">{{ __('shop.checkout_comment') }}</h2>
                <div>
                    <label for="comment">{{ __('shop.checkout_comment_label') }}</label>
                    <textarea id="comment" name="comment" rows="4">{{ old('comment') }}</textarea>
                    @error('comment') <p class="alert error">{{ $message }}</p> @enderror
                </div>
        </section>

                <div class="checkout-page__submit">
                    <button class="btn btn-buy" type="submit">{{ __('shop.checkout_submit') }}</button>
                </div>
        </form>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        <script>
            (function () {
                /* Сайт віддає Referrer-Policy: no-referrer (SetNoReferrerPolicy — заради фото на хостингу),
                   а сервери OpenStreetMap без Referer відповідають 403 «Access blocked». Для плиток Referer вмикаємо окремо. */
                function osmTileLayer(attribution) {
                    return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: attribution,
                        maxZoom: 19,
                        referrerPolicy: 'strict-origin-when-cross-origin'
                    });
                }

                {{-- Лише рядки, які читає цей скрипт: повний словник shop.* важив 82 КБ на кожній сторінці. --}}
                var T = {!! json_encode(\Illuminate\Support\Arr::only((array) trans('shop'), ['checkout_city_first', 'checkout_loading', 'checkout_region_first', 'js_address_fail', 'js_address_on_map', 'js_address_resolving', 'js_cities_load_fail', 'js_close', 'js_delivery', 'js_map_drag_hint', 'js_np_brand', 'js_np_heading_branch', 'js_np_key_invalid', 'js_np_selected_hint', 'js_regions_load_fail', 'js_select_city', 'js_select_region', 'js_select_warehouse', 'js_warehouses_load_fail']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
                var cfg = {
                    npEnabled: {{ $novaPoshtaConfigured ? 'true' : 'false' }},
                    useGoogleMaps: {{ $checkoutUseGoogleMaps ? 'true' : 'false' }},
                    googleMapsKey: @json($checkoutGoogleMapsKey),
                    areasUrl: @json(route('nova-poshta.areas')),
                    citiesByAreaUrl: @json(route('nova-poshta.cities-by-area')),
                    cityByRefUrl: @json(route('nova-poshta.city-by-ref')),
                    warehousesUrl: @json(route('nova-poshta.warehouses')),
                    geocodeUrl: @json(route('geocode.city')),
                    geocodeAddressUrl: @json(route('geocode.address')),
                    geocodeReverseUrl: @json(route('geocode.reverse')),
                    pickup: @json(\App\Models\Order::DELIVERY_PICKUP),
                    npWh: @json(\App\Models\Order::DELIVERY_NOVA_POSHTA_WAREHOUSE),
                    npCourier: @json(\App\Models\Order::DELIVERY_NOVA_POSHTA_COURIER),
                    taxi: @json(\App\Models\Order::DELIVERY_TAXI),
                    shopLat: @json($shopHasCoords ? (float) $pickupDisplay['lat'] : null),
                    shopLng: @json($shopHasCoords ? (float) $pickupDisplay['lng'] : null),
                    pickupHasMap: {{ $pickupHasMap ? 'true' : 'false' }},
                    pickupEnabled: {{ $pickupEnabled ? 'true' : 'false' }},
                    pickupLat: @json($pickupHasMap ? (float) $pickupDisplay['lat'] : null),
                    pickupLng: @json($pickupHasMap ? (float) $pickupDisplay['lng'] : null),
                    categoryRequiresPickupOnly: {{ $categoryRequiresPickupOnly ? 'true' : 'false' }},
                };

                var sel = document.getElementById('delivery_type');
                if (sel && sel.options.length === 1) {
                    sel.disabled = true;
                }
                var npBlock = document.getElementById('np-block');
                var pickupBlock = document.getElementById('pickup-block');
                var taxiBlock = document.getElementById('taxi-block');
                var lastSyncedDeliveryType = null;
                var npManualCityBlock = document.getElementById('np-manual-city-block');
                var npManualWarehouseBlock = document.getElementById('np-manual-warehouse-block');
                var npManualCourierBlock = document.getElementById('np-manual-courier-block');
                var regionSelect = document.getElementById('np_region_select');
                var citySelect = document.getElementById('np_city_select');
                var whSelect = document.getElementById('np_warehouse_select');
                var cityRefIn = document.getElementById('delivery_city_ref');
                var cityNameIn = document.getElementById('delivery_city');
                var branchH = document.getElementById('delivery_branch');
                var whRefH = document.getElementById('delivery_warehouse_ref');

                var npMap = null;
                var npMarkersLayer = null;
                var npGoogleMarkers = [];
                var npGoogleMarkerByRef = {};
                var npGoogleInfoWindow = null;
                var npLeafletWarehouseIcon = null;
                var npLeafletMarkerByRef = {};
                var googleMapsScriptRequested = false;
                var npGoogleMapSizeWait = 0;
                var npCityLat = null;
                var npCityLng = null;
                var npAreaRef = '';
                var areasLoaded = false;
                var areasLoading = false;
                var areasLoadCallbacks = [];
                var pickupMap = null;
                var pickupGoogleMarker = null;

                function hasCityRef() {
                    var r = cityRefIn && cityRefIn.value ? cityRefIn.value.trim() : '';
                    if (r.length < 32) return false;
                    return /^[0-9a-fA-F-]+$/.test(r);
                }

                function npWarehouseActive() {
                    return !!(sel && sel.value === cfg.npWh && cfg.npEnabled);
                }

                function npCourierActive() {
                    return !!(sel && sel.value === cfg.npCourier && cfg.npEnabled);
                }

                function syncNpFieldLock() {
                    var wh = npWarehouseActive();
                    var cr = npCourierActive();
                    var geo = wh || cr;
                    if (regionSelect) {
                        regionSelect.disabled = !geo;
                        regionSelect.required = geo;
                    }
                    if (citySelect) {
                        citySelect.disabled = !geo || !npAreaRef;
                        citySelect.required = geo && !!npAreaRef;
                    }
                    if (whSelect) {
                        whSelect.disabled = !wh || !hasCityRef();
                        whSelect.required = wh;
                    }
                }

                var courierLeafletMap = null;
                var courierLeafletMarker = null;
                var courierGoogleMap = null;
                var courierGoogleMarker = null;
                var courierGoogleInfoWindow = null;
                var courierReverseTimer = null;

                function destroyCourierMap() {
                    if (courierReverseTimer) {
                        clearTimeout(courierReverseTimer);
                        courierReverseTimer = null;
                    }
                    if (courierGoogleInfoWindow) {
                        try { courierGoogleInfoWindow.close(); } catch (e) {}
                        courierGoogleInfoWindow = null;
                    }
                    if (courierLeafletMap) {
                        try { courierLeafletMap.remove(); } catch (e) {}
                        courierLeafletMap = null;
                        courierLeafletMarker = null;
                    }
                    courierGoogleMap = null;
                    courierGoogleMarker = null;
                }

                function manualCourierActive() {
                    return !!(sel && sel.value === cfg.npCourier && !cfg.npEnabled);
                }

                // Таксі ділить з курʼєром карту й геокодування, але має своє поле адреси й контейнер карти.
                function taxiActive() {
                    return !!(sel && sel.value === cfg.taxi);
                }

                function getCourierAddressTextarea() {
                    if (taxiActive()) return document.getElementById('delivery_address_taxi');
                    return document.getElementById('delivery_address_courier') || document.getElementById('delivery_address_manual');
                }

                function courierHasSavedCoords() {
                    var la = document.getElementById('delivery_lat');
                    var ln = document.getElementById('delivery_lng');
                    return !!(la && ln && la.value && ln.value);
                }

                function buildCourierGeocodeQuery() {
                    var city = '';
                    if (!taxiActive()) {
                        if (cityNameIn && cityNameIn.value) city = cityNameIn.value.trim();
                        var mc = document.getElementById('delivery_city_manual');
                        if ((!city || city.length < 1) && mc && mc.value) city = mc.value.trim();
                    }
                    var ta = getCourierAddressTextarea();
                    var addr = ta && ta.value ? ta.value.trim() : '';
                    addr = addr.replace(/\s+/g, ' ');
                    addr = addr.replace(/,\s*Україна\s*$/i, '').replace(/,\s*Ukraine\s*$/i, '').trim();
                    city = city.replace(/\s+,?\s*Україна\s*$/i, '').replace(/\s+,?\s*Ukraine\s*$/i, '').trim();
                    if (!addr && !city) return '';
                    if (city && addr) {
                        return addr + ', ' + city + ', Ukraine';
                    }
                    if (city && !addr) {
                        return city + ', Ukraine';
                    }
                    return addr + ', Ukraine';
                }

                function getCourierPopupLine() {
                    var ta = getCourierAddressTextarea();
                    if (!ta || !ta.value) return '';
                    return String(ta.value).trim();
                }

                function courierAddressPopupHtml(text) {
                    var body = text == null || String(text).trim() === '' ? '—' : String(text).trim();
                    return (
                        '<div class="np-map-wh-popup">' +
                        '<div class="np-map-wh-popup__card">' +
                        '<div class="np-map-wh-popup__accent" aria-hidden="true"></div>' +
                        '<button type="button" class="np-map-wh-popup__close" aria-label="' + (T.js_close || 'Закрити') + '">&times;</button>' +
                        '<div class="np-map-wh-popup__top">' +
                        '<span class="np-map-wh-popup__brand">' + (T.js_delivery || 'Доставка') + '</span>' +
                        '<span class="np-map-wh-popup__heading">' + (T.js_address_on_map || 'Адреса на карті') + '</span>' +
                        '</div>' +
                        '<div class="np-map-wh-popup__body">' +
                        '<p class="np-map-wh-popup__name">' + npEscapeHtml(body) + '</p>' +
                        '</div>' +
                        '<p class="np-map-wh-popup__hint">' + (T.js_map_drag_hint || 'Перетягніть мітку або клацніть на карті') + '</p>' +
                        '</div>' +
                        '</div>'
                    );
                }

                function courierOpenMapPopup(text) {
                    if (text == null || String(text).trim() === '') {
                        text = '—';
                    } else {
                        text = String(text).trim();
                    }
                    if (cfg.useGoogleMaps && courierGoogleMap && courierGoogleMarker) {
                        if (typeof google === 'undefined' || !google.maps) return;
                        if (!courierGoogleInfoWindow) {
                            courierGoogleInfoWindow = new google.maps.InfoWindow({ maxWidth: 320 });
                        }
                        courierGoogleInfoWindow.setContent(courierAddressPopupHtml(text));
                        courierGoogleInfoWindow.open({ map: courierGoogleMap, anchor: courierGoogleMarker });
                    }
                    if (courierLeafletMap && courierLeafletMarker) {
                        var shell = '<div class="np-map-popup-shell">' + courierAddressPopupHtml(text) + '</div>';
                        courierLeafletMarker.setPopupContent(shell);
                        courierLeafletMarker.openPopup();
                    }
                }

                function setCourierHidden(lat, lng) {
                    var la = document.getElementById('delivery_lat');
                    var ln = document.getElementById('delivery_lng');
                    if (la) la.value = String(Math.round(lat * 1e7) / 1e7);
                    if (ln) ln.value = String(Math.round(lng * 1e7) / 1e7);
                }

                function fillCourierAddressFromCoords(lat, lng) {
                    if (courierReverseTimer) clearTimeout(courierReverseTimer);
                    courierReverseTimer = setTimeout(function () {
                        courierReverseTimer = null;
                        fetch(cfg.geocodeReverseUrl + '?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng), { credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                if (j && j.address) {
                                    var ta = getCourierAddressTextarea();
                                    if (ta) ta.value = j.address;
                                    courierOpenMapPopup(j.address);
                                } else {
                                    courierOpenMapPopup(getCourierPopupLine() || (T.js_address_fail || 'Не вдалося визначити адресу.'));
                                }
                            })
                            .catch(function () {
                                courierOpenMapPopup(getCourierPopupLine() || (T.js_address_fail || 'Не вдалося визначити адресу.'));
                            });
                    }, 200);
                }

                function setCourierCoordsAndSyncAddress(lat, lng) {
                    setCourierHidden(lat, lng);
                    courierOpenMapPopup(T.js_address_resolving || 'Уточнюємо адресу…');
                    fillCourierAddressFromCoords(lat, lng);
                }

                function getCourierMapContainer() {
                    if (taxiActive()) return document.getElementById('taxi-map');
                    return document.getElementById('courier-map') || document.getElementById('courier-map-manual');
                }

                function courierMapReadStart() {
                    var latIn = document.getElementById('delivery_lat');
                    var lngIn = document.getElementById('delivery_lng');
                    var startLat = 49.0;
                    var startLng = 31.0;
                    var z = 6;
                    if (latIn && lngIn && latIn.value && lngIn.value) {
                        var la0 = parseFloat(latIn.value);
                        var ln0 = parseFloat(lngIn.value);
                        if (!isNaN(la0) && !isNaN(ln0)) {
                            startLat = la0;
                            startLng = ln0;
                            z = 15;
                        }
                    } else if (taxiActive() && cfg.shopLat != null && cfg.shopLng != null) {
                        startLat = cfg.shopLat;
                        startLng = cfg.shopLng;
                        z = 12;
                    } else if (npCityLat != null && npCityLng != null) {
                        startLat = npCityLat;
                        startLng = npCityLng;
                        z = 12;
                    }
                    return { lat: startLat, lng: startLng, z: z };
                }

                function initCourierMap() {
                    if (!npCourierActive() && !manualCourierActive() && !taxiActive()) return;
                    var el = getCourierMapContainer();
                    if (!el) return;

                    if (cfg.useGoogleMaps) {
                        if (courierGoogleMap) {
                            setTimeout(function () {
                                if (courierGoogleMap && typeof google !== 'undefined' && google.maps) {
                                    google.maps.event.trigger(courierGoogleMap, 'resize');
                                }
                            }, 120);
                            return;
                        }
                        loadGoogleMapsScript();
                        if (typeof google === 'undefined' || !google.maps) {
                            var n = 0;
                            var iv = setInterval(function () {
                                n++;
                                if (typeof google !== 'undefined' && google.maps) {
                                    clearInterval(iv);
                                    initCourierMap();
                                } else if (n > 120) {
                                    clearInterval(iv);
                                }
                            }, 50);
                            return;
                        }
                        if (el.offsetWidth < 2 || el.offsetHeight < 2) {
                            setTimeout(function () { initCourierMap(); }, 60);
                            return;
                        }
                        var st = courierMapReadStart();
                        courierGoogleMap = new google.maps.Map(el, {
                            center: { lat: st.lat, lng: st.lng },
                            zoom: st.z,
                            gestureHandling: 'greedy',
                            mapTypeControl: false,
                            streetViewControl: false,
                            fullscreenControl: true,
                            clickableIcons: false,
                        });
                        courierGoogleMarker = new google.maps.Marker({
                            position: { lat: st.lat, lng: st.lng },
                            map: courierGoogleMap,
                            draggable: true,
                            optimized: false,
                            icon: {
                                url: npWarehouseMarkerDataUrl(),
                                scaledSize: new google.maps.Size(36, 46),
                                anchor: new google.maps.Point(18, 46),
                            },
                        });
                        courierGoogleMarker.addListener('click', function () {
                            courierOpenMapPopup(getCourierPopupLine() || '—');
                        });
                        courierGoogleMarker.addListener('dragend', function (ev) {
                            var p = ev.latLng;
                            setCourierCoordsAndSyncAddress(p.lat(), p.lng());
                        });
                        courierGoogleMap.addListener('click', function (ev) {
                            var p = ev.latLng;
                            courierGoogleMarker.setPosition(p);
                            setCourierCoordsAndSyncAddress(p.lat(), p.lng());
                        });
                        var latIn = document.getElementById('delivery_lat');
                        var lngIn = document.getElementById('delivery_lng');
                        if (latIn && lngIn && latIn.value && lngIn.value) {
                            var la1 = parseFloat(latIn.value);
                            var ln1 = parseFloat(lngIn.value);
                            if (!isNaN(la1) && !isNaN(ln1)) {
                                courierGoogleMarker.setPosition({ lat: la1, lng: ln1 });
                            }
                        }
                        var syncLatG = st.lat;
                        var syncLngG = st.lng;
                        if (latIn && lngIn && latIn.value && lngIn.value) {
                            var laS = parseFloat(latIn.value);
                            var lnS = parseFloat(lngIn.value);
                            if (!isNaN(laS) && !isNaN(lnS)) {
                                syncLatG = laS;
                                syncLngG = lnS;
                            }
                        }
                        // Мітка таксі стартує біля магазину: адресу магазину в поле не підставляємо.
                        if (!taxiActive() || courierHasSavedCoords()) {
                            setCourierCoordsAndSyncAddress(syncLatG, syncLngG);
                        }
                        setTimeout(function () {
                            if (courierGoogleMap && typeof google !== 'undefined' && google.maps) {
                                google.maps.event.trigger(courierGoogleMap, 'resize');
                            }
                        }, 80);
                        return;
                    }

                    if (typeof L === 'undefined') return;
                    if (courierLeafletMap && courierLeafletMap.getContainer && courierLeafletMap.getContainer() !== el) {
                        try { courierLeafletMap.remove(); } catch (e) {}
                        courierLeafletMap = null;
                        courierLeafletMarker = null;
                    }
                    if (courierLeafletMap) {
                        setTimeout(function () {
                            if (courierLeafletMap && typeof courierLeafletMap.invalidateSize === 'function') courierLeafletMap.invalidateSize();
                        }, 120);
                        return;
                    }
                    var st2 = courierMapReadStart();
                    courierLeafletMap = L.map(el, { scrollWheelZoom: false }).setView([st2.lat, st2.lng], st2.z);
                    el.addEventListener('wheel', function (e) { e.preventDefault(); }, { passive: false });
                    osmTileLayer('&copy; OpenStreetMap').addTo(courierLeafletMap);
                    courierLeafletMarker = L.marker([st2.lat, st2.lng], {
                        draggable: true,
                        icon: L.icon({
                            iconUrl: npWarehouseMarkerDataUrl(),
                            iconSize: [36, 46],
                            iconAnchor: [18, 46],
                            className: 'np-map-leaflet-marker',
                        }),
                    }).addTo(courierLeafletMap);
                    courierLeafletMarker.bindPopup('', { className: 'np-map-popup-shell', maxWidth: 320, closeButton: false });
                    courierLeafletMarker.on('click', function (e) {
                        if (e && e.originalEvent) {
                            L.DomEvent.stopPropagation(e.originalEvent);
                        }
                        courierOpenMapPopup(getCourierPopupLine() || '—');
                    });
                    courierLeafletMarker.on('dragend', function () {
                        var p = courierLeafletMarker.getLatLng();
                        setCourierCoordsAndSyncAddress(p.lat, p.lng);
                    });
                    courierLeafletMap.on('click', function (ev) {
                        courierLeafletMarker.setLatLng(ev.latlng);
                        setCourierCoordsAndSyncAddress(ev.latlng.lat, ev.latlng.lng);
                    });
                    var latIn2 = document.getElementById('delivery_lat');
                    var lngIn2 = document.getElementById('delivery_lng');
                    if (latIn2 && lngIn2 && latIn2.value && lngIn2.value) {
                        var la2 = parseFloat(latIn2.value);
                        var ln2 = parseFloat(lngIn2.value);
                        if (!isNaN(la2) && !isNaN(ln2)) {
                            courierLeafletMarker.setLatLng([la2, ln2]);
                        }
                    }
                    var syncLatL = st2.lat;
                    var syncLngL = st2.lng;
                    if (latIn2 && lngIn2 && latIn2.value && lngIn2.value) {
                        var laL = parseFloat(latIn2.value);
                        var lnL = parseFloat(lngIn2.value);
                        if (!isNaN(laL) && !isNaN(lnL)) {
                            syncLatL = laL;
                            syncLngL = lnL;
                        }
                    }
                    if (!taxiActive() || courierHasSavedCoords()) {
                        setCourierCoordsAndSyncAddress(syncLatL, syncLngL);
                    }
                    setTimeout(function () {
                        if (courierLeafletMap && typeof courierLeafletMap.invalidateSize === 'function') courierLeafletMap.invalidateSize();
                    }, 80);
                }

                function geocodeCourierAddress() {
                    var q = buildCourierGeocodeQuery();
                    if (q.length < 2) return;
                    fetch(cfg.geocodeAddressUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (!j || j.lat == null || j.lng == null) return;
                            setCourierHidden(j.lat, j.lng);
                            var taG = getCourierAddressTextarea();
                            if (j.address && taG) {
                                taG.value = j.address;
                            } else {
                                fillCourierAddressFromCoords(j.lat, j.lng);
                            }
                            if (!courierLeafletMap && !courierGoogleMap) {
                                initCourierMap();
                            }
                            if (courierGoogleMap && courierGoogleMarker) {
                                courierGoogleMarker.setPosition({ lat: j.lat, lng: j.lng });
                                courierGoogleMap.setCenter({ lat: j.lat, lng: j.lng });
                                courierGoogleMap.setZoom(16);
                            }
                            if (courierLeafletMap && courierLeafletMarker) {
                                courierLeafletMarker.setLatLng([j.lat, j.lng]);
                                courierLeafletMap.setView([j.lat, j.lng], 16);
                            }
                            if (j.address) {
                                courierOpenMapPopup(j.address);
                            }
                        });
                }

                function centerCourierMapOnCityIfNeeded() {
                    if (!npCourierActive() && !manualCourierActive()) return;
                    var la = document.getElementById('delivery_lat');
                    if (la && la.value) return;
                    if (npCityLat == null || npCityLng == null) return;
                    if (courierGoogleMap && courierGoogleMarker) {
                        courierGoogleMarker.setPosition({ lat: npCityLat, lng: npCityLng });
                        courierGoogleMap.setCenter({ lat: npCityLat, lng: npCityLng });
                        courierGoogleMap.setZoom(12);
                        setCourierCoordsAndSyncAddress(npCityLat, npCityLng);
                    }
                    if (courierLeafletMap && courierLeafletMarker) {
                        courierLeafletMarker.setLatLng([npCityLat, npCityLng]);
                        courierLeafletMap.setView([npCityLat, npCityLng], 12);
                        setCourierCoordsAndSyncAddress(npCityLat, npCityLng);
                    }
                }

                function ensureAreasLoaded(done) {
                    if (!cfg.npEnabled || !regionSelect) {
                        if (done) done();
                        return;
                    }
                    if (areasLoaded) {
                        if (done) done();
                        return;
                    }
                    if (typeof done === 'function') {
                        areasLoadCallbacks.push(done);
                    }
                    if (areasLoading) return;
                    areasLoading = true;
                    fetch(cfg.areasUrl, { credentials: 'same-origin' })
                        .then(function (r) {
                            return r.json().then(function (j) {
                                if (!r.ok) {
                                    var raw = (j && j.errors && j.errors[0]) ? String(j.errors[0]) : '';
                                    var msg = (j && j.message) ? String(j.message) : '';
                                    var text = raw || msg || ('HTTP ' + r.status);
                                    if (raw === 'API key incorrect' || /key incorrect/i.test(text)) {
                                        text = T.js_np_key_invalid || 'Некоректний ключ API Нової Пошти. Відкрийте адмінку → Налаштування → Інтеграції та вставте повний ключ UUID з кабінету Нової Пошти.';
                                    }
                                    throw new Error(text);
                                }
                                return j;
                            });
                        })
                        .then(function (j) {
                            var rows = (j && j.data) ? j.data : [];
                            regionSelect.innerHTML = '<option value="">' + (T.js_select_region || 'Оберіть область…') + '</option>';
                            rows.forEach(function (row) {
                                var opt = document.createElement('option');
                                opt.value = row.ref;
                                opt.textContent = row.label;
                                regionSelect.appendChild(opt);
                            });
                            areasLoaded = true;
                            areasLoading = false;
                            var cbs = areasLoadCallbacks.slice();
                            areasLoadCallbacks = [];
                            cbs.forEach(function (cb) { cb(); });
                        })
                        .catch(function (err) {
                            areasLoading = false;
                            var hint = (err && err.message) ? String(err.message) : (T.js_regions_load_fail || 'Не вдалося завантажити області');
                            regionSelect.innerHTML = '';
                            var optErr = document.createElement('option');
                            optErr.value = '';
                            optErr.textContent = hint.length > 120 ? hint.slice(0, 117) + '…' : hint;
                            regionSelect.appendChild(optErr);
                            var cbs = areasLoadCallbacks.slice();
                            areasLoadCallbacks = [];
                            cbs.forEach(function (cb) { cb(); });
                        });
                }

                function loadCitiesForArea(areaRef, afterLoad) {
                    if (!citySelect || !areaRef) {
                        if (afterLoad) afterLoad();
                        return;
                    }
                    citySelect.innerHTML = '<option value="">' + (T.checkout_loading || 'Завантаження…') + '</option>';
                    citySelect.disabled = true;
                    syncNpFieldLock();
                    fetch(cfg.citiesByAreaUrl + '?area_ref=' + encodeURIComponent(areaRef), { credentials: 'same-origin' })
                        .then(function (r) {
                            if (!r.ok) {
                                return Promise.reject(new Error('HTTP ' + r.status));
                            }
                            return r.json();
                        })
                        .then(function (j) {
                            var rows = (j && j.data) ? j.data : [];
                            window.__npCityRows = rows;
                            citySelect.innerHTML = '<option value="">' + (T.js_select_city || 'Оберіть місто…') + '</option>';
                            rows.forEach(function (row) {
                                var opt = document.createElement('option');
                                opt.value = row.ref;
                                opt.textContent = row.label;
                                citySelect.appendChild(opt);
                            });
                            citySelect.disabled = false;
                            syncNpFieldLock();
                            if (afterLoad) afterLoad();
                        })
                        .catch(function () {
                            citySelect.innerHTML = '<option value="">' + (T.js_cities_load_fail || 'Не вдалося завантажити міста') + '</option>';
                            citySelect.disabled = false;
                            syncNpFieldLock();
                            if (afterLoad) afterLoad();
                        });
                }

                function prefillFromCityRef() {
                    if (!cfg.npEnabled || !hasCityRef() || !cityRefIn) return;
                    var targetCityRef = cityRefIn.value.trim();
                    fetch(cfg.cityByRefUrl + '?ref=' + encodeURIComponent(targetCityRef), { credentials: 'same-origin' })
                        .then(function (r) {
                            if (!r.ok) {
                                return Promise.reject(new Error('HTTP ' + r.status));
                            }
                            return r.json();
                        })
                        .then(function (j) {
                            var d = j && j.data;
                            if (!d || !regionSelect) return;
                            if (d.area_ref) {
                                regionSelect.value = d.area_ref;
                                npAreaRef = d.area_ref;
                            }
                            syncNpFieldLock();
                            loadCitiesForArea(npAreaRef, function () {
                                if (citySelect && targetCityRef) {
                                    citySelect.value = String(targetCityRef);
                                }
                                if (cityNameIn) cityNameIn.value = d.label || '';
                                if (d.lat != null && d.lng != null) {
                                    npCityLat = d.lat;
                                    npCityLng = d.lng;
                                } else {
                                    npCityLat = null;
                                    npCityLng = null;
                                }
                                if (npWarehouseActive()) {
                                    loadWarehouses();
                                } else {
                                    if (whSelect) {
                                        whSelect.innerHTML = '<option value="">' + (T.checkout_city_first || 'Спочатку оберіть місто…') + '</option>';
                                        whSelect.disabled = true;
                                    }
                                    if (branchH) branchH.value = '';
                                    if (whRefH) whRefH.value = '';
                                    updateWarehouseMarkers([]);
                                }
                                centerMapOnCity(d);
                            });
                        });
                }

                function onCityChange() {
                    if (!citySelect) return;
                    var id = citySelect.value;
                    if (!id) {
                        if (cityRefIn) cityRefIn.value = '';
                        if (cityNameIn) cityNameIn.value = '';
                        if (whSelect) {
                            whSelect.innerHTML = '<option value="">' + (T.checkout_city_first || 'Спочатку оберіть місто…') + '</option>';
                            whSelect.disabled = true;
                        }
                        if (branchH) branchH.value = '';
                        if (whRefH) whRefH.value = '';
                        updateWarehouseMarkers([]);
                        npCityLat = null;
                        npCityLng = null;
                        mapResetUkraine();
                        return;
                    }
                    var row = null;
                    var rows = window.__npCityRows || [];
                    for (var i = 0; i < rows.length; i++) {
                        if (String(rows[i].ref) === String(id)) {
                            row = rows[i];
                            break;
                        }
                    }
                    if (!row) return;
                    if (cityNameIn) cityNameIn.value = row.label;
                    if (cityRefIn) cityRefIn.value = String(row.ref);
                    if (row.lat != null && row.lng != null) {
                        npCityLat = row.lat;
                        npCityLng = row.lng;
                    } else {
                        npCityLat = null;
                        npCityLng = null;
                    }
                    if (npWarehouseActive()) {
                        loadWarehouses();
                    } else {
                        if (whSelect) {
                            whSelect.innerHTML = '<option value="">' + (T.checkout_city_first || 'Спочатку оберіть місто…') + '</option>';
                            whSelect.disabled = true;
                        }
                        if (branchH) branchH.value = '';
                        if (whRefH) whRefH.value = '';
                        updateWarehouseMarkers([]);
                    }
                    centerMapOnCity(row);
                    if (npCourierActive()) {
                        setTimeout(function () {
                            if (!courierLeafletMap && !courierGoogleMap) {
                                initCourierMap();
                            }
                            centerCourierMapOnCityIfNeeded();
                        }, 0);
                    }
                }

                function mapResetUkraine() {
                    if (!npMap) return;
                    if (cfg.useGoogleMaps) {
                        npMap.setCenter({ lat: 49, lng: 31 });
                        npMap.setZoom(6);
                    } else {
                        npMap.setView([49.0, 31.0], 6);
                    }
                }

                function loadGoogleMapsScript() {
                    if (!cfg.useGoogleMaps || !cfg.googleMapsKey) return;
                    if (typeof google !== 'undefined' && google.maps) return;
                    if (googleMapsScriptRequested) return;
                    googleMapsScriptRequested = true;
                    window.__npGoogleMapsLoaded = function () {
                        if (cfg.npEnabled) {
                            initMap();
                        }
                        if (npMap && typeof google !== 'undefined' && google.maps) {
                            try {
                                google.maps.event.trigger(npMap, 'resize');
                            } catch (e) {}
                        }
                        updateWarehouseMarkers(window.__npWarehouseRows || []);
                        if (window.__npPendingCenterRow) {
                            var pr = window.__npPendingCenterRow;
                            window.__npPendingCenterRow = null;
                            centerMapOnCity(pr);
                        }
                        initPickupMap();
                        if (sel && sel.value === cfg.npCourier) {
                            setTimeout(function () {
                                initCourierMap();
                            }, 0);
                        }
                    };
                    var gs = document.createElement('script');
                    gs.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(cfg.googleMapsKey) + '&callback=__npGoogleMapsLoaded';
                    gs.async = true;
                    gs.defer = true;
                    document.head.appendChild(gs);
                }

                function destroyPickupMap() {
                    if (pickupGoogleMarker) {
                        try {
                            pickupGoogleMarker.setMap(null);
                        } catch (e) {}
                        pickupGoogleMarker = null;
                    }
                    if (pickupMap) {
                        if (cfg.useGoogleMaps && typeof google !== 'undefined' && google.maps && pickupMap.getDiv) {
                            try {
                                var gDiv = pickupMap.getDiv();
                                if (gDiv) {
                                    gDiv.innerHTML = '';
                                }
                            } catch (e) {}
                            pickupMap = null;
                        } else if (typeof pickupMap.remove === 'function') {
                            try {
                                pickupMap.remove();
                            } catch (e) {}
                            pickupMap = null;
                        } else {
                            pickupMap = null;
                        }
                    }
                }

                function initPickupMap() {
                    if (!cfg.pickupHasMap) return;
                    var el = document.getElementById('pickup-map');
                    if (!el) return;
                    if (pickupMap) {
                        if (cfg.useGoogleMaps && typeof google !== 'undefined' && google.maps && pickupMap.getDiv) {
                            google.maps.event.trigger(pickupMap, 'resize');
                        } else if (typeof pickupMap.invalidateSize === 'function') {
                            pickupMap.invalidateSize();
                        }
                        return;
                    }
                    if (cfg.useGoogleMaps) {
                        if (typeof google === 'undefined' || !google.maps) {
                            loadGoogleMapsScript();
                            return;
                        }
                        if (el.offsetWidth < 2 || el.offsetHeight < 2) {
                            setTimeout(function () {
                                initPickupMap();
                            }, 60);
                            return;
                        }
                        pickupMap = new google.maps.Map(el, {
                            center: { lat: cfg.pickupLat, lng: cfg.pickupLng },
                            zoom: 15,
                            gestureHandling: 'greedy',
                            mapTypeControl: false,
                            streetViewControl: false,
                            fullscreenControl: true,
                            clickableIcons: false,
                        });
                        pickupGoogleMarker = new google.maps.Marker({
                            position: { lat: cfg.pickupLat, lng: cfg.pickupLng },
                            map: pickupMap,
                            optimized: false,
                            icon: {
                                url: npWarehouseMarkerDataUrl(),
                                scaledSize: new google.maps.Size(36, 46),
                                anchor: new google.maps.Point(18, 46),
                            },
                        });
                        return;
                    }
                    if (typeof L === 'undefined') return;
                    pickupMap = L.map(el, { scrollWheelZoom: false }).setView([cfg.pickupLat, cfg.pickupLng], 15);
                    pickupMap.getContainer().addEventListener('wheel', function (e) {
                        e.preventDefault();
                    }, { passive: false });
                    osmTileLayer('&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>').addTo(pickupMap);
                    L.marker([cfg.pickupLat, cfg.pickupLng]).addTo(pickupMap);
                }

                function initMap() {
                    if (!cfg.npEnabled) return;
                    var el = document.getElementById('np-map');
                    if (!el || npMap) return;

                    if (cfg.useGoogleMaps) {
                        if (typeof google === 'undefined' || !google.maps) {
                            loadGoogleMapsScript();
                            return;
                        }
                        if (el.offsetWidth < 2 || el.offsetHeight < 2) {
                            if (npGoogleMapSizeWait < 30) {
                                npGoogleMapSizeWait++;
                                setTimeout(function () { initMap(); }, 60);
                            }
                            return;
                        }
                        npGoogleMapSizeWait = 0;
                        try {
                            npMap = new google.maps.Map(el, {
                                center: { lat: 49, lng: 31 },
                                zoom: 6,
                                /* cooperative пропускає жести на сторінку — сторінка «їде» при перетягуванні карти */
                                gestureHandling: 'greedy',
                                mapTypeControl: false,
                                streetViewControl: false,
                                fullscreenControl: true,
                            });
                        } catch (e) {
                            npMap = null;
                            return;
                        }
                        return;
                    }

                    if (typeof L === 'undefined') return;
                    npMap = L.map(el, { scrollWheelZoom: false }).setView([49.0, 31.0], 6);
                    /* scrollWheelZoom вимкнено — інакше wheel піднімає/опускає всю сторінку */
                    npMap.getContainer().addEventListener('wheel', function (e) {
                        e.preventDefault();
                    }, { passive: false });
                    osmTileLayer('&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>').addTo(npMap);
                    npMarkersLayer = L.layerGroup().addTo(npMap);
                }

                function refreshMapSize() {
                    if (!cfg.npEnabled) return;
                    initMap();
                    if (!npMap) return;
                    if (cfg.useGoogleMaps && typeof google !== 'undefined' && google.maps) {
                        google.maps.event.trigger(npMap, 'resize');
                    } else if (!cfg.useGoogleMaps && typeof npMap.invalidateSize === 'function') {
                        npMap.invalidateSize();
                    }
                }

                function centerMapOnCity(row) {
                    if (!cfg.npEnabled || !row) return;
                    initMap();
                    if (!npMap) {
                        if (cfg.useGoogleMaps) {
                            window.__npPendingCenterRow = row;
                        }
                        return;
                    }
                    var lat = row.lat;
                    var lng = row.lng;
                    if (typeof lat === 'number' && typeof lng === 'number' && !isNaN(lat) && !isNaN(lng)) {
                        npCityLat = lat;
                        npCityLng = lng;
                        if (cfg.useGoogleMaps) {
                            npMap.setCenter({ lat: lat, lng: lng });
                            npMap.setZoom(11);
                        } else {
                            npMap.setView([lat, lng], 11);
                        }
                        return;
                    }
                    var q = row.label || '';
                    if (!cfg.geocodeUrl || q.length < 2) return;
                    fetch(cfg.geocodeUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.lat != null && j.lng != null) {
                                npCityLat = j.lat;
                                npCityLng = j.lng;
                                var geoRow = { lat: j.lat, lng: j.lng, label: q };
                                initMap();
                                if (!npMap) {
                                    if (cfg.useGoogleMaps) {
                                        window.__npPendingCenterRow = geoRow;
                                    }
                                    return;
                                }
                                if (cfg.useGoogleMaps) {
                                    npMap.setCenter({ lat: j.lat, lng: j.lng });
                                    npMap.setZoom(11);
                                } else {
                                    npMap.setView([j.lat, j.lng], 11);
                                }
                                if (window.__npWarehouseRows && window.__npWarehouseRows.length) {
                                    updateWarehouseMarkers(window.__npWarehouseRows);
                                }
                            }
                        });
                }

                function npEscapeHtml(s) {
                    return String(s == null ? '' : s)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                var NP_WAREHOUSE_MARKER_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 46" width="36" height="46" aria-hidden="true"><defs><linearGradient id="np-pin-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff3b52"/><stop offset="1" stop-color="#b81428"/></linearGradient></defs><ellipse cx="18" cy="43.5" rx="6" ry="2.2" fill="#0f172a" opacity="0.18"/><path fill="url(#np-pin-g)" stroke="#8f0f22" stroke-width="0.75" d="M18 2C10.3 2 4 8.1 4 15.4c0 6.1 4.5 13.2 9.6 20.8 1.5 2.2 3.2 4.5 4.1 5.7.4.5 1 .8 1.7.8h.1c.7 0 1.3-.3 1.7-.9.9-1.2 2.6-3.5 4.1-5.7 5.1-7.6 9.6-14.7 9.6-20.8C32 8.1 25.7 2 18 2z"/><circle fill="#fff" cx="18" cy="15.2" r="5"/><circle fill="#e01e36" cx="18" cy="15.2" r="2.2"/></svg>';

                function npWarehouseMarkerDataUrl() {
                    return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(NP_WAREHOUSE_MARKER_SVG);
                }

                function npWarehousePopupHtml(row) {
                    return (
                        '<div class="np-map-wh-popup">' +
                        '<div class="np-map-wh-popup__card">' +
                        '<div class="np-map-wh-popup__accent" aria-hidden="true"></div>' +
                        '<button type="button" class="np-map-wh-popup__close" aria-label="' + (T.js_close || 'Закрити') + '">&times;</button>' +
                        '<div class="np-map-wh-popup__top">' +
                        '<span class="np-map-wh-popup__brand">' + (T.js_np_brand || 'Нова Пошта') + '</span>' +
                        '<span class="np-map-wh-popup__heading">' + (T.js_np_heading_branch || 'Відділення') + '</span>' +
                        '</div>' +
                        '<div class="np-map-wh-popup__body">' +
                        '<p class="np-map-wh-popup__name">' + npEscapeHtml(row.label || '') + '</p>' +
                        '</div>' +
                        '<p class="np-map-wh-popup__hint">' + (T.js_np_selected_hint || 'Обрано для замовлення') + '</p>' +
                        '</div>' +
                        '</div>'
                    );
                }

                function selectWarehouseByRef(ref) {
                    if (!whSelect || ref == null || ref === '') return;
                    var target = String(ref);
                    for (var i = 0; i < whSelect.options.length; i++) {
                        try {
                            var raw = whSelect.options[i].value;
                            if (!raw) continue;
                            var p = JSON.parse(raw);
                            if (p && String(p.ref) === target) {
                                whSelect.selectedIndex = i;
                                onWarehouseChange();
                                return;
                            }
                        } catch (e) {}
                    }
                }

                function updateWarehouseMarkers(rows) {
                    if (!cfg.npEnabled) return;
                    window.__npWarehouseRows = rows || [];
                    initMap();
                    if (!npMap) {
                        return;
                    }

                    if (cfg.useGoogleMaps) {
                        if (typeof google === 'undefined' || !google.maps) {
                            return;
                        }
                        if (npGoogleInfoWindow) {
                            npGoogleInfoWindow.close();
                            npGoogleInfoWindow = null;
                        }
                        npGoogleMarkers.forEach(function (m) { m.setMap(null); });
                        npGoogleMarkers = [];
                        npGoogleMarkerByRef = {};
                        var bounds = new google.maps.LatLngBounds();
                        var count = 0;
                        var lastPos = null;
                        (rows || []).forEach(function (row) {
                            var la = row.lat;
                            var ln = row.lng;
                            if (la == null || ln == null) return;
                            var pos = { lat: la, lng: ln };
                            lastPos = pos;
                            var marker = new google.maps.Marker({
                                position: pos,
                                map: npMap,
                                title: row.label || '',
                                cursor: 'pointer',
                                icon: {
                                    url: npWarehouseMarkerDataUrl(),
                                    scaledSize: new google.maps.Size(36, 46),
                                    anchor: new google.maps.Point(18, 46),
                                },
                                /* true інколи малює стандартний пін замість data: SVG */
                                optimized: false,
                            });
                            (function (r) {
                                marker.addListener('click', function () {
                                    selectWarehouseByRef(r.ref);
                                });
                            })(row);
                            npGoogleMarkers.push(marker);
                            npGoogleMarkerByRef[String(row.ref)] = marker;
                            bounds.extend(pos);
                            count++;
                        });
                        if (count === 1 && lastPos) {
                            npMap.setCenter(lastPos);
                            npMap.setZoom(14);
                        } else if (count > 1) {
                            npMap.fitBounds(bounds);
                            google.maps.event.addListenerOnce(npMap, 'idle', function () {
                                if (npMap.getZoom() > 15) npMap.setZoom(15);
                            });
                        } else if (npCityLat != null && npCityLng != null) {
                            npMap.setCenter({ lat: npCityLat, lng: npCityLng });
                            npMap.setZoom(11);
                        }
                        return;
                    }

                    if (!npMarkersLayer) return;
                    if (!npLeafletWarehouseIcon && typeof L !== 'undefined') {
                        npLeafletWarehouseIcon = L.icon({
                            iconUrl: npWarehouseMarkerDataUrl(),
                            iconSize: [36, 46],
                            iconAnchor: [18, 46],
                            popupAnchor: [0, -40],
                            className: 'np-map-leaflet-marker',
                        });
                    }
                    npMarkersLayer.clearLayers();
                    npLeafletMarkerByRef = {};
                    var bounds = [];
                    (rows || []).forEach(function (row) {
                        var la = row.lat;
                        var ln = row.lng;
                        if (la == null || ln == null) return;
                        var m = L.marker([la, ln], {
                            keyboard: true,
                            title: row.label || '',
                            icon: npLeafletWarehouseIcon || undefined,
                        });
                        m.bindPopup(npWarehousePopupHtml(row), {
                            maxWidth: 320,
                            className: 'np-map-popup-shell',
                        });
                        m.on('click', function () {
                            selectWarehouseByRef(row.ref);
                        });
                        npMarkersLayer.addLayer(m);
                        npLeafletMarkerByRef[String(row.ref)] = m;
                        bounds.push([la, ln]);
                    });
                    if (bounds.length === 1) {
                        npMap.setView(bounds[0], 14);
                    } else if (bounds.length > 1) {
                        npMap.fitBounds(bounds, { padding: [24, 24], maxZoom: 15 });
                    } else if (npCityLat != null && npCityLng != null) {
                        npMap.setView([npCityLat, npCityLng], 11);
                    }
                }

                /**
                 * Google panTo анімує лише коли зміщення менше за розмір вікна — інакше «телепорт».
                 * Інтерполюємо центр і зум самі, щоб перехід був плавним завжди.
                 */
                function googleSmoothMoveTo(map, endLat, endLng, endZoom, durationMs, done) {
                    var startC = map.getCenter();
                    var startLat = startC.lat();
                    var startLng = startC.lng();
                    var startZ = map.getZoom();
                    var tz = Math.round(endZoom);
                    var startTime = Date.now();
                    var lastZ = null;
                    function tick() {
                        var t = Math.min(1, (Date.now() - startTime) / durationMs);
                        var eased = 1 - Math.pow(1 - t, 3);
                        var lat = startLat + (endLat - startLat) * eased;
                        var lng = startLng + (endLng - startLng) * eased;
                        var z = Math.round(startZ + (tz - startZ) * eased);
                        map.setCenter({ lat: lat, lng: lng });
                        if (z !== lastZ) {
                            map.setZoom(z);
                            lastZ = z;
                        }
                        if (t < 1) {
                            requestAnimationFrame(tick);
                        } else {
                            map.setCenter({ lat: endLat, lng: endLng });
                            map.setZoom(tz);
                            if (typeof done === 'function') done();
                        }
                    }
                    requestAnimationFrame(tick);
                }

                function leafletSmoothMoveTo(map, endLat, endLng, endZoom, durationMs, done) {
                    var c = map.getCenter();
                    var startLat = c.lat;
                    var startLng = c.lng;
                    var startZ = map.getZoom();
                    var tz = Math.round(endZoom);
                    var startTime = Date.now();
                    function tick() {
                        var t = Math.min(1, (Date.now() - startTime) / durationMs);
                        var eased = 1 - Math.pow(1 - t, 3);
                        var lat = startLat + (endLat - startLat) * eased;
                        var lng = startLng + (endLng - startLng) * eased;
                        var z = Math.round(startZ + (tz - startZ) * eased);
                        map.setView([lat, lng], z, { animate: false });
                        if (t < 1) {
                            requestAnimationFrame(tick);
                        } else {
                            map.setView([endLat, endLng], tz, { animate: false });
                            if (typeof done === 'function') done();
                        }
                    }
                    requestAnimationFrame(tick);
                }

                function panToWarehouseRef(ref) {
                    if (!ref || !window.__npWarehouseRows) return;
                    var row = null;
                    for (var i = 0; i < window.__npWarehouseRows.length; i++) {
                        if (String(window.__npWarehouseRows[i].ref) === String(ref)) {
                            row = window.__npWarehouseRows[i];
                            break;
                        }
                    }
                    if (!row || row.lat == null || row.lng == null) return;
                    initMap();
                    if (!npMap) return;
                    var key = String(ref);
                    var moveMs = 680;
                    if (cfg.useGoogleMaps) {
                        if (typeof google === 'undefined' || !google.maps) return;
                        googleSmoothMoveTo(npMap, row.lat, row.lng, 15, moveMs, function () {
                            var gmk = npGoogleMarkerByRef[key];
                            if (gmk) {
                                if (!npGoogleInfoWindow) {
                                    npGoogleInfoWindow = new google.maps.InfoWindow({ maxWidth: 320 });
                                }
                                npGoogleInfoWindow.setContent(npWarehousePopupHtml(row));
                                npGoogleInfoWindow.open({ map: npMap, anchor: gmk });
                            }
                        });
                        return;
                    }
                    if (typeof npMap.stop === 'function') {
                        npMap.stop();
                    }
                    var lmk = npLeafletMarkerByRef[key];
                    leafletSmoothMoveTo(npMap, row.lat, row.lng, 15, moveMs, function () {
                        if (lmk && typeof lmk.openPopup === 'function') {
                            lmk.openPopup();
                            if (npMap && typeof npMap.invalidateSize === 'function') {
                                npMap.invalidateSize({ animate: false });
                            }
                        }
                    });
                }

                function syncDeliverySections() {
                    var v = sel && sel.value;
                    // Курʼєр і таксі малюють карту в різних контейнерах — при зміні способу будуємо її заново.
                    if (v !== lastSyncedDeliveryType) {
                        destroyCourierMap();
                    }
                    lastSyncedDeliveryType = v;
                    if (v !== cfg.pickup) {
                        destroyPickupMap();
                    }
                    if (pickupBlock) pickupBlock.hidden = v !== cfg.pickup;
                    if (taxiBlock) taxiBlock.hidden = v !== cfg.taxi;
                    if (v === cfg.pickup || v === cfg.npWh) {
                        var dla = document.getElementById('delivery_lat');
                        var dlng = document.getElementById('delivery_lng');
                        if (dla) dla.value = '';
                        if (dlng) dlng.value = '';
                    }
                    if (npBlock) {
                    if (cfg.npEnabled) {
                        npBlock.hidden = !(npWarehouseActive() || npCourierActive());
                        if (npManualCityBlock) npManualCityBlock.hidden = true;
                        if (npManualWarehouseBlock) npManualWarehouseBlock.hidden = true;
                        if (npManualCourierBlock) npManualCourierBlock.hidden = true;
                    } else {
                        npBlock.hidden = !(v === cfg.npWh || v === cfg.npCourier);
                        if (npManualCityBlock) npManualCityBlock.hidden = npBlock.hidden;
                        if (npManualWarehouseBlock) npManualWarehouseBlock.hidden = npBlock.hidden || v !== cfg.npWh;
                        if (npManualCourierBlock) npManualCourierBlock.hidden = npBlock.hidden || v !== cfg.npCourier;
                    }

                    var npCourierAddr = document.getElementById('np-courier-address-block');
                    var npMapCol = document.getElementById('np-map-column');
                    var npCourierMapCol = document.getElementById('np-courier-map-column');
                    var npWhWrap = document.getElementById('np-wh-block');
                    if (cfg.npEnabled && !npBlock.hidden) {
                        if (npCourierAddr) npCourierAddr.hidden = !npCourierActive();
                        if (npMapCol) npMapCol.hidden = !npWarehouseActive();
                        if (npCourierMapCol) npCourierMapCol.hidden = !npCourierActive();
                        if (npWhWrap) npWhWrap.hidden = !npWarehouseActive();
                    } else if (cfg.npEnabled) {
                        if (npCourierAddr) npCourierAddr.hidden = true;
                        if (npMapCol) npMapCol.hidden = true;
                        if (npCourierMapCol) npCourierMapCol.hidden = true;
                        if (npWhWrap) npWhWrap.hidden = true;
                    }

                    syncNpFieldLock();
                    if (!npBlock.hidden && cfg.npEnabled) {
                        ensureAreasLoaded(function () {
                            if (hasCityRef() && sel && (sel.value === cfg.npWh || sel.value === cfg.npCourier)) {
                                prefillFromCityRef();
                            }
                        });
                        setTimeout(function () {
                            refreshMapSize();
                        }, 80);
                    }

                    if (cfg.npEnabled && npCourierActive()) {
                        updateWarehouseMarkers([]);
                        if (whSelect) {
                            whSelect.innerHTML = '<option value="">' + (T.checkout_city_first || 'Спочатку оберіть місто…') + '</option>';
                            whSelect.disabled = true;
                        }
                        if (branchH) branchH.value = '';
                        if (whRefH) whRefH.value = '';
                    }

                    if (npCourierActive() || manualCourierActive()) {
                        setTimeout(function () {
                            initCourierMap();
                        }, 100);
                    }
                    }

                    var taxiMapCol = document.getElementById('taxi-map-column');
                    if (taxiMapCol) {
                        taxiMapCol.hidden = !taxiActive();
                    }
                    if (taxiActive()) {
                        setTimeout(function () {
                            initCourierMap();
                        }, 100);
                    }

                    var npCourierMapColManual = document.getElementById('np-courier-map-column-manual');
                    if (npCourierMapColManual) {
                        npCourierMapColManual.hidden = !manualCourierActive();
                    }

                    if (v === cfg.pickup && cfg.pickupHasMap) {
                        setTimeout(function () {
                            initPickupMap();
                            if (pickupMap && typeof pickupMap.invalidateSize === 'function') {
                                pickupMap.invalidateSize();
                            }
                        }, 120);
                    }

                    var deliveryMapCard = document.getElementById('checkout-delivery-map-card');
                    var deliveryRow = document.getElementById('checkout-delivery-row');
                    var pickupMapPanel = document.getElementById('pickup-map-panel');
                    if (pickupMapPanel) {
                        pickupMapPanel.hidden = !(v === cfg.pickup && cfg.pickupHasMap);
                    }
                    if (deliveryMapCard) {
                        var showDeliveryMaps = false;
                        if (v === cfg.pickup && cfg.pickupHasMap) {
                            showDeliveryMaps = true;
                        } else if (cfg.npEnabled && (v === cfg.npWh || v === cfg.npCourier)) {
                            showDeliveryMaps = true;
                        } else if (!cfg.npEnabled && v === cfg.npCourier) {
                            showDeliveryMaps = true;
                        } else if (v === cfg.taxi) {
                            showDeliveryMaps = true;
                        }
                        deliveryMapCard.hidden = !showDeliveryMaps;
                    }
                    if (deliveryRow) {
                        deliveryRow.classList.toggle('checkout-delivery-row--single', !!(deliveryMapCard && deliveryMapCard.hidden));
                    }
                }

                function loadWarehouses() {
                    if (!cfg.npEnabled || !whSelect || !cityRefIn || !hasCityRef()) {
                        return;
                    }
                    if (!npWarehouseActive()) {
                        return;
                    }
                    var url = cfg.warehousesUrl + '?city_ref=' + encodeURIComponent(cityRefIn.value.trim()) + '&kind=branch';
                    whSelect.innerHTML = '<option value="">' + (T.checkout_loading || 'Завантаження…') + '</option>';
                    whSelect.disabled = true;
                    fetch(url, { credentials: 'same-origin' })
                        .then(function (r) {
                            if (!r.ok) {
                                return Promise.reject(new Error('HTTP ' + r.status));
                            }
                            return r.json();
                        })
                        .then(function (j) {
                            var rows = (j && j.data) ? j.data : [];
                            whSelect.innerHTML = '<option value="">' + (T.js_select_warehouse || 'Оберіть відділення…') + '</option>';
                            rows.forEach(function (row) {
                                var opt = document.createElement('option');
                                opt.value = JSON.stringify({ ref: row.ref, label: row.label, lat: row.lat, lng: row.lng });
                                opt.textContent = row.label;
                                whSelect.appendChild(opt);
                            });
                            whSelect.disabled = false;
                            syncNpFieldLock();
                            updateWarehouseMarkers(rows);
                            if (npCityLat == null && cityNameIn && cityNameIn.value) {
                                centerMapOnCity({ label: cityNameIn.value, lat: null, lng: null });
                            }
                            if (branchH && branchH.value && whRefH) {
                                for (var i = 0; i < whSelect.options.length; i++) {
                                    try {
                                        var p = JSON.parse(whSelect.options[i].value);
                                        if (p && String(p.ref) === String(whRefH.value)) {
                                            whSelect.selectedIndex = i;
                                            break;
                                        }
                                    } catch (e) {}
                                }
                            }
                        })
                        .catch(function () {
                            whSelect.innerHTML = '<option value="">' + (T.js_warehouses_load_fail || 'Не вдалося завантажити відділення') + '</option>';
                            whSelect.disabled = false;
                            updateWarehouseMarkers([]);
                        });
                }

                function onWarehouseChange() {
                    if (!whSelect || !branchH || !whRefH) return;
                    var raw = whSelect.value;
                    if (!raw) {
                        branchH.value = '';
                        whRefH.value = '';
                        if (npGoogleInfoWindow) {
                            npGoogleInfoWindow.close();
                        }
                        if (npMap && typeof npMap.closePopup === 'function') {
                            npMap.closePopup();
                        }
                        return;
                    }
                    try {
                        var p = JSON.parse(raw);
                        branchH.value = p.label || '';
                        whRefH.value = p.ref || '';
                        panToWarehouseRef(p.ref);
                    } catch (e) {
                        branchH.value = '';
                        whRefH.value = '';
                    }
                }

                if (whSelect) whSelect.addEventListener('change', onWarehouseChange);

                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('.np-map-wh-popup__close');
                    if (!btn) return;
                    e.preventDefault();
                    e.stopPropagation();
                    var inCourier = !!(btn.closest('#courier-map') || btn.closest('#courier-map-manual'));
                    if (inCourier) {
                        if (cfg.useGoogleMaps && courierGoogleInfoWindow) {
                            courierGoogleInfoWindow.close();
                        }
                        if (courierLeafletMarker && typeof courierLeafletMarker.closePopup === 'function') {
                            courierLeafletMarker.closePopup();
                        }
                        return;
                    }
                    if (cfg.useGoogleMaps && npGoogleInfoWindow) {
                        npGoogleInfoWindow.close();
                        return;
                    }
                    if (npMap && typeof npMap.closePopup === 'function') {
                        npMap.closePopup();
                    }
                }, true);

                if (cfg.npEnabled && regionSelect) {
                    regionSelect.addEventListener('change', function () {
                        npAreaRef = regionSelect.value ? regionSelect.value.trim() : '';
                        syncNpFieldLock();
                        if (cityRefIn) cityRefIn.value = '';
                        if (cityNameIn) cityNameIn.value = '';
                        if (citySelect) {
                            if (npAreaRef) {
                                loadCitiesForArea(npAreaRef);
                            } else {
                                citySelect.innerHTML = '<option value="">' + (T.checkout_region_first || 'Спочатку оберіть область…') + '</option>';
                                citySelect.disabled = true;
                                syncNpFieldLock();
                            }
                        }
                        if (whSelect) {
                            whSelect.innerHTML = '<option value="">' + (T.checkout_city_first || 'Спочатку оберіть місто…') + '</option>';
                            whSelect.disabled = true;
                        }
                        if (branchH) branchH.value = '';
                        if (whRefH) whRefH.value = '';
                        updateWarehouseMarkers([]);
                        npCityLat = null;
                        npCityLng = null;
                        mapResetUkraine();
                    });
                }

                if (cfg.npEnabled && citySelect) {
                    citySelect.addEventListener('change', onCityChange);
                }

                if (sel) {
                    sel.addEventListener('change', function () {
                        syncDeliverySections();
                    });
                    syncDeliverySections();
                }

                var courierGeoBtn = document.getElementById('courier-geocode-btn');
                var courierGeoBtnManual = document.getElementById('courier-geocode-btn-manual');
                var taxiGeoBtn = document.getElementById('taxi-geocode-btn');
                if (taxiGeoBtn) {
                    taxiGeoBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        geocodeCourierAddress();
                    });
                }
                if (courierGeoBtn) {
                    courierGeoBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        geocodeCourierAddress();
                    });
                }
                if (courierGeoBtnManual) {
                    courierGeoBtnManual.addEventListener('click', function (e) {
                        e.preventDefault();
                        geocodeCourierAddress();
                    });
                }

                var checkoutForm = document.getElementById('checkout-form');
                if (checkoutForm) {
                    checkoutForm.addEventListener('submit', function () {
                        if (sel && sel.disabled) {
                            sel.disabled = false;
                        }
                        checkoutForm.querySelectorAll('input, select, textarea').forEach(function (el) {
                            if (el.closest('[hidden]')) el.disabled = true;
                        });
                    });
                }
            })();
        </script>
        <script>
            (function () {
                var form = document.querySelector('form.checkout-page__form');
                var button = form && form.querySelector('button[type="submit"]');
                if (!button) return;
                form.addEventListener('submit', function (event) {
                    setTimeout(function () {
                        if (event.defaultPrevented) return;
                        button.disabled = true;
                        button.setAttribute('aria-busy', 'true');
                    }, 0);
                });
                // Повернення кнопкою «Назад» відновлює сторінку з кешу разом із заблокованою кнопкою.
                window.addEventListener('pageshow', function () {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                });
            })();
        </script>
    @endpush
@endsection

@if (\App\Support\MarketingTags::enabled())
    @push('scripts')
        <script>
            window.zgTrack('begin_checkout', { currency: 'UAH', value: @json((float) $total) });
        </script>
    @endpush
@endif
