@extends('layouts.shop')

@php
    $opika = \App\Models\ShopSponsorshipSetting::resolved();
    $authUser = auth()->user();
@endphp

@section('title', $opika['texts']['form_title'].' — '.$animal->name.' — ZOOGLE')
@section('robots', 'noindex,follow')

@section('content')
    <section class="opika opika-form-page" aria-labelledby="opika-form-title">
        <nav class="opika__back"><a href="{{ route('sponsorship.show', $animal) }}">← {{ $animal->name }}</a></nav>

        <div class="opika-form-card">
            <header class="opika-form-card__head">
                @if ($cover = $animal->coverImageUrl())
                    <img class="opika-form-card__thumb" src="{{ $cover }}" alt="" width="72" height="72">
                @endif
                <div>
                    <h1 id="opika-form-title" class="opika__title">{{ $opika['texts']['form_title'] }}</h1>
                    <p class="opika-form-card__summary">
                        {{ $animal->name }} · {{ \App\Models\ShopSponsorshipSetting::termLabel($term) }} ·
                        <strong>{{ \App\Support\ShopPrice::amount($price) }} ₴</strong>
                    </p>
                </div>
            </header>

            <form class="opika-form" method="post" action="{{ route('sponsorship.store', $animal) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="term" value="{{ $term }}">
                <div class="visually-hidden" aria-hidden="true">
                    <label for="website">Сайт</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>
                @error('term') <p class="alert error">{{ $message }}</p> @enderror

                <div class="opika-form__field">
                    <label for="sponsor_name">Ім'я та прізвище</label>
                    <input id="sponsor_name" name="sponsor_name" value="{{ old('sponsor_name', $authUser?->name) }}" required maxlength="120" autocomplete="name">
                    @error('sponsor_name') <p class="alert error">{{ $message }}</p> @enderror
                </div>
                <div class="opika-form__row">
                    <div class="opika-form__field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $authUser?->email) }}" required maxlength="160" autocomplete="email">
                        @error('email') <p class="alert error">{{ $message }}</p> @enderror
                    </div>
                    <div class="opika-form__field">
                        <label for="phone">Телефон (за бажанням)</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="50" autocomplete="tel">
                        @error('phone') <p class="alert error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="opika-form__field">
                    <label for="message">Побажання тваринці (за бажанням)</label>
                    <textarea id="message" name="message" rows="3" maxlength="1000">{{ old('message') }}</textarea>
                    @error('message') <p class="alert error">{{ $message }}</p> @enderror
                </div>
                <div class="opika-form__field">
                    <label for="photo">{{ $opika['texts']['form_photo_label'] }}</label>
                    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
                    <p class="opika-form__hint">{{ $opika['texts']['form_photo_hint'] }}</p>
                    @error('photo') <p class="alert error">{{ $message }}</p> @enderror
                </div>
                <label class="opika-form__check">
                    <input type="hidden" name="show_publicly" value="0">
                    <input type="checkbox" name="show_publicly" value="1" @checked(old('show_publicly', '1') === '1')>
                    <span>Показувати моє ім'я та фото на сторінці тварини</span>
                </label>

                <button type="submit" class="btn btn-buy opika-form__submit">{{ $opika['texts']['form_submit'] }}</button>
            </form>
        </div>
    </section>
@endsection
