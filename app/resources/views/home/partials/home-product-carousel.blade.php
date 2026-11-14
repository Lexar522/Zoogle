@push('styles')
@vite('resources/css/storefront/home-carousel.css')
@endpush

@push('scripts')
<script src="{{ \App\Support\StorefrontAsset::url('js/storefront/home-carousel.js') }}" defer></script>
@endpush
