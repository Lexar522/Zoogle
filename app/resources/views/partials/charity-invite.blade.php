@php($charityInvite = \App\Models\ShopCharitySetting::resolved())
@if ($charityInvite['is_enabled'])
    <section class="card charity-invite" aria-label="{{ $charityInvite['header_label'] }}">
        <p class="charity-invite__text">{{ $charityInvite['order_prompt'] }}</p>
        <a class="btn charity-invite__btn" href="{{ route('charity') }}">{{ $charityInvite['header_label'] }}</a>
    </section>
@endif
