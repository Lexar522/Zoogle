<?php

namespace App\Http\Controllers;

use App\Models\ShopInfoPage;
use Illuminate\View\View;

class ShopInfoPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = ShopInfoPage::query()->where('slug', $slug)->first();

        abort_if($page === null, 404);

        $title = $page->titleForLocale();
        if ($title === '') {
            $title = __('shop.info_page_default_title');
        }

        $body = $page->bodyForLocale();

        return view('pages.info-show', [
            'page' => $page,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
