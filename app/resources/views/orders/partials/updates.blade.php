@php
    /** @var \App\Models\Order $order */
    $updates = $order->relationLoaded('updates') ? $order->updates : $order->updates()->get();
    $toneClass = $toneClass ?? 'order-updates';
@endphp

@if ($updates->isNotEmpty())
    <section class="{{ $toneClass }}" aria-labelledby="{{ $toneClass }}-title">
        <h2 id="{{ $toneClass }}-title" class="{{ $toneClass }}__title">{{ __('shop.order_updates_title') }}</h2>
        <ol class="{{ $toneClass }}__list">
            @foreach ($updates as $update)
                <li class="{{ $toneClass }}__item">
                    <div class="{{ $toneClass }}__meta">
                        <time datetime="{{ $update->created_at?->toIso8601String() }}">
                            {{ $update->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                        </time>
                        @if ($update->statusLabel())
                            <span class="{{ $toneClass }}__status">{{ $update->statusLabel() }}</span>
                        @endif
                    </div>
                    @if (filled($update->message))
                        <p class="{{ $toneClass }}__message">{{ $update->message }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endif
