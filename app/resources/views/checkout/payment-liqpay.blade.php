@extends('layouts.shop')

@section('title', 'Перехід до оплати — ZOOGLE')

@section('content')
    <div class="card">
        <h1>Оплата замовлення</h1>
        <p class="muted">Зачекайте, перенаправляємо на захищену сторінку LiqPay…</p>
        <form id="liqpay-form" method="post" action="https://www.liqpay.ua/api/3/checkout" accept-charset="utf-8">
            <input type="hidden" name="data" value="{{ $data }}">
            <input type="hidden" name="signature" value="{{ $signature }}">
            <noscript>
                <button class="btn btn-buy" type="submit">Перейти до оплати</button>
            </noscript>
        </form>
    </div>
    @push('scripts')
        <script>
            document.getElementById('liqpay-form').submit();
        </script>
    @endpush
@endsection
