<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\ProductReviews\ProductReviewResource;
use App\Models\ProductReview;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Головна адмінки: нові відгуки покупців — спершу ті, що чекають перевірки, — з посиланням на товар.
 */
class ReviewsAttentionWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = -8;

    private const RECENT_LIMIT = 6;

    protected string $view = 'filament.admin.widgets.reviews-attention';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Schema::hasTable('product_reviews')
            && ProductReviewResource::canAccess()
            && ProductReview::query()->exists();
    }

    /**
     * @return array{pending: int, total: int}
     */
    public function counts(): array
    {
        return [
            'pending' => ProductReview::query()->where('status', ProductReview::STATUS_PENDING)->count(),
            'total' => ProductReview::query()->count(),
        ];
    }

    /**
     * Спершу неперевірені, далі найсвіжіші.
     *
     * @return Collection<int, ProductReview>
     */
    public function recent(): Collection
    {
        return ProductReview::query()
            ->with('product:id,title,slug')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ProductReview::STATUS_PENDING])
            ->latest()
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    public function pendingUrl(): string
    {
        return ProductReviewResource::getUrl('index', ['filters' => ['status' => ['value' => ProductReview::STATUS_PENDING]]]);
    }

    public function allUrl(): string
    {
        return ProductReviewResource::getUrl('index');
    }

    public function editUrl(ProductReview $review): string
    {
        return ProductReviewResource::getUrl('edit', ['record' => $review]);
    }

    public function productUrl(ProductReview $review): ?string
    {
        return $review->product ? route('catalog.show', $review->product->slug).'#reviews' : null;
    }
}
