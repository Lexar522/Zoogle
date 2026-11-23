@php($counts = $this->counts())
<x-filament-widgets::widget>
    <section class="zo-attention" aria-labelledby="zo-reviews-title">
        <h2 id="zo-reviews-title" class="zo-attention__title">Нові відгуки</h2>
        <div class="zo-home__links">
            @if ($counts['pending'] > 0)
                <a href="{{ $this->pendingUrl() }}" wire:navigate class="zo-card zo-home__link" style="--zo-tone: #ff9500">
                    <span class="zo-icon"><x-filament::icon icon="heroicon-o-star" /></span>
                    <span>
                        <strong>{{ $counts['pending'] }} {{ trans_choice('відгук чекає|відгуки чекають|відгуків чекають', $counts['pending']) }} перевірки</strong>
                        <small>«Опублікувати» — відгук з'явиться на сайті й дасть зірочки в Google</small>
                    </span>
                </a>
            @endif
            <a href="{{ $this->allUrl() }}" wire:navigate class="zo-card zo-home__link" style="--zo-tone: #007aff">
                <span class="zo-icon"><x-filament::icon icon="heroicon-o-chat-bubble-left-right" /></span>
                <span>
                    <strong>Усі відгуки ({{ $counts['total'] }})</strong>
                    <small>Опублікувати, відхилити, змінити чи видалити</small>
                </span>
            </a>
        </div>

        <ul class="zo-attention__list" role="list">
            @foreach ($this->recent() as $review)
                <li class="zo-review-row">
                    <a href="{{ $this->editUrl($review) }}" wire:navigate class="zo-attention__row">
                        <span class="zo-review-row__stars" aria-label="Оцінка {{ $review->rating }} з 5">{{ str_repeat('★', $review->rating) }}<span>{{ str_repeat('★', \App\Models\ProductReview::RATING_MAX - $review->rating) }}</span></span>
                        <span class="zo-attention__text">
                            <strong>{{ $review->author_name }} · {{ mt((string) $review->product?->title) }}</strong>
                            <small>{{ \Illuminate\Support\Str::limit($review->body, 140) }}</small>
                        </span>
                        <span class="zo-attention__badges">
                            <span class="zo-attention__badge zo-review-row__status zo-review-row__status--{{ $review->status }}">{{ \App\Models\ProductReview::STATUS_LABELS[$review->status] ?? $review->status }}</span>
                        </span>
                    </a>
                    @if ($productUrl = $this->productUrl($review))
                        <a href="{{ $productUrl }}" target="_blank" rel="noopener" class="zo-review-row__product" title="Відкрити товар на сайті">
                            <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="zo-review-row__product-icon" />
                            <span>Товар</span>
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</x-filament-widgets::widget>
