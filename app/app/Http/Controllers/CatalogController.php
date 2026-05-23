<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCareArticle;
use App\Services\BundlePricingService;
use App\Services\ProductShowPageService;
use App\Services\VariantPricingService;
use App\Support\CatalogProductsTable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;

class CatalogController extends Controller
{
    /** @var list<string> */
    private const CATALOG_SORTS = ['newest', 'title_asc', 'title_desc', 'price_asc', 'price_desc', 'popular'];

    /** @var list<int> */
    private const CATALOG_PER_PAGES = [12, 16, 24, 48];

    public function __construct(
        private readonly ProductShowPageService $productShowPage,
        private readonly VariantPricingService $variantPricing,
        private readonly BundlePricingService $bundlePricing,
    ) {}

    public function index(Request $request): View|Response
    {
        $data = $this->catalogIndexData($request);

        if ($request->header('X-Catalog-Fragment') === '1') {
            return response()->view('catalog.partials.results', $data);
        }

        return view('catalog.index', $data);
    }

    /**
     * Дані навігації каталогу (дерево категорій, фільтри) без завантаження списку товарів.
     *
     * @return array<string, mixed>
     */
    public function navigationFilters(Request $request): array
    {
        return $this->catalogNavigationFilters($request);
    }

    /**
     * Дані для панелі категорій (каталог і сторінка товару).
     *
     * @return array{
     *     search: string,
     *     onSaleOnly: bool,
     *     categoryValueId: int,
     *     categoryFilterValueIds: list<int>,
     *     selectedIsParentCategory: bool,
     *     selectedRootCategoryId: int,
     *     rootCategoryIdForFilter: int,
     *     categoryGroupId: int,
     *     hasParentColumn: bool,
     *     hasListingCategoryParent: bool,
     *     hasListingSubcategory: bool,
     *     categoryValues: Collection,
     *     categoryTree: array,
     *     filters: array{q: string, category: int, on_sale: bool, sort: string, per_page: int}
     * }
     */
    private function catalogNavigationFilters(Request $request): array
    {
        $search = trim((string) $request->string('q'));

        $sort = (string) $request->string('sort', 'newest');
        if (! in_array($sort, self::CATALOG_SORTS, true)) {
            $sort = 'newest';
        }

        $perPage = (int) $request->integer('per_page', 24);
        if (! in_array($perPage, self::CATALOG_PER_PAGES, true)) {
            $perPage = 24;
        }
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $hasParentColumn = DbSchema::hasColumn('option_values', 'parent_id');
        $hasListingCategoryParent = DbSchema::hasColumn(CatalogProductsTable::name(), 'category_parent_value_id');
        $hasListingSubcategory = DbSchema::hasColumn(CatalogProductsTable::name(), 'category_value_id');

        $onSaleOnly = $request->boolean('on_sale');

        $categoryValueId = (int) $request->integer('category');
        $rootCategoryIdForFilter = 0;
        /** @var list<int> $categoryFilterValueIds */
        $categoryFilterValueIds = [];
        $selectedIsParentCategory = false;
        $selectedRootCategoryId = 0;
        if ($categoryValueId > 0 && $categoryGroupId > 0) {
            $exists = OptionValue::query()
                ->where('option_group_id', $categoryGroupId)
                ->whereKey($categoryValueId)
                ->exists();
            if (! $exists) {
                $categoryValueId = 0;
            } elseif ($hasParentColumn) {
                $allCategoryIds = OptionValue::query()
                    ->where('option_group_id', $categoryGroupId)
                    ->get(['id', 'parent_id']);

                $byId = $allCategoryIds->keyBy('id');
                $byParent = $allCategoryIds->groupBy(fn ($row): int => (int) ($row->parent_id ?? 0));
                $selected = $byId->get($categoryValueId);

                if ($selected) {
                    $selectedIsParentCategory = $selected->parent_id === null;
                    $selectedRootCategoryId = (int) $selected->id;

                    $guard = 0;
                    while ($guard < 10) {
                        $parentId = (int) ($byId->get($selectedRootCategoryId)?->parent_id ?? 0);
                        if ($parentId <= 0) {
                            break;
                        }
                        $selectedRootCategoryId = $parentId;
                        $guard++;
                    }

                    $descendantIds = [];
                    $queue = [(int) $selected->id];
                    $seen = [];
                    while ($queue !== []) {
                        $current = array_shift($queue);
                        if ($current === null || isset($seen[$current])) {
                            continue;
                        }
                        $seen[$current] = true;
                        foreach ($byParent->get((int) $current, collect()) as $childRow) {
                            $childId = (int) $childRow->id;
                            $descendantIds[] = $childId;
                            $queue[] = $childId;
                        }
                    }

                    $categoryFilterValueIds = array_values(array_unique(array_merge([(int) $selected->id], $descendantIds)));
                }
            }
        } else {
            $categoryValueId = 0;
        }
        if ($categoryValueId > 0 && $categoryFilterValueIds === []) {
            $categoryFilterValueIds = [$categoryValueId];
        }
        $rootCategoryIdForFilter = $selectedRootCategoryId > 0 ? $selectedRootCategoryId : $categoryValueId;

        $categoryValues = $categoryGroupId > 0
            ? OptionValue::query()
                ->where('option_group_id', $categoryGroupId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get($hasParentColumn ? ['id', 'name', 'parent_id'] : ['id', 'name'])
            : collect();

        $categoryTree = $hasParentColumn
            ? (function () use ($categoryValues): array {
                $byParent = $categoryValues->groupBy(fn (OptionValue $value): int => (int) ($value->parent_id ?? 0));

                $buildNode = function (OptionValue $node) use (&$buildNode, $byParent): array {
                    $children = $byParent
                        ->get((int) $node->id, collect())
                        ->map(fn (OptionValue $child): array => $buildNode($child))
                        ->values()
                        ->all();

                    return [
                        'id' => (int) $node->id,
                        'name' => (string) $node->name,
                        'children' => $children,
                    ];
                };

                return $byParent
                    ->get(0, collect())
                    ->map(fn (OptionValue $root): array => $buildNode($root))
                    ->values()
                    ->all();
            })()
            : $categoryValues
                ->map(fn (OptionValue $parent): array => [
                    'id' => (int) $parent->id,
                    'name' => (string) $parent->name,
                    'children' => [],
                ])
                ->values()
                ->all();

        return [
            'search' => $search,
            'onSaleOnly' => $onSaleOnly,
            'categoryValueId' => $categoryValueId,
            'categoryFilterValueIds' => $categoryFilterValueIds,
            'selectedIsParentCategory' => $selectedIsParentCategory,
            'selectedRootCategoryId' => $selectedRootCategoryId,
            'rootCategoryIdForFilter' => $rootCategoryIdForFilter,
            'categoryGroupId' => $categoryGroupId,
            'hasParentColumn' => $hasParentColumn,
            'hasListingCategoryParent' => $hasListingCategoryParent,
            'hasListingSubcategory' => $hasListingSubcategory,
            'categoryValues' => $categoryValues,
            'categoryTree' => $categoryTree,
            'filters' => [
                'q' => $search,
                'category' => $categoryValueId,
                'on_sale' => $onSaleOnly,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * Фільтр карток за обраною категорією в дереві (корінь / підкатегорія / під-підкатегорія):
     * колонки products + усі id з піддерева + збіг у variant_options (legacy).
     *
     * @param  list<int>  $categoryFilterValueIds
     */
    private function applyCatalogCategoryFilterToQuery(
        Builder $query,
        int $categoryValueId,
        array $categoryFilterValueIds,
        bool $selectedIsParentCategory,
        int $selectedRootCategoryId,
        bool $hasListingCategoryParent,
        bool $hasListingSubcategory,
        int $categoryGroupId,
    ): void {
        if ($categoryFilterValueIds === []) {
            return;
        }

        $rootId = $selectedRootCategoryId > 0 ? $selectedRootCategoryId : $categoryValueId;

        if ($hasListingCategoryParent && $hasListingSubcategory) {
            $query->where(function (Builder $outer) use (
                $categoryValueId,
                $categoryFilterValueIds,
                $selectedIsParentCategory,
                $rootId,
                $categoryGroupId,
            ): void {
                $outer->where(function (Builder $byColumns) use (
                    $categoryValueId,
                    $categoryFilterValueIds,
                    $selectedIsParentCategory,
                    $rootId
                ): void {
                    if ($selectedIsParentCategory) {
                        $byColumns->where('category_parent_value_id', $categoryValueId);

                        return;
                    }

                    // Листова категорія: лише явний category_value_id (або збіг у variant_options нижче).
                    // Без orWhereNull — інакше «уся група» з NULL підкатегорією потрапляє в кожну дочірню (аксесуари + тварини тощо).
                    $byColumns
                        ->where('category_parent_value_id', $rootId)
                        ->whereIn('category_value_id', $categoryFilterValueIds);
                });

                $outer->orWhere(function (Builder $json) use ($categoryFilterValueIds, $categoryGroupId): void {
                    $this->applyCatalogVariantOptionsJsonCategoryFilter($json, $categoryFilterValueIds, $categoryGroupId);
                });
            });

            return;
        }

        $this->applyCatalogVariantOptionsJsonCategoryFilter($query, $categoryFilterValueIds, $categoryGroupId);
    }

    /**
     * JSON_TABLE лишаємо лише для справжнього Oracle MySQL ≥ 8.0.4.
     * На PDO з driver=mysql але сервером MariaDB (типово для shared-хостингів) — VERSION() містить «mariadb»;
     * JSON_TABLE там часто падає з 1064, тому перехід на JSON_CONTAINS + JSON із PHP.
     */
    private function databaseSupportsCatalogJsonTable(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        try {
            $raw = trim((string) DB::scalar('SELECT VERSION()'));
            if ($raw === '' || stripos($raw, 'mariadb') !== false) {
                return false;
            }

            if (! preg_match('/^(\d+)\.(\d+)\.(\d+)/', $raw, $p)) {
                return false;
            }

            $major = (int) $p[1];
            $minor = (int) $p[2];
            $patch = (int) $p[3];

            if ($major < 8) {
                return false;
            }

            if ($major === 8 && $minor === 0 && $patch < 4) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** JSON-підряд одного блоку категорії в variant_options (для JSON_CONTAINS під MariaDB/MySQL). */
    private function encodedCatalogVariantOptionCategoryRow(int $categoryGroupId, int $categoryValueId): string
    {
        return json_encode(
            [
                'option_group_id' => $categoryGroupId,
                'option_value_ids' => [$categoryValueId],
            ],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Збіг у variant_options лише в рядку групи «Категорія» (option_group_id = системна група каталогу).
     * Інакше JSON_SEARCH знаходить id у будь-якій групі опцій і в каталозі з’являються зайві товари (наприклад «Єнот» у «аксесуарах для собак»).
     *
     * @param  list<int>  $categoryFilterValueIds
     */
    private function applyCatalogVariantOptionsJsonCategoryFilter(
        Builder $builder,
        array $categoryFilterValueIds,
        int $categoryGroupId,
    ): void {
        if ($categoryGroupId <= 0) {
            $builder->where(function (Builder $nested) use ($categoryFilterValueIds): void {
                $this->applyCatalogVariantOptionsJsonLegacyFilter($nested, $categoryFilterValueIds);
            });

            return;
        }

        $driver = DB::connection()->getDriverName();
        /** PDO driver часто mysql при реальній MariaDB — без CAST другого аргументу. */
        $mariadbStyleJsonContain = ($driver === 'mariadb');
        if ($driver === 'mysql') {
            try {
                $mariadbStyleJsonContain = stripos((string) DB::scalar('SELECT VERSION()'), 'mariadb') !== false;
            } catch (\Throwable) {
                $mariadbStyleJsonContain = false;
            }
        }

        if ($driver === 'mysql' && $this->databaseSupportsCatalogJsonTable()) {
            $builder->where(function (Builder $nested) use ($categoryFilterValueIds, $categoryGroupId): void {
                $ors = [];
                $bindings = [(int) $categoryGroupId];
                foreach ($categoryFilterValueIds as $valueId) {
                    // Скаляр як JSON через рядок: CAST(INTEGER) AS JSON на MariaDB ламається.
                    $ors[] = 'JSON_CONTAINS(COALESCE(jt.oids, JSON_ARRAY()), CAST(? AS JSON), \'$\')';
                    $bindings[] = json_encode((int) $valueId, JSON_THROW_ON_ERROR);
                }
                $sql = 'EXISTS (
                    SELECT 1 FROM JSON_TABLE(
                        COALESCE(variant_options, JSON_ARRAY()),
                        \'$[*]\' COLUMNS(
                            gid INT PATH \'$.option_group_id\',
                            oids JSON PATH \'$.option_value_ids\'
                        )
                    ) AS jt
                    WHERE jt.gid = ? AND ('.implode(' OR ', $ors).')
                )';
                $nested->whereRaw($sql, $bindings);
            });

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            /** Fallback для MariaDB і MySQL без JSON_TABLE: один JSON-параметр з PHP. */
            $blobs = [];
            foreach ($categoryFilterValueIds as $valueId) {
                try {
                    $blobs[] = $this->encodedCatalogVariantOptionCategoryRow($categoryGroupId, (int) $valueId);
                } catch (\JsonException) {
                    continue;
                }
            }

            if ($blobs !== []) {
                /** MariaDB або mysql+MariaDB-сервер: CAST(? AS JSON) ламається (1064); передаємо JSON-текст. */
                $rawTemplate = $mariadbStyleJsonContain
                    ? 'JSON_CONTAINS(COALESCE(variant_options, JSON_ARRAY()), ?, \'$\')'
                    : 'JSON_CONTAINS(COALESCE(variant_options, JSON_ARRAY()), CAST(? AS JSON), \'$\')';

                $builder->where(function (Builder $nested) use ($blobs, $rawTemplate): void {
                    $nested->where(function (Builder $group) use ($blobs, $rawTemplate): void {
                        $first = true;
                        foreach ($blobs as $blob) {
                            if ($first) {
                                $group->whereRaw($rawTemplate, [$blob]);
                                $first = false;
                            } else {
                                $group->orWhereRaw($rawTemplate, [$blob]);
                            }
                        }
                    });
                });
            }

            return;
        }

        if ($driver === 'sqlite') {
            $builder->where(function (Builder $nested) use ($categoryFilterValueIds, $categoryGroupId): void {
                $placeholders = implode(',', array_fill(0, count($categoryFilterValueIds), '?'));
                $bindings = [(int) $categoryGroupId];
                foreach ($categoryFilterValueIds as $valueId) {
                    $bindings[] = (int) $valueId;
                }
                $sql = 'EXISTS (
                    SELECT 1 FROM json_each(COALESCE(variant_options, \'[]\')) AS je
                    WHERE json_type(je.value) = \'object\'
                    AND json_extract(je.value, \'$.option_group_id\') = ?
                    AND EXISTS (
                        SELECT 1 FROM json_each(
                            COALESCE(json_extract(je.value, \'$.option_value_ids\'), \'[]\')
                        ) AS vid
                        WHERE CAST(vid.value AS INTEGER) IN ('.$placeholders.')
                    )
                )';
                $nested->whereRaw($sql, $bindings);
            });

            return;
        }

        $builder->where(function (Builder $nested) use ($categoryFilterValueIds): void {
            $this->applyCatalogVariantOptionsJsonLegacyFilter($nested, $categoryFilterValueIds);
        });
    }

    /**
     * @param  list<int>  $categoryFilterValueIds
     */
    private function applyCatalogVariantOptionsJsonLegacyFilter(Builder $nested, array $categoryFilterValueIds): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $nested->where(function (Builder $q) use ($categoryFilterValueIds): void {
                foreach ($categoryFilterValueIds as $valueId) {
                    $q->orWhereRaw(
                        "JSON_SEARCH(COALESCE(variant_options, JSON_ARRAY()), 'one', CAST(? as CHAR), NULL, '$[*].option_value_ids[*]') IS NOT NULL",
                        [$valueId]
                    );
                }
            });

            return;
        }

        $nested->where(function (Builder $q) use ($categoryFilterValueIds): void {
            foreach ($categoryFilterValueIds as $valueId) {
                $q->orWhere('variant_options', 'like', '%"option_value_ids":['.$valueId.'%')
                    ->orWhere('variant_options', 'like', '%"option_value_ids":%'.$valueId.'%');
            }
        });
    }

    /**
     * Варіанти рядка для LIKE у SQLite: LIKE там не згортає регістр для non-ASCII, а вбудована lower()
     * кирилицю не змінює — залишаємо кілька варіантів регістру з PHP (зокрема title case «Па» для «па»).
     *
     * @return list<string>
     */
    private function catalogSearchCaseFoldLikeTerms(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $terms = [
            $search,
            mb_strtolower($search, 'UTF-8'),
            mb_strtoupper($search, 'UTF-8'),
            mb_convert_case($search, MB_CASE_TITLE, 'UTF-8'),
        ];

        return array_values(array_unique(array_filter($terms, fn (string $s): bool => $s !== '')));
    }

    private function escapeSqlLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function applyCatalogTextSearchToListingQuery(Builder $q, string $search, string $table): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $driver = DB::connection()->getDriverName();
        $hasSku = DbSchema::hasColumn($table, 'sku');
        $hasSearchTags = DbSchema::hasColumn($table, 'search_tags');

        if ($driver === 'pgsql') {
            $pattern = '%'.$this->escapeSqlLikePattern($search).'%';
            $q->where(function (Builder $w) use ($pattern, $hasSku, $hasSearchTags): void {
                $w->where('title', 'ilike', $pattern)
                    ->orWhere('short_description', 'ilike', $pattern)
                    ->orWhere('description', 'ilike', $pattern);
                if ($hasSku) {
                    $w->orWhere('sku', 'ilike', $pattern);
                }
                if ($hasSearchTags) {
                    $w->orWhereRaw('CAST(search_tags AS TEXT) ILIKE ?', [$pattern]);
                }
            });

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $pattern = '%'.$this->escapeSqlLikePattern(mb_strtolower($search, 'UTF-8')).'%';
            $q->where(function (Builder $w) use ($pattern, $hasSku, $hasSearchTags): void {
                $w->whereRaw('LOWER(`title`) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(`short_description`, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(`description`, \'\')) LIKE ?', [$pattern]);
                if ($hasSku) {
                    $w->orWhereRaw('LOWER(COALESCE(`sku`, \'\')) LIKE ?', [$pattern]);
                }
                if ($hasSearchTags) {
                    $w->orWhereRaw('LOWER(CAST(`search_tags` AS CHAR(2048))) LIKE ?', [$pattern]);
                }
            });

            return;
        }

        $terms = $this->catalogSearchCaseFoldLikeTerms($search);
        if ($terms === []) {
            return;
        }

        $q->where(function (Builder $outer) use ($terms, $hasSku, $hasSearchTags): void {
            foreach ($terms as $i => $term) {
                $pattern = '%'.$this->escapeSqlLikePattern($term).'%';
                $method = $i === 0 ? 'where' : 'orWhere';
                $outer->{$method}(function (Builder $inner) use ($pattern, $hasSku, $hasSearchTags): void {
                    $inner->where('title', 'like', $pattern)
                        ->orWhere('short_description', 'like', $pattern)
                        ->orWhere('description', 'like', $pattern);
                    if ($hasSku) {
                        $inner->orWhere('sku', 'like', $pattern);
                    }
                    if ($hasSearchTags) {
                        $inner->orWhere('search_tags', 'like', $pattern);
                    }
                });
            }
        });
    }

    private function makeProductCatalogQuery(
        string $search,
        bool $onSaleOnly,
        int $categoryValueId,
        array $categoryFilterValueIds,
        bool $selectedIsParentCategory,
        int $selectedRootCategoryId,
        bool $hasListingCategoryParent,
        bool $hasListingSubcategory,
        int $categoryGroupId,
    ): Builder {
        return Product::query()
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->when($onSaleOnly, function (Builder $q): void {
                $q->whereExists(function ($sub): void {
                    VariantPricingService::bindActivePromotionExists($sub, 'product', 'products.id');
                });
            })
            ->when($search !== '', function (Builder $q) use ($search): void {
                $this->applyCatalogTextSearchToListingQuery($q, $search, CatalogProductsTable::name());
            })
            ->when($categoryValueId > 0 && $search === '', function (Builder $q) use (
                $categoryValueId,
                $categoryFilterValueIds,
                $selectedIsParentCategory,
                $selectedRootCategoryId,
                $hasListingCategoryParent,
                $hasListingSubcategory,
                $categoryGroupId
            ): void {
                $this->applyCatalogCategoryFilterToQuery(
                    $q,
                    $categoryValueId,
                    $categoryFilterValueIds,
                    $selectedIsParentCategory,
                    $selectedRootCategoryId,
                    $hasListingCategoryParent,
                    $hasListingSubcategory,
                    $categoryGroupId,
                );
            });
    }

    private function makeBundleCatalogQuery(
        string $search,
        bool $onSaleOnly,
        int $categoryValueId,
        array $categoryFilterValueIds,
        bool $selectedIsParentCategory,
        int $selectedRootCategoryId,
        bool $hasListingCategoryParent,
        bool $hasListingSubcategory,
        int $categoryGroupId,
    ): Builder {
        return Bundle::query()
            ->where('is_visible', true)
            ->where('is_active', true)
            ->when($onSaleOnly, function (Builder $q): void {
                $q->whereExists(function ($sub): void {
                    BundlePricingService::bindActivePromotionExists($sub, 'bundles.id');
                });
            })
            ->when($search !== '', function (Builder $q) use ($search): void {
                $this->applyCatalogTextSearchToListingQuery($q, $search, (new Bundle)->getTable());
            })
            ->when($categoryValueId > 0 && $search === '', function (Builder $q) use (
                $categoryValueId,
                $categoryFilterValueIds,
                $selectedIsParentCategory,
                $selectedRootCategoryId,
                $hasListingCategoryParent,
                $hasListingSubcategory,
                $categoryGroupId
            ): void {
                $this->applyCatalogCategoryFilterToQuery(
                    $q,
                    $categoryValueId,
                    $categoryFilterValueIds,
                    $selectedIsParentCategory,
                    $selectedRootCategoryId,
                    $hasListingCategoryParent,
                    $hasListingSubcategory,
                    $categoryGroupId,
                );
            });
    }

    /**
     * @param  Collection<int, array{type: string, id: int, sort_at: int, title: string, price_sort: float, popularity: int}>  $rows
     */
    private function sortCatalogRows(Collection $rows, string $sort): Collection
    {
        $cmpTitle = static function (array $a, array $b): int {
            $t = strcasecmp($a['title'] ?? '', $b['title'] ?? '');

            return $t !== 0 ? $t : ($a['id'] <=> $b['id']);
        };

        $cmpNewest = static function (array $a, array $b): int {
            return [$b['sort_at'], $b['id'], $b['type'] ?? '']
                <=> [$a['sort_at'], $a['id'], $a['type'] ?? ''];
        };

        $cmpPrice = static function (array $a, array $b): int {
            $p = ($a['price_sort'] ?? 0.0) <=> ($b['price_sort'] ?? 0.0);

            return $p !== 0 ? $p : ($a['id'] <=> $b['id']);
        };

        $cmpPopular = static function (array $a, array $b) use ($cmpNewest): int {
            $pop = ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0);

            return $pop !== 0 ? $pop : $cmpNewest($a, $b);
        };

        $sorted = match ($sort) {
            'title_asc' => $rows->sort($cmpTitle),
            'title_desc' => $rows->sort(fn (array $a, array $b): int => $cmpTitle($b, $a)),
            'price_asc' => $rows->sort($cmpPrice),
            'price_desc' => $rows->sort(fn (array $a, array $b): int => -$cmpPrice($a, $b)),
            'popular' => $rows->sort($cmpPopular),
            default => $rows->sort($cmpNewest),
        };

        return $sorted->values();
    }

    /**
     * Наближена сума комплекту: SUM(qty * product.price) по bundle_items.
     */
    private function bundleSortPriceSelectExpression(): string
    {
        $bundleTable = (new Bundle)->getTable();
        $bundleItemsTable = (new BundleItem)->getTable();
        $productsTable = (new Product)->getTable();
        $driver = DB::connection()->getDriverName();
        $mul = $driver === 'sqlite'
            ? "({$bundleItemsTable}.qty * CAST({$productsTable}.price AS REAL))"
            : "({$bundleItemsTable}.qty * CAST({$productsTable}.price AS DECIMAL(14, 4)))";

        return "(SELECT COALESCE(SUM({$mul}), 0) FROM {$bundleItemsTable} INNER JOIN {$productsTable} ON {$productsTable}.id = {$bundleItemsTable}.product_id WHERE {$bundleItemsTable}.bundle_id = {$bundleTable}.id)";
    }

    /**
     * Сума лайків (product_favorites) усіх товарів, що входять у комплект, для сортування «Популярні».
     */
    private function bundleCatalogPopularitySelectExpression(): string
    {
        $bundleTable = (new Bundle)->getTable();
        $bundleItemsTable = (new BundleItem)->getTable();
        $pf = 'product_favorites';

        return "(SELECT COUNT(*) FROM {$pf} pf WHERE EXISTS (SELECT 1 FROM {$bundleItemsTable} bi WHERE bi.bundle_id = {$bundleTable}.id AND bi.product_id = pf.product_id))";
    }

    /**
     * @param  Collection<int, array{type: string, id: int, sort_at: int}>  $rows
     * @return LengthAwarePaginator<int, array{type: string, id: int, sort_at: int}>
     */
    private function paginateCatalogRows(Collection $rows, Request $request, int $perPage = 24): LengthAwarePaginator
    {
        $page = max(1, (int) $request->integer('page', 1));
        $total = $rows->count();
        $items = $rows
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => 'page',
            ],
        );
    }

    private function catalogIndexData(Request $request): array
    {
        $nav = $this->catalogNavigationFilters($request);
        extract($nav);

        $shouldShowCatalogGrid = $categoryValueId > 0 || $search !== '' || $onSaleOnly;

        $sort = $filters['sort'] ?? 'newest';
        $perPage = (int) ($filters['per_page'] ?? 24);

        if (! $shouldShowCatalogGrid) {
            $page = max(1, (int) $request->integer('page', 1));
            $emptyListings = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                    'pageName' => 'page',
                ],
            );

            return [
                'listings' => $emptyListings,
                'listingQuotes' => [],
                'bundleQuotes' => [],
                'categoryValues' => $categoryValues,
                'categoryTree' => $categoryTree,
                'filters' => $filters,
                'favoriteProductIds' => $this->favoriteProductIdsForUser(),
                'showCatalogGrid' => false,
            ];
        }

        $productQuery = $this->makeProductCatalogQuery(
            $search,
            $onSaleOnly,
            $categoryValueId,
            $categoryFilterValueIds,
            $selectedIsParentCategory,
            $selectedRootCategoryId,
            $hasListingCategoryParent,
            $hasListingSubcategory,
            $categoryGroupId,
        );
        $bundleQuery = $this->makeBundleCatalogQuery(
            $search,
            $onSaleOnly,
            $categoryValueId,
            $categoryFilterValueIds,
            $selectedIsParentCategory,
            $selectedRootCategoryId,
            $hasListingCategoryParent,
            $hasListingSubcategory,
            $categoryGroupId,
        );

        $bundleTable = (new Bundle)->getTable();

        // select() після withCount() затирає підзапит лічильника — популярність була завжди 0.
        $productRows = (clone $productQuery)
            ->select(['id', 'title', 'price', 'published_at', 'created_at'])
            ->withCount('favoritedByUsers')
            ->get()
            ->map(fn (Product $product): array => [
                'type' => 'product',
                'id' => (int) $product->id,
                'sort_at' => (int) (($product->published_at ?? $product->created_at)?->getTimestamp() ?? 0),
                'title' => (string) $product->title,
                'price_sort' => (float) $product->price,
                'popularity' => (int) ($product->favorited_by_users_count ?? 0),
            ]);

        $sortPriceSql = $this->bundleSortPriceSelectExpression();
        $popularitySql = $this->bundleCatalogPopularitySelectExpression();

        $bundleRows = (clone $bundleQuery)
            ->select([
                "{$bundleTable}.id",
                "{$bundleTable}.title",
                "{$bundleTable}.created_at",
            ])
            ->selectRaw("{$sortPriceSql} as sort_price")
            ->selectRaw("{$popularitySql} as catalog_popularity")
            ->get()
            ->map(fn (Bundle $bundle): array => [
                'type' => 'bundle',
                'id' => (int) $bundle->id,
                'sort_at' => (int) ($bundle->created_at?->getTimestamp() ?? 0),
                'title' => (string) $bundle->title,
                'price_sort' => (float) ($bundle->sort_price ?? 0.0),
                'popularity' => (int) ($bundle->catalog_popularity ?? 0),
            ]);

        $catalogRows = $this->sortCatalogRows(
            $productRows->concat($bundleRows),
            is_string($sort) ? $sort : 'newest'
        );

        $listings = $this->paginateCatalogRows($catalogRows, $request, $perPage);
        $pageRows = collect($listings->items());

        $productIds = $pageRows
            ->where('type', 'product')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $bundleIds = $pageRows
            ->where('type', 'bundle')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->withCount('favoritedByUsers')
            ->get()
            ->keyBy(fn (Product $product): int => (int) $product->id);
        $bundles = Bundle::query()
            ->with(['items.product.variants'])
            ->whereIn('id', $bundleIds)
            ->get()
            ->keyBy(fn (Bundle $bundle): int => (int) $bundle->id);

        $listings->setCollection(
            $pageRows
                ->map(function (array $row) use ($products, $bundles): Product|Bundle|null {
                    return $row['type'] === 'bundle'
                        ? $bundles->get((int) $row['id'])
                        : $products->get((int) $row['id']);
                })
                ->filter()
                ->values()
        );

        $currentProducts = collect($listings->items())
            ->filter(fn ($record): bool => $record instanceof Product)
            ->values();
        $currentBundles = collect($listings->items())
            ->filter(fn ($record): bool => $record instanceof Bundle)
            ->values();

        $listingQuotes = $this->variantPricing->quoteManyProducts($currentProducts);
        $bundleQuotes = [];
        foreach ($currentBundles as $bundle) {
            $bundleQuotes[(int) $bundle->id] = $this->bundlePricing->quote($bundle);
        }

        return [
            'listings' => $listings,
            'listingQuotes' => $listingQuotes,
            'bundleQuotes' => $bundleQuotes,
            'categoryValues' => $categoryValues,
            'categoryTree' => $categoryTree,
            'filters' => $filters,
            'favoriteProductIds' => $this->favoriteProductIdsForUser(),
            'showCatalogGrid' => true,
        ];
    }

    /**
     * @return list<int>
     */
    private function favoriteProductIdsForUser(): array
    {
        $authUser = Auth::user();
        if ($authUser === null) {
            return [];
        }

        $productTable = (new Product)->getTable();

        return $authUser->favoriteProducts()
            ->pluck($productTable.'.id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function show(Request $request, string $slug): View
    {
        $nav = $this->catalogNavigationFilters($request);

        $listing = $this->storefrontProductBySlug($slug);
        $listing->loadCount('favoritedByUsers');

        return view('catalog.show', array_merge(
            $this->productShowPage->buildViewData($request, $listing, $nav),
            ['favoriteProductIds' => $this->favoriteProductIdsForUser()],
        ));
    }

    public function careIndex(Request $request, string $slug): View
    {
        $nav = $this->catalogNavigationFilters($request);
        $listing = $this->storefrontProductBySlug($slug);
        $careArticles = $listing->publishedCareArticles()->get();

        abort_if($careArticles->isEmpty(), 404);

        return view('catalog.care-index', array_merge($nav, [
            'listing' => $listing,
            'careArticles' => $careArticles,
        ]));
    }

    public function careShow(Request $request, string $slug, string $articleSlug): View
    {
        $nav = $this->catalogNavigationFilters($request);
        $listing = $this->storefrontProductBySlug($slug);
        $careArticles = $listing->publishedCareArticles()->get();
        $article = $careArticles
            ->first(fn (ProductCareArticle $row): bool => (string) $row->slug === $articleSlug);

        abort_if(! $article, 404);

        return view('catalog.care-show', array_merge($nav, [
            'listing' => $listing,
            'article' => $article,
            'careArticles' => $careArticles,
        ]));
    }

    private function storefrontProductBySlug(string $slug): Product
    {
        return Product::query()
            ->where('slug', $slug)
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->firstOrFail();
    }
}
