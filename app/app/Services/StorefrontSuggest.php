<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCareArticle;
use App\Support\CatalogTitleMatch;
use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StorefrontSuggest
{
    /**
     * @return array{
     *     query: string,
     *     search_label: string,
     *     search_url: string,
     *     searches: list<array{query: string, label: string, url: string}>,
     *     empty: string,
     *     groups: list<array{key: string, label: string, items: list<array<string, mixed>>}>
     * }
     */
    public function build(string $query): array
    {
        $query = trim($query);
        $browsing = $query === '';
        $searches = $browsing ? [] : $this->searchQueries($query);

        $groups = [];
        $categories = $this->categories($query, $browsing);
        if ($categories !== []) {
            $groups[] = [
                'key' => 'categories',
                'label' => __('shop.search_suggest_categories'),
                'items' => $categories,
            ];
        }

        if ($browsing) {
            $articles = $this->articles($query, true);
            if ($articles !== []) {
                $groups[] = [
                    'key' => 'articles',
                    'label' => __('shop.search_suggest_articles'),
                    'items' => $articles,
                ];
            }
        }

        return [
            'query' => $query,
            'search_label' => $searches[0]['label'] ?? '',
            'search_url' => $searches[0]['url'] ?? route('catalog.index'),
            'searches' => $searches,
            'empty' => __('shop.search_suggest_empty'),
            'groups' => $groups,
        ];
    }

    /**
     * Автодоповнення як у пошуку: рядки — це назви товарів із каталогу.
     *
     * @return list<array{query: string, label: string, url: string}>
     */
    private function searchQueries(string $query): array
    {
        $titles = $this->matchingTitles($query);
        $wordRows = [];
        foreach (CatalogTitleMatch::suggestWords($query, array_merge($titles, $this->categoryNames())) as $word) {
            $wordRows[] = [
                'kind' => 'word',
                'query' => $word,
                'label' => $word,
                'url' => route('catalog.index', ['q' => $word]),
            ];
        }

        usort($titles, function (string $a, string $b) use ($query): int {
            $rank = (CatalogTitleMatch::score($query, $a) ?? 99) <=> (CatalogTitleMatch::score($query, $b) ?? 99);
            if ($rank !== 0) {
                return $rank;
            }
            $length = mb_strlen($a) <=> mb_strlen($b);

            return $length !== 0 ? $length : strcmp(mb_strtolower($a, 'UTF-8'), mb_strtolower($b, 'UTF-8'));
        });

        $rows = $wordRows;
        $seen = [];
        foreach ($wordRows as $wordRow) {
            $seen[mb_strtolower($wordRow['label'], 'UTF-8')] = true;
        }
        $titleCount = 0;
        foreach ($titles as $title) {
            $key = mb_strtolower($title, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = [
                'query' => $title,
                'label' => $title,
                'url' => route('catalog.index', ['q' => $title]),
            ];
            $titleCount++;
            if ($titleCount >= 8) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function matchingTitles(string $query): array
    {
        $titles = Product::query()
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->pluck('title')
            ->all();

        if (SchemaCache::hasTable((new Bundle)->getTable())) {
            $titles = array_merge($titles, Bundle::query()
                ->where('is_visible', true)
                ->where('is_active', true)
                ->pluck('title')
                ->all());
        }

        return array_values(array_filter(array_map(
            fn (mixed $title): string => trim(mt(is_string($title) ? $title : '')),
            $titles
        ), fn (string $title): bool => $title !== '' && CatalogTitleMatch::matches($query, $title)));
    }

    /**
     * @return list<string>
     */
    private function categoryNames(): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($groupId <= 0 || ! SchemaCache::hasTable('option_values')) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $name): string => trim(mt(is_string($name) ? $name : '')),
            OptionValue::query()
                ->where('option_group_id', $groupId)
                ->where('is_active', true)
                ->pluck('name')
                ->all()
        )));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categories(string $query, bool $browsing): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($groupId <= 0 || ! SchemaCache::hasTable('option_values')) {
            return [];
        }

        $hasParent = SchemaCache::hasColumn('option_values', 'parent_id');
        $columns = ['id', 'name'];
        if ($hasParent) {
            $columns[] = 'parent_id';
        }

        $rows = OptionValue::query()
            ->where('option_group_id', $groupId)
            ->where('is_active', true)
            ->when($browsing && $hasParent, function (Builder $q): void {
                $q->whereNull('parent_id');
            })
            ->when(! $browsing, function (Builder $q) use ($query): void {
                $this->applyLike($q, ['name'], $query);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($browsing ? 8 : 5)
            ->get($columns);

        $parentNames = [];
        if ($hasParent) {
            $parentIds = $rows
                ->map(fn (OptionValue $value): int => (int) ($value->parent_id ?? 0))
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values();
            if ($parentIds->isNotEmpty()) {
                $parentNames = OptionValue::query()
                    ->whereIn('id', $parentIds)
                    ->pluck('name', 'id')
                    ->all();
            }
        }

        return $rows->map(function (OptionValue $value) use ($parentNames): array {
            $parentId = (int) ($value->parent_id ?? 0);
            $parentName = $parentId > 0
                ? trim(mt((string) ($parentNames[$parentId] ?? '')))
                : '';

            return [
                'type' => 'category',
                'title' => mt($value->name),
                'meta' => $parentName,
                'url' => route('catalog.index', ['category' => $value->id]),
                'image' => null,
                'price' => '',
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(string $query, bool $browsing): array
    {
        if (! SchemaCache::hasTable('product_care_articles')) {
            return [];
        }

        $rows = ProductCareArticle::query()
            ->published()
            ->when(! $browsing, function (Builder $q) use ($query): void {
                $this->applyLike($q, ['title'], $query);
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($browsing ? 4 : 4)
            ->get();

        return $rows->map(function (ProductCareArticle $article): array {
            $path = $article->categoryPathLabel();

            return [
                'type' => 'article',
                'title' => mt($article->title),
                'meta' => $path !== '' ? $path : __('shop.search_suggest_article'),
                'url' => $article->storefrontUrl(),
                'image' => null,
                'price' => '',
            ];
        })->all();
    }

    /**
     * @param  list<string>  $columns
     */
    private function applyLike(Builder $query, array $columns, string $search): void
    {
        $columns = array_values(array_filter($columns, fn (string $column): bool => $column !== ''));
        if ($columns === [] || trim($search) === '') {
            $query->whereRaw('0 = 1');

            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $pattern = '%'.$this->escapeLike($search).'%';
            $query->where(function (Builder $w) use ($columns, $pattern): void {
                foreach ($columns as $index => $column) {
                    $w->{$index === 0 ? 'where' : 'orWhere'}($column, 'ilike', $pattern);
                }
            });

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $pattern = '%'.$this->escapeLike(mb_strtolower($search, 'UTF-8')).'%';
            $query->where(function (Builder $w) use ($columns, $pattern): void {
                foreach ($columns as $index => $column) {
                    $sql = 'LOWER(COALESCE(`'.$column.'`, \'\')) LIKE ?';
                    $w->{$index === 0 ? 'whereRaw' : 'orWhereRaw'}($sql, [$pattern]);
                }
            });

            return;
        }

        $terms = $this->caseFoldTerms($search);
        $query->where(function (Builder $outer) use ($columns, $terms): void {
            foreach ($terms as $index => $term) {
                $pattern = '%'.$this->escapeLike($term).'%';
                $outer->{$index === 0 ? 'where' : 'orWhere'}(function (Builder $inner) use ($columns, $pattern): void {
                    foreach ($columns as $columnIndex => $column) {
                        $inner->{$columnIndex === 0 ? 'where' : 'orWhere'}($column, 'like', $pattern);
                    }
                });
            }
        });
    }

    /**
     * @return list<string>
     */
    private function caseFoldTerms(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        return array_values(array_unique(array_filter([
            $search,
            mb_strtolower($search, 'UTF-8'),
            mb_strtoupper($search, 'UTF-8'),
            mb_convert_case($search, MB_CASE_TITLE, 'UTF-8'),
        ], fn (string $term): bool => $term !== '')));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
