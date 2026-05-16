@php
    $current = app()->getLocale();
    $display = ['uk' => 'UA', 'ru' => 'RU', 'en' => 'EN'][$current] ?? 'UA';
    $return = request()->getRequestUri();
    $suffix = $langSwitcherSuffix ?? '';
@endphp
<details class="site-header__lang-details" id="site-lang-switcher{{ $suffix }}">
    <summary class="site-header__lang" aria-label="{{ __('shop.lang_menu') }}">{{ $display }}</summary>
    <nav class="site-header__lang-menu" aria-label="{{ __('shop.lang_menu') }}">
        @foreach (['uk', 'ru', 'en'] as $code)
            @php $active = $current === $code; @endphp
            <a
                href="{{ route('locale.switch', ['locale' => $code]) }}?{{ http_build_query(['return' => $return]) }}"
                class="site-header__lang-option @if ($active) is-active @endif"
                @if ($active) aria-current="true" @endif
                hreflang="{{ $code }}"
            >
                <span class="site-header__lang-option-code">{{ ['uk' => 'UA', 'ru' => 'RU', 'en' => 'EN'][$code] }}</span>
                <span class="site-header__lang-option-label">{{ __('shop.lang_name_'.$code) }}</span>
            </a>
        @endforeach
    </nav>
</details>
