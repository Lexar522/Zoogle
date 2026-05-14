@php
    $isBundle = $listing instanceof \App\Models\Bundle;
    if ($isBundle) {
        $quote = ($bundleQuotes ?? [])[$listing->id] ?? ['subtotal' => 0, 'discount' => 0, 'total' => 0];
        $pay = (float) ($quote['total'] ?? 0);
        $onCardSale = (float) ($quote['discount'] ?? 0) > 0.001;
        $strike = $onCardSale ? (float) ($quote['subtotal'] ?? 0) : null;
        $photo = $listing->firstCatalogPhotoPath();
        $sellable = (bool) $listing->is_visible && (bool) $listing->is_active;
        $showUrl = route('bundles.show', $listing->slug);
    } else {
        $quote = ($listingQuotes ?? [])[$listing->id] ?? null;
        $pay = $quote ? (float) $quote->effectivePrice : (float) ($listing->price ?? 0);
        $onCardSale = $quote && $quote->isOnSale();
        $strike = $onCardSale && $quote->strikePrice !== null ? (float) $quote->strikePrice : null;
        $photo = $listing->firstCatalogPhotoPath();
        $sellable = (bool) $listing->is_available;
        $showUrl = route('catalog.show', $listing->slug);
    }
    $excerptPlain = '';
    if (! empty($listing->short_description)) {
        $excerptPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($listing->short_description)));
    } elseif (! empty($listing->description)) {
        $excerptPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($listing->description)));
    }

    $cardState = 'default';
    $cardBadge = 'В наявності';
    $cardAccent = '#0f766e';
    $cardAccentSoft = 'rgba(15, 118, 110, 0.24)';
    $cardMediaStart = '#99f6e4';
    $cardMediaEnd = '#14b8a6';

    if (! $sellable) {
        $cardState = 'unavailable';
        $cardBadge = 'Немає';
        $cardAccent = '#64748b';
        $cardAccentSoft = 'rgba(100, 116, 139, 0.24)';
        $cardMediaStart = '#cbd5e1';
        $cardMediaEnd = '#94a3b8';
    } elseif ($onCardSale) {
        $cardState = 'sale';
        $cardBadge = 'Акція';
        $cardAccent = '#ef4444';
        $cardAccentSoft = 'rgba(239, 68, 68, 0.24)';
        $cardMediaStart = '#fda4af';
        $cardMediaEnd = '#fb7185';
    } elseif ($isBundle) {
        $cardState = 'bundle';
        $cardBadge = 'Комплект';
        $cardAccent = '#7c3aed';
        $cardAccentSoft = 'rgba(124, 58, 237, 0.24)';
        $cardMediaStart = '#c4b5fd';
        $cardMediaEnd = '#8b5cf6';
    }

    $eagerImage = ($cardImagePriority ?? null) !== null
        ? (int) $cardImagePriority
        : (int) (isset($loop) ? $loop->index : 0);
    $homeCardBadge = filled($homeCardBadge ?? null) ? (string) $homeCardBadge : null;
    $homeCardBadgeClass = filled($homeCardBadgeClass ?? null) ? (string) $homeCardBadgeClass : 'default';
@endphp
<div class="product-card-shell">
<article
    class="card product-card product-card--{{ $cardState }}"
    style="
        --product-card-accent: {{ $cardAccent }};
        --product-card-accent-soft: {{ $cardAccentSoft }};
        --product-card-media-start: {{ $cardMediaStart }};
        --product-card-media-end: {{ $cardMediaEnd }};
    "
