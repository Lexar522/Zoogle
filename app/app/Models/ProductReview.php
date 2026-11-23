<?php

namespace App\Models;

use App\Support\StorefrontModerators;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Відгук покупця про товар. На сайті й у розмітці для Google — лише схвалені адміністратором.
 *
 * @property int $rating
 * @property string $status
 * @property ?Carbon $approved_at
 */
class ProductReview extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Чекає перевірки',
        self::STATUS_APPROVED => 'Опубліковано',
        self::STATUS_REJECTED => 'Відхилено',
    ];

    public const RATING_MIN = 1;

    public const RATING_MAX = 5;

    protected $fillable = [
        'product_id',
        'user_id',
        'author_name',
        'rating',
        'body',
        'status',
        'is_verified_buyer',
        'approved_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_buyer' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<ProductReview>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED);
    }

    /** Ті самі люди, що модерують відгуки в адмінці. */
    public static function canBeModeratedBy(?User $user): bool
    {
        return StorefrontModerators::allows($user);
    }

    public function approve(): void
    {
        $this->forceFill(['status' => self::STATUS_APPROVED, 'approved_at' => now()])->save();
    }

    public function reject(): void
    {
        $this->forceFill(['status' => self::STATUS_REJECTED, 'approved_at' => null])->save();
    }

    /** Лише ім'я, без прізвища: «Олена Петренко» → «Олена». */
    public function publicName(): string
    {
        $first = preg_split('/\s+/u', trim($this->author_name), 2)[0] ?? '';

        return $first !== '' ? $first : __('shop.reviews_anonymous');
    }
}
