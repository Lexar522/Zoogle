<?php

namespace App\Support;

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
     *     indexable: bool
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
        ];
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
