@extends('account.layout')

@section('title', __('shop.account_page_title_favorites'))

@section('account_content')
    <section class="account-card">
        <div class="account-card__head account-card__head--hero">
            <h1>{{ __('shop.account_favorites_h1') }}</h1>
        </div>
        @if ($products->isEmpty())
            <p class="account-empty">{{ __('shop.account_favorites_empty') }}</p>
        @else
            <div class="account-fav-grid">
                @foreach ($products as $product)
                    <a href="{{ route('catalog.show', $product->slug) }}" class="account-fav-card">
                        @php($photo = $product->firstCatalogPhotoPath())
                        @if ($photo)
                            <img
                                class="account-fav-card__img"
                                src="{{ \App\Support\ListingPhotoDerivative::cardUrlForPath($photo) }}"
                                alt=""
                                loading="lazy"
                            >
                        @else
                            <div class="account-fav-card__img" role="presentation"></div>
                        @endif
                        <div class="account-fav-card__body">
                            <p class="account-fav-card__title">{{ mt($product->title) }}</p>
                            @if ($product->price !== null)
                                <p class="account-fav-card__price">{{ \App\Support\ShopPrice::amount((float) $product->price) }} ₴</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="account-pagination">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
