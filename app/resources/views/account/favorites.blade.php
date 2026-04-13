@extends('account.layout')

@section('title', 'Обране — ZOOGLE')

@section('account_content')
    <section class="account-card">
        <div class="account-card__head account-card__head--hero">
            <h1>Обране</h1>
        </div>
        @if ($products->isEmpty())
            <p class="account-empty" style="margin:0;">Немає збережених товарів. Додавайте сердечком у каталозі.</p>
        @else
            <div class="account-fav-grid">
                @foreach ($products as $product)
                    <a href="{{ route('catalog.show', $product->slug) }}" class="account-fav-card">
                        @php($photo = $product->firstCatalogPhotoPath())
                        @if ($photo)
                            <img
                                class="account-fav-card__img"
                                src="{{ asset('storage/'.$photo) }}"
                                alt=""
                                loading="lazy"
                            >
                        @else
                            <div class="account-fav-card__img" role="presentation"></div>
                        @endif
                        <div class="account-fav-card__body">
                            <p class="account-fav-card__title">{{ $product->title }}</p>
                            @if ($product->price !== null)
                                <p class="account-fav-card__price">{{ number_format((float) $product->price, 2) }} UAH</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
            <div style="margin-top:1.1rem;">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
