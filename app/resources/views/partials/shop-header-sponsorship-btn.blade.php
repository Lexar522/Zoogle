@if (\App\Models\ShopSponsorshipSetting::isEnabled())
    <a class="site-header__charity-btn site-header__charity-btn--opika" href="{{ route('sponsorship.index') }}">{{ \App\Models\ShopSponsorshipSetting::text('header_label') }}</a>
@endif
