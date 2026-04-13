@extends('account.layout')

@section('title', 'Замовлення '.$order->number.' — ZOOGLE')

@section('account_content')
    @include('account.partials.order-summary', ['order' => $order])

    <div class="account-back-row">
        <a class="btn secondary" href="{{ route('account.orders.index') }}">До списку замовлень</a>
    </div>
@endsection
