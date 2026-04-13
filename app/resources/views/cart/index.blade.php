@extends('layouts.shop')

@section('title', 'Кошик — ZOOGLE')

@section('content')
    <div class="card">
        <h1>Кошик</h1>
        <p class="muted">Сторінка лишається як резервний варіант, але основна робота з кошиком відбуватиметься у бічній панелі.</p>
    </div>

    <div class="card" data-cart-page-content>
        @include('cart.partials.drawer-content', ['items' => $items, 'summary' => $summary])
    </div>
@endsection
