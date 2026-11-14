<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopHomeBenefit;
use App\Models\ShopHomeProductSection;
use App\Models\ShopHomeListItem;
use App\Services\VariantPricingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __construct(
        private readonly CatalogController $catalogController,
        private readonly VariantPricingService $variantPricing,
    ) {}

    public function index(Request $request): View
    {
        $nav = $this->catalogController->navigationFilters($request);

        $hits = $this->orderedHomeProducts(ShopHomeListItem::LIST_BESTSELLERS);
        $recommended = $this->orderedHomeProducts(ShopHomeListItem::LIST_RECOMMENDED);

        $allForQuotes = $hits->merge($recommended)->unique('id')->values();
        $listingQuotes = $this->variantPricing->quoteManyProducts($allForQuotes);

        $favoriteProductIds = $this->favoriteProductIdsForUser();

        return view('home', array_merge($nav, [
            'hitsProducts' => $hits,
            'recommendedProducts' => $recommended,
            'listingQuotes' => $listingQuotes,
            'favoriteProductIds' => $favoriteProductIds,
            'homeBenefits' => ShopHomeBenefit::forStorefront(),
            'homeProductSections' => ShopHomeProductSection::forStorefront(),
        ]));
    }

    public function sidebarPreview(Request $request): View
    {
        $nav = $this->catalogController->navigationFilters($request);
        $filterIds = array_values(array_filter(
            array_map('intval', $nav['categoryFilterValueIds'] ?? []),
            fn (int $id): bool => $id > 0
        ));

        $products = Product::query()
            ->where('is_available', true)
            ->with('variants')
            ->withCount('favoritedByUsers')
            ->when(
                $filterIds !== [] && (($nav['hasListingCategoryParent'] ?? false) || ($nav['hasListingSubcategory'] ?? false)),
                function ($query) use ($filterIds, $nav): void {
                    $query->where(function ($inner) use ($filterIds, $nav): void {
                        $hasSub = (bool) ($nav['hasListingSubcategory'] ?? false);
                        $hasParent = (bool) ($nav['hasListingCategoryParent'] ?? false);
                        if ($hasSub) {
                            $inner->whereIn('category_value_id', $filterIds);
                        }
                        if ($hasParent) {
                            $hasSub
                                ? $inner->orWhereIn('category_parent_value_id', $filterIds)
                                : $inner->whereIn('category_parent_value_id', $filterIds);
                        }
                    });
                }
            )
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        return view('preview.catalog-sidebar', array_merge($nav, [
            'previewProducts' => $products,
            'listingQuotes' => $this->variantPricing->quoteManyProducts($products),
            'favoriteProductIds' => $this->favoriteProductIdsForUser(),
            'previewOpenCategoryIds' => $this->openCategoryIds(
                $nav['categoryNavigationTree'] ?? [],
                (int) ($nav['categoryValueId'] ?? 0)
            ),
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     * @return list<int>
     */
    private function openCategoryIds(array $tree, int $selectedId): array
    {
        $open = [];
        $walk = function (array $nodes, array $parents) use (&$walk, &$open, $selectedId): void {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $id = (int) ($node['id'] ?? 0);
                $children = is_array($node['children'] ?? null) ? $node['children'] : [];
                if ($id > 0 && $id === $selectedId) {
                    foreach ($parents as $parentId) {
                        $open[$parentId] = true;
                    }
                }
                if ($children !== []) {
                    $walk($children, $id > 0 ? [...$parents, $id] : $parents);
                }
            }
        };
        $walk($tree, []);

        return array_map('intval', array_keys($open));
    }

    /**
     * @return Collection<int, Product>
     */
    private function orderedHomeProducts(string $list): Collection
    {
        $items = ShopHomeListItem::query()
            ->forList($list)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with([
                'product' => fn ($q) => $q->with('variants')->withCount('favoritedByUsers'),
            ])
            ->get();

        return $items
            ->map(fn (ShopHomeListItem $row): ?Product => $row->product)
            ->filter(fn (?Product $p): bool => $p instanceof Product);
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
}
