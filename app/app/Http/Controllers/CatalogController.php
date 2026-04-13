<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
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
     *     filters: array{q: string, category: int, on_sale: bool}
     * }
     */
    private function catalogNavigationFilters(Request $request): array
    {
        $search = trim((string) $request->string('q'));
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
                $rootId
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

                    $byColumns
                        ->where('category_parent_value_id', $rootId)
                        ->where(function (Builder $v) use ($categoryFilterValueIds): void {
                            $v->whereIn('category_value_id', $categoryFilterValueIds)
                                ->orWhereNull('category_value_id');
                        });
                });

                $driver = DB::connection()->getDriverName();
                $outer->orWhere(function (Builder $json) use ($categoryFilterValueIds, $driver): void {
                    if ($driver === 'mysql') {
                        $json->where(function (Builder $nested) use ($categoryFilterValueIds): void {
                            foreach ($categoryFilterValueIds as $valueId) {
                                $nested->orWhereRaw(
                                    "JSON_SEARCH(COALESCE(variant_options, JSON_ARRAY()), 'one', CAST(? as CHAR), NULL, '$[*].option_value_ids[*]') IS NOT NULL",
                                    [$valueId]
                                );
                            }
                        });
                    } else {
                        $json->where(function (Builder $nested) use ($categoryFilterValueIds): void {
                            foreach ($categoryFilterValueIds as $valueId) {
                                $nested->orWhere('variant_options', 'like', '%"option_value_ids":['.$valueId.'%')
                                    ->orWhere('variant_options', 'like', '%"option_value_ids":%'.$valueId.'%');
                            }
                        });
                    }
                });
            });

            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $query->where(function (Builder $nested) use ($categoryFilterValueIds): void {
                foreach ($categoryFilterValueIds as $valueId) {
                    $nested->orWhereRaw(
                        "JSON_SEARCH(COALESCE(variant_options, JSON_ARRAY()), 'one', CAST(? as CHAR), NULL, '$[*].option_value_ids[*]') IS NOT NULL",
                        [$valueId]
                    );
                }
            });
        } else {
            $query->where(function (Builder $nested) use ($categoryFilterValueIds): void {
                foreach ($categoryFilterValueIds as $valueId) {
                    $nested->orWhere('variant_options', 'like', '%"option_value_ids":['.$valueId.'%')
                        ->orWhere('variant_options', 'like', '%"option_value_ids":%'.$valueId.'%');
                }
            });
        }
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
                $q->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($categoryValueId > 0, function (Builder $q) use (
                $categoryValueId,
                $categoryFilterValueIds,
                $selectedIsParentCategory,
                $selectedRootCategoryId,
                $hasListingCategoryParent,
                $hasListingSubcategory
            ): void {
                $this->applyCatalogCategoryFilterToQuery(
                    $q,
                    $categoryValueId,
                    $categoryFilterValueIds,
                    $selectedIsParentCategory,
                    $selectedRootCategoryId,
                    $hasListingCategoryParent,
                    $hasListingSubcategory,
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
                $q->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($categoryValueId > 0, function (Builder $q) use (
                $categoryValueId,
                $categoryFilterValueIds,
                $selectedIsParentCategory,
                $selectedRootCategoryId,
                $hasListingCategoryParent,
                $hasListingSubcategory
            ): void {
                $this->applyCatalogCategoryFilterToQuery(
                    $q,
                    $categoryValueId,
                    $categoryFilterValueIds,
                    $selectedIsParentCategory,
                    $selectedRootCategoryId,
                    $hasListingCategoryParent,
                    $hasListingSubcategory,
                );
            });
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

        $productQuery = $this->makeProductCatalogQuery(
            $search,
            $onSaleOnly,
            $categoryValueId,
            $categoryFilterValueIds,
            $selectedIsParentCategory,
            $selectedRootCategoryId,
            $hasListingCategoryParent,
            $hasListingSubcategory,
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
        );

        $productRows = (clone $productQuery)
            ->select(['id', 'published_at', 'created_at'])
            ->get()
            ->map(fn (Product $product): array => [
                'type' => 'product',
                'id' => (int) $product->id,
                'sort_at' => (int) (($product->published_at ?? $product->created_at)?->getTimestamp() ?? 0),
            ]);

        $bundleRows = (clone $bundleQuery)
            ->select(['id', 'created_at'])
            ->get()
            ->map(fn (Bundle $bundle): array => [
                'type' => 'bundle',
                'id' => (int) $bundle->id,
                'sort_at' => (int) ($bundle->created_at?->getTimestamp() ?? 0),
            ]);

        $catalogRows = $productRows
            ->concat($bundleRows)
            ->sort(function (array $left, array $right): int {
                return [$right['sort_at'], $right['id'], $right['type']]
                    <=> [$left['sort_at'], $left['id'], $left['type']];
            })
            ->values();

        $listings = $this->paginateCatalogRows($catalogRows, $request);
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

        $favoriteProductIds = [];
        $authUser = Auth::user();
        if ($authUser !== null) {
            $productTable = (new Product)->getTable();
            $favoriteProductIds = $authUser->favoriteProducts()
                ->pluck($productTable.'.id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        return [
            'listings' => $listings,
            'listingQuotes' => $listingQuotes,
            'bundleQuotes' => $bundleQuotes,
            'categoryValues' => $categoryValues,
            'categoryTree' => $categoryTree,
            'filters' => $filters,
            'favoriteProductIds' => $favoriteProductIds,
        ];
    }

    public function show(Request $request, string $slug): View
    {
        $nav = $this->catalogNavigationFilters($request);

        $listing = Product::query()
            ->where('slug', $slug)
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->firstOrFail();

        return view('catalog.show', $this->productShowPage->buildViewData($request, $listing, $nav));
    }
}
