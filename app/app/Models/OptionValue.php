<?php

namespace App\Models;

use App\Support\CatalogProductsTable;
use App\Support\SchemaCache;
use App\Support\StorefrontCategoryTreeCache;
use App\Support\StorefrontMemo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OptionValue extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        $forgetTree = function (OptionValue $value): void {
            StorefrontMemo::flush();
            $groupId = (int) ($value->option_group_id ?? 0);
            if ($groupId > 0 && $groupId === OptionGroup::systemCategoryGroupIdForCatalog()) {
                StorefrontCategoryTreeCache::bump();
            }
        };
        static::saved($forgetTree);
        static::deleted($forgetTree);

        static::deleting(function (OptionValue $value): void {
            $group = $value->relationLoaded('group')
                ? $value->group
                : $value->group()->first();

            if (! $group || $group->slug !== 'category') {
                return;
            }

            // nullOnDelete інакше «відв’яже» дітей у корінь при видаленні батька.
            $childIds = self::query()
                ->where('parent_id', $value->id)
                ->pluck('id');

            foreach ($childIds as $childId) {
                $child = self::query()->find($childId);
                if ($child) {
                    $child->delete();
                }
            }

            if (self::isUsedInListingsOrVariants((int) $value->id)) {
                throw ValidationException::withMessages([
                    'name' => 'Цю категорію не можна видалити, поки є товари з цією категорією.',
                ]);
            }
        });

        static::saving(function (OptionValue $value): void {
            if ((int) ($value->parent_id ?? 0) <= 0) {
                $value->parent_id = null;
            }

            if ($value->parent_id !== null && (int) ($value->option_group_id ?? 0) <= 0) {
                $value->option_group_id = (int) (self::query()
                    ->whereKey((int) $value->parent_id)
                    ->value('option_group_id') ?? 0);
            }

            if (
                $value->parent_id !== null &&
                (int) ($value->id ?? 0) > 0 &&
                (int) $value->parent_id === (int) $value->id
            ) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Категорія не може бути батьківською сама для себе.',
                ]);
            }

            $groupId = (int) ($value->option_group_id ?? 0);
            $slugSource = trim((string) $value->slug) !== '' ? (string) $value->slug : (string) $value->name;
            $slugUnchanged = $value->exists
                && ! $value->isDirty('slug')
                && ! $value->isDirty('name')
                && trim((string) $value->slug) !== '';

            if (! $slugUnchanged) {
                $value->slug = self::makeUniqueSlugForGroup(
                    $groupId,
                    $slugSource,
                    (int) ($value->id ?? 0)
                );
            }

            if ($groupId <= 0) {
                return;
            }

            if ($value->parent_id === null) {
                return;
            }

            $value->pickup_only_subtree = false;
            $value->defer_online_payment = false;

            $parentExistsInSameGroup = self::query()
                ->whereKey((int) $value->parent_id)
                ->where('option_group_id', $groupId)
                ->exists();

            if (! $parentExistsInSameGroup) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Батьківська категорія має бути з цієї ж групи "Категорія".',
                ]);
            }
        });
    }

    protected $fillable = [
        'option_group_id',
        'name',
        'price',
        'color_hex',
        'parent_id',
        'slug',
        'sort_order',
        'is_active',
        'pickup_only_subtree',
        'defer_online_payment',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'pickup_only_subtree' => 'boolean',
        'defer_online_payment' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    private static function makeUniqueSlugForGroup(int $groupId, string $source, int $ignoreId = 0): string
    {
        $baseSlug = Str::slug(trim($source));
        if ($baseSlug === '') {
            $baseSlug = 'item';
        }

        if ($groupId <= 0) {
            return $baseSlug;
        }

        $slug = $baseSlug;
        $suffix = 2;

        // Швидкий шлях: якщо базовий слаг вільний — без циклу зайвих EXISTS.
        $taken = self::query()
            ->where('option_group_id', $groupId)
            ->where('slug', $slug)
            ->when($ignoreId > 0, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if (! $taken) {
            return $slug;
        }

        while ($taken) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
            $taken = self::query()
                ->where('option_group_id', $groupId)
                ->where('slug', $slug)
                ->when($ignoreId > 0, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if ($suffix > 5000) {
                return $baseSlug.'-'.Str::lower(Str::random(6));
            }
        }

        return $slug;
    }

    /**
     * Чи використовується значення категорії в товарах/варіантах/комплектах.
     * Лише SQL EXISTS (без Product::get() / Variant::get()) — інакше збереження дерева категорій
     * у Filament зависає на delete orphan під час sync repeater.
     */
    private static function isUsedInListingsOrVariants(int $valueId): bool
    {
        if ($valueId <= 0) {
            return false;
        }

        if (SchemaCache::hasTable('product_categories')) {
            if (DB::table('product_categories')->where('category_value_id', $valueId)->exists()) {
                return true;
            }
        }

        $productsTable = CatalogProductsTable::name();
        if (SchemaCache::hasColumn($productsTable, 'category_value_id')
            && Product::query()->where('category_value_id', $valueId)->exists()) {
            return true;
        }
        if (SchemaCache::hasColumn($productsTable, 'category_parent_value_id')
            && Product::query()->where('category_parent_value_id', $valueId)->exists()) {
            return true;
        }

        if (
            SchemaCache::hasTable('product_care_articles')
            && SchemaCache::hasColumn('product_care_articles', 'category_value_id')
            && DB::table('product_care_articles')->where('category_value_id', $valueId)->exists()
        ) {
            return true;
        }

        if (SchemaCache::hasTable('bundles')) {
            if (SchemaCache::hasColumn('bundles', 'category_value_id')
                && DB::table('bundles')->where('category_value_id', $valueId)->exists()) {
                return true;
            }
            if (SchemaCache::hasColumn('bundles', 'category_parent_value_id')
                && DB::table('bundles')->where('category_parent_value_id', $valueId)->exists()) {
                return true;
            }
        }

        if (SchemaCache::hasColumn($productsTable, 'variant_options')
            && self::jsonColumnContainsOptionValueId(Product::query(), 'variant_options', $valueId, 'option_value_ids')) {
            return true;
        }

        $variantTable = (new ProductVariant)->getTable();
        if (SchemaCache::hasColumn($variantTable, 'options')
            && self::jsonColumnContainsOptionValueId(ProductVariant::query(), 'options', $valueId, 'option_value_id')) {
            return true;
        }

        if (SchemaCache::hasTable('bundles') && SchemaCache::hasColumn('bundles', 'variant_options')) {
            if (self::jsonColumnContainsOptionValueId(DB::table('bundles'), 'variant_options', $valueId, 'option_value_ids')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     */
    private static function jsonColumnContainsOptionValueId(mixed $query, string $column, int $valueId, string $jsonKey): bool
    {
        $driver = DB::connection()->getDriverName();
        $id = (int) $valueId;
        $base = $query->whereNotNull($column);

        if ($driver === 'sqlite') {
            if ($jsonKey === 'option_value_ids') {
                return $base->whereRaw(
                    "EXISTS (
                        SELECT 1 FROM json_each(COALESCE({$column}, '[]')) AS je
                        WHERE json_type(je.value) = 'object'
                        AND EXISTS (
                            SELECT 1 FROM json_each(
                                COALESCE(json_extract(je.value, '$.option_value_ids'), '[]')
                            ) AS vid
                            WHERE CAST(vid.value AS INTEGER) = ?
                        )
                    )",
                    [$id]
                )->exists();
            }

            return $base->whereRaw(
                "EXISTS (
                    SELECT 1 FROM json_each(COALESCE({$column}, '[]')) AS je
                    WHERE json_type(je.value) = 'object'
                    AND CAST(json_extract(je.value, '$.option_value_id') AS INTEGER) = ?
                )",
                [$id]
            )->exists();
        }

        // MySQL/MariaDB/інше: EXISTS + LIKE з межами числа (JSON_SEARCH не бачить numeric id).
        // Це один SQL без гідрації моделей — на відміну від колишнього Product::get().
        if ($jsonKey === 'option_value_ids') {
            return $base->where(function ($q) use ($column, $id): void {
                $q->where($column, 'like', '%"option_value_ids":['.$id.']%')
                    ->orWhere($column, 'like', '%"option_value_ids":['.$id.',%')
                    ->orWhere($column, 'like', '%"option_value_ids":[%,' .$id.',%')
                    ->orWhere($column, 'like', '%"option_value_ids":[%,' .$id.']%')
                    ->orWhere($column, 'like', '%"option_value_ids": ['.$id.']%')
                    ->orWhere($column, 'like', '%"option_value_ids": ['.$id.',%')
                    ->orWhere($column, 'like', '%"option_value_ids": [%, '.$id.',%')
                    ->orWhere($column, 'like', '%"option_value_ids": [%, '.$id.']%');
            })->exists();
        }

        return $base->where(function ($q) use ($column, $id): void {
            $q->where($column, 'like', '%"option_value_id":'.$id.'}%')
                ->orWhere($column, 'like', '%"option_value_id":'.$id.',%')
                ->orWhere($column, 'like', '%"option_value_id": '.$id.'}%')
                ->orWhere($column, 'like', '%"option_value_id": '.$id.',%')
                ->orWhere($column, 'like', '%"option_value_id":'.$id.']%')
                ->orWhere($column, 'like', '%"option_value_id": '.$id.']%');
        })->exists();
    }
}
