@php
    /** @var array<string, mixed> $shopFooter */
    $sf = $shopFooter ?? [
        'use_dynamic_columns' => false,
        'link_column_count' => 3,
        'brand' => ['body' => null, 'phone' => null, 'site_title' => null, 'logo_url' => asset('images/zoogle-logo-new.png')],
        'columns' => [],
        'info_pages' => [],
        'pickup_map' => ['address' => null, 'embed_url' => null, 'maps_url' => null, 'has_embed' => false, 'embed_provider' => null],
    ];
    $useDynamic = ! empty($sf['use_dynamic_columns']);
    $showLinksPanel = $useDynamic;
    $linkCols = (int) ($sf['link_column_count'] ?? 3);
    $phoneRaw = isset($sf['brand']['phone']) ? trim((string) $sf['brand']['phone']) : '';
    $telHref = $phoneRaw !== '' ? 'tel:'.preg_replace('/\s+/', '', $phoneRaw) : '';
    $hasHeaderContacts = ! empty($hasShopHeaderContacts);
    $footerTripleClass = $showLinksPanel ? 'site-footer__triple--with-nav' : 'site-footer__triple--brand-only';
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
                                        width="1277"
                                        height="320"
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
                            @if(filled($sf['brand']['body'] ?? null))
                                <p class="site-footer__muted">{!! nl2br(e((string) $sf['brand']['body'])) !!}</p>
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
                                <div class="site-header__contacts-google">
                                    @include('partials.shop-header-contacts', ['contactIconsColored' => true])
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
                            @if(($col['title'] ?? '') !== '' || ($col['links'] ?? []) !== [])
                                <div class="site-footer__col">
                                    @if(($col['title'] ?? '') !== '')
                                        <span class="site-footer__heading">{{ e($col['title']) }}</span>
                                    @endif
                                    <ul class="site-footer__links">
                                        @foreach($col['links'] ?? [] as $item)
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
        </div>
    </div>

    <div class="site-footer__bottom">
        <div class="container site-footer__bottom-inner">
            <span>&copy; {{ date('Y') }} ZOOGLE. {{ __('shop.footer_copyright') }}</span>
        </div>
    </div>
</footer>
