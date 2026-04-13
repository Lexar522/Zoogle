<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductFavoriteController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $productTable = (new Product)->getTable();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists($productTable, 'id')],
        ]);

        /** @var User $user */
        $user = $request->user();
        $productId = (int) $validated['product_id'];

        if ($user->favoriteProducts()->whereKey($productId)->exists()) {
            $user->favoriteProducts()->detach($productId);

            return response()->json(['favorited' => false]);
        }

        $user->favoriteProducts()->attach($productId);

        return response()->json(['favorited' => true]);
    }

    public function sync(Request $request): JsonResponse
    {
        $productTable = (new Product)->getTable();

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'max:200'],
            'product_ids.*' => ['integer', Rule::exists($productTable, 'id')],
        ]);

        /** @var User $user */
        $user = $request->user();
        $ids = array_values(array_unique(array_map('intval', $validated['product_ids'])));

        $user->favoriteProducts()->syncWithoutDetaching($ids);

        return response()->json(['synced' => count($ids)]);
    }
}
