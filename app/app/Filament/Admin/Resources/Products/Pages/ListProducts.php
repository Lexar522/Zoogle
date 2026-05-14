<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected ?string $heading = 'Товари';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Створити товар'),
        ];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function sortProductsTableByPriceDesc(): void
    {
        $this->sortTable('variant_price', 'desc');
    }
}
