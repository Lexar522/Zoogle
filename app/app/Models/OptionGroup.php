<?php

namespace App\Models;

use App\Support\CatalogCategoryTree;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class OptionGroup extends Model
{
    use HasFactory;

    /**
     * Значення `product_type` для загального каталогу в `option_groups` та `products`.
     */
    public const CATALOG_PRODUCT_TYPE = 'product';

    /**
     * Id системної групи «Категорія» для основного каталогу.
     */
    public static function systemCategoryGroupIdForCatalog(): int
    {
        return self::systemCategoryGroupId(self::CATALOG_PRODUCT_TYPE);
    }

    /**
     * @return list<string>
     */
    public static function catalogListingProductTypes(): array
    {
        return [self::CATALOG_PRODUCT_TYPE];
    }

    protected static function booted(): void
    {
        static::deleting(function (OptionGroup $group): void {
            if ($group->slug !== 'category') {
                return;
            }

            throw ValidationException::withMessages([
                'name' => 'Системну групу "Категорія" видаляти не можна. Дозволено тільки редагувати її значення.',
            ]);
        });

        static::updating(function (OptionGroup $group): void {
            if ($group->slug === 'category' && $group->isDirty(['product_type', 'slug', 'selection_mode', 'value_type'])) {
                throw ValidationException::withMessages([
                    'slug' => 'Системну групу "Категорія" не можна змінювати. Можна лише керувати її значеннями.',
                ]);
            }

            if (! $group->isDirty('value_type')) {
                return;
            }

            if (! $group->values()->exists()) {
                return;
            }

            $original = (string) $group->getOriginal('value_type');
            $new = (string) $group->value_type;

            // Після створення значень можна перейти з «текст» на «колір» (додасться color_hex у формах).
            if ($original === 'text' && $new === 'color') {
                return;
            }

            // З «колір» на «текст» — лише якщо ще не задавали hex (інакше втрата сенсу для вітрини).
            if ($original === 'color' && $new === 'text') {
                $hasHex = $group->values()
                    ->whereNotNull('color_hex')
                    ->where('color_hex', '!=', '')
                    ->exists();

                if ($hasHex) {
                    throw ValidationException::withMessages([
                        'value_type' => 'Неможливо перейти на «текст»: у значень уже задано колір. Спочатку очистіть колір у значеннях або залиште формат «колір».',
                    ]);
                }

                return;
            }

            throw ValidationException::withMessages([
                'value_type' => 'Тип значення не можна змінити для цієї групи з уже створеними значеннями.',
            ]);
        });
    }

    protected $fillable = [
        'product_type',
        'name',
        'slug',
        'selection_mode',
        'value_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class, 'option_group_id');
    }

    /**
     * Ключі product_type з довідника груп опцій для цього оголошення:
     * базовий product + slug-и всіх рівнів дерева категорії товару (корінь → лист).
     *
     * @return list<string>
     */
    public static function productTypeScopeKeysForProduct(Product $listing): array
    {
        $keys = [self::CATALOG_PRODUCT_TYPE];

        $categoryGroupId = self::systemCategoryGroupIdForCatalog();

        if ($categoryGroupId <= 0) {
            return array_values(array_unique(array_filter($keys, fn (string $k): bool => $k !== '')));
        }

        $valueId = (int) ($listing->category_value_id ?? 0);
        if ($valueId <= 0) {
            $valueId = (int) ($listing->category_parent_value_id ?? 0);
        }

        if ($valueId <= 0) {
            foreach ($listing->variant_options ?? [] as $row) {
                if ((int) ($row['option_group_id'] ?? 0) !== $categoryGroupId) {
                    continue;
                }
                $first = collect($row['option_value_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->first();
                if ($first) {
                    $valueId = $first;
                    break;
                }
            }
        }

        $seen = [];
        while ($valueId > 0 && ! isset($seen[$valueId])) {
            $seen[$valueId] = true;
            $v = OptionValue::query()
                ->whereKey($valueId)
                ->where('option_group_id', $categoryGroupId)
                ->first(['id', 'parent_id', 'slug']);
            if (! $v) {
                break;
            }
            $slug = trim((string) ($v->slug ?? ''));
            if ($slug !== '') {
                $keys[] = $slug;
            }
            $valueId = (int) ($v->parent_id ?? 0);
        }

        return array_values(array_unique(array_filter($keys, fn (string $k): bool => $k !== '')));
    }

    public static function systemCategoryGroupId(?string $productType = null): int
    {
        return (int) (self::query()
            ->where('slug', 'category')
            ->when($productType !== null && $productType !== '', fn ($q) => $q->where('product_type', $productType))
            ->where('is_active', true)
            ->value('id') ?? 0);
    }

    /**
     * Обчислити product_type для збереження з полів форми (applies_mode + до 5 рівнів категорії).
     *
     * @param  array<string, mixed>  $data
     */
    public static function productTypeFromAppliesFormState(array $data): string
    {
        $mode = (string) ($data['applies_mode'] ?? self::CATALOG_PRODUCT_TYPE);

        if ($mode !== 'category') {
            return self::CATALOG_PRODUCT_TYPE;
        }

        $deepest = 0;
        for ($i = CatalogCategoryTree::MAX_DEPTH; $i >= 1; $i--) {
            $v = (int) ($data['scope_category_level_'.$i.'_id'] ?? 0);
            if ($v > 0) {
                $deepest = $v;
                break;
            }
        }

        if ($deepest <= 0) {
            return self::CATALOG_PRODUCT_TYPE;
        }

        $categoryGroupId = self::systemCategoryGroupId();
        if ($categoryGroupId <= 0) {
            return self::CATALOG_PRODUCT_TYPE;
        }

        $slug = OptionValue::query()
            ->whereKey($deepest)
            ->where('option_group_id', $categoryGroupId)
            ->value('slug');

        $slug = trim((string) $slug);

        return $slug !== '' ? $slug : self::CATALOG_PRODUCT_TYPE;
    }

    /**
     * Поля форми для заповнення applies_mode + рівнів категорії зі збереженого product_type.
     *
     * @return array<string, mixed>
     */
    public static function appliesFormStateFromProductType(string $productType): array
    {
        $productType = trim($productType);

        $emptyLevels = [];
        for ($i = 1; $i <= CatalogCategoryTree::MAX_DEPTH; $i++) {
            $emptyLevels['scope_category_level_'.$i.'_id'] = null;
        }

        if ($productType === 'accessory') {
            $productType = self::CATALOG_PRODUCT_TYPE;
        }

        if ($productType === '' || $productType === self::CATALOG_PRODUCT_TYPE) {
            return array_merge([
                'applies_mode' => self::CATALOG_PRODUCT_TYPE,
            ], $emptyLevels);
        }

        $categoryGroupId = self::systemCategoryGroupId();
        if ($categoryGroupId <= 0) {
            return array_merge([
                'applies_mode' => self::CATALOG_PRODUCT_TYPE,
            ], $emptyLevels);
        }

        $node = OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->where('slug', $productType)
            ->first(['id']);

        if (! $node) {
            return array_merge([
                'applies_mode' => self::CATALOG_PRODUCT_TYPE,
            ], $emptyLevels);
        }

        $deepestId = (int) $node->id;
        $chain = CatalogCategoryTree::ancestorsChainFromNode($deepestId, $categoryGroupId);

        $levels = $emptyLevels;
        foreach ($chain as $idx => $id) {
            if ($idx >= CatalogCategoryTree::MAX_DEPTH) {
                break;
            }
            $levels['scope_category_level_'.($idx + 1).'_id'] = $id;
        }

        return array_merge([
            'applies_mode' => 'category',
        ], $levels);
    }
}
