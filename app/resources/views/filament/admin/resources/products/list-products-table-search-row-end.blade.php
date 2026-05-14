@php
    use App\Filament\Admin\Resources\Products\Tables\ProductsTable;
    use Filament\Support\Icons\Heroicon;

    $options = ProductsTable::categoryOptionsForTableFilter();
@endphp

    <div class="fi-ta-list-products-search-row__category">
        <label class="fi-sr-only">
            {{ __('Категорія') }}
        </label>

        <x-filament::input.wrapper :prefix="__('Категорія')">
            <x-filament::input.select wire:model.live="tableFilters.catalog_category_id.value">
                <option value="">{{ __('Усі категорії') }}</option>

                @foreach ($options as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    <div class="fi-ta-list-products-search-row__sort">
        <x-filament::button
            type="button"
            :icon="Heroicon::OutlinedBarsArrowDown"
            color="gray"
            wire:click="sortProductsTableByPriceDesc"
        >
            {{ __('Від дорогого до дешевого') }}
        </x-filament::button>
    </div>
</div>
