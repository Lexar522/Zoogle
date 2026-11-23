<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCareArticle;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\ShopCatalogPaginationSetting;
use App\Models\ShopHeaderBanner;
use App\Services\BundlePricingService;
use App\Services\CareArticleCatalog;
use App\Services\ProductShowPageService;
use App\Services\ShopAnalytics;
use App\Services\VariantPricingService;
use App\Support\CatalogCategoryTree;
use App\Support\CatalogProductsTable;
use App\Support\CatalogTitleMatch;
use App\Support\SchemaCache;
use App\Support\ShopHeaderViewData;
use App\Support\ShopSeo;
use App\Support\StorefrontCategoryTreeCache;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    /** @var list<string> */
    private const CATALOG_SORTS = ['random', 'newest', 'title_asc', 'title_desc', 'price_asc', 'price_desc', 'popular'];

    /** @var list<int> */
    private const CATALOG_PER_PAGES = [12, 16, 24, 48];

    /** Скільки останніх відгуків показувати на картці товару. */
    private const REVIEWS_SHOWN = 20;

    /** Скільки останніх питань із відповідями показувати на картці товару. */
    private const QUESTIONS_SHOWN = 20;

    public function __construct(
        private readonly ProductShowPageService $productShowPage,
        private readonly VariantPricingService $variantPricing,
        private readonly BundlePricingService $bundlePricing,
        private readonly CareArticleCatalog $careArticleCatalog,
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
     *     categoryNavigationTree: array,
     *     filters: array{q: string, category: int, on_sale: bool, sort: string, per_page: int}
     * }
     */
    private function catalogNavigationFilters(Request $request): array
    {
        $search = trim((string) $request->string('q'));

        $sort = (string) $request->string('sort', 'random');
        if (! in_array($sort, self::CATALOG_SORTS, true)) {
            $sort = 'random';
        }

        $defaultPerPage = ShopCatalogPaginationSetting::perPage();
        $perPage = (int) $request->integer('per_page', $defaultPerPage);
        if (! in_array($perPage, self::CATALOG_PER_PAGES, true)) {
            $perPage = $defaultPerPage;
        }
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $hasParentColumn = SchemaCache::hasColumn('option_values', 'parent_id');
        $hasListingCategoryParent = SchemaCache::hasColumn(CatalogProductsTable::name(), 'category_parent_value_id');
        $hasListingSubcategory = SchemaCache::hasColumn(CatalogProductsTable::name(), 'category_value_id');

        $onSaleOnly = $request->boolean('on_sale');
        $bundlesOnly = $request->boolean('bundles');
        if ($bundlesOnly) {
            $onSaleOnly = false;
        }

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

        $cachedTree = StorefrontCategoryTreeCache::forGroup($categoryGroupId, $hasParentColumn);
        $categoryValues = $cachedTree['categoryValues'];
        $categoryTree = $cachedTree['categoryTree'];
        $categoryNavigationTree = $cachedTree['categoryNavigationTree'];

        $featuredSlots = ShopHeaderViewData::featuredCategorySlots();
        $headerHub = CatalogCategoryTree::partitionForHeaderHub(
            $categoryNavigationTree,
            $featuredSlots['left'],
            $featuredSlots['right'],
        );

        /** @var list<int> $subcategoryFilterIds */
        $subcategoryFilterIds = collect($request->input('subcategories', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->filter(fn (int $id): bool => $categoryValueId <= 0
                || CatalogCategoryTree::isDescendantOfCategory($categoryTree, $categoryValueId, $id))
            ->values()
            ->all();

        $categorySubcategoryFilterGroups = $categoryValueId > 0
            ? CatalogCategoryTree::resolveSubcategoryFilterGroups(
                $categoryTree,
                $categoryValueId,
                $subcategoryFilterIds,
            )
            : [];

        $categorySubcategoryFilterGroups = CatalogCategoryTree::pruneSubcategoryFilterGroups(
            $categorySubcategoryFilterGroups,
            $categoryFilterValueIds,
        );

        if ($subcategoryFilterIds !== []) {
            $categoryFilterValueIds = CatalogCategoryTree::expandCategoryFilterIdsWithDescendants(
                $categoryTree,
                $subcategoryFilterIds,
            );
            $selectedIsParentCategory = false;
        }

        $headerBannerSlots = ShopHeaderViewData::activeBannersBySlot();
        $headerLayoutHeights = ShopHeaderViewData::layoutHeights();

        return [
            'search' => $search,
            'onSaleOnly' => $onSaleOnly,
            'bundlesOnly' => $bundlesOnly,
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
            'categoryNavigationTree' => $categoryNavigationTree,
            'categorySubcategoryFilterGroups' => $categorySubcategoryFilterGroups,
            'headerFeaturedLeft' => $headerHub['headerFeaturedLeft'],
            'headerFeaturedRight' => $headerHub['headerFeaturedRight'],
            'headerRestCategories' => $headerHub['headerRestCategories'],
            'headerBannerSlotTop' => $headerBannerSlots[ShopHeaderBanner::SLOT_TOP],
            'headerBannerSlotBottom' => $headerBannerSlots[ShopHeaderBanner::SLOT_BOTTOM],
            'headerFeaturedLeftPanelMaxHeightPx' => $headerLayoutHeights['featured_left_panel_max_height_px'],
            'headerFeaturedRightPanelMaxHeightPx' => $headerLayoutHeights['featured_right_panel_max_height_px'],
            'headerBannerHeightPx' => $headerLayoutHeights['banner_height_px'],
            'headerChipStylesCss' => ShopHeaderViewData::chipStylesInlineCss(),
            'filters' => [
                'q' => $search,
                'category' => $categoryValueId,
                'subcategories' => $subcategoryFilterIds,
                'on_sale' => $onSaleOnly,
                'bundles' => $bundlesOnly,
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
                $query,
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

                if ($query->getModel() instanceof Product && SchemaCache::hasTable('product_categories')) {
                    $outer->orWhereHas('categories', function (Builder $cats) use ($categoryFilterValueIds): void {
                        $cats->whereIn('option_values.id', $categoryFilterValueIds);
                    });
                }
            });

            return;
        }

        if ($query->getModel() instanceof Product && SchemaCache::hasTable('product_categories')) {
            $query->where(function (Builder $outer) use ($categoryFilterValueIds, $categoryGroupId): void {
                $outer->where(function (Builder $json) use ($categoryFilterValueIds, $categoryGroupId): void {
                    $this->applyCatalogVariantOptionsJsonCategoryFilter($json, $categoryFilterValueIds, $categoryGroupId);
                });
                $outer->orWhereHas('categories', function (Builder $cats) use ($categoryFilterValueIds): void {
                    $cats->whereIn('option_values.id', $categoryFilterValueIds);
                });
            });

            return;
        }

        $this->applyCatalogVariantOptionsJsonCategoryFilter($query, $categoryFilterValueIds, $categoryGroupId);
    }

    /** Версія сервера не змінюється в межах процесу, а фільтр категорії питав її двічі на запит. */
    private function databaseServerVersion(): string
    {
        static $version = null;

        return $version ??= trim((string) DB::scalar('SELECT VERSION()'));
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
            $raw = $this->databaseServerVersion();
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
                $mariadbStyleJsonContain = stripos($this->databaseServerVersion(), 'mariadb') !== false;
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
        $hasSku = SchemaCache::hasColumn($table, 'sku');
        $fuzzyIds = CatalogTitleMatch::matchingIds($table, $search);

        $q->where(function (Builder $outer) use ($driver, $hasSku, $search, $fuzzyIds): void {
            $outer->where(function (Builder $like) use ($driver, $hasSku, $search): void {
                if ($driver === 'pgsql') {
                    $pattern = '%'.$this->escapeSqlLikePattern($search).'%';
                    $like->where('title', 'ilike', $pattern);
                    if ($hasSku) {
                        $like->orWhere('sku', 'ilike', $pattern);
                    }

                    return;
                }

                if ($driver === 'mysql' || $driver === 'mariadb') {
                    $pattern = '%'.$this->escapeSqlLikePattern(mb_strtolower($search, 'UTF-8')).'%';
                    $like->whereRaw('LOWER(`title`) LIKE ?', [$pattern]);
                    if ($hasSku) {
                        $like->orWhereRaw('LOWER(COALESCE(`sku`, \'\')) LIKE ?', [$pattern]);
                    }

                    return;
                }

                $terms = $this->catalogSearchCaseFoldLikeTerms($search);
                if ($terms === []) {
                    $like->whereRaw('0 = 1');

                    return;
                }

                foreach ($terms as $i => $term) {
                    $pattern = '%'.$this->escapeSqlLikePattern($term).'%';
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $like->{$method}(function (Builder $inner) use ($pattern, $hasSku): void {
                        $inner->where('title', 'like', $pattern);
                        if ($hasSku) {
                            $inner->orWhere('sku', 'like', $pattern);
                        }
                    });
                }
            });

            if ($fuzzyIds !== []) {
                $outer->orWhereIn('id', $fuzzyIds);
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
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortCatalogRowsByRelevance(Collection $rows, string $search): Collection
    {
        return $rows
            ->map(fn (array $row): array => [
                ...$row,
                'relevance' => CatalogTitleMatch::relevance($search, mt((string) ($row['title'] ?? '')))
                    ?? CatalogTitleMatch::RELEVANCE_LAST,
            ])
            ->sort(fn (array $a, array $b): int => [$a['relevance'], -($a['popularity'] ?? 0), $a['id']]
                <=> [$b['relevance'], -($b['popularity'] ?? 0), $b['id']])
            ->values();
    }

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
     * Однакове зерно дає той самий порядок, тож сторінка 2 не повторює сторінку 1.
     * Повне завантаження сторінки бере нове зерно — оновлення перемішує список.
     *
     * @param  Collection<int, array{type: string, id: int}>  $rows
     */
    private function shuffleCatalogRows(Collection $rows, int $seed): Collection
    {
        $items = $rows->values()->all();
        $count = count($items);
        if ($count < 2) {
            return collect($items);
        }

        $state = $seed & 0x7FFFFFFF;
        if ($state === 0) {
            $state = 1;
        }

        for ($i = $count - 1; $i > 0; $i--) {
            $state = (1103515245 * $state + 12345) & 0x7FFFFFFF;
            $j = $state % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return collect($items);
    }

    private function catalogShuffleSeed(Request $request): int
    {
        if ($request->header('X-Catalog-Fragment') === '1') {
            $given = (int) $request->query('mix', 0);
            if ($given > 0 && $given <= 2_000_000_000) {
                return $given;
            }
        }

        return random_int(1, 2_000_000_000);
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
        $through = max(0, (int) $request->integer('through', 0));
        $total = $rows->count();
        $showingAll = $request->boolean('all') && ShopCatalogPaginationSetting::showAllEnabled();
        if ($showingAll) {
            $limit = max(1, ShopCatalogPaginationSetting::showAllLimit());
            $perPage = min(max($total, 1), $limit);
            if ($total > $limit) {
                $page = min($page, (int) ceil($total / $perPage));
                $items = $rows
                    ->slice(($page - 1) * $perPage, $perPage)
                    ->values();
            } else {
                $page = 1;
                $items = $rows->take($perPage)->values();
            }
        } elseif ($through > 1) {
            $page = $through;
            $items = $rows
                ->slice(0, $through * $perPage)
                ->values();
        } else {
            $items = $rows
                ->slice(($page - 1) * $perPage, $perPage)
                ->values();
        }

        $query = $request->query();
        unset($query['through']);
        if (! $showingAll) {
            unset($query['all']);
        }

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $query,
                'pageName' => 'page',
            ],
        );
    }

    private function catalogIndexData(Request $request): array
    {
        $nav = $this->catalogNavigationFilters($request);
        extract($nav);

        $rawCategoryName = '';
        if ($categoryValueId > 0 && $categoryValues instanceof Collection) {
            $rawCategoryName = (string) ($categoryValues->firstWhere('id', $categoryValueId)?->name ?? '');
        }
        $catalogSeo = ShopSeo::catalog($request, $rawCategoryName !== '' ? mt($rawCategoryName) : '');

        $shouldShowCatalogGrid = $categoryValueId > 0 || $search !== '' || $onSaleOnly || $bundlesOnly;

        $sort = $filters['sort'] ?? 'random';
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

            return array_merge($nav, [
                'listings' => $emptyListings,
                'listingQuotes' => [],
                'bundleQuotes' => [],
                'favoriteProductIds' => $this->favoriteProductIdsForUser(),
                'showCatalogGrid' => false,
                'catalogSeo' => $catalogSeo,
            ]);
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
        $productRows = $bundlesOnly
            ? collect()
            : (clone $productQuery)
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

        $combinedRows = $productRows->concat($bundleRows);
        $sort = is_string($sort) ? $sort : 'random';
        if ($sort === 'random' && $search !== '') {
            // Пошук без обраного сортування — найкращі збіги першими, а не випадковий порядок
            $request->query->remove('mix');
            $catalogRows = $this->sortCatalogRowsByRelevance($combinedRows, $search);
        } elseif ($sort === 'random') {
            $mix = $this->catalogShuffleSeed($request);
            $request->query->set('mix', $mix);
            $catalogRows = $this->shuffleCatalogRows($combinedRows, $mix);
        } else {
            $request->query->remove('mix');
            $catalogRows = $this->sortCatalogRows($combinedRows, $sort);
        }

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

        // Картка перевіряє наявність і опції за варіантами: без with() це окремий запит на кожну.
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with('variants')
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
        $bundleQuotes = $this->bundlePricing->quoteMany($currentBundles);

        if ($search !== '' && (int) $request->integer('page', 1) <= 1) {
            ShopAnalytics::recordSearch($request, $search, (int) $listings->total());
        }

        return array_merge(
            $nav,
            [
                'listings' => $listings,
                'listingQuotes' => $listingQuotes,
                'bundleQuotes' => $bundleQuotes,
                'favoriteProductIds' => $this->favoriteProductIdsForUser(),
                'showCatalogGrid' => true,
                'catalogSeo' => $catalogSeo,
            ],
        );
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
        ShopAnalytics::recordProductView($request, (int) $listing->id, (string) $listing->title);

        return view('catalog.show', array_merge(
            $this->productShowPage->buildViewData($request, $listing, $nav),
            ['favoriteProductIds' => $this->favoriteProductIdsForUser()],
            $this->reviewsViewData($listing),
            $this->questionsViewData($listing),
        ));
    }

    public function careIndex(Request $request, string $slug): View
    {
        $nav = $this->catalogNavigationFilters($request);
        $listing = $this->storefrontProductBySlug($slug);
        $careArticles = $this->careArticleCatalog->publishedForProduct($listing);

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
        $careArticles = $this->careArticleCatalog->publishedForProduct($listing);
        $article = $careArticles
            ->first(fn (ProductCareArticle $row): bool => (string) $row->slug === $articleSlug);

        abort_if(! $article, 404);

        return view('catalog.care-show', array_merge($nav, [
            'listing' => $listing,
            'article' => $article,
            'careArticles' => $careArticles,
        ]));
    }

    /**
     * Схвалені відгуки для картки товару: підсумок рахуємо з усіх, показуємо останні.
     *
     * @return array{reviews: Collection<int, ProductReview>, reviewCount: int, reviewAverage: ?float}
     */
    private function reviewsViewData(Product $listing): array
    {
        if (! SchemaCache::hasTable('product_reviews')) {
            return ['reviews' => collect(), 'reviewCount' => 0, 'reviewAverage' => null];
        }

        $summary = $listing->reviews()->approved()
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as review_average')
            ->first();
        $count = (int) ($summary?->review_count ?? 0);

        return [
            'reviews' => $count > 0 ? $listing->approvedReviews()->limit(self::REVIEWS_SHOWN)->get() : collect(),
            'reviewCount' => $count,
            'reviewAverage' => $count > 0 ? round((float) $summary->review_average, 1) : null,
        ];
    }

    /**
     * Питання покупців із відповідями магазину.
     *
     * @return array{questions: Collection<int, ProductQuestion>, questionCount: int}
     */
    private function questionsViewData(Product $listing): array
    {
        if (! SchemaCache::hasTable('product_questions')) {
            return ['questions' => collect(), 'questionCount' => 0];
        }

        $count = $listing->questions()->answered()->count();

        return [
            'questions' => $count > 0 ? $listing->answeredQuestions()->limit(self::QUESTIONS_SHOWN)->get() : collect(),
            'questionCount' => $count,
        ];
    }

    private function storefrontProductBySlug(string $slug): Product
    {
        return Product::query()
            ->where('slug', $slug)
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->with('variants')
            ->firstOrFail();
    }
}
