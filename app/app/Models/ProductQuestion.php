<?php

namespace App\Models;

use App\Support\StorefrontModerators;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Питання покупця про товар. На сайті з'являється разом із відповіддю магазину.
 *
 * @property string $status
 * @property ?string $answer
 * @property ?Carbon $answered_at
 * @property ?Carbon $answer_emailed_at
 */
class ProductQuestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_HIDDEN = 'hidden';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Чекає відповіді',
        self::STATUS_ANSWERED => 'Відповідь на сайті',
        self::STATUS_HIDDEN => 'Приховано',
    ];

    protected $fillable = [
        'product_id',
        'user_id',
        'author_name',
        'email',
        'body',
        'answer',
        'status',
        'answered_by',
        'answered_at',
        'answer_emailed_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'answer_emailed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * @param  Builder<ProductQuestion>  $query
     */
    public function scopeAnswered(Builder $query): void
    {
        $query->where('status', self::STATUS_ANSWERED)->whereNotNull('answer');
    }

    public static function canBeModeratedBy(?User $user): bool
    {
        return StorefrontModerators::allows($user);
    }

    public function saveAnswer(string $answer, ?User $by): void
    {
        $this->forceFill([
            'answer' => trim($answer),
            'status' => self::STATUS_ANSWERED,
            'answered_by' => $by?->id,
            'answered_at' => $this->answered_at ?? now(),
        ])->save();
    }

    public function hide(): void
    {
        $this->forceFill(['status' => self::STATUS_HIDDEN])->save();
    }

    /** Лише ім'я, без прізвища. */
    public function publicName(): string
    {
        $first = preg_split('/\s+/u', trim($this->author_name), 2)[0] ?? '';

        return $first !== '' ? $first : __('shop.reviews_anonymous');
    }
}
