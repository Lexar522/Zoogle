<?php

namespace App\Filament\Admin\Resources\CareArticles\Pages;

use App\Filament\Admin\Resources\CareArticles\CareArticleResource;
use App\Models\OptionGroup;
use App\Models\ProductCareArticle;
use App\Support\CatalogCategoryTree;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCareArticle extends EditRecord
{
    protected static string $resource = CareArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($groupId > 0) {
            $data = array_merge($data, CatalogCategoryTree::levelFieldsForFormFill($data, $groupId));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['body'] = ProductCareArticle::normalizeRichEditorValueForStorage($data['body'] ?? null);
        $deepest = CatalogCategoryTree::deepestLevelIdFromFormData($data);
        $data = CatalogCategoryTree::stripLevelFieldsFromFormData($data);

        $record = $this->getRecord();
        $hasLegacyProduct = $record instanceof ProductCareArticle && (int) ($record->product_id ?? 0) > 0;
        $hasExistingCategory = $record instanceof ProductCareArticle && (int) ($record->category_value_id ?? 0) > 0;

        if ($deepest <= 0 && ! $hasLegacyProduct && ! $hasExistingCategory) {
            throw ValidationException::withMessages([
                'category_level_1_id' => 'Оберіть напрямок статті в каталозі.',
            ]);
        }

        if ($deepest > 0) {
            $data['category_value_id'] = $deepest;
        }

        return $data;
    }
}
