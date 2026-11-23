<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\ProductQuestions\ProductQuestionResource;
use App\Models\ProductQuestion;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Головна адмінки: питання покупців, на які ще немає відповіді.
 */
class QuestionsAttentionWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    private const RECENT_LIMIT = 6;

    protected string $view = 'filament.admin.widgets.questions-attention';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Schema::hasTable('product_questions')
            && ProductQuestionResource::canAccess()
            && self::pendingCount() > 0;
    }

    public static function pendingCount(): int
    {
        return ProductQuestion::query()->where('status', ProductQuestion::STATUS_PENDING)->count();
    }

    /**
     * Найстаріші першими: на них покупці чекають довше.
     *
     * @return Collection<int, ProductQuestion>
     */
    public function waiting(): Collection
    {
        return ProductQuestion::query()
            ->with('product:id,title,slug')
            ->where('status', ProductQuestion::STATUS_PENDING)
            ->oldest()
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    public function pendingUrl(): string
    {
        return ProductQuestionResource::getUrl('index', ['filters' => ['status' => ['value' => ProductQuestion::STATUS_PENDING]]]);
    }

    public function answerUrl(ProductQuestion $question): string
    {
        return ProductQuestionResource::getUrl('edit', ['record' => $question]);
    }

    public function productUrl(ProductQuestion $question): ?string
    {
        return $question->product ? route('catalog.show', $question->product->slug).'#questions' : null;
    }
}
