<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const DELIVERY_PICKUP = 'pickup';

    public const DELIVERY_COURIER = 'courier';

    public const DELIVERY_NOVA_POSHTA = 'nova_poshta';

    public const STATUS_NEW = 'new';

    public const STATUS_PAID = 'paid';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @return array<string, string> */
    public static function deliveryTypeLabels(): array
    {
        return [
            self::DELIVERY_PICKUP => 'Самовивіз',
            self::DELIVERY_COURIER => "Кур'єр",
            self::DELIVERY_NOVA_POSHTA => 'Нова Пошта',
        ];
    }

    protected $fillable = [
        'user_id',
        'number',
        'status',
        'payment_status',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'delivery_type',
        'delivery_city',
        'delivery_branch',
        'delivery_address',
        'comment',
        'customer_notes',
        'total',
        'success_token',
        'placed_at',
        'paid_at',
        'payment_provider',
        'payment_external_id',
        'payment_checkout_url',
        'payment_last_callback_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'placed_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_last_callback_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (! $order->number) {
                $order->number = 'ORD-'.now()->format('YmdHis').'-'.random_int(100, 999);
            }

            if (! $order->placed_at) {
                $order->placed_at = now();
            }

            if (! $order->success_token) {
                $order->success_token = Str::uuid()->toString();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
