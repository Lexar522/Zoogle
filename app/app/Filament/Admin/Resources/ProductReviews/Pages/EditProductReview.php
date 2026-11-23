<?php

namespace App\Filament\Admin\Resources\ProductReviews\Pages;

use App\Filament\Admin\Resources\ProductReviews\ProductReviewResource;
use App\Filament\Admin\Resources\ProductReviews\Tables\ProductReviewActions;
use App\Models\ProductReview;
use Filament\Resources\Pages\EditRecord;

class EditProductReview extends EditRecord
{
    protected static string $resource = ProductReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductReviewActions::approve(),
            ProductReviewActions::reject(),
        ];
    }

    /**
     * Дата публікації — коли статус уперше стає «Опубліковано».
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var ProductReview $record */
        $record = $this->getRecord();
        $approved = ($data['status'] ?? null) === ProductReview::STATUS_APPROVED;

        return [
            ...$data,
            'approved_at' => $approved ? ($record->approved_at ?? now()) : null,
        ];
    }
}
