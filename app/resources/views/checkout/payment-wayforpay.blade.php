@extends('layouts.shop')

@section('title', 'Перехід до оплати — ZOOGLE')
@section('robots', 'noindex,follow')

@section('content')
    <div class="checkout-page">
        <header class="checkout-page__toolbar">
            <h1 class="checkout-page__title">Оплата замовлення</h1>
            <p class="checkout-page__lead">Зачекайте, перенаправляємо на захищену сторінку WayForPay…</p>
        </header>

        <section class="card">
            <p class="muted">Якщо перенаправлення не відбулося, натисніть кнопку нижче.</p>
            <form id="wayforpay-form" method="post" action="{{ $action }}" accept-charset="utf-8">
                @foreach ($fields as $name => $value)
                    @if (in_array($name, ['productName', 'productPrice', 'productCount'], true))
                        @continue
                    @endif
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                @foreach ($fields['productName'] as $i => $label)
                    <input type="hidden" name="productName[]" value="{{ $label }}">
                    <input type="hidden" name="productPrice[]" value="{{ $fields['productPrice'][$i] }}">
                    <input type="hidden" name="productCount[]" value="{{ $fields['productCount'][$i] }}">
                @endforeach
                <noscript>
                    <button class="btn btn-buy" type="submit">Перейти до оплати</button>
                </noscript>
            </form>
        </section>
    </div>
    @push('scripts')
        <script>
            document.getElementById('wayforpay-form').submit();
        </script>
    @endpush
@endsection
