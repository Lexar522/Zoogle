<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopAnalyticsPresence extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'shop_analytics_presence';

    protected $primaryKey = 'visitor_key';

    protected $keyType = 'string';

    protected $fillable = [
        'visitor_key',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
