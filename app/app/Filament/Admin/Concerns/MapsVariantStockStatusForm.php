<?php

namespace App\Filament\Admin\Concerns;

trait MapsVariantStockStatusForm
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveStockStatusFromRecordData(array $data): string
    {
        if (! empty($data['allows_preorder'])) {
            return 'preorder';
        }

        if (! empty($data['is_low_stock'])) {
            return 'low_stock';
        }

        if (! empty($data['is_available'])) {
            return 'in_stock';
        }

        return 'unavailable';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mapStockStatusToVariantData(array $data): array
    {
        $status = (string) ($data['stock_status'] ?? 'in_stock');

        $data['is_available'] = $status === 'in_stock' || $status === 'low_stock';
        $data['is_low_stock'] = $status === 'low_stock';
        $data['allows_preorder'] = $status === 'preorder';

        unset($data['stock_status']);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    protected function stockStatusSelectOptions(): array
    {
        return [
            'in_stock' => 'В наявності',
            'low_stock' => 'Закінчується',
            'preorder' => 'Передзамовлення',
            'unavailable' => 'Немає в наявності',
        ];
    }
}
