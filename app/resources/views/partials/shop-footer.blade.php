@php
    /** @var array<string, mixed> $shopFooter */
    $sf = $shopFooter ?? [
        'use_dynamic_columns' => false,
        'link_column_count' => 3,
        'brand' => ['body' => null, 'phone' => null, 'site_title' => null, 'logo_url' => asset('images/zoogle-logo-new.png')],
        'columns' => [],
        'info_pages' => [],
        'faq' => ['title' => __('shop.footer_faq_block_title'), 'items' => []],
        'has_faq' => false,
        'pickup_map' => ['address' => null, 'embed_url' => null, 'maps_url' => null, 'has_embed' => false, 'embed_provider' => null],
    ];
    $useDynamic = ! empty($sf['use_dynamic_columns']);
    $showLinksPanel = $useDynamic;
    $linkCols = (int) ($sf['link_column_count'] ?? 3);
    $phoneRaw = isset($sf['brand']['phone']) ? trim((string) $sf['brand']['phone']) : '';
    $telHref = $phoneRaw !== '' ? 'tel:'.preg_replace('/\s+/', '', $phoneRaw) : '';
    $hasHeaderContacts = ! empty($hasShopHeaderContacts);
    $faq = is_array($sf['faq'] ?? null) ? $sf['faq'] : ['title' => __('shop.footer_faq_block_title'), 'items' => []];
    $faqItems = is_array($faq['items'] ?? null) ? $faq['items'] : [];
    $hasFaq = ! empty($sf['has_faq']) && $faqItems !== [];
    $footerTripleClass = match (true) {
        $showLinksPanel && $hasFaq => 'site-footer__triple--with-nav-and-faq',
        $showLinksPanel => 'site-footer__triple--with-nav',
        $hasFaq => 'site-footer__triple--with-faq',
        default => 'site-footer__triple--brand-only',
    };
    $infoPages = is_array($sf['info_pages'] ?? null) ? $sf['info_pages'] : [];
    $hasInfoPages = false;
    foreach ($infoPages as $ip) {
        if (is_array($ip) && trim((string) ($ip['slug'] ?? '')) !== '') {
            $hasInfoPages = true;
            break;
        }
    }
