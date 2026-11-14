<?php

namespace App\Http\Controllers;

use App\Services\StorefrontSuggest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogSuggestController extends Controller
{
    public function __invoke(Request $request, StorefrontSuggest $suggest): JsonResponse
    {
        $query = mb_substr(trim((string) $request->string('q')), 0, 80);

        return response()->json($suggest->build($query));
    }
}
