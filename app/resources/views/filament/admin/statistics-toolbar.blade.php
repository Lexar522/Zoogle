@php
    $current = (string) ($getLivewire()->filters['range'] ?? '7');
    $groups = \App\Services\ShopAnalytics::groupedRangeOptions();
    $quick = $groups['Період'] ?? [];
    $quarters = $groups['Квартали'] ?? [];
    $isQuarter = array_key_exists($current, $quarters);
@endphp

<div class="zs-toolbar">
    <div class="zs-segmented" role="group" aria-label="Період">
        @foreach ($quick as $value => $label)
            <button
                type="button"
                class="zs-segmented__item @if ($current === (string) $value) is-active @endif"
                aria-pressed="{{ $current === (string) $value ? 'true' : 'false' }}"
                wire:click="$set('filters.range', @js((string) $value))"
            >{{ $label }}</button>
        @endforeach
    </div>

    <label class="zs-quarter @if ($isQuarter) is-active @endif">
        <select
            wire:key="zs-quarter-{{ $current }}"
            aria-label="Квартал"
            x-on:change="$wire.set('filters.range', $event.target.value)"
        >
            <option value="" disabled @selected(! $isQuarter)>Квартал</option>
            @foreach ($quarters as $value => $label)
                <option value="{{ $value }}" @selected($current === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <span class="zs-toolbar__spinner" wire:loading wire:target="filters" aria-label="Оновлення"></span>
</div>
