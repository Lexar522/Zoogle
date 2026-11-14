@php
    use App\Filament\Admin\Resources\Products\Tables\ProductsTable;
    use App\Models\Product;
    use Filament\Support\Icons\Heroicon;

    $options = ProductsTable::categoryOptionsForTableFilter();
    $stockOptions = Product::stockStatusOptions();
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

    <div class="fi-ta-list-products-search-row__stock">
        <label class="fi-sr-only">
            {{ __('Наявність') }}
        </label>

        <x-filament::input.wrapper :prefix="__('Наявність')">
            <x-filament::input.select wire:model.live="tableFilters.stock_status.value">
                <option value="">{{ __('Усі статуси') }}</option>

                @foreach ($stockOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    <div class="fi-ta-list-products-search-row__vitrine">
        <label class="fi-sr-only">
            {{ __('На вітрині') }}
        </label>

        <x-filament::input.wrapper :prefix="__('На вітрині')">
            <x-filament::input.select wire:model.live="tableFilters.is_available.value">
                <option value="">{{ __('Усі') }}</option>
                <option value="1">{{ __('Так') }}</option>
                <option value="0">{{ __('Ні') }}</option>
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
