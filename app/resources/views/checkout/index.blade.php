@extends('layouts.shop')

@section('title', 'Оформлення замовлення — ZOOGLE')
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
    $categoryRequiresPickupOnly = $categoryRequiresPickupOnly ?? false;
    $categoryDefersOnlinePayment = $categoryDefersOnlinePayment ?? false;
    $pickupHasMap = isset($pickupDisplay['lat'], $pickupDisplay['lng'])
        && is_numeric($pickupDisplay['lat'])
        && is_numeric($pickupDisplay['lng']);
    $deliveryTypeCurrent = old('delivery_type', \App\Models\Order::DELIVERY_PICKUP);
    if ($categoryRequiresPickupOnly) {
        $deliveryTypeCurrent = \App\Models\Order::DELIVERY_PICKUP;
    }
    $showPickupMapPanel = $pickupHasMap && $deliveryTypeCurrent === \App\Models\Order::DELIVERY_PICKUP;
    $showNpWarehouseMap = $novaPoshtaConfigured && $deliveryTypeCurrent === \App\Models\Order::DELIVERY_NOVA_POSHTA_WAREHOUSE;
    $showNpCourierMapApi = $novaPoshtaConfigured && $deliveryTypeCurrent === \App\Models\Order::DELIVERY_NOVA_POSHTA_COURIER;
    $showNpCourierMapManual = ! $novaPoshtaConfigured && $deliveryTypeCurrent === \App\Models\Order::DELIVERY_NOVA_POSHTA_COURIER;
