@if (! empty($hasShopHeaderContacts))
    {{-- Контакти й соцмережі: кругла кнопка в правому нижньому куті, список розкривається над нею --}}
    <aside class="floating-contacts" data-floating-contacts aria-label="{{ __('shop.floating_contacts_title') }}">
        <div id="floating-contacts-list" class="floating-contacts__panel" data-floating-contacts-panel hidden>
            <p class="floating-contacts__title">{{ __('shop.floating_contacts_title') }}</p>
            <div class="floating-contacts__list">
                @include('partials.shop-header-contacts', ['contactIconsColored' => true])
            </div>
        </div>
        <button type="button" class="floating-contacts__toggle" aria-expanded="false" aria-controls="floating-contacts-list" aria-label="{{ __('shop.floating_contacts_open') }}" data-label-open="{{ __('shop.floating_contacts_open') }}" data-label-close="{{ __('shop.floating_contacts_close') }}" data-floating-contacts-toggle>
            <svg class="floating-contacts__icon floating-contacts__icon--open floating-contacts__icon--phone" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1.02l-2.2 2.2z"/></svg>
            <svg class="floating-contacts__icon floating-contacts__icon--close" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.3 5.71 12 12.01l-6.3-6.3-1.41 1.41 6.3 6.3-6.3 6.3 1.41 1.41 6.3-6.3 6.3 6.3 1.41-1.41-6.3-6.3 6.3-6.3z"/></svg>
        </button>
    </aside>
@endif
