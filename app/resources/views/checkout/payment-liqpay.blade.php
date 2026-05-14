@extends('layouts.shop')

@section('title', 'Перехід до оплати — ZOOGLE')
@section('robots', 'noindex,follow')

@section('content')
    <div class="checkout-page">
        <header class="checkout-page__toolbar">
            <h1 class="checkout-page__title">Оплата замовлення</h1>
            <p class="checkout-page__lead">Зачекайте, перенаправляємо на захищену сторінку LiqPay…</p>
        </header>

        <section class="card">
            <p class="muted">Якщо перенаправлення не відбулося, натисніть кнопку нижче.</p>
            <form id="liqpay-form" method="post" action="https://www.liqpay.ua/api/3/checkout" accept-charset="utf-8">
                <input type="hidden" name="data" value="{{ $data }}">
                <input type="hidden" name="signature" value="{{ $signature }}">
                <noscript>
                    <button class="btn btn-buy" type="submit">Перейти до оплати</button>
                </noscript>
            </form>
        </section>
    </div>
    @push('scripts')
        <script>
            document.getElementById('liqpay-form').submit();
        </script>
    @endpush
@endsection
