@extends('layouts.shop')

@push('styles')
    <style>
        .account-page {
            max-width: 1120px;
            margin: 0 auto;
        }
        .account-page__grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 900px) {
            .account-page__grid {
                grid-template-columns: 252px minmax(0, 1fr);
                gap: 1.75rem;
                align-items: start;
            }
            .account-nav {
                position: sticky;
                top: calc(var(--header-sticky-offset) + 12px);
            }
        }
        .account-nav {
            background: linear-gradient(165deg, var(--surface) 0%, #f4f7fb 55%, #eef3f9 100%);
            border: 1px solid var(--border);
            border-radius: calc(var(--radius) + 4px);
            padding: 1rem 0.9rem;
            box-shadow: var(--shadow);
        }
        .account-nav__brand {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 0 0.65rem 0.75rem;
            margin-bottom: 0.35rem;
            border-bottom: 1px solid var(--border);
        }
        .account-nav__links {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        @media (max-width: 899px) {
            .account-nav__links {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 0.35rem;
            }
            .account-nav__brand {
                width: 100%;
                border-bottom: 0;
                padding-bottom: 0.5rem;
                margin-bottom: 0;
            }
        }
        .account-nav__link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 0.75rem;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text);
            font-weight: 600;
            font-size: 0.92rem;
            border: 1px solid transparent;
            transition:
                background-color var(--duration-hover) var(--ease-hover),
                border-color var(--duration-hover) var(--ease-hover),
                box-shadow var(--duration-hover) var(--ease-hover);
        }
        .account-nav__link:hover {
            background: rgba(54, 125, 241, 0.08);
            border-color: rgba(54, 125, 241, 0.12);
        }
        .account-nav__link.is-active {
            background: linear-gradient(135deg, rgba(54, 125, 241, 0.14) 0%, rgba(254, 180, 0, 0.1) 100%);
            border-color: rgba(54, 125, 241, 0.22);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8) inset;
        }
        .account-nav__dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.45;
            flex-shrink: 0;
        }
        .account-nav__link.is-active .account-nav__dot {
            opacity: 1;
            background: var(--color-cta);
            box-shadow: 0 0 0 2px rgba(var(--color-cta-rgb), 0.25);
        }
        .account-nav__logout {
            margin-top: 0.85rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--border);
        }
        @media (max-width: 899px) {
            .account-nav__logout {
                margin-top: 0.5rem;
                padding-top: 0.65rem;
                width: 100%;
                border-top: 1px solid var(--border);
            }
            .account-nav__logout form {
                width: 100%;
            }
            .account-nav__logout .btn {
                width: 100%;
            }
        }
        .account-main {
            min-width: 0;
        }
        .account-hero {
            position: relative;
            overflow: hidden;
            border-radius: calc(var(--radius) + 6px);
            border: 1px solid var(--border);
            background: linear-gradient(125deg, #1e5bb8 0%, #367df1 42%, #4a8ef5 100%);
            color: #fff;
            padding: clamp(1.25rem, 4vw, 1.85rem);
            margin-bottom: 1.25rem;
            box-shadow: 0 12px 40px rgba(54, 125, 241, 0.28);
        }
        .account-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 120% at 100% 0%, rgba(254, 180, 0, 0.35) 0%, transparent 55%);
            pointer-events: none;
        }
        .account-hero__inner {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1.1rem;
        }
        .account-hero__avatar {
            width: 4.25rem;
            height: 4.25rem;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.45);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
        }
        .account-hero__avatar--initials {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, rgba(254, 180, 0, 0.95) 0%, #feb400 100%);
            color: #1a2b4a;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .account-hero__text h1 {
            margin: 0 0 0.2rem;
            font-size: clamp(1.35rem, 3vw, 1.65rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .account-hero__text p {
            margin: 0;
            font-size: 0.95rem;
            opacity: 0.92;
        }
        .account-tiles {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .account-tile {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.35rem;
            padding: 1.1rem 1.15rem;
            border-radius: calc(var(--radius) + 2px);
            border: 1px solid var(--border);
            background: var(--surface);
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow);
            transition:
                transform 0.22s var(--ease-hover),
                box-shadow var(--duration-hover) var(--ease-hover),
                border-color var(--duration-hover) var(--ease-hover);
        }
        .account-tile:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover-lift);
            border-color: rgba(54, 125, 241, 0.25);
        }
        .account-tile__label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
        }
        .account-tile__value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text);
        }
        .account-tile__hint {
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 0.15rem;
        }
        .account-tile--orders .account-tile__value { color: var(--color-cta); }
        .account-tile--fav .account-tile__value { color: var(--color-price); }
        .account-section-title {
            margin: 0 0 1rem;
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text);
        }
        .account-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: calc(var(--radius) + 4px);
            padding: clamp(1rem, 3vw, 1.35rem) clamp(1rem, 3vw, 1.5rem);
            margin-bottom: 1.1rem;
            box-shadow: var(--shadow);
        }
        .account-card:last-child { margin-bottom: 0; }
        .account-card__head {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 0.5rem 1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid var(--border);
        }
        .account-card__head h1,
        .account-card__head h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
        }
        .account-card__head--hero h1 {
            font-size: clamp(1.25rem, 2.5vw, 1.45rem);
        }
        .account-card__head a {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            border-bottom: 1px solid rgba(54, 125, 241, 0.35);
        }
        .account-card__head a:hover {
            border-bottom-color: var(--color-cta);
            color: var(--color-cta);
        }
        .account-order-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .account-order-list li {
            margin: 0;
            padding: 0;
        }
        .account-order-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.65rem 1rem;
            align-items: center;
            padding: 0.85rem 0;
            border-bottom: 1px solid #f0f2f5;
            text-decoration: none;
            color: inherit;
            transition: background 0.18s var(--ease-hover);
            margin: 0 -0.35rem;
            padding-left: 0.35rem;
            padding-right: 0.35rem;
            border-radius: 8px;
        }
        .account-order-row:last-child {
            border-bottom: 0;
        }
        .account-order-row:hover {
            background: rgba(54, 125, 241, 0.05);
        }
        .account-order-row__num {
            font-weight: 800;
            color: var(--text);
        }
        .account-order-row__meta {
            font-size: 0.88rem;
            color: var(--muted);
            margin-top: 0.15rem;
        }
        .account-order-row__side {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            text-align: right;
            gap: 0.15rem;
        }
        .account-order-row__sum {
            font-weight: 800;
            color: var(--color-price);
            font-variant-numeric: tabular-nums;
        }
        .account-order-row__status {
            display: inline-block;
            margin-top: 0.25rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.2rem 0.5rem;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
        }
        .account-order-row__chev {
            color: var(--muted);
            font-size: 1.1rem;
            margin-left: 0.35rem;
        }
        .account-table-wrap {
            overflow-x: auto;
            margin: 0 -0.25rem;
            border-radius: 10px;
        }
        .account-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .account-table th {
            text-align: left;
            padding: 0.65rem 0.75rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            background: #f4f7fb;
            border-bottom: 1px solid var(--border);
        }
        .account-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #eef0f4;
            vertical-align: middle;
        }
        .account-table tbody tr {
            transition: background 0.18s var(--ease-hover);
        }
        .account-table tbody tr:hover {
            background: rgba(54, 125, 241, 0.04);
        }
        .account-table tbody tr:last-child td {
            border-bottom: 0;
        }
        .account-table a {
            font-weight: 700;
            color: var(--text);
            text-decoration: none;
        }
        .account-table a:hover {
            color: var(--color-cta);
        }
        .account-fav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .account-fav-card {
            display: block;
            border-radius: calc(var(--radius) + 2px);
            border: 1px solid var(--border);
            background: var(--surface);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow);
            transition:
                transform 0.22s var(--ease-hover),
                box-shadow var(--duration-hover) var(--ease-hover);
        }
        .account-fav-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover-lift);
        }
        .account-fav-card__img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            background: #f0f2f6;
        }
        .account-fav-card__body {
            padding: 0.75rem 0.85rem 0.95rem;
        }
        .account-fav-card__title {
            margin: 0;
            font-weight: 700;
            font-size: 0.95rem;
            line-height: 1.35;
        }
        .account-fav-card__price {
            margin: 0.35rem 0 0;
            font-weight: 800;
            color: var(--color-price);
            font-variant-numeric: tabular-nums;
        }
        .account-empty {
            margin: 0;
            padding: 1.5rem 1rem;
            text-align: center;
            color: var(--muted);
            font-size: 0.95rem;
            background: #fafbfc;
            border-radius: 10px;
            border: 1px dashed var(--border);
        }
        .account-back-row {
            margin-top: 1.25rem;
        }
    </style>
@endpush

@section('content')
    <div class="account-page">
        <div class="account-page__grid">
            @include('account.partials.nav')
            <div class="account-main">
                @yield('account_content')
            </div>
        </div>
    </div>
@endsection
