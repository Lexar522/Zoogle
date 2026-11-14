<x-filament-widgets::widget>
    <x-filament::section
        heading="Шлях до покупки"
        description="Скільки людей дійшло до кожного кроку і яка частка перейшла далі."
    >
        <ol class="zs-funnel">
            @foreach ($this->steps() as $step)
                <li class="zs-funnel__step">
                    <div class="zs-funnel__row">
                        <span class="zs-funnel__label">{{ $step['label'] }}</span>
                        <span class="zs-funnel__value">
                            {{ number_format($step['value'], 0, ',', ' ') }}
                            @if ($step['step_percent'] !== null)
                                <small>{{ $step['step_percent'] }}% з попереднього</small>
                            @endif
                        </span>
                    </div>
                    <div class="zs-bar"><span style="width: {{ $step['width'] }}%"></span></div>
                </li>
            @endforeach
        </ol>
    </x-filament::section>
</x-filament-widgets::widget>
