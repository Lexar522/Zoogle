<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use App\Support\SponsorPhoto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Заявка на віртуальну опіку. Оплата йде на банку Monobank, тож менеджер підтверджує її вручну.
 */
class Sponsorship extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Очікує оплату',
        self::STATUS_ACTIVE => 'Активна',
        self::STATUS_CANCELLED => 'Скасована',
    ];

    public const PHOTO_NONE = 'none';

    public const PHOTO_PENDING = 'pending';

    public const PHOTO_APPROVED = 'approved';

    public const PHOTO_REJECTED = 'rejected';

    /** @var array<string, string> */
    public const PHOTO_LABELS = [
        self::PHOTO_NONE => 'Без фото',
        self::PHOTO_PENDING => 'Фото на перевірці',
        self::PHOTO_APPROVED => 'Фото схвалено',
        self::PHOTO_REJECTED => 'Фото відхилено',
    ];

    /** @var array<string, array{months: int, price_column: string}> */
    public const TERMS = [
        'month' => ['months' => 1, 'price_column' => 'price_month'],
        'half_year' => ['months' => 6, 'price_column' => 'price_half_year'],
        'year' => ['months' => 12, 'price_column' => 'price_year'],
    ];

    protected $fillable = [
        'sponsor_animal_id',
        'animal_name',
        'term',
        'months',
        'amount',
        'sponsor_name',
        'email',
        'phone',
        'message',
        'photo_path',
        'photo_status',
        'show_publicly',
        'status',
        'paid_at',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'amount' => 'decimal:2',
            'show_publicly' => 'boolean',
            'paid_at' => 'datetime',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sponsorship): void {
            $sponsorship->public_token ??= Str::random(48);
        });
    }

    /** @return BelongsTo<SponsorAnimal, $this> */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(SponsorAnimal::class, 'sponsor_animal_id');
    }

    public function reference(): string
    {
        return 'OP-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function isActiveOn(Carbon $day): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->ends_at !== null
            && $this->ends_at->copy()->endOfDay()->gte($day);
    }

    /**
     * Менеджер побачив переказ на банці — опікунство діє з сьогодні на обраний термін.
     */
    public function confirmPayment(?Carbon $today = null): void
    {
        // Умова на статус — щоб два одночасні натискання не переписали дати.
        self::query()
            ->whereKey($this->getKey())
            ->where('status', self::STATUS_PENDING)
            ->update([
                'status' => self::STATUS_ACTIVE,
                'paid_at' => now(),
                ...$this->periodFrom($today ?? Carbon::today()),
                'updated_at' => now(),
            ]);

        $this->refresh();
    }

    /**
     * @return array{starts_at: Carbon, ends_at: Carbon}
     */
    public function periodFrom(Carbon $day): array
    {
        $start = $day->copy()->startOfDay();

        return [
            'starts_at' => $start,
            // Спершу день назад, потім +N місяців: старт 31.01 → кінець 28.02, а не 27.02
            'ends_at' => $start->copy()->subDay()->addMonthsNoOverflow(max(1, (int) $this->months)),
        ];
    }

    /**
     * Прибирає файл фото з диска (відхилене фото чи скасована заявка).
     */
    public function discardPhoto(): void
    {
        SponsorPhoto::delete($this->photo_path);
        $this->forceFill(['photo_path' => null])->save();
    }

    /**
     * Активні опікуни, яких можна показати на сторінці тварини.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePubliclyActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('show_publicly', true)
            ->whereDate('ends_at', '>=', Carbon::today())
            ->orderBy('starts_at');
    }

    /**
     * Ім'я для сайту: лише перше слово, без прізвища.
     */
    public function publicName(): string
    {
        $first = preg_split('/\s+/u', trim($this->sponsor_name), 2)[0] ?? '';

        return $first !== '' ? $first : '—';
    }

    public function publicPhotoUrl(): ?string
    {
        if ($this->photo_status !== self::PHOTO_APPROVED || blank($this->photo_path)) {
            return null;
        }

        return PublicStorageUrl::forPath($this->photo_path);
    }

    public function termLabel(): string
    {
        return ShopSponsorshipSetting::termLabel($this->term);
    }
}
