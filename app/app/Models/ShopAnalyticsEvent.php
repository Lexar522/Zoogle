<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopAnalyticsEvent extends Model
{
    public const TYPE_PAGE = 'page';

    public const TYPE_PRODUCT_VIEW = 'product_view';

    public const TYPE_BUNDLE_VIEW = 'bundle_view';

    public const TYPE_SEARCH = 'search';

    public const TYPE_CART = 'cart';

    public const TYPE_CHECKOUT = 'checkout';

    public const TYPE_FAVORITE = 'favorite';

    public $timestamps = false;

    protected $fillable = [
        'occurred_on',
        'occurred_at',
        'visitor_key',
        'type',
        'subject_id',
        'label',
        'quantity',
        'results_count',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'occurred_at' => 'datetime',
        'subject_id' => 'integer',
        'quantity' => 'integer',
        'results_count' => 'integer',
    ];
}
