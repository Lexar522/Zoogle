<?php

namespace App\Filament\Admin\Resources\CareArticles\Pages;

use App\Filament\Admin\Resources\CareArticles\CareArticleResource;
use App\Models\ProductCareArticle;
use App\Support\CatalogCategoryTree;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateCareArticle extends CreateRecord
{
    protected static string $resource = CareArticleResource::class;

    protected ?string $heading = 'Нова стаття';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->persistCategoryAndBody($data, requireCategory: true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function persistCategoryAndBody(array $data, bool $requireCategory): array
    {
        $data['body'] = ProductCareArticle::normalizeRichEditorValueForStorage($data['body'] ?? null);
        $deepest = CatalogCategoryTree::deepestLevelIdFromFormData($data);
        $data = CatalogCategoryTree::stripLevelFieldsFromFormData($data);

        if ($deepest <= 0 && $requireCategory) {
            throw ValidationException::withMessages([
                'category_level_1_id' => 'Оберіть напрямок статті в каталозі.',
            ]);
        }

        if ($deepest > 0) {
            $data['category_value_id'] = $deepest;
        }

        $data['product_id'] = $data['product_id'] ?? null;

        return $data;
    }
}
