@extends('account.layout')

@section('title', 'Замовлення '.$order->number.' — ZOOGLE')

@section('account_content')
    @if (session('error'))
        <p class="alert error" role="alert" style="margin-bottom:1rem;">{{ session('error') }}</p>
    @endif
    @include('account.partials.order-summary', [
        'order' => $order,
        'liqPayConfigured' => $liqPayConfigured ?? false,
    ])

    <div class="account-back-row">
        <a class="btn secondary" href="{{ route('account.orders.index') }}">До списку замовлень</a>
    </div>
@endsection
