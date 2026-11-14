@php
    $shopAuthPayload = [
        'loggedIn' => auth()->check(),
        'csrf' => csrf_token(),
        'toggleUrl' => auth()->check() ? route('favorites.toggle') : '',
        'syncUrl' => auth()->check() ? route('favorites.sync') : '',
    ];
@endphp
<script>window.zoogleShopAuth = @json($shopAuthPayload);</script>
<script src="{{ \App\Support\StorefrontAsset::url('js/storefront/catalog.js') }}" defer></script>
