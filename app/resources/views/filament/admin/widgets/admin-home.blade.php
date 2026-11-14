<x-filament-widgets::widget>
    <h2 class="zo-home__title">{{ $this->greeting() }}</h2>
    <p class="zo-home__lead">Що будемо робити сьогодні?</p>
    <nav class="zo-home__links" aria-label="Швидкий перехід">
        @foreach ($this->links() as $link)
            <a href="{{ $link['url'] }}" wire:navigate class="zo-card zo-home__link" style="--zo-tone: {{ $link['tone'] }}">
                <span class="zo-icon"><x-filament::icon :icon="$link['icon']" /></span>
                <span>
                    <strong>{{ $link['label'] }}</strong>
                    <small>{{ $link['hint'] }}</small>
                </span>
            </a>
        @endforeach
    </nav>
</x-filament-widgets::widget>
