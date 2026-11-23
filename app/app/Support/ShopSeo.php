<?php

namespace App\Support;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use Illuminate\Http\Request;

/**
 * Заголовки і правила індексації каталогу.
 * У пошук потрапляють головна, чиста категорія і картка товару.
 * Пошук, сортування і службові фільтри лишаються без індексації.
 */
final class ShopSeo
{
    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     robots: string,
     *     og_title: string,
     *     og_description: string,
     *     indexable: bool,
     *     breadcrumb: list<array{name: string, url: string}>
     * }
     */
    public static function catalog(Request $request, string $categoryName): array
    {
        $categoryId = max(0, (int) $request->integer('category'));
        $categoryName = trim($categoryName);
        $hasCategory = $categoryId > 0 && $categoryName !== '';
        $indexable = self::catalogIsIndexable($request) && ($categoryId === 0 || $hasCategory);

        if ($hasCategory) {
            $title = __('shop.catalog_category_page_title', ['category' => $categoryName]);
            $description = __('shop.catalog_category_meta_description', ['category' => $categoryName]);
            $canonical = route('catalog.index', ['category' => $categoryId]);
            $ogTitle = $title;
            $ogDescription = $description;
        } else {
            $title = __('shop.catalog_page_title');
            $description = __('shop.catalog_meta_description');
            $canonical = route('catalog.index');
            $ogTitle = __('shop.catalog_og_title');
            $ogDescription = __('shop.catalog_og_description');
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $indexable ? 'index,follow' : 'noindex,follow',
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'indexable' => $indexable,
            'breadcrumb' => $hasCategory ? self::categoryBreadcrumb($categoryId) : [],
        ];
    }

    /**
     * Хлібні крихти для BreadcrumbList: магазин → батьківські категорії → поточна (чисті URL без параметрів).
     *
     * @return list<array{name: string, url: string}>
     */
    public static function categoryBreadcrumb(int $categoryId): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $chain = CatalogCategoryTree::ancestorsChainFromNode($categoryId, $groupId);
        if ($chain === []) {
            return [];
        }

        $byId = StorefrontOptionCatalog::valuesInGroup($groupId)->keyBy(fn (OptionValue $value): int => (int) $value->id);
        $crumbs = [['name' => 'ZOOGLE', 'url' => route('home')]];
        foreach ($chain as $id) {
            $name = trim((string) mt($byId->get($id)?->name));
            if ($name !== '') {
                $crumbs[] = ['name' => $name, 'url' => route('catalog.index', ['category' => $id])];
            }
        }

        return $crumbs;
    }

    public static function catalogIsIndexable(Request $request): bool
    {
        foreach (array_keys($request->query()) as $key) {
            if ($key !== 'category') {
                return false;
            }
        }

        return (int) $request->integer('category') >= 0;
    }
}
