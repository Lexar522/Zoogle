@php($pending = static::pendingCount())
<x-filament-widgets::widget>
    <section class="zo-attention" aria-labelledby="zo-questions-title">
        <h2 id="zo-questions-title" class="zo-attention__title">Питання покупців: потрібна відповідь</h2>
        <div class="zo-home__links">
            <a href="{{ $this->pendingUrl() }}" wire:navigate class="zo-card zo-home__link" style="--zo-tone: #ff3b30">
                <span class="zo-icon"><x-filament::icon icon="heroicon-o-question-mark-circle" /></span>
                <span>
                    <strong>{{ $pending }} {{ trans_choice('питання чекає|питання чекають|питань чекають', $pending) }} відповіді</strong>
                    <small>Відповідь з'явиться на сторінці товару, а покупцю з email прийде лист</small>
                </span>
            </a>
        </div>

        <ul class="zo-attention__list" role="list">
            @foreach ($this->waiting() as $question)
                <li class="zo-review-row">
                    <a href="{{ $this->answerUrl($question) }}" wire:navigate class="zo-attention__row">
                        <span class="zo-attention__photo zo-attention__photo--empty zo-question-row__mark" aria-hidden="true">?</span>
                        <span class="zo-attention__text">
                            <strong>{{ $question->author_name }} · {{ mt((string) $question->product?->title) }}</strong>
                            <small>{{ \Illuminate\Support\Str::limit($question->body, 140) }}</small>
                        </span>
                        <span class="zo-attention__badges">
                            <span class="zo-attention__badge zo-question-row__age">{{ $question->created_at?->diffForHumans() }}</span>
                        </span>
                    </a>
                    @if ($productUrl = $this->productUrl($question))
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
