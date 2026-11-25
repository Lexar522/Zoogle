@props([
    'item',
    'class' => '',
    'id' => null,
    'alt' => '',
    'loading' => null,
    'decoding' => 'async',
    'autoplay' => false,
    'muted' => true,
    'loop' => false,
    'controls' => false,
    'playsinline' => true,
    'catalogCard' => false,
    'deferSrc' => false,
    'fetchpriority' => null,
])

@php
    use App\Models\ShopMediaSetting;
    use App\Support\ListingMedia;

    $item = is_array($item ?? null) ? $item : [];
    $path = (string) ($item['path'] ?? '');
    $url = (string) ($item['url'] ?? '');
    $type = (string) ($item['type'] ?? 'image');
    $pathForType = $path !== '' ? $path : (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $resolvedType = $pathForType !== '' ? ListingMedia::typeForPath($pathForType) : $type;

    if (in_array($resolvedType, [ListingMedia::TYPE_GIF, ListingMedia::TYPE_VIDEO], true)) {
        $type = $resolvedType;
    }

    $style = (string) ($item['object_position'] ?? 'object-fit: cover; object-position: 50% 50%;');
    $posterUrl = (string) ($item['poster_url'] ?? '');
    $isGif = $type === ListingMedia::TYPE_GIF;
    $isVideo = $type === ListingMedia::TYPE_VIDEO;
    $optimizeCard = $catalogCard && ShopMediaSetting::optimizeImagesEnabled();
    $displayUrl = $url;
    if ($optimizeCard) {
        $displayUrl = (string) ($item['card_url'] ?? '');
        if ($displayUrl === '' && ! $isGif && ! $isVideo) {
            $displayUrl = $url;
        }
    }
    $defer = $optimizeCard && filter_var($deferSrc, FILTER_VALIDATE_BOOLEAN) && $displayUrl !== '';
    $priority = is_string($fetchpriority) ? $fetchpriority : '';
@endphp

@if ($optimizeCard)
    @if ($displayUrl !== '')
        <img
            @if ($id) id="{{ $id }}" @endif
            class="{{ trim($class.' product-card__photo'.($defer ? ' product-card__photo--pending' : '')) }}"
            @if ($defer) data-src="{{ $displayUrl }}" @else src="{{ $displayUrl }}" @endif
            alt="{{ $alt }}"
            style="{{ $style }}"
            width="640"
            height="640"
            decoding="async"
            @if (! $defer) loading="eager" @endif
            @if (! $defer && $priority !== '') fetchpriority="{{ $priority }}" @endif
        >
    @endif
@elseif ($url !== '')
    @if ($isVideo)
        <video
            @if ($id) id="{{ $id }}" @endif
            class="{{ trim($class) }}"
            src="{{ $url }}"
            @if ($posterUrl !== '') poster="{{ $posterUrl }}" @endif
            style="{{ $style }}"
            data-zoogle-animated-media="video"
            @if ($catalogCard) data-catalog-card-media="video" @endif
            preload="{{ $catalogCard ? 'auto' : 'metadata' }}"
            @if ($playsinline) playsinline @endif
            @if ($muted) muted @endif
            @if ($loop) loop @endif
            @if ($autoplay) autoplay @endif
            @if ($controls) controls @endif
            aria-label="{{ $alt }}"
        ></video>
    @else
        <img
            @if ($id) id="{{ $id }}" @endif
            class="{{ trim($class) }}"
            src="{{ $url }}"
            alt="{{ $alt }}"
            style="{{ $style }}"
            @if ($isGif) data-zoogle-animated-media="gif" data-zoogle-animated-gif="1" @endif
            @if ($catalogCard && $isGif) data-catalog-card-media="gif" @endif
            decoding="{{ $isGif && $catalogCard ? 'sync' : $decoding }}"
            @if ($isGif && $catalogCard) loading="eager" @elseif ($loading) loading="{{ $loading }}" @endif
        >
    @endif
@endif
