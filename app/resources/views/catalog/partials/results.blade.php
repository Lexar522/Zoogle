<div class="catalog-toolbar">
    <div>
        @php
            $toolbarTitle = 'Каталог';
            if (($filters['category'] ?? 0) > 0) {
                $selectedCategoryName = $categoryValues->firstWhere('id', (int) ($filters['category'] ?? 0))?->name;
                if (filled($selectedCategoryName)) {
                    $toolbarTitle = $selectedCategoryName;
                }
            }
            if (! empty($filters['on_sale'] ?? false)) {
                $toolbarTitle = (($filters['category'] ?? 0) > 0 ? $toolbarTitle.' · ' : '').'Акції';
            }
        @endphp
        <h1 class="catalog-toolbar__title">{{ $toolbarTitle }}</h1>
    </div>
    <div class="catalog-toolbar__meta">
        Знайдено: <strong>{{ $listings->total() }}</strong>
        @if ($filters['q'] !== '')
            <span>· запит: «{{ $filters['q'] }}»</span>
        @endif
        @if ($filters['category'] > 0)
            @php
                $catName = $categoryValues->firstWhere('id', (int) $filters['category'])?->name;
            @endphp
            @if ($catName)
                <span>· категорія: {{ $catName }}</span>
            @endif
        @endif
        @if (! empty($filters['on_sale'] ?? false))
            <span>· лише акційні</span>
        @endif
    </div>
</div>

@php
    $selectedCategoryId = (int) ($filters['category'] ?? 0);
    $activeCategoryPath = [];

    $findCategoryPath = function (array $nodes, int $targetId, array $trail = []) use (&$findCategoryPath): array {
        foreach ($nodes as $node) {
            $nodeId = is_array($node) ? (int) ($node['id'] ?? 0) : (int) ($node->id ?? 0);
            if ($nodeId <= 0) {
                continue;
            }

            $normalizedNode = [
                'id' => $nodeId,
                'name' => (string) (is_array($node) ? ($node['name'] ?? '') : ($node->name ?? '')),
            ];

            $currentTrail = $trail;
            $currentTrail[] = $normalizedNode;

            if ($nodeId === $targetId) {
                return $currentTrail;
            }

            $children = is_array($node) ? ($node['children'] ?? []) : ($node->children ?? []);
            if ($children instanceof \Illuminate\Support\Collection) {
                $children = $children->all();
            }

            $found = $findCategoryPath(is_array($children) ? $children : [], $targetId, $currentTrail);
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    };

    if ($selectedCategoryId > 0) {
        $activeCategoryPath = $findCategoryPath($categoryTree ?? [], $selectedCategoryId);
    }
@endphp

@if (($filters['q'] ?? '') !== '' || ($filters['category'] ?? 0) > 0 || ! empty($filters['on_sale'] ?? false))
    <div class="catalog-active-filters" style="display:flex;flex-wrap:wrap;gap:.45rem;margin:0 0 .85rem;">
        @if (($filters['q'] ?? '') !== '')
            <a
                class="btn secondary"
                href="{{ route('catalog.index', array_filter(['category' => ($filters['category'] ?? 0) ?: null, 'on_sale' => ! empty($filters['on_sale']) ? 1 : null])) }}"
                style="padding:.35rem .65rem;"
            >
                Пошук: {{ $filters['q'] }} ×
            </a>
        @endif

        @if ($selectedCategoryId > 0 && $activeCategoryPath !== [])
            @foreach ($activeCategoryPath as $index => $activeCategory)
                @php
                    $fallbackCategoryId = $index > 0 ? ($activeCategoryPath[$index - 1]['id'] ?? null) : null;
                @endphp
                <a
                    class="btn secondary"
                    href="{{ route('catalog.index', array_filter(['q' => $filters['q'] ?: null, 'category' => $fallbackCategoryId, 'on_sale' => ! empty($filters['on_sale']) ? 1 : null])) }}"
                    style="padding:.35rem .65rem;"
                >
                    {{ $activeCategory['name'] }} ×
                </a>
            @endforeach
        @endif

        @if (! empty($filters['on_sale'] ?? false))
            <a
                class="btn secondary"
                href="{{ route('catalog.index', array_filter(['q' => $filters['q'] ?: null, 'category' => ($filters['category'] ?? 0) ?: null])) }}"
                style="padding:.35rem .65rem;"
            >
                Акції ×
            </a>
        @endif
    </div>
@endif

@php
@endphp
<div class="grid catalog-results__grid">
    @forelse ($listings as $listing)
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
                $photo = null;
                if (is_array($listing->photos ?? null) && count($listing->photos)) {
                    $photo = $listing->photos[0];
                }
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
        @endphp
        <div class="product-card-shell @if ($excerptPlain !== '') product-card-shell--expandable @endif">
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
            <div class="product-card__badge product-card__badge--{{ $cardState }}">{{ $cardBadge }}</div>
            @if ($photo)
                <div class="product-card__media">
                    <img
                        src="{{ asset('storage/' . ltrim($photo, '/')) }}"
                        alt=""
                        width="520"
                        height="460"
                        decoding="async"
                        @if ($loop->index < 3)
                            loading="eager" fetchpriority="{{ $loop->index === 0 ? 'high' : 'low' }}"
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
                            <div class="product-card__prices">
                                @if ($onCardSale)
                                    <p class="product-card__price product-card__price--old">
                                        {{ number_format((float) $strike, 2, ',', ' ') }}
                                        <span class="product-card__price-currency">₴</span>
                                    </p>
                                @endif
                                <p class="product-card__price @if ($onCardSale) product-card__price--sale @endif">
                                    {{ number_format((float) $pay, 2, ',', ' ') }}
                                    <span class="product-card__price-currency">₴</span>
                                </p>
                            </div>
                        </div>
                        <div class="product-card__action-buttons">
                            @if ($isBundle)
                                <form method="POST" action="{{ route('bundles.add-to-cart', $listing) }}" class="product-card__cart-form" data-cart-add-form>
                                    @csrf
                                    <button
                                        type="submit"
                                        class="product-card__btn product-card__btn--icon"
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
                                <form method="POST" action="{{ route('cart.store') }}" class="product-card__cart-form" data-cart-add-form>
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $listing->id }}">
                                    <button
                                        type="submit"
                                        class="product-card__btn product-card__btn--icon"
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
                                <button
                                    type="button"
                                    class="product-card__favorite"
                                    data-product-id="{{ $listing->id }}"
                                    data-favorite-key="catalog"
                                    aria-label="Додати в обране"
                                    aria-pressed="false"
                                >
                                    <svg class="product-card__heart-icon" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </article>
        </div>
    @empty
        <div class="card" style="grid-column: 1 / -1;">
            <p style="margin:0;">Нічого не знайдено. Спробуйте змінити запит або <a href="{{ route('catalog.index') }}">скинути фільтри</a>.</p>
        </div>
    @endforelse
</div>

<div class="pagination-wrap">
    {{ $listings->links('vendor.pagination.shop') }}
</div>
