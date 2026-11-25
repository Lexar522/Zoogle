{{--
    Відгуки й питання покупців — одна картка з двома вкладками.
    Відгуки з'являються після перевірки в адмінці; питання — разом із відповіддю магазину.
--}}
@php
    $reviewErrors = $errors->getBag('review');
    $questionErrors = $errors->getBag('question');
    $reviewOld = fn (string $key, $default = null) => $reviewErrors->any() ? old($key, $default) : $default;
    $questionOld = fn (string $key, $default = null) => $questionErrors->any() ? old($key, $default) : $default;
    $oldRating = (int) $reviewOld('rating', 0);
    $canModerate = \App\Support\StorefrontModerators::allows(auth()->user());
    $activeTab = ($questionErrors->any() || session('question_sent') || session('question_deleted')) ? 'questions' : 'reviews';
@endphp
<section class="pdp-reviews" data-feedback aria-label="{{ __('shop.feedback_title') }}">
    <div class="pdp-feedback__tabs" role="tablist" aria-label="{{ __('shop.feedback_title') }}">
        <button type="button" role="tab" id="pdp-tab-reviews" class="pdp-feedback__tab" data-feedback-tab="reviews" aria-controls="reviews" aria-selected="{{ $activeTab === 'reviews' ? 'true' : 'false' }}" @if ($activeTab !== 'reviews') tabindex="-1" @endif>
            {{ __('shop.reviews_title') }} <span class="pdp-feedback__count">{{ $reviewCount }}</span>
        </button>
        <button type="button" role="tab" id="pdp-tab-questions" class="pdp-feedback__tab" data-feedback-tab="questions" aria-controls="questions" aria-selected="{{ $activeTab === 'questions' ? 'true' : 'false' }}" @if ($activeTab !== 'questions') tabindex="-1" @endif>
            {{ __('shop.questions_title') }} <span class="pdp-feedback__count">{{ $questionCount }}</span>
        </button>
    </div>

    {{-- ===== Відгуки ===== --}}
    <div class="pdp-feedback__panel" id="reviews" role="tabpanel" aria-labelledby="pdp-tab-reviews" @if ($activeTab !== 'reviews') hidden @endif>
        <div class="pdp-reviews__head">
            <h2 class="pdp-reviews__title">{{ __('shop.reviews_title') }}</h2>
            <div class="pdp-reviews__summary">
                @if ($reviewAverage !== null)
                    <span class="pdp-stars" role="img" aria-label="{{ __('shop.reviews_average_aria', ['rating' => number_format($reviewAverage, 1, ',', '')]) }}">
                        <span class="pdp-stars__fill" style="width: {{ $reviewAverage / \App\Models\ProductReview::RATING_MAX * 100 }}%"></span>
                    </span>
                    <strong class="pdp-reviews__average">{{ number_format($reviewAverage, 1, ',', '') }}</strong>
                @endif
                <span class="pdp-reviews__count">{{ $reviewCount > 0 ? trans_choice('shop.reviews_count', $reviewCount, ['count' => $reviewCount]) : __('shop.reviews_none') }}</span>
            </div>
        </div>

        @if (session('review_deleted'))
            <p class="pdp-reviews__notice" role="status">{{ __('shop.reviews_deleted') }}</p>
        @endif
        @if (session('review_sent'))
            <p class="pdp-reviews__notice" role="status">{{ __('shop.reviews_sent') }}</p>
        @endif

        @if ($reviews->isEmpty())
            <p class="pdp-reviews__empty">{{ __('shop.reviews_empty') }}</p>
        @else
            <ul class="pdp-reviews__list">
                @foreach ($reviews as $review)
                    <li class="pdp-review">
                        <div class="pdp-review__meta">
                            <strong class="pdp-review__name">{{ $review->publicName() }}</strong>
                            <span class="pdp-stars pdp-stars--sm" role="img" aria-label="{{ __('shop.reviews_average_aria', ['rating' => $review->rating]) }}">
                                <span class="pdp-stars__fill" style="width: {{ $review->rating / \App\Models\ProductReview::RATING_MAX * 100 }}%"></span>
                            </span>
                            @if ($review->is_verified_buyer)
                                <span class="pdp-review__verified">✓ {{ __('shop.reviews_verified') }}</span>
                            @endif
                            <time class="pdp-review__date" datetime="{{ ($review->approved_at ?? $review->created_at)->toDateString() }}">{{ ($review->approved_at ?? $review->created_at)->format('d.m.Y') }}</time>
                        </div>
                        <p class="pdp-review__body">{{ $review->body }}</p>
                        @if ($canModerate)
                            <form method="POST" action="{{ route('catalog.reviews.destroy', [$listing->slug, $review]) }}" class="pdp-review__admin" onsubmit="return confirm(@js(__('shop.reviews_delete_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="pdp-review__delete">{{ __('shop.reviews_delete') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <details class="pdp-reviews__form-wrap" @if ($reviewErrors->any()) open @endif>
            <summary class="pdp-reviews__form-toggle">{{ __('shop.reviews_form_title') }}</summary>
            <form method="POST" action="{{ route('catalog.reviews.store', $listing->slug) }}" class="pdp-reviews__form" novalidate>
                @csrf
                <div class="pdp-reviews__hp" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <label class="pdp-reviews__field">
                    <span>{{ __('shop.reviews_name') }}</span>
                    <input type="text" name="author_name" maxlength="80" required value="{{ $reviewOld('author_name', auth()->user()?->name) }}" autocomplete="given-name">
                    @if ($reviewErrors->has('author_name'))
                        <span class="pdp-reviews__error">{{ $reviewErrors->first('author_name') }}</span>
                    @endif
                </label>

                <fieldset class="pdp-reviews__rating">
                    <legend>{{ __('shop.reviews_rating') }}</legend>
                    <div class="pdp-rate">
                        @for ($star = \App\Models\ProductReview::RATING_MAX; $star >= \App\Models\ProductReview::RATING_MIN; $star--)
                            <input type="radio" id="review-rating-{{ $star }}" name="rating" value="{{ $star }}" @checked($oldRating === $star) required>
                            <label for="review-rating-{{ $star }}" title="{{ trans_choice('shop.reviews_star', $star, ['count' => $star]) }}">
                                <span class="visually-hidden">{{ trans_choice('shop.reviews_star', $star, ['count' => $star]) }}</span>★
                            </label>
                        @endfor
                    </div>
                    @if ($reviewErrors->has('rating'))
                        <p class="pdp-reviews__error">{{ $reviewErrors->first('rating') }}</p>
                    @endif
                </fieldset>

                <label class="pdp-reviews__field">
                    <span>{{ __('shop.reviews_body') }}</span>
                    <textarea name="body" rows="4" maxlength="2000" required placeholder="{{ __('shop.reviews_body_placeholder') }}">{{ $reviewOld('body') }}</textarea>
                    @if ($reviewErrors->has('body'))
                        <span class="pdp-reviews__error">{{ $reviewErrors->first('body') }}</span>
                    @endif
                </label>

                <button type="submit" class="pdp-reviews__submit">{{ __('shop.reviews_submit') }}</button>
            </form>
        </details>
    </div>

    {{-- ===== Питання ===== --}}
    <div class="pdp-feedback__panel" id="questions" role="tabpanel" aria-labelledby="pdp-tab-questions" @if ($activeTab !== 'questions') hidden @endif>
        <div class="pdp-reviews__head">
            <h2 class="pdp-reviews__title">{{ __('shop.questions_title') }}</h2>
            <span class="pdp-reviews__count">{{ $questionCount > 0 ? trans_choice('shop.questions_count', $questionCount, ['count' => $questionCount]) : __('shop.questions_none') }}</span>
        </div>

        @if (session('question_deleted'))
            <p class="pdp-reviews__notice" role="status">{{ __('shop.questions_deleted') }}</p>
        @endif
        @if (session('question_sent'))
            <p class="pdp-reviews__notice" role="status">{{ __('shop.questions_sent') }}</p>
        @endif

        @if ($questions->isEmpty())
            <p class="pdp-reviews__empty">{{ __('shop.questions_empty') }}</p>
        @else
            <ul class="pdp-reviews__list">
                @foreach ($questions as $question)
                    <li class="pdp-review pdp-question">
                        <div class="pdp-review__meta">
                            <span class="pdp-question__mark" aria-hidden="true">?</span>
                            <strong class="pdp-review__name">{{ $question->publicName() }}</strong>
                            <time class="pdp-review__date" datetime="{{ $question->created_at->toDateString() }}">{{ $question->created_at->format('d.m.Y') }}</time>
                        </div>
                        <p class="pdp-review__body">{{ $question->body }}</p>
                        <div class="pdp-question__answer">
                            <span class="pdp-question__answer-label">{{ __('shop.questions_answer_label') }}</span>
                            <p class="pdp-review__body">{{ $question->answer }}</p>
                        </div>
                        @if ($canModerate)
                            <form method="POST" action="{{ route('catalog.questions.destroy', [$listing->slug, $question]) }}" class="pdp-review__admin" onsubmit="return confirm(@js(__('shop.questions_delete_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="pdp-review__delete">{{ __('shop.questions_delete') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <details class="pdp-reviews__form-wrap" @if ($questionErrors->any()) open @endif>
            <summary class="pdp-reviews__form-toggle">{{ __('shop.questions_form_title') }}</summary>
            <form method="POST" action="{{ route('catalog.questions.store', $listing->slug) }}" class="pdp-reviews__form" novalidate>
                @csrf
                <div class="pdp-reviews__hp" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <label class="pdp-reviews__field">
                    <span>{{ __('shop.questions_body') }}</span>
                    <textarea name="body" rows="3" maxlength="1000" required placeholder="{{ __('shop.questions_body_placeholder') }}">{{ $questionOld('body') }}</textarea>
                    @if ($questionErrors->has('body'))
                        <span class="pdp-reviews__error">{{ $questionErrors->first('body') }}</span>
                    @endif
                </label>

                <div class="pdp-reviews__row">
                    <label class="pdp-reviews__field">
                        <span>{{ __('shop.reviews_name') }}</span>
                        <input type="text" name="author_name" maxlength="80" required value="{{ $questionOld('author_name', auth()->user()?->name) }}" autocomplete="given-name">
                        @if ($questionErrors->has('author_name'))
                            <span class="pdp-reviews__error">{{ $questionErrors->first('author_name') }}</span>
                        @endif
                    </label>

                    <label class="pdp-reviews__field">
                        <span>{{ __('shop.questions_email') }} <small>{{ __('shop.questions_email_hint') }}</small></span>
                        <input type="email" name="email" maxlength="160" value="{{ $questionOld('email', auth()->user()?->email) }}" autocomplete="email" inputmode="email">
                        @if ($questionErrors->has('email'))
                            <span class="pdp-reviews__error">{{ $questionErrors->first('email') }}</span>
                        @endif
                    </label>
                </div>

                <p class="pdp-reviews__note">{{ __('shop.questions_note') }}</p>
                <button type="submit" class="pdp-reviews__submit">{{ __('shop.questions_submit') }}</button>
            </form>
        </details>
    </div>
</section>
<script src="{{ \App\Support\StorefrontAsset::url('js/storefront/product-feedback.js') }}" defer></script>
