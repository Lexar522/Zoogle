@php($counts = static::counts())
<x-filament-widgets::widget>
    <section class="zo-attention" aria-labelledby="zo-attention-title">
        <h2 id="zo-attention-title" class="zo-attention__title">Віртуальна опіка: потребує уваги</h2>
        <div class="zo-home__links">
            @if ($counts['payments'] > 0)
                <a href="{{ $this->paymentsUrl() }}" wire:navigate class="zo-card zo-home__link" style="--zo-tone: #ff9500">
                    <span class="zo-icon"><x-filament::icon icon="heroicon-o-banknotes" /></span>
                    <span>
                        <strong>{{ $counts['payments'] }} {{ trans_choice('заявка|заявки|заявок', $counts['payments']) }} чекають оплату</strong>
                        <small>Перевірте банку й натисніть «Підтвердити оплату»</small>
                    </span>
                </a>
            @endif
            @if ($counts['photos'] > 0)
                <a href="{{ $this->photosUrl() }}" wire:navigate class="zo-card zo-home__link" style="--zo-tone: #34c759">
                    <span class="zo-icon"><x-filament::icon icon="heroicon-o-photo" /></span>
                    <span>
                        <strong>{{ $counts['photos'] }} фото на перевірці</strong>
                        <small>Схваліть, щоб фото з'явилось на сторінці тварини</small>
                    </span>
                </a>
            @endif
        </div>

        <ul class="zo-attention__list" role="list">
            @foreach ($this->recent() as $item)
                <li>
                    <a href="{{ $this->editUrl($item) }}" wire:navigate class="zo-attention__row">
                        @if ($item->photo_status === \App\Models\Sponsorship::PHOTO_PENDING && filled($item->photo_path))
                            <img src="{{ \App\Support\PublicStorageUrl::forPath($item->photo_path) }}" alt="" class="zo-attention__photo">
                        @else
                            <span class="zo-attention__photo zo-attention__photo--empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($item->sponsor_name, 0, 1)) }}</span>
                        @endif
                        <span class="zo-attention__text">
                            <strong>{{ $item->reference() }} · {{ $item->sponsor_name }}</strong>
                            <small>{{ $item->animal_name }} · {{ $item->termLabel() }} · {{ number_format((float) $item->amount, 0, ',', ' ') }} ₴</small>
                        </span>
                        <span class="zo-attention__badges">
                            @if ($item->status === \App\Models\Sponsorship::STATUS_PENDING)
                                <span class="zo-attention__badge zo-attention__badge--pay">Оплата</span>
                            @endif
                            @if ($item->photo_status === \App\Models\Sponsorship::PHOTO_PENDING)
                                <span class="zo-attention__badge zo-attention__badge--photo">Фото</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
</x-filament-widgets::widget>
