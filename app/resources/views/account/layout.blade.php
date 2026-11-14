@extends('layouts.shop')

@section('robots', 'noindex,follow')

@push('styles')
    @vite('resources/css/storefront/account.css')
@endpush

@section('content')
    <div class="account-page">
        <div class="account-page__grid">
            @include('account.partials.nav')
            <div class="account-main">
                @yield('account_content')
            </div>
        </div>
    </div>
@endsection
