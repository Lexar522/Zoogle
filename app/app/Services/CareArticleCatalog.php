<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCareArticle;
use App\Support\CatalogCategoryTree;
use App\Support\SchemaCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CareArticleCatalog
{
    /**
     * Опубліковані статті товару: власні + успадковані з категорії та предків.
     *
     * @return Collection<int, ProductCareArticle>
     */
    public function publishedForProduct(Product $product): Collection
    {
        $product->loadMissing('categories');

        if (! $this->productIsInAnimalBranch($product)) {
            return collect();
        }

        $query = ProductCareArticle::query()->published();
        $categoryIds = $this->matchingCategoryIdsForProduct($product);

        $query->where(function (Builder $nested) use ($product, $categoryIds): void {
            $nested->where('product_id', $product->id);
            if ($categoryIds !== []) {
                $nested->orWhereIn('category_value_id', $categoryIds);
            }
        });

        return $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, ProductCareArticle>
     */
    public function randomPublished(int $limit = 4): Collection
    {
        if ($limit <= 0 || ! SchemaCache::hasTable('product_care_articles')) {
            return collect();
        }

        return ProductCareArticle::query()
            ->published()
            ->with('category')
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<int, array{id: int, name: string, children?: array<int, array<string, mixed>>}>  $categoryTree
     * @return LengthAwarePaginator<int, ProductCareArticle>
     */
    public function paginateLibrary(
        ?string $search = null,
        ?int $categoryId = null,
        int $perPage = 12,
        array $categoryTree = [],
    ): LengthAwarePaginator {
        $query = ProductCareArticle::query()
            ->published()
            ->with('category')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $q = trim((string) $search);
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function (Builder $nested) use ($like): void {
                $nested
                    ->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('body', 'like', $like);
            });
        }

        $animalIds = $this->animalCategoryIds($categoryTree);

        if ($categoryId !== null && $categoryId > 0) {
            if ($animalIds !== [] && ! in_array($categoryId, $animalIds, true)) {
                $query->whereRaw('0 = 1');
            } else {
                $ids = $this->categoryFilterIds($categoryId);
                if ($ids === []) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->whereIn('category_value_id', $ids);
                }
            }
        } elseif ($animalIds !== []) {
            $query->whereIn('category_value_id', $animalIds);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Гілка «Тварини» для хаба порад: категорії та підкатегорії без інших напрямків каталогу.
     *
     * @param  array<int, array{id: int, name: string, children?: array<int, array<string, mixed>>}>  $categoryTree
     * @return list<array{id: int, name: string, children: list<array{id: int, name: string}>}>
     */
    public function animalCareGroups(array $categoryTree): array
    {
        $root = $this->findAnimalsRoot($categoryTree);
        $nodes = $root !== null
            ? (is_array($root['children'] ?? null) && $root['children'] !== []
                ? $root['children']
                : [$root])
            : $categoryTree;

        return array_values(array_map(function (array $node): array {
            $children = [];
            foreach ($node['children'] ?? [] as $child) {
                $childId = (int) ($child['id'] ?? 0);
                if ($childId <= 0) {
                    continue;
                }
                $children[] = [
                    'id' => $childId,
                    'name' => (string) ($child['name'] ?? ''),
                ];
            }

            return [
                'id' => (int) ($node['id'] ?? 0),
                'name' => (string) ($node['name'] ?? ''),
                'children' => $children,
            ];
        }, $nodes));
    }

    /**
     * @param  array<int, array{id: int, name: string, children?: array<int, array<string, mixed>>}>  $categoryTree
     * @return list<int>
     */
    public function animalCategoryIds(array $categoryTree): array
    {
        $root = $this->findAnimalsRoot($categoryTree);
        if ($root === null) {
            return [];
        }

        return $this->collectNodeIds($root);
    }

    /**
     * @param  array<int, array{id: int, name: string, children?: array<int, array<string, mixed>>}>  $categoryTree
     * @return array{id: int, name: string, children?: array<int, array<string, mixed>>}|null
     */
    public function findAnimalsRoot(array $categoryTree): ?array
    {
        $found = $this->firstAnimalsNode($categoryTree);
        if ($found !== null) {
            return $found;
        }

        $ids = [];
        foreach ($categoryTree as $node) {
            $id = (int) ($node['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return null;
        }

        $slugs = OptionValue::query()
            ->whereIn('id', $ids)
            ->pluck('slug', 'id');

        foreach ($categoryTree as $node) {
            $slug = mb_strtolower((string) ($slugs[(int) ($node['id'] ?? 0)] ?? ''));
            if ($this->slugLooksLikeAnimals($slug)) {
                return $node;
            }
        }

        return null;
    }

    public function publishedBySlug(string $slug): ?ProductCareArticle
    {
        return ProductCareArticle::query()
            ->published()
            ->with('category')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @return Collection<int, ProductCareArticle>
     */
    public function relatedPublished(ProductCareArticle $article, int $limit = 6): Collection
    {
        $categoryId = (int) ($article->category_value_id ?? 0);
        if ($categoryId > 0) {
            $siblings = ProductCareArticle::query()
                ->published()
                ->with('category')
                ->where('category_value_id', $categoryId)
                ->whereKeyNot($article->getKey())
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit($limit)
                ->get();
            if ($siblings->isNotEmpty()) {
                return $siblings;
            }
        }

        return $this->randomPublished($limit)
            ->reject(fn (ProductCareArticle $row): bool => (int) $row->id === (int) $article->id)
            ->values();
    }

    /**
     * Категорії, у яких уже є опубліковані статті (для чіпів фільтра).
     *
     * @return Collection<int, OptionValue>
     */
    public function publishedCategoryFilters(): Collection
    {
        $ids = ProductCareArticle::query()
            ->published()
            ->whereNotNull('category_value_id')
            ->distinct()
            ->pluck('category_value_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return OptionValue::query()
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->get();
    }

    public function productIsInAnimalBranch(Product $product): bool
    {
        $product->loadMissing('categories');

        $ids = $this->matchingCategoryIdsForProduct($product);
        if ($ids === []) {
            return false;
        }

        $values = OptionValue::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'slug']);

        foreach ($values as $value) {
            if ($this->nameLooksLikeAnimals((string) $value->name)
                || $this->slugLooksLikeAnimals(mb_strtolower((string) $value->slug))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function matchingCategoryIdsForProduct(Product $product): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $match = [];

        foreach ($this->productPlacementCategoryIds($product) as $placementId) {
            foreach (CatalogCategoryTree::ancestorsChainFromNode($placementId, $groupId) as $id) {
                $match[$id] = true;
            }
        }

        return array_values(array_keys($match));
    }

    /**
     * @return list<int>
     */
    private function productPlacementCategoryIds(Product $product): array
    {
        $ids = [];
        $primary = $product->resolvedCatalogCategoryNodeId();
        if ($primary > 0) {
            $ids[] = $primary;
        }

        foreach ($product->categories as $category) {
            $id = (int) $category->id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function categoryFilterIds(int $categoryId): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $ids = [$categoryId];
        foreach (CatalogCategoryTree::descendantIdsFromDatabase($categoryId, $groupId) as $id) {
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, array{id: int, name: string, children?: array<int, array<string, mixed>>}>  $nodes
     * @return array{id: int, name: string, children?: array<int, array<string, mixed>>}|null
     */
    private function firstAnimalsNode(array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if ($this->nameLooksLikeAnimals((string) ($node['name'] ?? ''))) {
                return $node;
            }
        }

        foreach ($nodes as $node) {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $nested = $this->firstAnimalsNode($children);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    private function nameLooksLikeAnimals(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'тварин')
            || str_contains($normalized, 'животн')
            || preg_match('/\banimals?\b/u', $normalized) === 1;
    }

    private function slugLooksLikeAnimals(string $slug): bool
    {
        return str_contains($slug, 'tvar')
            || str_contains($slug, 'animals');
    }

    /**
     * @param  array{id?: int, children?: array<int, array<string, mixed>>}  $node
     * @return list<int>
     */
    private function collectNodeIds(array $node): array
    {
        $ids = [];
        $id = (int) ($node['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $ids = array_merge($ids, $this->collectNodeIds($child));
            }
        }

        return array_values(array_unique($ids));
    }
}
