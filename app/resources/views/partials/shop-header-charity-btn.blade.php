@php($headerCharity = \App\Models\ShopCharitySetting::resolved())
@if ($headerCharity['is_enabled'])
    <a class="site-header__charity-btn" href="{{ route('charity') }}">{{ $headerCharity['header_label'] }}</a>
@endif
