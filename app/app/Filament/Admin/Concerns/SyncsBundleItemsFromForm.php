<?php

namespace App\Filament\Admin\Concerns;

use App\Models\Bundle;
use App\Models\BundleItem;

trait SyncsBundleItemsFromForm
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function syncBundleItemsFromForm(Bundle $bundle, array $rows): void
    {
        $bundle->items()->delete();

        $sort = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            BundleItem::query()->create([
                'bundle_id' => $bundle->id,
                'product_id' => $productId,
                'option_value_ids' => is_array($row['option_value_ids'] ?? null) ? array_values($row['option_value_ids']) : [],
                'qty' => max(1, (int) ($row['qty'] ?? 1)),
                'sort_order' => (int) ($row['sort_order'] ?? $sort),
            ]);
            $sort++;
        }
    }
}
