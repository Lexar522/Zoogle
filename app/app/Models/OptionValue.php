<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OptionValue extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (OptionValue $value): void {
            $group = $value->group;
            if (! $group || $group->slug !== 'category') {
                return;
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
            $value->slug = self::makeUniqueSlugForGroup(
                $groupId,
                trim((string) $value->slug) !== '' ? (string) $value->slug : (string) $value->name,
                (int) ($value->id ?? 0)
            );

            if ($groupId <= 0) {
                return;
            }

            if ($value->parent_id === null) {
                return;
            }

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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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

        while (self::query()
            ->where('option_group_id', $groupId)
            ->where('slug', $slug)
            ->when($ignoreId > 0, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function isUsedInListingsOrVariants(int $valueId): bool
    {
        $inListings = Product::query()
            ->select(['id', 'variant_options'])
            ->whereNotNull('variant_options')
            ->get()
            ->contains(function (Product $product) use ($valueId): bool {
                foreach (($product->variant_options ?? []) as $row) {
                    foreach (($row['option_value_ids'] ?? []) as $id) {
                        if ((int) $id === $valueId) {
                            return true;
                        }
                    }
                }

                return false;
            });

        if ($inListings) {
            return true;
        }

        $inProductVariants = ProductVariant::query()
            ->select(['id', 'options'])
            ->whereNotNull('options')
            ->get()
            ->contains(function (ProductVariant $variant) use ($valueId): bool {
                foreach (($variant->options ?? []) as $pair) {
                    if ((int) ($pair['option_value_id'] ?? 0) === $valueId) {
                        return true;
                    }
                }

                return false;
            });

        if ($inProductVariants) {
            return true;
        }

        return false;
    }
}
