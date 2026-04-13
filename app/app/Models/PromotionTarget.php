<?php

namespace App\Models;

use App\Enums\PromotionDiscountMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class PromotionTarget extends Model
{
    protected $fillable = [
        'promotion_id',
        'target_type',
        'target_id',
        'discount_mode',
        'discount_value',
        'ends_at',
    ];

    protected $casts = [
        'discount_mode' => PromotionDiscountMode::class,
        'discount_value' => 'decimal:2',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PromotionTarget $target): void {
            if ($target->discount_mode === PromotionDiscountMode::Percent && (float) $target->discount_value > 100) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Для відсоткової знижки значення не може перевищувати 100.',
                ]);
            }
        });
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'target_type', 'target_id');
    }
}
