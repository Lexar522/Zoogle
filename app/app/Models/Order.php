<?php

namespace App\Models;

use App\Support\OnlinePaymentSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    /**
     * Замовлення, які має бачити користувач у кабінеті: свої за user_id або гостьові з тим самим e-mail.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeForAccountUser($query, User $user): void
    {
        $email = strtolower(trim((string) $user->email));

        $query->where(function ($q) use ($user, $email): void {
            $q->where('user_id', $user->id);

            if ($email !== '') {
                $q->orWhere(function ($q2) use ($email): void {
                    $q2->whereNull('user_id')
                        ->whereNotNull('customer_email')
                        ->where('customer_email', '!=', '')
                        ->whereRaw('LOWER(TRIM(customer_email)) = ?', [$email]);
                });
            }
        });
    }

    public const DELIVERY_PICKUP = 'pickup';

    /** @deprecated Використовуйте {@see DELIVERY_NOVA_POSHTA_COURIER} */
    public const DELIVERY_COURIER = 'courier';

    /** @deprecated Використовуйте конкретний тип НП */
    public const DELIVERY_NOVA_POSHTA = 'nova_poshta';

    public const DELIVERY_NOVA_POSHTA_WAREHOUSE = 'nova_poshta_warehouse';

    public const DELIVERY_NOVA_POSHTA_COURIER = 'nova_poshta_courier';

    public const DELIVERY_NOVA_POSHTA_LOCKER = 'nova_poshta_locker';

    public const STATUS_NEW = 'new';

    public const STATUS_PAID = 'paid';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function novaPoshtaDeliveryTypes(): array
    {
        return [
            self::DELIVERY_NOVA_POSHTA_WAREHOUSE,
            self::DELIVERY_NOVA_POSHTA_COURIER,
            self::DELIVERY_NOVA_POSHTA_LOCKER,
        ];
    }

    public static function isNovaPoshtaDelivery(?string $deliveryType): bool
    {
        return in_array($deliveryType, self::novaPoshtaDeliveryTypes(), true);
    }

    public static function isNovaPoshtaWarehouseOrLocker(?string $deliveryType): bool
    {
        return in_array($deliveryType, [
            self::DELIVERY_NOVA_POSHTA_WAREHOUSE,
            self::DELIVERY_NOVA_POSHTA_LOCKER,
            self::DELIVERY_NOVA_POSHTA,
        ], true);
    }

    public static function isNovaPoshtaCourier(?string $deliveryType): bool
    {
        return in_array($deliveryType, [
            self::DELIVERY_NOVA_POSHTA_COURIER,
            self::DELIVERY_COURIER,
        ], true);
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_NEW => __('shop.order_status_new'),
            self::STATUS_PAID => __('shop.order_status_paid'),
            self::STATUS_PROCESSING => __('shop.order_status_processing'),
            self::STATUS_SHIPPED => __('shop.order_status_shipped'),
            self::STATUS_COMPLETED => __('shop.order_status_completed'),
            self::STATUS_CANCELLED => __('shop.order_status_cancelled'),
        ];
    }

    /** @return array<string, string> */
    public static function paymentStatusLabels(): array
    {
        return [
            'pending' => __('shop.order_payment_pending'),
            'partial' => __('shop.order_payment_partial'),
            'paid' => __('shop.order_payment_paid'),
            'failed' => __('shop.order_payment_failed'),
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    public function paymentStatusLabel(): string
    {
        $s = (string) $this->payment_status;

        return self::paymentStatusLabels()[$s] ?? $s;
    }

    public function effectiveImmediateSubtotal(): float
    {
        if ($this->immediate_subtotal !== null) {
            return (float) $this->immediate_subtotal;
        }

        return 0.0;
    }

    public function effectiveDeferredSubtotal(): float
    {
        if ($this->deferred_subtotal !== null) {
            $d = (float) $this->deferred_subtotal;
            if ($d > 0.00001) {
                return $d;
            }
            // У БД могло зберегтися 0, а менеджер вручну ввімкнув відкладену доплату — беремо суму замовлення.
            if ($this->deferred_online_payment && (float) $this->total > 0.00001) {
                return (float) $this->total;
            }

            return $d;
        }
        if ($this->deferred_online_payment) {
            return (float) $this->total;
        }

        return 0.0;
    }

    public function isMixedPaymentPlan(): bool
    {
        return (bool) $this->mixed_payment_plan;
    }

    /** @return array<string, string> */
    public static function deliveryTypeLabels(): array
    {
        return [
            self::DELIVERY_PICKUP => __('shop.order_delivery_pickup'),
            self::DELIVERY_NOVA_POSHTA_WAREHOUSE => __('shop.order_delivery_np_warehouse'),
            self::DELIVERY_NOVA_POSHTA_COURIER => __('shop.order_delivery_np_courier'),
            self::DELIVERY_NOVA_POSHTA_LOCKER => __('shop.order_delivery_np_locker'),
            self::DELIVERY_NOVA_POSHTA => __('shop.order_delivery_np_legacy'),
            self::DELIVERY_COURIER => __('shop.order_delivery_courier_legacy'),
        ];
    }

    /**
     * Підпис для списку замовлень: відкладена LiqPay.
     */
    public function accountDeferredPaymentLabel(): ?string
    {
        if (! $this->deferred_online_payment) {
            return null;
        }
        if ($this->payment_status === 'paid') {
            return null;
        }
        if ($this->deferred_portion_paid_at !== null) {
            return null;
        }
        if ($this->mixed_payment_plan
            && $this->checkout_payment_method === 'online'
            && $this->immediate_portion_paid_at === null
            && $this->effectiveImmediateSubtotal() > 0.00001) {
            return __('shop.order_deferred_mixed_online_first');
        }
        if ($this->checkout_payment_method === 'cod' && $this->mixed_payment_plan) {
            return __('shop.order_deferred_mixed_cod_animals');
        }
        if ($this->online_payment_unlocked_at !== null) {
            if ((float) $this->effectiveDeferredSubtotal() > 0) {
                return __('shop.order_deferred_online_can_pay_animals');
            }

            return __('shop.order_deferred_online_allowed');
        }

        return __('shop.order_deferred_online_pending_animals');
    }

    /**
     * Відкладена онлайн-оплата: після дозволу в адмінці (LiqPay з акаунта / за посиланням).
     */
    public function canPayDeferredLiqPay(): bool
    {
        if (! app(OnlinePaymentSettings::class)->isConfigured()) {
            return false;
        }
        if ($this->payment_status === 'paid') {
            return false;
        }
        if (! $this->deferred_online_payment) {
            return false;
        }
        if ((float) $this->effectiveDeferredSubtotal() <= 0) {
            return false;
        }
        if ($this->deferred_portion_paid_at !== null) {
            return false;
        }
        if ($this->checkout_payment_method === 'cod' && $this->mixed_payment_plan) {
            return false;
        }
        if ($this->mixed_payment_plan
            && $this->checkout_payment_method === 'online'
            && $this->immediate_portion_paid_at === null
            && $this->effectiveImmediateSubtotal() > 0.00001) {
            return false;
        }
        if ($this->online_payment_unlocked_at === null) {
            return false;
        }

        return true;
    }

    /**
     * Перша онлайн-частина змішаного замовлення (аксесуари) — до сплати другої (тварин) після дозволу.
     */
    public function canPayImmediateLiqPay(): bool
    {
        if (! app(OnlinePaymentSettings::class)->isConfigured()) {
            return false;
        }
        if ($this->payment_status === 'paid') {
            return false;
        }
        if (! $this->mixed_payment_plan || $this->checkout_payment_method !== 'online') {
            return false;
        }
        if ($this->effectiveImmediateSubtotal() <= 0.00001) {
            return false;
        }
        if ($this->immediate_portion_paid_at !== null) {
            return false;
        }

        return true;
    }

    public function deliverySummaryText(): string
    {
        if ($this->delivery_type === self::DELIVERY_PICKUP) {
            return '';
        }

        if (self::isNovaPoshtaWarehouseOrLocker($this->delivery_type)) {
            $city = trim((string) ($this->delivery_city ?? ''));
            $branch = trim((string) ($this->delivery_branch ?? ''));

            return trim($city.($city !== '' && $branch !== '' ? ' — ' : '').$branch);
        }

        if (self::isNovaPoshtaCourier($this->delivery_type)) {
            $city = trim((string) ($this->delivery_city ?? ''));
            $addr = trim((string) ($this->delivery_address ?? ''));
            if ($city !== '' && $addr !== '') {
                return $city.' — '.$addr;
            }

            return $addr !== '' ? $addr : $city;
        }

        return '';
    }

    /**
     * Текст адреси самовивозу з адмінки (Налаштування → Інтеграції).
     */
    public function pickupShopAddressLine(): ?string
    {
        if ($this->delivery_type !== self::DELIVERY_PICKUP) {
            return null;
        }

        $addr = trim((string) (ShopIntegrationSetting::record()->pickup_address ?? ''));

        return $addr !== '' ? $addr : null;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function pickupShopMapPosition(): ?array
    {
        if ($this->delivery_type !== self::DELIVERY_PICKUP) {
            return null;
        }

        $s = ShopIntegrationSetting::record();
        if ($s->pickup_lat === null || $s->pickup_lng === null) {
            return null;
        }

        return [
            'lat' => (float) $s->pickup_lat,
            'lng' => (float) $s->pickup_lng,
        ];
    }

    protected $fillable = [
        'user_id',
        'number',
        'status',
        'payment_status',
        'deferred_online_payment',
        'online_payment_unlocked_at',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'delivery_type',
        'delivery_city',
        'delivery_branch',
        'delivery_city_ref',
        'delivery_warehouse_ref',
        'delivery_street',
        'delivery_street_ref',
        'delivery_building',
        'delivery_flat',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'nova_poshta_ttn',
        'comment',
        'customer_notes',
        'total',
        'immediate_subtotal',
        'deferred_subtotal',
        'mixed_payment_plan',
        'checkout_payment_method',
        'success_token',
        'placed_at',
        'paid_at',
        'immediate_portion_paid_at',
        'deferred_portion_paid_at',
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
        'online_payment_unlocked_at' => 'datetime',
        'immediate_portion_paid_at' => 'datetime',
        'deferred_portion_paid_at' => 'datetime',
        'delivery_lat' => 'float',
        'delivery_lng' => 'float',
        'deferred_online_payment' => 'boolean',
        'mixed_payment_plan' => 'boolean',
        'immediate_subtotal' => 'decimal:2',
        'deferred_subtotal' => 'decimal:2',
    ];

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function courierDeliveryMapPosition(): ?array
    {
        if (! self::isNovaPoshtaCourier($this->delivery_type)) {
            return null;
        }

        if ($this->delivery_lat === null || $this->delivery_lng === null) {
            return null;
        }

        return [
            'lat' => (float) $this->delivery_lat,
            'lng' => (float) $this->delivery_lng,
        ];
    }

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