@endphp
<footer class="site-footer" role="contentinfo" style="--footer-link-cols: {{ $linkCols }}; --footer-nav-cols: {{ $linkCols }};">
    <div class="site-footer__shell">
        <div class="container site-footer__triple {{ $footerTripleClass }}">
            <div class="site-footer__panel site-footer__panel--brand">
                <div class="site-footer__brand-row">
                    <div class="site-footer__block site-footer__block--brand">
                        <div class="site-footer__brand">
                            @if(! empty($sf['brand']['logo_url']))
                                <div class="site-footer__brand-logo-wrap">
                                    <img
                                        src="{{ $sf['brand']['logo_url'] }}"
                                        alt=""
                                        class="site-footer__brand-logo"
                                        width="1484"
                                        height="551"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </div>
                            @endif
                            @if(filled($sf['brand']['site_title'] ?? null))
                                <strong class="site-footer__brand-name">{{ $sf['brand']['site_title'] }}</strong>
                            @elseif(empty($sf['brand']['logo_url']))
                                <strong>ZOOGLE</strong>
                            @endif
                            @php
                                $brandBodyHtml = \App\Support\RichTextSanitizer::toHtml($sf['brand']['body'] ?? null);
                            @endphp
                            @if($brandBodyHtml !== '')
                                <div class="site-footer__muted">{!! $brandBodyHtml !!}</div>
                            @endif
                            @if(! $hasHeaderContacts && $phoneRaw !== '')
                                <p class="site-footer__phone-line">
                                    <a href="{{ e($telHref) }}" class="site-footer__phone">{{ $phoneRaw }}</a>
                                </p>
                            @endif
                        </div>
                    </div>

                    @if($hasHeaderContacts)
                        <div class="site-footer__block site-footer__block--social">
                            <span class="site-footer__block-title">{{ __('shop.footer_social_block_title') }}</span>
                            <div class="site-footer__contacts-wrap site-footer__contacts-wrap--social-block">
                                <div class="site-header__contacts-google site-header__contacts-google--footer">
                                    @foreach(\App\Support\ShopHeaderContacts::groupsForFooter($shopHeaderContactItems ?? []) as $contactGroup => $contactGroupItems)
                                        <div class="site-footer__contact-group site-footer__contact-group--{{ $contactGroup }}">
                                            @include('partials.shop-header-contacts', [
                                                'shopHeaderContactItems' => $contactGroupItems,
                                                'contactIconsColored' => true,
                                            ])
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($hasInfoPages)
                        <div class="site-footer__block site-footer__block--info">
                            <span class="site-footer__block-title">{{ __('shop.footer_info_block_title') }}</span>
                            <div class="site-footer__info-wrap">
                                <ul class="site-footer__links site-footer__info-links">
                                    @foreach($infoPages as $info)
                                        @php
                                            $slug = trim((string) ($info['slug'] ?? ''));
                                        @endphp
                                        @if($slug !== '')
                                            <li class="site-footer__info-item">
                                                <a href="{{ route('info.show', ['slug' => $slug]) }}" class="site-footer__info-card">{{ e((string) ($info['title'] ?? $slug)) }}</a>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if($showLinksPanel)
                <div class="site-footer__panel site-footer__panel--links">
                    <div class="site-footer__nav">
                        @foreach($sf['columns'] as $col)
                            @if(($col['links'] ?? []) !== [])
                                <div class="site-footer__col">
                                    @if(($col['title'] ?? '') !== '')
                                        <span class="site-footer__heading">{{ e($col['title']) }}</span>
                                    @endif
                                    <ul class="site-footer__links">
                                        @foreach($col['links'] as $item)
                                            <li>
                                                <a
                                                    href="{{ e($item['href'] ?? '#') }}"
                                                    @if(! empty($item['open_new_tab'])) target="_blank" rel="noopener noreferrer" @endif
                                                >{{ e($item['label'] ?? '') }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if($hasFaq)
                @php
                    $faqLogoUrl = filled($sf['brand']['logo_url'] ?? null)
                        ? (string) $sf['brand']['logo_url']
                        : asset('images/zoogle-logo-new.png');
                @endphp
                <div class="site-footer__panel site-footer__panel--faq">
                    <section class="site-footer__faq" aria-labelledby="site-footer-faq-title" itemscope itemtype="https://schema.org/FAQPage">
                        <div class="site-footer__faq-chrome">
                            <a href="{{ route('home') }}" class="site-footer__faq-logo" aria-label="{{ __('shop.logo_aria') }}">
                                <img
                                    src="{{ $faqLogoUrl }}"
                                    alt=""
                                    class="site-footer__faq-logo-img"
                                    width="1484"
                                    height="551"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </a>
                            <form
                                class="site-footer__faq-search"
                                role="search"
                                data-faq-search
                                action="#"
                                onsubmit="return false;"
                            >
                                <label class="visually-hidden" for="site-footer-faq-q">{{ __('shop.footer_faq_search_aria') }}</label>
                                <input
                                    type="search"
                                    class="site-footer__faq-search-input"
                                    id="site-footer-faq-q"
                                    name="faq_q"
                                    value=""
                                    maxlength="2048"
                                    autocapitalize="off"
                                    autocomplete="off"
                                    placeholder="{{ __('shop.footer_faq_search_placeholder') }}"
                                    aria-label="{{ __('shop.footer_faq_search_aria') }}"
                                    aria-controls="site-footer-faq-list"
                                >
                                <div class="site-footer__faq-search-actions">
                                    <button
                                        type="button"
                                        class="site-footer__faq-search-clear"
                                        data-faq-search-clear
                                        hidden
                                        aria-label="{{ __('shop.search_clear_aria') }}"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                            <path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                                        </svg>
                                    </button>
                                    <span class="site-footer__faq-search-divider" aria-hidden="true"></span>
                                    <span class="site-footer__faq-search-tools" aria-hidden="true">
                                        <span class="site-footer__faq-search-tool site-footer__faq-search-keyboard" title="Keyboard">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">
                                                <path fill="currentColor" d="M20 5H4c-1.1 0-1.99.9-1.99 2L2 17c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-9 3h2v2h-2V8zm0 3h2v2h-2v-2zM8 8h2v2H8V8zm0 3h2v2H8v-2zm-1 2H5v-2h2v2zm0-3H5V8h2v2zm9 7H8v-2h8v2zm0-4h-2v-2h2v2zm0-3h-2V8h2v2zm3 3h-2v-2h2v2zm0-3h-2V8h2v2z"/>
                                            </svg>
                                        </span>
                                        <span class="site-footer__faq-search-tool site-footer__faq-search-mic" title="Voice">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">
                                                <path fill="currentColor" d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5-3c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                                            </svg>
                                        </span>
                                        <span class="site-footer__faq-search-tool site-footer__faq-search-lens" title="Lens">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">
                                                <path fill="currentColor" d="M9.5 13.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5S11.83 12 11 12s-1.5.67-1.5 1.5zM20 4h-3.17L15 2H9L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-8 13c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
                                            </svg>
                                        </span>
                                    </span>
                                    <button
                                        type="submit"
                                        class="site-footer__faq-search-submit"
                                        aria-label="{{ __('shop.search_submit_aria') }}"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                            <path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                        </svg>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <h2 class="site-footer__faq-title" id="site-footer-faq-title">{{ e((string) ($faq['title'] ?? __('shop.footer_faq_block_title'))) }}</h2>
                        <div class="site-footer__faq-list" id="site-footer-faq-list" data-faq-list>
                            @foreach($faqItems as $faqItem)
                                @php
                                    $question = trim((string) ($faqItem['question'] ?? ''));
                                    $answer = trim((string) ($faqItem['answer'] ?? ''));
                                    $answerHtml = \App\Support\RichTextSanitizer::toHtml($answer);
                                    $searchBlob = mb_strtolower(
                                        trim(preg_replace(
                                            '/\s+/u',
                                            ' ',
                                            html_entity_decode(strip_tags($question.' '.$answer), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                                        ) ?? ''),
                                        'UTF-8'
                                    );
                                @endphp
                                @if($question !== '')
                                    <details
                                        class="site-footer__faq-item"
                                        name="site-footer-faq"
                                        itemscope
                                        itemprop="mainEntity"
                                        itemtype="https://schema.org/Question"
                                        data-faq-item
                                        data-faq-search-text="{{ e($searchBlob) }}"
                                    >
                                        <summary class="site-footer__faq-question" itemprop="name">
                                            <span class="site-footer__faq-question-text">{{ e($question) }}</span>
                                            <span class="site-footer__faq-chevron" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" width="18" height="18" focusable="false">
                                                    <path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" fill="currentColor"/>
                                                </svg>
                                            </span>
                                        </summary>
                                        @if($answerHtml !== '')
                                            <div class="site-footer__faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                                                <div itemprop="text">{!! $answerHtml !!}</div>
                                            </div>
                                        @endif
                                    </details>
                                @endif
                            @endforeach
                        </div>
                        <p class="site-footer__faq-empty" data-faq-empty hidden>{{ __('shop.footer_faq_search_empty') }}</p>
                    </section>
                </div>
            @endif
        </div>
    </div>

    <div class="site-footer__bottom">
        <div class="container site-footer__bottom-inner">
            <span>&copy; {{ date('Y') }} ZOOGLE. {{ __('shop.footer_copyright') }}</span>
        </div>
    </div>
</footer>

<script src="{{ \App\Support\StorefrontAsset::url('js/storefront/footer.js') }}" defer></script>
