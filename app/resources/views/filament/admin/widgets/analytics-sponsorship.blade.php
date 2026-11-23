@php
    use App\Filament\Admin\Resources\Sponsorships\SponsorshipResource;
    use App\Services\ShopAnalytics;

    $data = $this->insights()->sponsorship($this->insightsRange());
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Віртуальна опіка"
        description="Опікуни тварин і підтверджені оплати за обраний період."
    >
        @if ($data !== null)
            <div class="zs-mini-stats">
                <div class="zs-mini-stat">
                    <span class="zs-mini-stat__value">{{ $data['active'] }}</span>
                    <span class="zs-mini-stat__label">активних опікунів зараз</span>
                </div>
                <a class="zs-mini-stat zs-mini-stat--link" href="{{ SponsorshipResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]]) }}">
                    <span class="zs-mini-stat__value">{{ $data['pending'] }}</span>
                    <span class="zs-mini-stat__label">чекають підтвердження оплати</span>
                </a>
                <div class="zs-mini-stat">
                    <span class="zs-mini-stat__value">{{ ShopAnalytics::formatMoney($data['confirmed_sum']) }}</span>
                    <span class="zs-mini-stat__label">підтверджено за період ({{ $data['confirmed_count'] }})</span>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
