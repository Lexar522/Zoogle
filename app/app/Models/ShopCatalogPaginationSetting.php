<?php

namespace App\Models;

use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Model;

class ShopCatalogPaginationSetting extends Model
{
    public const DEFAULT_VISIBLE_PAGES = 5;

    public const DEFAULT_PER_PAGE = 24;

    public const DEFAULT_SHOW_ALL_LIMIT = 1000;

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [12, 16, 24, 48];

    protected $fillable = [
        'visible_pages',
        'show_last_arrow',
        'show_all_enabled',
        'per_page',
        'show_all_limit',
    ];

    protected function casts(): array
    {
        return [
            'visible_pages' => 'integer',
            'show_last_arrow' => 'boolean',
            'show_all_enabled' => 'boolean',
            'per_page' => 'integer',
            'show_all_limit' => 'integer',
        ];
    }

    /**
     * @var array{visible_pages: int, show_last_arrow: bool, show_all: bool, per_page: int, show_all_limit: int}|null
     */
    private static ?array $resolved = null;

    /**
     * @return array{visible_pages: int, show_last_arrow: bool, show_all: bool, per_page: int, show_all_limit: int}
     */
    public static function resolved(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $row = self::stored();

        return self::$resolved = [
            'visible_pages' => self::clampVisiblePages((int) ($row->visible_pages ?? self::DEFAULT_VISIBLE_PAGES)),
            'show_last_arrow' => $row === null ? true : (bool) $row->show_last_arrow,
            'show_all' => $row === null ? true : (bool) $row->show_all_enabled,
            'per_page' => self::clampPerPage((int) ($row->per_page ?? self::DEFAULT_PER_PAGE)),
            'show_all_limit' => self::clampShowAllLimit((int) ($row->show_all_limit ?? self::DEFAULT_SHOW_ALL_LIMIT)),
        ];
    }

    public static function perPage(): int
    {
        return self::resolved()['per_page'];
    }

    public static function showAllEnabled(): bool
    {
        return self::resolved()['show_all'];
    }

    public static function showAllLimit(): int
    {
        return self::resolved()['show_all_limit'];
    }

    public static function forgetResolvedCache(): void
    {
        self::$resolved = null;
    }

    public static function clampVisiblePages(int $value): int
    {
        return max(self::DEFAULT_VISIBLE_PAGES, min(15, $value));
    }

    public static function clampPerPage(int $value): int
    {
        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : self::DEFAULT_PER_PAGE;
    }

    public static function clampShowAllLimit(int $value): int
    {
        if ($value < 24) {
            return self::DEFAULT_SHOW_ALL_LIMIT;
        }

        return min(5000, $value);
    }

    private static function stored(): ?self
    {
        if (! SchemaCache::hasTable('shop_catalog_pagination_settings')) {
            return null;
        }

        return self::query()->orderBy('id')->first();
    }
}