@endphp

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <style>
            .checkout-delivery-section {
                display: flex;
                flex-direction: column;
                gap: 14px;
                min-width: 0;
            }
            .checkout-delivery-row {
                display: flex;
                flex-wrap: nowrap;
                align-items: stretch;
                gap: 16px;
                min-height: 0;
            }
            .checkout-delivery-row > .checkout-delivery__card {
                flex: 1 1 0;
                min-width: 0;
                min-height: 0;
                align-self: stretch;
            }
            .checkout-delivery-row--single {
                flex-direction: column;
            }
            .checkout-delivery__card {
                display: flex;
                flex-direction: column;
                min-height: 0;
                min-width: 0;
            }
            .checkout-delivery__card--info {
                gap: 1.05rem;
            }
            .checkout-delivery__card--map {
                gap: 0;
                min-height: 0;
            }
            .checkout-delivery__card--map[hidden] {
                display: none !important;
            }
            .checkout-delivery__map-heading {
                flex: 0 0 auto;
                margin: 0 0 10px;
                font-size: 1.05rem;
                font-weight: 500;
                letter-spacing: -0.01em;
                color: #202124;
            }
            .checkout-delivery__maps-inner {
                display: flex;
                flex-direction: column;
                flex: 1 1 auto;
                gap: 0;
                min-height: 0;
                overflow: hidden;
            }
            /* Контейнер карти тягнеться на всю висоту блоку під заголовком */
            .checkout-delivery__card--map .np-checkout-map-col,
            .checkout-delivery__card--map .checkout-delivery__map-panel {
                display: flex;
                flex-direction: column;
                flex: 1 1 auto;
                min-height: 0;
                gap: 6px;
            }
            .checkout-delivery__card--map .np-map-caption {
                flex: 0 0 auto;
                margin-bottom: 0;
            }
            .checkout-delivery__card--map .np-map {
                flex: 1 1 auto;
                width: 100%;
                min-height: 0 !important;
                height: 100%;
            }
            .checkout-delivery__card--map .np-checkout-map-col > .muted {
                flex: 0 0 auto;
                margin-top: auto;
                padding-top: 8px;
            }
            /* display:flex на панелях перекриває нативне [hidden] — лишаємо одну видиму карту */
            .checkout-delivery__card--map .np-checkout-map-col[hidden],
            .checkout-delivery__card--map .checkout-delivery__map-panel[hidden] {
                display: none !important;
            }
            .checkout-delivery__main {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                min-width: 0;
            }
            .np-checkout-form-col {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            @media (max-width: 900px) {
                .checkout-delivery-row {
                    flex-direction: column;
                }
            }
            .np-map-caption {
                margin: 0 0 0.5rem;
                font-size: 0.92rem;
            }
            .np-map {
                width: 100%;
                min-height: 280px;
                border-radius: 10px;
                overflow: hidden;
                border: 1px solid rgba(0, 0, 0, 0.08);
                background: #e8eef2;
                /* Не віддавати drag/touch скролу батьківській сторінці (Leaflet/Google). */
                touch-action: none;
                overscroll-behavior: contain;
            }
            /* Попап відділення (Google InfoWindow + Leaflet) */
            .np-map-wh-popup {
                min-width: 0;
                max-width: 292px;
                margin: 0;
                padding: 0;
                font-family: inherit;
                text-align: left;
                -webkit-font-smoothing: antialiased;
            }
            .np-map-wh-popup__card {
                position: relative;
                padding: 14px 42px 14px 16px;
                border-radius: 14px;
                background: linear-gradient(160deg, #ffffff 0%, #f1f5f9 52%, #eef2f7 100%);
                border: 1px solid rgba(15, 23, 42, 0.07);
                box-shadow:
                    inset 0 1px 0 rgba(255, 255, 255, 0.95),
                    0 12px 36px rgba(15, 23, 42, 0.12),
                    0 4px 10px rgba(15, 23, 42, 0.06);
            }
            .np-map-wh-popup__close {
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 3;
                width: 30px;
                height: 30px;
                margin: 0;
                padding: 0;
                border: none;
                border-radius: 8px;
                background: rgba(15, 23, 42, 0.07);
                color: #475569;
                font-size: 1.25rem;
                line-height: 1;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: background 0.15s ease, color 0.15s ease;
            }
            .np-map-wh-popup__close:hover {
                background: rgba(224, 30, 54, 0.14);
                color: #b81428;
            }
            .np-map-wh-popup__close:focus-visible {
                outline: 2px solid rgba(224, 30, 54, 0.45);
                outline-offset: 2px;
            }
            .np-map-wh-popup__accent {
                position: absolute;
                left: 0;
                top: 12px;
                bottom: 12px;
                width: 4px;
                border-radius: 0 5px 5px 0;
                background: linear-gradient(180deg, #ff3b52 0%, #d41028 55%, #9f0c1f 100%);
                box-shadow: 1px 0 8px rgba(224, 30, 54, 0.35);
            }
            .np-map-wh-popup__top {
                display: flex;
                flex-wrap: wrap;
                align-items: baseline;
                gap: 6px 10px;
                margin: 0 0 10px;
                padding-left: 8px;
            }
            .np-map-wh-popup__brand {
                display: inline-flex;
                align-items: center;
                padding: 3px 8px 3px 7px;
                border-radius: 6px;
                font-size: 0.62rem;
                font-weight: 800;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                color: #fff;
                background: linear-gradient(135deg, #e01e36 0%, #b81428 100%);
                box-shadow: 0 2px 6px rgba(224, 30, 54, 0.35);
            }
            .np-map-wh-popup__heading {
                font-size: 0.75rem;
                font-weight: 600;
                color: #64748b;
                letter-spacing: 0.02em;
            }
            .np-map-wh-popup__body {
                margin: 0;
                padding: 11px 12px 12px;
                padding-left: 12px;
                margin-left: 8px;
                border-radius: 10px;
                background: rgba(255, 255, 255, 0.72);
                border: 1px solid rgba(15, 23, 42, 0.06);
                box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8) inset;
            }
            .np-map-wh-popup__name {
                font-size: 0.9rem;
                font-weight: 600;
                color: #0f172a;
                margin: 0;
                line-height: 1.5;
                letter-spacing: -0.01em;
            }
            .np-map-wh-popup__hint {
                margin: 10px 0 0;
                padding-left: 8px;
                font-size: 0.68rem;
                font-weight: 600;
                color: #94a3b8;
                letter-spacing: 0.02em;
            }
            /* Google Maps — без другої «прозорої» рамки: тінь лише на .np-map-wh-popup__card; штатний X ховаємо */
            .np-map .gm-style .gm-style-iw-c {
                padding: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                border: none !important;
                background: transparent !important;
            }
            .np-map .gm-style .gm-style-iw-d {
                overflow: visible !important;
                max-height: none !important;
                padding: 0 !important;
            }
            .np-map .gm-style .gm-style-iw-t::after {
                background: transparent !important;
            }
            .np-map .gm-style .gm-ui-hover-effect,
            .np-map .gm-style .gm-style-iw-tc button,
            .np-map .gm-style .gm-style-iw-c > button {
                display: none !important;
            }
            /* Google Maps: нижній ряд атрибуції (дані карт, умови, «повідомити…»). Класи API інколи оновлюються. */
            .np-map .gm-style-cc,
            .np-map .gm-style a[href*='maps.google.com/maps/attribution'] {
                display: none !important;
            }
            /* Leaflet — оболонка попапу */
            .np-map-popup-shell .leaflet-popup-content-wrapper {
                border-radius: 0;
                padding: 0;
                overflow: visible;
                box-shadow: none;
                border: none;
                background: transparent;
            }
            .np-map-popup-shell .leaflet-popup-content {
                margin: 0;
                min-width: 220px;
                width: max-content;
                max-width: min(292px, calc(100vw - 48px));
            }
            .np-map-popup-shell .leaflet-popup-close-button {
                display: none !important;
            }
            .np-map-popup-shell .leaflet-popup-tip {
                box-shadow: 0 4px 14px rgba(15, 23, 42, 0.1);
                border: 1px solid rgba(15, 23, 42, 0.06);
            }
            .np-map .np-map-leaflet-marker.leaflet-marker-icon {
                filter: drop-shadow(0 2px 5px rgba(15, 23, 42, 0.25));
            }
            /* .np-delivery задає display:flex — без цього [hidden] не ховає блок Нової Пошти при самовивозі */
            .np-delivery[hidden],
            .pickup-delivery[hidden] {
                display: none !important;
            }
            .checkout-payment-buttons {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin: 0;
                padding: 0;
            }
            @media (max-width: 520px) {
                .checkout-payment-buttons {
                    grid-template-columns: 1fr;
                }

                .np-map {
                    min-height: 220px;
                    border-radius: 14px;
                }

                .np-map-wh-popup {
                    max-width: min(292px, calc(100vw - 44px));
                }

                .np-map-wh-popup__card {
                    padding: 12px 38px 12px 14px;
                    border-radius: 14px;
                }

                .checkout-payment-btn .checkout-payment-btn__inner {
                    min-height: 46px;
                    border-radius: 12px;
                }
            }
            .checkout-payment-btn {
                display: block;
                margin: 0;
                cursor: pointer;
            }
            .checkout-payment-btn .checkout-payment-btn__inner {
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
                min-height: 48px;
                padding: 12px 16px;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 500;
                line-height: 1.4;
                color: #202124;
                background: #fff;
                border: 1px solid #dadce0;
                box-shadow: none;
                transition:
                    border-color 0.15s ease,
                    background 0.15s ease,
                    box-shadow 0.15s ease;
            }
            .checkout-payment-btn:hover .checkout-payment-btn__inner {
                border-color: #bdc1c6;
                background: #f8f9fa;
            }
            .checkout-payment-btn:has(:checked) .checkout-payment-btn__inner {
                border-color: #1a73e8;
                background: #e8f0fe;
                color: #1967d2;
                box-shadow: 0 0 0 1px rgba(26, 115, 232, 0.35);
            }
            .checkout-payment-btn:has(:focus-visible) .checkout-payment-btn__inner {
                outline: 2px solid #1a73e8;
                outline-offset: 2px;
            }
            .checkout-payment-btn--readonly {
                cursor: default;
            }
            .checkout-payment-btn--readonly .checkout-payment-btn__inner {
                border-color: #1a73e8;
                background: #e8f0fe;
                color: #1967d2;
                box-shadow: 0 0 0 1px rgba(26, 115, 232, 0.25);
            }
            .np-courier-geocode-btn {
                margin-top: 0.55rem;
                align-self: flex-start;
            }
            .checkout-page {
                width: min(72vw, 1120px);
                max-width: none;
                margin: clamp(6px, 1vw, 14px) auto clamp(18px, 3vw, 36px);
                color: #202124;
            }
            .checkout-page,
            .checkout-page * {
                font-family: inherit;
            }
            .checkout-page__toolbar {
                position: relative;
                overflow: hidden;
                margin-bottom: clamp(14px, 2vw, 24px);
                padding: clamp(20px, 2.8vw, 34px);
                border-radius: 20px;
                border: 1px solid #e8eaed;
                background:
                    radial-gradient(circle at 8% 0%, rgba(54, 125, 241, 0.16), transparent 34%),
                    radial-gradient(circle at 92% 20%, rgba(51, 154, 57, 0.14), transparent 32%),
                    linear-gradient(180deg, #fff 0%, #f8fbff 100%);
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            }
            .checkout-page__toolbar::after {
                content: "";
                position: absolute;
                right: clamp(18px, 3vw, 40px);
                bottom: clamp(14px, 2vw, 28px);
                width: clamp(74px, 9vw, 128px);
                aspect-ratio: 1;
                border-radius: 999px;
                background:
                    linear-gradient(135deg, rgba(239, 56, 41, 0.9), rgba(252, 181, 1, 0.85));
                opacity: 0.14;
                filter: blur(1px);
                pointer-events: none;
            }
            .checkout-page__title {
                position: relative;
                z-index: 1;
                margin: 0 0 10px;
                color: #367df1;
                font-size: clamp(1.7rem, 3vw, 2.35rem);
                font-weight: 700;
                line-height: 1.15;
                letter-spacing: -0.035em;
            }
            .checkout-page__lead {
                position: relative;
                z-index: 1;
                max-width: 52rem;
                color: #5f6368;
                font-size: clamp(0.92rem, 1.15vw, 1.02rem);
                line-height: 1.55;
            }
            .checkout-page .card,
            .checkout-delivery-section {
                border-radius: 20px;
                border: 1px solid #e8eaed;
                background: #fff;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
                transition:
                    transform .32s cubic-bezier(0.22, 1, 0.36, 1),
                    box-shadow .32s cubic-bezier(0.22, 1, 0.36, 1),
                    border-color .32s ease;
                will-change: transform, box-shadow;
            }
            .checkout-page .card {
                padding: clamp(18px, 2.35vw, 30px);
            }
            .checkout-delivery-section {
                padding: clamp(18px, 2.35vw, 30px);
                margin: 0;
            }
            @media (hover: hover) and (pointer: fine) {
                .checkout-page .card:hover,
                .checkout-delivery-section:hover,
                .checkout-page__toolbar:hover {
                    transform: translateY(-3px);
                    border-color: #dbe4ee;
                    box-shadow: 0 18px 42px rgba(15, 23, 42, 0.13);
                }
            }
            .checkout-page__section-heading,
            .checkout-delivery-section > .checkout-page__section-heading {
                position: relative;
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 0 0 18px;
                padding: 0 0 14px;
                border-bottom: 1px solid #eef2f7;
                color: #202124;
                font-size: clamp(1.12rem, 1.55vw, 1.35rem);
                font-weight: 600;
                letter-spacing: -0.015em;
            }
            .checkout-page__section-heading::before {
                content: "";
                width: 11px;
                height: 11px;
                border-radius: 999px;
                background: #367df1;
                box-shadow: 0 0 0 6px rgba(54, 125, 241, 0.12);
                flex: 0 0 auto;
            }
            .checkout-page label {
                margin-bottom: 8px;
                color: #475467;
                font-size: 0.84rem;
                font-weight: 600;
            }
            .checkout-page input,
            .checkout-page select,
            .checkout-page textarea {
                min-height: 48px;
                border-radius: 20px;
                border-color: #e4e7ec;
                background: linear-gradient(180deg, #fff 0%, #fbfdff 100%);
                color: #202124;
                font-family: inherit;
                font-size: 0.95rem;
                font-weight: 500;
                letter-spacing: -0.005em;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
                transition:
                    transform .2s cubic-bezier(0.22, 1, 0.36, 1),
                    border-color .18s ease,
                    box-shadow .22s cubic-bezier(0.22, 1, 0.36, 1),
                    background-color .18s ease;
            }
            .checkout-page textarea {
                font-weight: 400;
                line-height: 1.55;
                border-radius: 20px;
            }
            .checkout-page select {
                font-weight: 600;
            }
            .checkout-page input:hover,
            .checkout-page select:hover,
            .checkout-page textarea:hover {
                border-color: rgba(54, 125, 241, 0.35);
                box-shadow:
                    0 8px 20px rgba(15, 23, 42, 0.08),
                    0 0 0 4px rgba(54, 125, 241, 0.05);
            }
            .checkout-page input:focus,
            .checkout-page select:focus,
            .checkout-page textarea:focus {
                border-color: #367df1;
                box-shadow:
                    0 10px 24px rgba(54, 125, 241, 0.12),
                    0 0 0 4px rgba(54, 125, 241, 0.16);
            }
            .checkout-page__form--blocks {
                gap: clamp(14px, 1.8vw, 22px);
            }
            .checkout-contact-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 16px 20px;
            }
            @media (max-width: 980px) {
                .checkout-contact-grid {
                    grid-template-columns: 1fr;
                }
            }
            .checkout-page .cart-drawer__lines {
                gap: 12px;
            }
            .checkout-page .cart-drawer__line--checkout {
                border-radius: 20px;
                border-color: #eef2f7;
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
                transition: transform .24s cubic-bezier(0.22, 1, 0.36, 1), box-shadow .24s ease;
            }
            .checkout-page .cart-drawer__line--checkout:hover {
                transform: translateY(-2px);
                box-shadow: 0 14px 28px rgba(15, 23, 42, 0.1);
            }
            .cart-drawer__checkout-qty {
                border-radius: 20px;
                background: #f8fbff;
                color: #367df1;
                border-color: rgba(54, 125, 241, 0.2);
                box-shadow: 0 5px 14px rgba(54, 125, 241, 0.08);
            }
            .checkout-page__total {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                margin-top: 20px;
                padding: 16px 18px 0;
                color: #339a39;
                font-size: clamp(1.15rem, 2vw, 1.55rem);
                font-weight: 700;
            }
            .np-map {
                border-radius: 20px;
                border-color: #e4e7ec;
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.1);
            }
            .checkout-delivery__map-heading {
                color: #367df1;
                font-weight: 600;
            }
            .checkout-payment-btn .checkout-payment-btn__inner {
                min-height: 54px;
                border-radius: 20px;
                border-color: #e4e7ec;
                font-weight: 600;
                box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
                transition:
                    transform .26s cubic-bezier(0.22, 1, 0.36, 1),
                    border-color .18s ease,
                    background .18s ease,
                    color .18s ease,
                    box-shadow .26s cubic-bezier(0.22, 1, 0.36, 1);
            }
            .checkout-payment-btn:hover .checkout-payment-btn__inner {
                transform: translateY(-3px) scale(1.015);
                border-color: rgba(54, 125, 241, 0.35);
                background: #f8fbff;
                box-shadow:
                    0 14px 28px rgba(15, 23, 42, 0.12),
                    0 0 0 4px rgba(54, 125, 241, 0.08);
            }
            .checkout-payment-btn:has(:checked) .checkout-payment-btn__inner,
            .checkout-payment-btn--readonly .checkout-payment-btn__inner {
                border-color: #367df1;
                background: #eaf2ff;
                color: #1d5fd6;
                box-shadow:
                    0 14px 30px rgba(54, 125, 241, 0.2),
                    0 0 0 4px rgba(54, 125, 241, 0.14);
            }
            .checkout-page .btn.secondary,
            .np-courier-geocode-btn {
                border-radius: 20px;
                box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
            }
            .checkout-page__submit {
                margin-top: 0;
                padding-top: 2px;
            }
            .checkout-page__submit .btn-buy {
                min-height: 56px;
                border-radius: 20px;
                background: #ef3829;
                font-size: 1.05rem;
                font-weight: 700;
                box-shadow: 0 10px 26px rgba(239, 56, 41, 0.32);
            }
            .checkout-page__submit .btn-buy:hover {
                transform: translateY(-2px);
                background: #d62f22;
                box-shadow: 0 16px 34px rgba(239, 56, 41, 0.4);
            }
            @media (prefers-reduced-motion: reduce) {
                .checkout-page .card,
                .checkout-delivery-section,
                .checkout-page__toolbar,
                .checkout-page input,
                .checkout-page select,
                .checkout-page textarea,
                .checkout-payment-btn .checkout-payment-btn__inner,
                .checkout-page .cart-drawer__line--checkout {
                    transition: box-shadow .18s ease, border-color .18s ease;
                    will-change: auto;
                }
                .checkout-page .card:hover,
                .checkout-delivery-section:hover,
                .checkout-page__toolbar:hover,
                .checkout-page .cart-drawer__line--checkout:hover,
                .checkout-payment-btn:hover .checkout-payment-btn__inner,
                .checkout-page__submit .btn-buy:hover {
                    transform: none;
                }
            }
            @media (max-width: 768px) {
                .checkout-page {
                    width: 100%;
                    margin-top: 0;
                }
                .checkout-page__toolbar,
                .checkout-page .card,
                .checkout-delivery-section {
                    border-radius: 18px;
                }
                .checkout-page__toolbar,
                .checkout-page .card,
                .checkout-delivery-section {
                    padding: 16px;
                }
                .checkout-payment-btn .checkout-payment-btn__inner,
                .checkout-page input,
                .checkout-page select,
                .checkout-page textarea,
                .checkout-page__submit .btn-buy,
                .np-map {
                    border-radius: 18px;
                }
            }
    </style>
@endpush

@section('content')
    <div class="checkout-page">
        <header class="checkout-page__toolbar">
            <h1 class="checkout-page__title">Оформлення замовлення</h1>
            <p class="checkout-page__lead">Перевірте склад замовлення та вкажіть контакт, доставку та спосіб оплати.</p>
        </header>

        <section class="card" aria-labelledby="checkout-items-heading">
            <h2 id="checkout-items-heading" class="checkout-page__section-heading">Ваше замовлення</h2>
            <div class="cart-drawer__lines">
                @foreach ($items as $item)
                    @include('cart.partials.line-checkout', ['item' => $item])
                @endforeach
            </div>
            <p class="checkout-page__total">Разом: {{ number_format((float) $total, 2, ',', ' ') }} ₴</p>
        </section>

        <form class="checkout-page__form checkout-page__form--blocks" method="POST" action="{{ route('checkout.store') }}" id="checkout-form">
                @csrf

        <section class="card" aria-labelledby="checkout-contact-heading">
            <h2 id="checkout-contact-heading" class="checkout-page__section-heading">Контактна інформація</h2>
                <div class="checkout-contact-grid">
                    <div>
                        <label for="customer_name">Прізвище та імʼя</label>
                        <input id="customer_name" name="customer_name" value="{{ old('customer_name', $checkoutPrefill['customer_name'] ?? '') }}" required autocomplete="name">
                        @error('customer_name') <p class="alert error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="customer_phone">Телефон</label>
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
            <h2 id="checkout-delivery-heading" class="checkout-page__section-heading">Доставка</h2>
            @if ($categoryRequiresPickupOnly)
                <p class="muted" style="margin:0 0 0.75rem;line-height:1.5;font-size:0.95rem;">
                    У кошику є товари з категорії, яку можна отримати лише <strong>самовивозом з магазину</strong> (відправка Новою Поштою недоступна).
                </p>
            @endif

            <div class="checkout-delivery-row" id="checkout-delivery-row">
                <section class="card checkout-delivery__card checkout-delivery__card--info" aria-label="Спосіб доставки та адреса">
                <div class="checkout-delivery__main">
                    <div>
                        <label for="delivery_type">Спосіб доставки</label>
                        @php($dt = $deliveryTypeCurrent)
                        <select id="delivery_type" name="delivery_type" required @if($categoryRequiresPickupOnly) class="checkout-delivery-type--locked" aria-readonly="true" @endif>
                            <option value="{{ \App\Models\Order::DELIVERY_PICKUP }}" @selected($dt === \App\Models\Order::DELIVERY_PICKUP)>Самовивіз</option>
                            @unless ($categoryRequiresPickupOnly)
                                <option value="{{ \App\Models\Order::DELIVERY_NOVA_POSHTA_WAREHOUSE }}" @selected($dt === \App\Models\Order::DELIVERY_NOVA_POSHTA_WAREHOUSE)>Нова Пошта — відділення</option>
                                <option value="{{ \App\Models\Order::DELIVERY_NOVA_POSHTA_COURIER }}" @selected($dt === \App\Models\Order::DELIVERY_NOVA_POSHTA_COURIER)>Нова Пошта — кур’єр до адреси</option>
                            @endunless
                        </select>
                        @error('delivery_type') <p class="alert error">{{ $message }}</p> @enderror
                    </div>

                    <div id="pickup-block" class="pickup-delivery" hidden>
                        <div class="np-field">
                            <p style="margin:0 0 0.35rem;font-weight:700;">Самовивіз</p>
                            @if (filled($pickupDisplay['address'] ?? null))
                                <p style="margin:0;line-height:1.5;">{{ $pickupDisplay['address'] }}</p>
                            @else
                                <p class="muted" style="margin:0;line-height:1.5;">Точну адресу пункту видачі повідомить менеджер після оформлення.</p>
                            @endif
                        </div>
                    </div>

                    <div id="np-block" class="np-delivery" hidden>
                        @unless ($novaPoshtaConfigured)
                            <p class="muted" style="margin:0 0 1rem;font-size:0.92rem;line-height:1.5;">
                                Автоматичний список міст і відділень з Нової Пошти працює лише з ключем API (НП видає його безкоштовно в особистому кабінеті на novaposhta.ua — це не обов’язково «бізнес-кабінет»).
                                <strong>Якщо ключа немає</strong>, введіть місто та відділення або адресу кур’єра вручну — цього достатньо для доставки.
                            </p>
                        @endunless

                        @if ($novaPoshtaConfigured)
                            <div class="np-checkout-form-col">
                                <div class="np-field">
                                    <label for="np_region_select">Область</label>
                                    <select id="np_region_select" autocomplete="address-level1">
                                        <option value="">Завантаження…</option>
                                    </select>
                                </div>
                                <div class="np-field">
                                    <label for="np_city_select">Місто</label>
                                    <select id="np_city_select" autocomplete="address-level2" disabled>
                                        <option value="">Спочатку оберіть область…</option>
                                    </select>
                                    <input type="hidden" name="delivery_city" id="delivery_city" value="{{ old('delivery_city') }}">
                                    <input type="hidden" name="delivery_city_ref" id="delivery_city_ref" value="{{ old('delivery_city_ref') }}">
                                    @error('delivery_city') <p class="alert error">{{ $message }}</p> @enderror
                                    @error('delivery_city_ref') <p class="alert error">{{ $message }}</p> @enderror
                                </div>

                                <div id="np-wh-block" class="np-field">
                                    <label for="np_warehouse_select">Відділення</label>
                                    <select id="np_warehouse_select">
                                        <option value="">Спочатку оберіть місто…</option>
                                    </select>
                                    <input type="hidden" name="delivery_branch" id="delivery_branch" value="{{ old('delivery_branch') }}">
                                    <input type="hidden" name="delivery_warehouse_ref" id="delivery_warehouse_ref" value="{{ old('delivery_warehouse_ref') }}">
                                    @error('delivery_branch') <p class="alert error">{{ $message }}</p> @enderror
                                    @error('delivery_warehouse_ref') <p class="alert error">{{ $message }}</p> @enderror
                                </div>

                                <div id="np-courier-address-block" class="np-field" hidden>
                                    <label for="delivery_address_courier">Адреса доставки <span class="muted">(вулиця, будинок, під’їзд, поверх)</span></label>
                                    <textarea id="delivery_address_courier" name="delivery_address" rows="3" placeholder="Наприклад: вул. Хрещатик, 1, під’їзд 2, кв. 5">{{ old('delivery_address') }}</textarea>
                                    <button type="button" class="btn secondary np-courier-geocode-btn" id="courier-geocode-btn">Знайти за адресою</button>
                                    @error('delivery_address') <p class="alert error">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @else
                            <div id="np-manual-city-block" class="np-field" hidden>
                                <label for="delivery_city_manual">Місто <span class="muted">(обов’язково)</span></label>
                                <input type="text" name="delivery_city" id="delivery_city_manual" value="{{ old('delivery_city') }}" placeholder="Місто">
                                <input type="hidden" name="delivery_city_ref" id="delivery_city_ref_manual" value="{{ old('delivery_city_ref') }}">
                                @error('delivery_city') <p class="alert error">{{ $message }}</p> @enderror
                                @error('delivery_city_ref') <p class="alert error">{{ $message }}</p> @enderror
                            </div>

                            <div id="np-manual-warehouse-block" class="np-field" hidden>
                                <label for="delivery_branch_manual">Відділення <span class="muted">(обов’язково)</span></label>
                                <input type="text" name="delivery_branch" id="delivery_branch_manual" value="{{ old('delivery_branch') }}" placeholder="Наприклад: відділення № 12, вул. Хрещатик, 1">
                                <input type="hidden" name="delivery_warehouse_ref" id="delivery_warehouse_ref_manual" value="{{ old('delivery_warehouse_ref') }}">
                                @error('delivery_branch') <p class="alert error">{{ $message }}</p> @enderror
                                @error('delivery_warehouse_ref') <p class="alert error">{{ $message }}</p> @enderror
                            </div>

                            <div id="np-manual-courier-block" hidden>
                                <div class="np-field">
                                    <label for="delivery_address_manual">Адреса доставки кур’єром <span class="muted">(обов’язково)</span></label>
                                    <textarea name="delivery_address" id="delivery_address_manual" rows="3" placeholder="Вулиця, будинок, під’їзд, поверх">{{ old('delivery_address') }}</textarea>
                                    <button type="button" class="btn secondary np-courier-geocode-btn" id="courier-geocode-btn-manual">Знайти за адресою</button>
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
                    <label for="customer_notes">Примітки до доставки</label>
                    <textarea id="customer_notes" name="customer_notes" rows="2">{{ old('customer_notes') }}</textarea>
                    @error('customer_notes') <p class="alert error">{{ $message }}</p> @enderror
                </div>
                </section>

                <section id="checkout-delivery-map-card" class="card checkout-delivery__card checkout-delivery__card--map" aria-labelledby="checkout-delivery-map-title" hidden>
                    <h3 id="checkout-delivery-map-title" class="checkout-delivery__map-heading">Карта доставки</h3>
                    <div id="checkout-delivery-map-column" class="checkout-delivery__maps-inner">
                    @if ($pickupHasMap)
                        <div id="pickup-map-panel" class="checkout-delivery__map-panel np-checkout-map-col" @unless($showPickupMapPanel) hidden @endunless>
                            <p class="np-map-caption muted">Як нас знайти</p>
                            <div id="pickup-map" class="np-map" role="region" aria-label="Карта пункту самовивозу"></div>
                        </div>
                    @endif

                    @if ($novaPoshtaConfigured)
                        <div id="np-map-column" class="np-checkout-map-col" @unless($showNpWarehouseMap) hidden @endunless>
                            <p class="np-map-caption muted">Відділення Нової Пошти на карті</p>
                            <div id="np-map" class="np-map" role="region" aria-label="Карта відділень Нової Пошти"></div>
                        </div>
                        <div id="np-courier-map-column" class="np-checkout-map-col" @unless($showNpCourierMapApi) hidden @endunless>
                            <p class="np-map-caption muted">Адреса доставки на карті</p>
                            <div id="courier-map" class="np-map" role="region" aria-label="Карта адреси кур’єра"></div>
                            <p class="muted" style="font-size:0.86rem;line-height:1.45;margin:0.5rem 0 0;">Перетягніть мітку або клацніть по карті. Пошук за текстом — кнопка ліворуч від карти.</p>
                        </div>
                    @endif
                    @unless ($novaPoshtaConfigured)
                        <div id="np-courier-map-column-manual" class="np-checkout-map-col" @unless($showNpCourierMapManual) hidden @endunless>
                            <p class="np-map-caption muted">Адреса на карті</p>
                            <div id="courier-map-manual" class="np-map" role="region" aria-label="Карта адреси кур’єра"></div>
                            <p class="muted" style="font-size:0.86rem;line-height:1.45;margin:0.5rem 0 0;">Перетягніть мітку або клацніть по карті. Пошук за текстом — кнопка ліворуч від карти.</p>
                        </div>
                    @endunless
                    </div>
                </section>
            </div>
        </div>

        <section class="card" aria-labelledby="checkout-payment-heading">
            <h2 id="checkout-payment-heading" class="checkout-page__section-heading">Спосіб оплати</h2>
            @if ($checkoutPaymentIsMixed)
                <p class="muted" style="margin:0 0 0.75rem;line-height:1.5;font-size:0.95rem;">
                    У замовленні є позиції з оплатою «зараз» та позиції (наприклад, тварини), для яких онлайн-доплата буде <strong>після узгодження з менеджером</strong>.
                    Якщо обираєте картку зараз — списується лише сума за частину без відкладеної категорії; решту можна сплатити онлайн після дозволу або накладним платежем за домовленістю.
                </p>
            @elseif ($categoryDefersOnlinePayment)
                <p class="muted" style="margin:0 0 0.75rem;line-height:1.5;font-size:0.95rem;">
                    Онлайн-оплата карткою для цього замовлення буде доступна <strong>після узгодження з менеджером</strong> (зв’яжіться з нами або очікуйте дзвінок). Зараз можна обрати оплату при отриманні в магазині.
                </p>
            @endif
                @if ($allowsOnlineAtCheckout)
                    <div class="checkout-payment-buttons" role="radiogroup" aria-labelledby="checkout-payment-heading">
                        <label class="checkout-payment-btn">
                            <input class="visually-hidden" type="radio" name="payment_method" value="cod" @checked($pmOld === 'cod') required>
                            <span class="checkout-payment-btn__inner">При отриманні</span>
                        </label>
                        <label class="checkout-payment-btn">
                            <input class="visually-hidden" type="radio" name="payment_method" value="online" @checked($pmOld === 'online')>
                            <span class="checkout-payment-btn__inner">Карткою онлайн</span>
                        </label>
                    </div>
                @elseif ($liqPayConfigured && $categoryDefersOnlinePayment && ! $checkoutPaymentIsMixed)
                    <input type="hidden" name="payment_method" value="cod">
                    <div class="checkout-payment-buttons" aria-labelledby="checkout-payment-heading">
                        <div class="checkout-payment-btn checkout-payment-btn--readonly" aria-current="true">
                            <span class="checkout-payment-btn__inner">При отриманні</span>
                        </div>
                    </div>
                @else
                    <input type="hidden" name="payment_method" value="cod">
                    <div class="checkout-payment-buttons" aria-labelledby="checkout-payment-heading">
                        <div class="checkout-payment-btn checkout-payment-btn--readonly" aria-current="true">
                            <span class="checkout-payment-btn__inner">При отриманні</span>
                        </div>
                    </div>
                    <p class="muted" style="margin:0.75rem 0 0;line-height:1.5;font-size:0.92rem;">Онлайн-оплата з’явиться після підключення в Інтеграціях (LiqPay або WayForPay).</p>
                @endif
                @error('payment_method') <p class="alert error">{{ $message }}</p> @enderror
        </section>

        <section class="card" aria-labelledby="checkout-comment-heading">
            <h2 id="checkout-comment-heading" class="checkout-page__section-heading">Коментар до замовлення</h2>
                <div>
                    <label for="comment">Коментар</label>
                    <textarea id="comment" name="comment" rows="4">{{ old('comment') }}</textarea>
                    @error('comment') <p class="alert error">{{ $message }}</p> @enderror
                </div>
        </section>

                <div class="checkout-page__submit">
                    <button class="btn btn-buy" type="submit">Підтвердити замовлення</button>
                </div>
        </form>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
        <script>
            (function () {
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
                    pickupHasMap: {{ $pickupHasMap ? 'true' : 'false' }},
                    pickupLat: @json($pickupHasMap ? (float) $pickupDisplay['lat'] : null),
                    pickupLng: @json($pickupHasMap ? (float) $pickupDisplay['lng'] : null),
                    categoryRequiresPickupOnly: {{ $categoryRequiresPickupOnly ? 'true' : 'false' }},
                };

                var sel = document.getElementById('delivery_type');
                if (cfg.categoryRequiresPickupOnly && sel) {
                    sel.value = cfg.pickup;
                    sel.disabled = true;
                }
                var npBlock = document.getElementById('np-block');
                var pickupBlock = document.getElementById('pickup-block');
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

                function getCourierAddressTextarea() {
                    return document.getElementById('delivery_address_courier') || document.getElementById('delivery_address_manual');
                }

                function buildCourierGeocodeQuery() {
                    var city = '';
                    if (cityNameIn && cityNameIn.value) city = cityNameIn.value.trim();
                    var mc = document.getElementById('delivery_city_manual');
                    if ((!city || city.length < 1) && mc && mc.value) city = mc.value.trim();
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
                        '<button type="button" class="np-map-wh-popup__close" aria-label="Закрити">&times;</button>' +
                        '<div class="np-map-wh-popup__top">' +
                        '<span class="np-map-wh-popup__brand">Доставка</span>' +
                        '<span class="np-map-wh-popup__heading">Адреса на карті</span>' +
                        '</div>' +
                        '<div class="np-map-wh-popup__body">' +
                        '<p class="np-map-wh-popup__name">' + npEscapeHtml(body) + '</p>' +
                        '</div>' +
                        '<p class="np-map-wh-popup__hint">Перетягніть мітку або клацніть на карті</p>' +
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
                                    courierOpenMapPopup(getCourierPopupLine() || 'Не вдалося визначити адресу.');
                                }
                            })
                            .catch(function () {
                                courierOpenMapPopup(getCourierPopupLine() || 'Не вдалося визначити адресу.');
                            });
                    }, 200);
                }

                function setCourierCoordsAndSyncAddress(lat, lng) {
                    setCourierHidden(lat, lng);
                    courierOpenMapPopup('Уточнюємо адресу…');
                    fillCourierAddressFromCoords(lat, lng);
                }

                function getCourierMapContainer() {
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
                    } else if (npCityLat != null && npCityLng != null) {
                        startLat = npCityLat;
                        startLng = npCityLng;
                        z = 12;
                    }
                    return { lat: startLat, lng: startLng, z: z };
                }

                function initCourierMap() {
                    if (!npCourierActive() && !manualCourierActive()) return;
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
                        setCourierCoordsAndSyncAddress(syncLatG, syncLngG);
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
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap',
                        maxZoom: 19
                    }).addTo(courierLeafletMap);
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
                    setCourierCoordsAndSyncAddress(syncLatL, syncLngL);
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
                                        text = 'Некоректний ключ API Нової Пошти. Відкрийте адмінку → Налаштування → Інтеграції та вставте повний ключ UUID з кабінету Нової Пошти.';
                                    }
                                    throw new Error(text);
                                }
                                return j;
                            });
                        })
                        .then(function (j) {
                            var rows = (j && j.data) ? j.data : [];
                            regionSelect.innerHTML = '<option value="">Оберіть область…</option>';
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
                            var hint = (err && err.message) ? String(err.message) : 'Не вдалося завантажити області';
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
                    citySelect.innerHTML = '<option value="">Завантаження…</option>';
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
                            citySelect.innerHTML = '<option value="">Оберіть місто…</option>';
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
                            citySelect.innerHTML = '<option value="">Не вдалося завантажити міста</option>';
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
                                        whSelect.innerHTML = '<option value="">Спочатку оберіть місто…</option>';
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
                            whSelect.innerHTML = '<option value="">Спочатку оберіть місто…</option>';
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
                            whSelect.innerHTML = '<option value="">Спочатку оберіть місто…</option>';
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
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                        maxZoom: 19,
                    }).addTo(pickupMap);
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
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                        maxZoom: 19
                    }).addTo(npMap);
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
                        '<button type="button" class="np-map-wh-popup__close" aria-label="Закрити">&times;</button>' +
                        '<div class="np-map-wh-popup__top">' +
                        '<span class="np-map-wh-popup__brand">Нова Пошта</span>' +
                        '<span class="np-map-wh-popup__heading">Відділення</span>' +
                        '</div>' +
                        '<div class="np-map-wh-popup__body">' +
                        '<p class="np-map-wh-popup__name">' + npEscapeHtml(row.label || '') + '</p>' +
                        '</div>' +
                        '<p class="np-map-wh-popup__hint">Обрано для замовлення</p>' +
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
                    if (v !== cfg.npCourier) {
                        destroyCourierMap();
                    }
                    if (v !== cfg.pickup) {
                        destroyPickupMap();
                    }
                    if (pickupBlock) pickupBlock.hidden = v !== cfg.pickup;
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
                            whSelect.innerHTML = '<option value="">Спочатку оберіть місто…</option>';
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
                    whSelect.innerHTML = '<option value="">Завантаження…</option>';
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
                            whSelect.innerHTML = '<option value="">Оберіть відділення…</option>';
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
                            whSelect.innerHTML = '<option value="">Не вдалося завантажити відділення</option>';
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
                                citySelect.innerHTML = '<option value="">Спочатку оберіть область…</option>';
                                citySelect.disabled = true;
                                syncNpFieldLock();
                            }
                        }
                        if (whSelect) {
                            whSelect.innerHTML = '<option value="">Спочатку оберіть місто…</option>';
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
    @endpush
@endsection