>
    <div class="product-card__shine" aria-hidden="true"></div>
    <div class="product-card__glow" aria-hidden="true"></div>
    <a href="{{ $showUrl }}" class="product-card__link-overlay">
        <span class="visually-hidden">{{ $listing->title }}</span>
    </a>
    @if ($homeCardBadge !== null)
        <div class="product-card__badge product-card__badge--home product-card__badge--{{ $homeCardBadgeClass }}">{{ $homeCardBadge }}</div>
    @elseif ($cardState !== 'default')
        <div class="product-card__badge product-card__badge--{{ $cardState }}">{{ $cardBadge }}</div>
    @endif
    @if ($photo)
        <div class="product-card__media">
            <img
                src="{{ asset('storage/' . ltrim($photo, '/')) }}"
                alt=""
                width="520"
                height="460"
                decoding="async"
                @if ($eagerImage < 3)
                    loading="eager" fetchpriority="{{ $eagerImage === 0 ? 'high' : 'low' }}"
                @else
                    loading="lazy"
                @endif
            >
        </div>
    @else
        <div class="product-card__media product-card__media--empty" aria-hidden="true">Немає фото</div>
    @endif
    <div class="product-card__body">
        @if ($isBundle)
            <p class="product-card__eyebrow">Комплект</p>
        @endif
        <h3 class="product-card__title">
            <span class="product-card__title-text">{{ $listing->title }}</span>
        </h3>
        <div class="product-card__actions">
            @if ($excerptPlain !== '')
                <p class="product-card__excerpt">{{ $excerptPlain }}</p>
            @endif
            <div class="product-card__footer">
                <div class="product-card__price-inline">
                    <div class="product-card__prices @if ($onCardSale) product-card__prices--on-sale @endif">
                        @if ($onCardSale)
                            <p class="product-card__price product-card__price--old">
                                <span class="product-card__price-amount">{{ number_format((float) $strike, 2, ',', ' ') }}</span><span class="product-card__price-currency" aria-hidden="true">₴</span>
                            </p>
                        @endif
                        <p class="product-card__price @if ($onCardSale) product-card__price--sale @endif">
                            <span class="product-card__price-amount">{{ number_format((float) $pay, 2, ',', ' ') }}</span><span class="product-card__price-currency" aria-hidden="true">₴</span>
                        </p>
                    </div>
                </div>
                <div class="product-card__action-buttons">
                    @if ($isBundle)
                        <div class="product-card__cart-react">
                            <form method="POST" action="{{ route('bundles.add-to-cart', $listing) }}" class="product-card__cart-form" data-cart-add-form>
                                @csrf
                                <button
                                    type="submit"
                                    class="product-card__cart-add"
                                    @disabled(!$sellable)
                                    title="{{ $sellable ? 'Додати комплект у кошик' : 'Недоступно для замовлення' }}"
                                    aria-label="{{ $sellable ? 'Додати комплект у кошик' : 'Недоступно для замовлення' }}"
                                >
                                    <svg class="product-card__cart-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="9" cy="21" r="1"/>
                                        <circle cx="20" cy="21" r="1"/>
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                        <a
                            href="{{ $showUrl }}"
                            class="product-card__btn product-card__btn--icon"
                            title="Відкрити комплект"
                            aria-label="Відкрити комплект"
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M5 12h14"/>
                                <path d="m12 5 7 7-7 7"/>
                            </svg>
                        </a>
                    @else
                        <div class="product-card__cart-react">
                            <form method="POST" action="{{ route('cart.store') }}" class="product-card__cart-form" data-cart-add-form>
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $listing->id }}">
                                <button
                                    type="submit"
                                    class="product-card__cart-add"
                                    @disabled(!$sellable)
                                    title="{{ $sellable ? 'У кошик' : 'Недоступно для замовлення' }}"
                                    aria-label="{{ $sellable ? 'Додати в кошик' : 'Недоступно для замовлення' }}"
                                >
                                    <svg class="product-card__cart-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="9" cy="21" r="1"/>
                                        <circle cx="20" cy="21" r="1"/>
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                        <div class="product-card__like-react">
                            <button
                                type="button"
                                class="product-card__favorite"
                                data-product-id="{{ $listing->id }}"
                                data-favorite-key="catalog"
                                aria-label="Уподобання"
                                aria-pressed="false"
                            >
                                <svg
                                    class="product-card__heart-icon"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="22"
                                    height="22"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M19.4626 3.99415C16.7809 2.34923 14.4404 3.01211 13.0344 4.06801C12.4578 4.50096 12.1696 4.71743 12 4.71743C11.8304 4.71743 11.5422 4.50096 10.9656 4.06801C9.55962 3.01211 7.21909 2.34923 4.53744 3.99415C1.01807 6.15294 0.221721 13.2749 8.33953 19.2834C9.88572 20.4278 10.6588 21 12 21C13.3412 21 14.1143 20.4278 15.6605 19.2834C23.7783 13.2749 22.9819 6.15294 19.4626 3.99415Z"
                                        stroke="#707277"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        fill="#707277"
                                    />
                                </svg>
                            </button>
                            <span class="product-card__like-count" data-product-like-count>{{ (int) ($listing->favorited_by_users_count ?? 0) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</article>
</div>
