<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
        ]));
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
                'product' => fn ($q) => $q->withCount('favoritedByUsers'),
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
