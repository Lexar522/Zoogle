<?php

namespace App\Filament\Admin\Resources\ProductQuestions\Pages;

use App\Filament\Admin\Resources\ProductQuestions\ProductQuestionResource;
use App\Filament\Admin\Resources\ProductQuestions\Tables\ProductQuestionActions;
use App\Models\ProductQuestion;
use Filament\Resources\Pages\EditRecord;

class EditProductQuestion extends EditRecord
{
    protected static string $resource = ProductQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductQuestionActions::answer()->after(fn () => $this->refreshFormData(['answer', 'status'])),
            ProductQuestionActions::hide(),
        ];
    }

    /**
     * «Відповідь на сайті» без тексту відповіді не має сенсу — повертаємо в «Чекає відповіді».
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === ProductQuestion::STATUS_ANSWERED && blank($data['answer'] ?? null)) {
            $data['status'] = ProductQuestion::STATUS_PENDING;
        }
        if (($data['status'] ?? null) === ProductQuestion::STATUS_ANSWERED) {
            /** @var ProductQuestion $record */
            $record = $this->getRecord();
            $data['answered_at'] = $record->answered_at ?? now();
            $data['answered_by'] = $record->answered_by ?? auth()->id();
        }

        return $data;
    }
}
