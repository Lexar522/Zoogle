@extends('layouts.shop')

@section('robots', 'noindex,follow')

@push('styles')
    <style>
        .account-page {
            max-width: 1120px;
            margin: 0 auto;
            padding: clamp(1rem, 3vw, 2rem) clamp(0.75rem, 3vw, 1.5rem);
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
                top: clamp(16px, calc(var(--header-sticky-offset) + 12px), 96px);
                align-self: start;
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
            display: grid;
            gap: 1.25rem;
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
        .account-card__lead {
            margin: 0 0 1rem;
            font-size: 0.92rem;
            line-height: 1.5;
        }
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
        .account-orders-index {
            overflow: hidden;
        }
        .account-orders-index__head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.15rem;
        }
        .account-orders-index__title {
            margin: 0;
            font-size: clamp(1.25rem, 3vw, 1.55rem);
            font-weight: 900;
            letter-spacing: -0.03em;
            color: var(--color-cta);
        }
        .account-orders-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            justify-content: flex-end;
        }
        .account-orders-tabs__link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.25rem;
            padding: 0.48rem 0.85rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 800;
            line-height: 1.1;
            box-shadow: 0 4px 12px rgba(31, 41, 55, 0.05);
            transition:
                transform 0.18s var(--ease-hover),
                border-color var(--duration-hover) var(--ease-hover),
                background-color var(--duration-hover) var(--ease-hover);
        }
        .account-orders-tabs__link:hover {
            transform: translateY(-1px);
            border-color: rgba(54, 125, 241, 0.28);
            background: #f8fbff;
        }
        .account-orders-tabs__link.is-active {
            border-color: rgba(34, 197, 94, 0.35);
            background: #dcfce7;
            color: #147a36;
        }
        .account-orders-list {
            display: grid;
            gap: 0.85rem;
        }
        .account-order-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-areas:
                "main amount"
                "state actions";
            gap: 0.95rem 1.25rem;
            align-items: start;
            padding: 1.15rem;
            border: 1px solid #e6ebf2;
            border-radius: calc(var(--radius) + 4px);
            background: #fff;
            box-shadow: 0 6px 18px rgba(31, 41, 55, 0.05);
            text-decoration: none;
            color: inherit;
            transition:
                transform 0.18s var(--ease-hover),
                box-shadow var(--duration-hover) var(--ease-hover),
                border-color var(--duration-hover) var(--ease-hover);
        }
        .account-order-card:hover {
            transform: translateY(-2px);
            border-color: rgba(54, 125, 241, 0.24);
            box-shadow: 0 14px 34px rgba(31, 41, 55, 0.09);
        }
        .account-order-card--payable {
            background: linear-gradient(90deg, rgba(54, 125, 241, 0.055), #fff 48%);
            border-color: rgba(54, 125, 241, 0.18);
        }
        .account-order-card__main {
            grid-area: main;
            min-width: 0;
        }
        .account-order-card__amount {
            grid-area: amount;
            justify-self: end;
            min-width: 8.5rem;
            text-align: right;
        }
        .account-order-card__number {
            display: inline-flex;
            max-width: 100%;
            color: var(--color-cta) !important;
            font-weight: 900;
            line-height: 1.25;
            text-decoration: none;
            word-break: break-word;
        }
        .account-order-card__number:hover {
            color: var(--color-cta-hover);
        }
        .account-order-card__date {
            margin-top: 0.3rem;
            color: var(--muted);
            font-size: 0.86rem;
            font-weight: 700;
        }
        .account-order-card__sum {
            color: var(--color-price);
            font-size: 1.12rem;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .account-order-card__label {
            display: block;
            margin-bottom: 0.32rem;
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }
        .account-order-card__badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .account-order-card__state {
            grid-area: state;
            min-width: 0;
            padding-top: 0.85rem;
            border-top: 1px solid #eef2f6;
        }
        .account-order-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
            min-height: 2rem;
            padding: 0.45rem 0.78rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 900;
            line-height: 1.1;
        }
        .account-order-badge::before {
            content: '';
            width: 0.44rem;
            height: 0.44rem;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.38;
        }
        .account-order-badge--success {
            background: #eaf8ef;
            color: #147a36;
        }
        .account-order-badge--pending {
            background: #fff7df;
            color: #9b6400;
        }
        .account-order-badge--info {
            background: #eaf2ff;
            color: #1d5fd6;
        }
        .account-order-badge--danger {
            background: #fff0f0;
            color: #b42318;
        }
        .account-order-card__payment-note {
            max-width: 32rem;
            margin: 0.65rem 0 0;
            color: var(--muted);
            font-size: 0.84rem;
            font-weight: 650;
            line-height: 1.45;
        }
        .account-order-card__actions {
            grid-area: actions;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
            justify-self: end;
            align-self: end;
            padding-top: 0.85rem;
            border-top: 1px solid #eef2f6;
            min-width: 8rem;
        }
        .account-order-card__actions .btn {
            min-width: 7.5rem;
            justify-content: center;
            font-size: 0.84rem;
            padding: 0.48rem 0.85rem;
            white-space: nowrap;
        }
        .account-order-card__open {
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 800;
            text-decoration: none;
        }
        .account-order-card__open:hover {
            color: var(--color-cta);
        }
        @media (max-width: 980px) {
            .account-orders-index__head {
                align-items: flex-start;
                flex-direction: column;
            }
            .account-orders-tabs {
                justify-content: flex-start;
            }
            .account-order-card {
                grid-template-columns: minmax(0, 1fr) auto;
            }
        }
        @media (max-width: 640px) {
            .account-order-card {
                grid-template-columns: 1fr;
                grid-template-areas:
                    "main"
                    "amount"
                    "state"
                    "actions";
                align-items: stretch;
            }
            .account-order-card__amount {
                justify-self: start;
                text-align: left;
            }
            .account-order-card__actions {
                align-items: stretch;
                justify-self: stretch;
            }
            .account-order-card__actions .btn {
                width: 100%;
            }
        }
        .account-order-detail__hero {
            position: relative;
            overflow: hidden;
            border-radius: calc(var(--radius) + 10px);
            border: 1px solid rgba(54, 125, 241, 0.18);
            background:
                radial-gradient(circle at 92% 10%, rgba(254, 180, 0, 0.25), transparent 30%),
                linear-gradient(135deg, #1e5bb8 0%, #367df1 52%, #5ca0ff 100%);
            color: #fff;
            padding: clamp(1.45rem, 3.5vw, 2.25rem);
            box-shadow: 0 18px 48px rgba(54, 125, 241, 0.26);
            margin-bottom: 1.4rem;
        }
        .account-order-detail__hero::after {
            content: '';
            position: absolute;
            width: 12rem;
            height: 12rem;
            right: -4rem;
            bottom: -6rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            pointer-events: none;
        }
        .account-order-detail__hero-inner {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .account-order-detail__eyebrow {
            display: inline-flex;
            margin: 0 0 0.65rem;
            padding: 0.34rem 0.68rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .account-order-detail__title {
            margin: 0;
            font-size: clamp(1.35rem, 3vw, 2rem);
            line-height: 1.1;
            letter-spacing: -0.04em;
        }
        .account-order-detail__hint {
            margin: 0.55rem 0 0;
            color: rgba(255, 255, 255, 0.86);
            font-weight: 600;
        }
        .account-order-detail__hero-actions {
            display: flex;
            gap: 0.55rem;
            flex-wrap: wrap;
        }
        .account-order-detail__hero-actions .btn {
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.16);
        }
        .account-order-detail__panel {
            border: 1px solid var(--border);
            border-radius: calc(var(--radius) + 8px);
            background: rgba(255, 255, 255, 0.94);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.4rem;
        }
        .account-order-detail__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            border-bottom: 1px solid var(--border);
        }
        .account-order-detail__cell {
            padding: 1.15rem 1.25rem;
            border-right: 1px solid var(--border);
        }
        .account-order-detail__cell:last-child {
            border-right: 0;
        }
        .account-order-detail__label {
            display: block;
            margin-bottom: 0.35rem;
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .account-order-detail__value {
            display: block;
            color: var(--text);
            font-weight: 800;
            line-height: 1.3;
        }
        .account-order-detail__value--price {
            color: var(--color-price);
            font-size: 1.15rem;
            font-variant-numeric: tabular-nums;
        }
        .account-order-detail__badge {
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
        .account-order-detail__badge::before {
            content: '';
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 0 3px currentColor;
            opacity: 0.35;
        }
        .account-order-detail__badge--success {
            background: #eaf8ef;
            color: #147a36;
        }
        .account-order-detail__badge--pending {
            background: #fff7df;
            color: #9b6400;
        }
        .account-order-detail__badge--danger {
            background: #fff0f0;
            color: #b42318;
        }
        .account-order-detail__body {
            padding: 1.25rem;
        }
        .account-order-detail__delivery {
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #edf1f6;
            padding: 1.15rem 1.2rem;
        }
        .account-order-detail__delivery p {
            margin: 0.25rem 0 0;
            color: var(--muted);
            font-weight: 600;
            line-height: 1.45;
        }
        .account-order-detail__items-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            padding: 1.15rem 1.25rem;
            border-bottom: 1px solid var(--border);
        }
        .account-order-detail__items-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 900;
            letter-spacing: -0.02em;
        }
        .account-order-items {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .account-order-item {
            display: grid;
            grid-template-columns: 72px minmax(0, 1fr) auto;
            gap: 0.95rem 1.15rem;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #eef0f4;
        }
        .account-order-item:last-child {
            border-bottom: 0;
        }
        .account-order-item__media {
            width: 72px;
            aspect-ratio: 1;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #e6ebf2;
            background: linear-gradient(145deg, #f7fafc, #e9eef6);
            box-shadow: 0 5px 14px rgba(31, 41, 55, 0.06);
        }
        .account-order-item__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .account-order-item__empty {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            padding: 0.35rem;
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 800;
            line-height: 1.1;
            text-align: center;
        }
        .account-order-item__title {
            margin: 0;
            font-weight: 800;
            color: var(--text);
            line-height: 1.35;
        }
        .account-order-item__title a {
            color: inherit;
            text-decoration: none;
            border-bottom: 1px solid rgba(54, 125, 241, 0.22);
        }
        .account-order-item__title a:hover {
            color: var(--color-cta);
            border-bottom-color: currentColor;
        }
        .account-order-item__meta {
            margin: 0.2rem 0 0;
            color: var(--muted);
            font-size: 0.86rem;
            font-weight: 600;
        }
        .account-order-item__price {
            color: var(--color-price);
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        @media (max-width: 560px) {
            .account-order-detail__hero-inner {
                align-items: flex-start;
            }
            .account-order-detail__grid {
                grid-template-columns: 1fr;
            }
            .account-order-detail__cell {
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }
            .account-order-detail__cell:last-child {
                border-bottom: 0;
            }
            .account-order-item {
                grid-template-columns: 56px minmax(0, 1fr);
                align-items: start;
            }
            .account-order-item__media {
                width: 56px;
            }
            .account-order-item__price {
                grid-column: 2;
            }
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
        .account-flash {
            margin: 0 0 1rem;
            font-size: 0.92rem;
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

        @media (max-width: 768px) {
            .account-page {
                padding: 0 max(12px, env(safe-area-inset-right, 0px)) 1rem max(12px, env(safe-area-inset-left, 0px));
            }

            .account-page__grid,
            .account-main {
                gap: 0.75rem;
            }

            .account-nav {
                position: sticky;
                top: 0;
                z-index: 20;
                margin: 0 -12px;
                border-radius: 0 0 16px 16px;
                padding: 0.65rem 0.7rem;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.08);
            }

            .account-nav__links {
                flex-wrap: nowrap;
                overflow-x: auto;
                overflow-y: hidden;
                padding-bottom: 0.1rem;
                scrollbar-width: none;
            }

            .account-nav__links::-webkit-scrollbar {
                display: none;
            }

            .account-nav__link {
                flex: 0 0 auto;
                min-height: 36px;
                padding: 0.45rem 0.68rem;
                border-radius: 999px;
                white-space: nowrap;
                font-size: 0.82rem;
            }

            .account-nav__link:hover {
                box-shadow: none;
            }

            .account-nav__brand,
            .account-nav__logout {
                display: none;
            }

            .account-hero {
                border-radius: 16px;
                padding: 0.85rem 0.9rem;
                margin-bottom: 0.75rem;
                box-shadow: 0 6px 20px rgba(54, 125, 241, 0.18);
            }

            .account-hero__inner {
                align-items: flex-start;
                gap: 0.65rem;
            }

            .account-hero__avatar {
                width: 3rem;
                height: 3rem;
                border-width: 2px;
            }

            .account-hero__avatar--initials {
                font-size: 1.15rem;
            }

            .account-hero__text h1 {
                font-size: 1.05rem;
            }

            .account-hero__text p {
                font-size: 0.8rem;
            }

            .account-section-title {
                font-size: 0.94rem;
                margin-bottom: 0.75rem;
            }

            .account-tiles {
                grid-template-columns: 1fr 1fr;
                gap: 0.55rem;
                margin-bottom: 0.75rem;
            }

            .account-tile {
                padding: 0.75rem 0.8rem;
                border-radius: 14px;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.06);
            }

            .account-tile:hover {
                transform: none;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.06);
            }

            .account-tile__label {
                font-size: 0.68rem;
            }

            .account-tile__value {
                font-size: 1.05rem;
            }

            .account-tile__hint {
                font-size: 0.72rem;
                line-height: 1.3;
            }

            .account-card {
                border-radius: 16px;
                padding: 0.85rem;
                margin-bottom: 0.75rem;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.06);
            }

            .account-card__head {
                align-items: flex-start;
                margin-bottom: 0.7rem;
                padding-bottom: 0.65rem;
                gap: 0.35rem 0.65rem;
            }

            .account-card__head h1,
            .account-card__head h2 {
                font-size: 0.94rem;
            }

            .account-card__head--hero h1 {
                font-size: 1rem;
            }

            .account-card__head a {
                font-size: 0.78rem;
            }

            .account-card__lead {
                font-size: 0.8rem;
                margin-bottom: 0.75rem;
            }

            .account-profile-form label {
                font-size: 0.78rem;
                margin-bottom: 4px;
            }

            .account-profile-form input {
                padding: 8px 10px;
                font-size: 0.88rem;
                border-radius: 8px;
            }

            .account-profile-form .btn {
                width: 100%;
                min-height: 40px;
                font-size: 0.88rem;
            }

            .account-order-row {
                grid-template-columns: 1fr;
                gap: 0.35rem;
                padding: 0.65rem 0;
            }

            .account-order-row__num {
                font-size: 0.88rem;
            }

            .account-order-row__meta {
                font-size: 0.72rem;
            }

            .account-order-row__sum {
                font-size: 0.88rem;
            }

            .account-order-row__status {
                font-size: 0.66rem;
                padding: 0.15rem 0.42rem;
            }

            .account-order-row__chev {
                font-size: 0.95rem;
            }

            .account-order-row__side {
                align-items: flex-start;
                text-align: left;
            }

            .account-orders-index__head {
                margin-bottom: 0.75rem;
                gap: 0.55rem;
            }

            .account-orders-index__title {
                font-size: 0.94rem;
            }

            .account-orders-tabs {
                flex-wrap: nowrap;
                width: 100%;
                overflow-x: auto;
                overflow-y: hidden;
                padding-bottom: 0.1rem;
                scrollbar-width: none;
                gap: 0.35rem;
            }

            .account-orders-tabs::-webkit-scrollbar {
                display: none;
            }

            .account-orders-tabs__link {
                flex: 0 0 auto;
                min-height: 34px;
                padding: 0.4rem 0.72rem;
                font-size: 0.76rem;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.05);
            }

            .account-orders-tabs__link:hover {
                transform: none;
            }

            .account-orders-list {
                gap: 0.65rem;
            }

            .account-order-card {
                padding: 0.85rem;
                border-radius: 16px;
                gap: 0.65rem 0.85rem;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.06);
            }

            .account-order-card:hover {
                transform: none;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.06);
            }

            .account-order-card__number {
                font-size: 0.88rem;
            }

            .account-order-card__date {
                font-size: 0.72rem;
                margin-top: 0.2rem;
            }

            .account-order-card__sum {
                font-size: 0.94rem;
            }

            .account-order-card__label {
                font-size: 0.62rem;
                margin-bottom: 0.2rem;
            }

            .account-order-card__payment-note {
                font-size: 0.76rem;
                margin-top: 0.45rem;
            }

            .account-order-card__state,
            .account-order-card__actions {
                padding-top: 0.6rem;
            }

            .account-order-card__actions .btn {
                min-height: 38px;
                font-size: 0.8rem;
                padding: 0.42rem 0.75rem;
            }

            .account-order-card__open {
                font-size: 0.76rem;
            }

            .account-order-badge,
            .account-order-detail__badge {
                min-height: 1.85rem;
                padding: 0.38rem 0.65rem;
                font-size: 0.72rem;
            }

            .account-order-detail__hero {
                border-radius: 16px;
                padding: 0.9rem;
                margin-bottom: 0.75rem;
                box-shadow: 0 6px 20px rgba(54, 125, 241, 0.18);
            }

            .account-order-detail__eyebrow {
                font-size: 0.66rem;
                padding: 0.28rem 0.55rem;
                margin-bottom: 0.45rem;
            }

            .account-order-detail__title {
                font-size: 1.05rem;
            }

            .account-order-detail__hint {
                font-size: 0.8rem;
                margin-top: 0.35rem;
            }

            .account-order-detail__hero-actions,
            .account-order-detail__hero-actions .btn {
                width: 100%;
            }

            .account-order-detail__hero-actions .btn {
                min-height: 40px;
                font-size: 0.88rem;
            }

            .account-order-detail__panel {
                border-radius: 16px;
                margin-bottom: 0.75rem;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.06);
            }

            .account-order-detail__cell,
            .account-order-detail__body,
            .account-order-detail__items-head,
            .account-order-item {
                padding: 0.85rem;
            }

            .account-order-detail__label {
                font-size: 0.66rem;
            }

            .account-order-detail__value {
                font-size: 0.88rem;
            }

            .account-order-detail__value--price {
                font-size: 0.94rem;
            }

            .account-order-detail__items-title {
                font-size: 0.92rem;
            }

            .account-order-detail__delivery {
                padding: 0.85rem;
            }

            .account-order-detail__delivery p {
                font-size: 0.8rem;
            }

            .account-order-item {
                gap: 0.65rem;
            }

            .account-order-item__media {
                width: 56px;
                border-radius: 10px;
            }

            .account-order-item__title {
                font-size: 0.86rem;
            }

            .account-order-item__meta {
                font-size: 0.74rem;
            }

            .account-order-item__price {
                font-size: 0.88rem;
            }

            .account-fav-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.55rem;
            }

            .account-fav-card {
                border-radius: 14px;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.06);
            }

            .account-fav-card:hover {
                transform: none;
                box-shadow: 0 1px 3px rgba(31, 41, 55, 0.06);
            }

            .account-fav-card__body {
                padding: 0.55rem 0.6rem 0.65rem;
            }

            .account-fav-card__title {
                font-size: 0.82rem;
            }

            .account-fav-card__price {
                margin-top: 0.25rem;
                font-size: 0.86rem;
            }

            .account-flash {
                margin-bottom: 0.75rem;
                font-size: 0.82rem;
            }

            .account-empty {
                padding: 1rem 0.75rem;
                font-size: 0.82rem;
            }

            .account-back-row .btn {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                min-height: 40px;
                padding: 8px 12px;
                font-size: 0.88rem;
                text-align: center;
                border-radius: 12px;
            }
        }

        @media (max-width: 420px) {
            .account-tiles {
                grid-template-columns: 1fr;
            }

            .account-fav-grid {
                grid-template-columns: 1fr;
            }

            .account-order-card__badges {
                flex-direction: column;
                align-items: flex-start;
            }

            .account-order-badge,
            .account-order-detail__badge {
                width: 100%;
                justify-content: flex-start;
            }
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
