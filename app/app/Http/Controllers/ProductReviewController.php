<?php

namespace App\Http\Controllers;

use App\Models\OptionGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Rules\NoProfanity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Відгук із картки товару. Потрапляє на сайт лише після схвалення в адмінці.
 */
class ProductReviewController extends Controller
{
    /** Приховане поле: люди його не бачать і не заповнюють, боти — заповнюють. */
    private const HONEYPOT_FIELD = 'website';

    public function store(Request $request, string $slug): RedirectResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->firstOrFail();

        $back = route('catalog.show', $product->slug).'#reviews';

        if (filled($request->input(self::HONEYPOT_FIELD))) {
            return redirect()->to($back);
        }

        // Помилки показуємо біля форми: повертаємо одразу до блоку відгуків, а не на верх сторінки
        $validator = Validator::make($request->all(), [
            'author_name' => ['required', 'string', 'min:2', 'max:80', new NoProfanity],
            'rating' => ['required', 'integer', 'between:'.ProductReview::RATING_MIN.','.ProductReview::RATING_MAX],
            'body' => ['required', 'string', 'min:10', 'max:2000', new NoProfanity],
        ]);
        if ($validator->fails()) {
            return redirect()->to($back)->withErrors($validator, 'review')->withInput();
        }
        $data = $validator->validated();

        $user = $request->user();

        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'author_name' => trim(strip_tags($data['author_name'])),
            'rating' => (int) $data['rating'],
            'body' => trim(strip_tags($data['body'])),
            'status' => ProductReview::STATUS_PENDING,
            'is_verified_buyer' => $user instanceof User && $this->hasBought($user, $product),
        ]);

        return redirect()->to($back)->with('review_sent', true);
    }

    /** Видалення відгуку прямо зі сторінки товару (лише для адміністрації). */
    public function destroy(Request $request, string $slug, ProductReview $review): RedirectResponse
    {
        abort_unless(ProductReview::canBeModeratedBy($request->user()), 403);

        $product = Product::query()->where('slug', $slug)->firstOrFail();
        abort_unless((int) $review->product_id === (int) $product->id, 404);

        $review->delete();

        return redirect()->to(route('catalog.show', $product->slug).'#reviews')->with('review_deleted', true);
    }

    private function hasBought(User $user, Product $product): bool
    {
        return Order::query()
            ->forAccountUser($user)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->whereHas('items', fn ($items) => $items->where('product_id', $product->id))
            ->exists();
    }
}
