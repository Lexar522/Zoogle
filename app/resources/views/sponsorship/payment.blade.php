@extends('layouts.shop')

@php($opika = \App\Models\ShopSponsorshipSetting::resolved())

@section('title', $opika['texts']['payment_title'].' — ZOOGLE')
@section('robots', 'noindex,follow')

@section('content')
    <section class="opika opika-form-page" aria-labelledby="opika-payment-title">
        <div class="opika-form-card opika-payment">
            @php($isActiveNow = $sponsorship->isActiveOn(today()))
            @if ($isActiveNow)
                <p class="opika-payment__status opika-payment__status--active">Опікунство активне до {{ $sponsorship->ends_at?->format('d.m.Y') }}</p>
            @endif
            <h1 id="opika-payment-title" class="opika__title">{{ $isActiveNow ? $opika['texts']['active_title'] : $opika['texts']['payment_title'] }}</h1>

            <dl class="opika-payment__details">
                <div><dt>Номер заявки</dt><dd><strong>{{ $sponsorship->reference() }}</strong></dd></div>
                <div><dt>Тваринка</dt><dd>{{ $sponsorship->animal_name }}</dd></div>
                <div><dt>Термін</dt><dd>{{ $sponsorship->termLabel() }}</dd></div>
                <div><dt>Сума</dt><dd><strong>{{ \App\Support\ShopPrice::amount((float) $sponsorship->amount) }} ₴</strong></dd></div>
            </dl>

            @if ($sponsorship->status === \App\Models\Sponsorship::STATUS_PENDING)
                <p class="opika-payment__hint">{{ $opika['texts']['payment_hint'] }}</p>
                @if ($jarUrl !== null)
                    <p class="opika-payment__actions">
                        <a class="btn btn-buy opika-payment__pay" href="{{ $jarUrl }}" target="_blank" rel="noopener noreferrer">{{ $opika['texts']['pay_button'] }}</a>
                    </p>
                @endif
            @endif

            <div class="opika-payment__thanks info-page__body">{!! $opika['thanks_html'] !!}</div>

            @if ($sponsorship->animal)
                <p class="opika-payment__back"><a href="{{ route('sponsorship.show', $sponsorship->animal) }}">← {{ $sponsorship->animal_name }}</a></p>
            @endif
        </div>
    </section>
@endsection
