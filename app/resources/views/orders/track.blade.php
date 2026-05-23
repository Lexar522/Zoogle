@extends('layouts.shop')

@section('title', __('shop.order_track_page_title', ['number' => $order->number]))
@section('robots', 'noindex,follow')

@push('styles')
    <style>
        .order-track {
            max-width: 980px;
            margin: 0 auto;
        }

        .order-track__hero {
            position: relative;
            overflow: hidden;
            border-radius: calc(var(--radius) + 10px);
            border: 1px solid rgba(54, 125, 241, 0.18);
            background:
                radial-gradient(circle at 92% 10%, rgba(254, 180, 0, 0.25), transparent 30%),
                linear-gradient(135deg, #1e5bb8 0%, #367df1 50%, #5ca0ff 100%);
            color: #fff;
            padding: clamp(1.35rem, 4vw, 2rem);
            box-shadow: 0 18px 50px rgba(54, 125, 241, 0.28);
            margin-bottom: 1.15rem;
        }

        .order-track__hero::after {
            content: '';
            position: absolute;
            width: 13rem;
            height: 13rem;
            right: -4rem;
            bottom: -6rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            pointer-events: none;
        }

        .order-track__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin: 0 0 0.7rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .order-track__title {
            position: relative;
            z-index: 1;
            margin: 0;
            max-width: 760px;
            font-size: clamp(1.55rem, 4vw, 2.35rem);
            line-height: 1.1;
            letter-spacing: -0.04em;
        }

        .order-track__meta {
            position: relative;
            z-index: 1;
            margin: 0.65rem 0 0;
            color: rgba(255, 255, 255, 0.86);
            font-weight: 600;
        }

        .order-track__panel {
            border: 1px solid var(--border);
            border-radius: calc(var(--radius) + 8px);
            background: rgba(255, 255, 255, 0.92);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .order-track__summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 0;
            border-bottom: 1px solid var(--border);
        }

        .order-track__summary-item {
            padding: 1rem 1.05rem;
            border-right: 1px solid var(--border);
        }

        .order-track__summary-item:last-child {
            border-right: 0;
        }

        .order-track__label {
            display: block;
            margin-bottom: 0.35rem;
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .order-track__value {
            display: block;
            color: var(--text);
            font-weight: 800;
            font-size: 1rem;
            line-height: 1.3;
        }

        .order-track__value--price {
            color: var(--color-price);
            font-size: 1.18rem;
            font-variant-numeric: tabular-nums;
        }

        .order-track__badge {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            width: fit-content;
            min-height: 2.15rem;
            padding: 0.56rem 1rem;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.9rem;
            line-height: 1.15;
        }

        .order-track__badge::before {
            content: '';
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 0 3px currentColor;
            opacity: 0.35;
        }

        .order-track__badge--success {
            background: #eaf8ef;
            color: #147a36;
        }

        .order-track__badge--pending {
            background: #fff7df;
            color: #9b6400;
        }

        .order-track__badge--danger {
            background: #fff0f0;
            color: #b42318;
        }

        .order-track__details {
            display: grid;
            gap: 0.85rem;
            padding: 1.05rem;
        }

        .order-track__detail-card {
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #edf1f6;
            padding: 0.95rem 1rem;
        }

        .order-track__detail-card p {
            margin: 0.25rem 0 0;
            color: var(--muted);
            font-weight: 600;
            line-height: 1.45;
        }

        .order-track__detail-card a {
            color: var(--color-cta);
            font-weight: 800;
        }

        .order-track__section-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            padding: 1rem 1.05rem;
            border-bottom: 1px solid var(--border);
        }

        .order-track__section-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 900;
            letter-spacing: -0.02em;
        }

        .order-track__items {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .order-track__item {
            display: grid;
            grid-template-columns: 84px minmax(0, 1fr) auto;
            gap: 0.85rem 1rem;
            align-items: center;
            padding: 0.95rem 1.05rem;
            border-bottom: 1px solid #eef2f6;
        }

        .order-track__item:last-child {
            border-bottom: 0;
        }

        .order-track__item-media {
            width: 84px;
            aspect-ratio: 1;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e6ebf2;
            background: linear-gradient(145deg, #f7fafc, #e9eef6);
            box-shadow: 0 6px 18px rgba(31, 41, 55, 0.07);
        }

        .order-track__item-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .order-track__item-media-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 800;
            line-height: 1.15;
            text-align: center;
            padding: 0.4rem;
        }

        .order-track__item-title {
            margin: 0;
            font-weight: 800;
            color: var(--text);
            line-height: 1.35;
        }

        .order-track__item-title a {
            color: inherit;
            text-decoration: none;
            border-bottom: 1px solid rgba(54, 125, 241, 0.22);
        }

        .order-track__item-title a:hover {
            color: var(--color-cta);
            border-bottom-color: currentColor;
        }

        .order-track__item-meta {
            margin: 0.2rem 0 0;
            color: var(--muted);
            font-size: 0.88rem;
            font-weight: 600;
        }

        .order-track__item-price {
            color: var(--color-price);
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .order-track__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
            margin-top: 1.15rem;
        }

        .order-track__actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            min-height: 48px;
            padding: 12px 22px;
            border-radius: calc(var(--radius) + 4px);
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
        }

        @media (max-width: 768px) {
            .order-track {
                box-sizing: border-box;
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding-left: max(12px, env(safe-area-inset-left, 0px));
                padding-right: max(12px, env(safe-area-inset-right, 0px));
                padding-bottom: max(16px, calc(env(safe-area-inset-bottom, 0px) + 8px));
            }

            .order-track__hero {
                border-radius: 18px;
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .order-track__title {
                font-size: clamp(1.35rem, 7vw, 1.8rem);
            }

            .order-track__panel {
                border-radius: 18px;
                margin-bottom: 1rem;
            }

            .order-track__summary-grid {
                grid-template-columns: 1fr;
            }

            .order-track__summary-item {
                padding: 0.9rem 1rem;
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .order-track__summary-item:last-child {
                border-bottom: 0;
            }

            .order-track__item {
                grid-template-columns: 72px minmax(0, 1fr);
                align-items: start;
                gap: 0.8rem;
                padding: 0.9rem 1rem;
            }

            .order-track__item-media {
                width: 72px;
                border-radius: 14px;
            }

            .order-track__item-price {
                grid-column: 2;
            }

            .order-track__details,
            .order-track__section-head {
                padding: 1rem;
            }

            .order-track__badge {
                min-height: 2.05rem;
                padding: 0.48rem 0.78rem;
                font-size: 0.78rem;
            }

            .order-track__actions {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
                margin-top: 1rem;
                gap: 0.65rem;
            }

            .order-track__actions .btn,
            .order-track__actions .btn-buy {
                display: flex;
                width: 100%;
                max-width: 100%;
                min-height: 44px;
                justify-content: center;
            }
        }
    </style>
@endpush

@section('content')
    <div class="order-track">
        @include('orders.partials.summary', ['order' => $order])

        <div class="order-track__actions">
            <a class="btn btn-buy" href="{{ route('catalog.index') }}">{{ __('shop.order_track_to_catalog') }}</a>
        </div>
    </div>
@endsection
