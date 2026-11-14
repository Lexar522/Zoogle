@php
    $title = (string) ($placeholder['title'] ?? '');
    $price = (float) ($placeholder['price'] ?? 0);
@endphp
<div class="product-card-shell">
    <article class="card product-card product-card--default product-card--placeholder" aria-label="{{ $title }}">
        <div class="product-card__shine" aria-hidden="true"></div>
        <div class="product-card__glow" aria-hidden="true"></div>
        <div class="product-card__media product-card__media--empty" aria-hidden="true">
            <svg class="preview-placeholder__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                @switch($placeholder['icon'] ?? 'box')
                    @case('bed')
                        <path d="M3 18v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6"/>
                        <path d="M3 18h18"/>
                        <path d="M6 10V7a2 2 0 0 1 2-2h3"/>
                        @break
                    @case('bowl')
                        <path d="M4 10h16"/>
                        <path d="M6 10c0 4 2.5 7 6 7s6-3 6-7"/>
                        <path d="M9 6c.4 1 .8 1 1.2 0"/>
                        @break
                    @case('leash')
                        <circle cx="7" cy="7" r="3"/>
                        <path d="M10 10l8 8"/>
                        <path d="M14 18h4v-4"/>
                        @break
                    @case('bag')
                        <path d="M6 8h12l-1 12H7L6 8z"/>
                        <path d="M9 8V6a3 3 0 0 1 6 0v2"/>
                        @break
                    @case('rope')
                        <path d="M8 8c4 0 4 8 8 8"/>
                        <path d="M8 12c3 0 3 4 6 4"/>
                        <circle cx="7" cy="8" r="1.5"/>
                        <circle cx="17" cy="16" r="1.5"/>
                        @break
                    @case('bottle')
                        <path d="M10 3h4"/>
                        <path d="M11 3v3l-3 4v9a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-9l-3-4V3"/>
                        @break
                    @case('carrier')
                        <rect x="3" y="8" width="18" height="11" rx="2"/>
                        <path d="M8 8V6a4 4 0 0 1 8 0v2"/>
                        <path d="M3 13h18"/>
                        @break
                    @default
                        <path d="M4 14h10v6H4z"/>
                        <path d="M14 16h6v4h-6z"/>
                        <path d="M8 14V8"/>
                        <path d="M8 8h5"/>
                @endswitch
            </svg>
        </div>
        <div class="product-card__body">
            <h3 class="product-card__title">
                <span class="product-card__title-text">{{ $title }}</span>
            </h3>
            <div class="product-card__actions">
                <div class="product-card__footer">
                    <div class="product-card__price-inline">
                        <div class="product-card__prices">
                            <p class="product-card__price">
                                <span class="product-card__price-amount">{{ number_format($price, 2, ',', ' ') }}</span><span class="product-card__price-currency" aria-hidden="true">₴</span>
                            </p>
                        </div>
                    </div>
                    <div class="product-card__action-buttons">
                        <div class="product-card__cart-react">
                            <button type="button" class="product-card__cart-add" tabindex="-1" aria-hidden="true">
                                <svg class="product-card__cart-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="9" cy="21" r="1"/>
                                    <circle cx="20" cy="21" r="1"/>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                </svg>
                            </button>
                        </div>
                        <div class="product-card__like-react">
                            <button type="button" class="product-card__favorite" tabindex="-1" aria-hidden="true">
                                <svg class="product-card__heart-icon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M19.4626 3.99415C16.7809 2.34923 14.4404 3.01211 13.0344 4.06801C12.4578 4.50096 12.1696 4.71743 12 4.71743C11.8304 4.71743 11.5422 4.50096 10.9656 4.06801C9.55962 3.01211 7.21909 2.34923 4.53744 3.99415C1.01807 6.15294 0.221721 13.2749 8.33953 19.2834C9.88572 20.4278 10.6588 21 12 21C13.3412 21 14.1143 20.4278 15.6605 19.2834C23.7783 13.2749 22.9819 6.15294 19.4626 3.99415Z" stroke="#707277" stroke-width="2" stroke-linecap="round" fill="#707277"/>
                                </svg>
                            </button>
                            <span class="product-card__like-count">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </article>
</div>
