{{-- Ключ спільний із головною: там цей CSS уже входить у home-page.css --}}
@pushOnce('styles', 'storefront-home-carousel-css')
@vite('resources/css/storefront/home-carousel.css')
@endPushOnce

@push('scripts')
<script src="{{ \App\Support\StorefrontAsset::url('js/storefront/home-carousel.js') }}" defer></script>
<script src="{{ \App\Support\StorefrontAsset::url('js/storefront/home-swipe-pages.js') }}" defer></script>
@endpush
