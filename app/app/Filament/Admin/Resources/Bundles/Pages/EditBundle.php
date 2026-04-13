<?php

namespace App\Filament\Admin\Resources\Bundles\Pages;

use App\Filament\Admin\Concerns\HandlesListingVariantOptions;
use App\Filament\Admin\Concerns\PreparesBundleFormCatalogData;
use App\Filament\Admin\Concerns\SyncsBundleItemsFromForm;
use App\Filament\Admin\Resources\Bundles\BundleResource;
use App\Models\Bundle;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditBundle extends EditRecord
{
    use HandlesListingVariantOptions;
    use PreparesBundleFormCatalogData;
    use SyncsBundleItemsFromForm;

    protected static string $resource = BundleResource::class;

    /** @var list<array<string, mixed>>|null */
    protected ?array $pendingBundleItems = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function listingForVariantOptionsMerge(): ?Product
    {
        return null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->hydrateBundleFormDataForFill($data);

        $record = $this->getRecord();
        if ($record instanceof Bundle) {
            $data['items'] = $record->items()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'qty' => $item->qty,
                    'sort_order' => $item->sort_order,
                ])
                ->all();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $rawItems = $data['items'] ?? [];
        unset($data['items']);

        $validItems = [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $productId = (int) ($row['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $validItems[] = [
                    'product_id' => $productId,
                    'qty' => max(1, (int) ($row['qty'] ?? 1)),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];
            }
        }

        if ($validItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Додайте хоча б один товар у комплект (оберіть товар у позиції).',
            ]);
        }

        $this->pendingBundleItems = $validItems;

        return $this->prepareBundleFormDataBeforePersist($data);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record instanceof Bundle && $this->pendingBundleItems !== null) {
            $this->syncBundleItemsFromForm($record, $this->pendingBundleItems);
            $this->pendingBundleItems = null;
        }
    }
}
