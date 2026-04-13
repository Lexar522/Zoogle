@extends('layouts.shop')

@section('title', 'Замовлення '.$order->number.' — ZOOGLE')

@section('content')
    @include('orders.partials.summary', ['order' => $order])
    <p><a class="btn" href="{{ route('catalog.index') }}">У каталог</a></p>
@endsection
