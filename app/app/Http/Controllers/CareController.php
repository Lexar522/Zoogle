<?php

namespace App\Http\Controllers;

use App\Services\CareArticleCatalog;
use App\Support\CatalogCategoryTree;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CareController extends Controller
{
    public function __construct(
        private readonly CatalogController $catalogController,
        private readonly CareArticleCatalog $careArticles,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $categoryId = (int) $request->integer('category');
        $navRequest = $request->duplicate();
        $navRequest->query->set('q', '');
        $navRequest->request->set('q', '');
        $nav = $this->catalogController->navigationFilters($navRequest);

        $categoryTree = is_array($nav['categoryTree'] ?? null) ? $nav['categoryTree'] : [];
        $showArticles = $search !== '' || $categoryId > 0;
        $articles = $showArticles
            ? $this->careArticles->paginateLibrary(
                $search !== '' ? $search : null,
                $categoryId > 0 ? $categoryId : null,
                12,
                $categoryTree,
            )
            : null;

        $selectedNode = $categoryId > 0
            ? CatalogCategoryTree::findNodeInTree($categoryTree, $categoryId)
            : null;

        return view('care.index', array_merge($nav, [
            'articles' => $articles,
            'showCareArticles' => $showArticles,
            'careSearch' => $search,
            'careCategoryId' => $categoryId > 0 ? $categoryId : 0,
            'careCategoryName' => is_array($selectedNode) ? (string) ($selectedNode['name'] ?? '') : '',
            'careAnimalGroups' => $this->careArticles->animalCareGroups($categoryTree),
        ]));
    }

    public function show(Request $request, string $articleSlug): View
    {
        $nav = $this->catalogController->navigationFilters($request);
        $article = $this->careArticles->publishedBySlug($articleSlug);
        abort_if($article === null, 404);

        return view('care.show', array_merge($nav, [
            'article' => $article,
            'relatedArticles' => $this->careArticles->relatedPublished($article),
        ]));
    }
}
