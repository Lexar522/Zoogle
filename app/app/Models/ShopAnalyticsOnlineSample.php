<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopAnalyticsOnlineSample extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sampled_at',
        'online_count',
    ];

    protected $casts = [
        'sampled_at' => 'datetime',
        'online_count' => 'integer',
    ];
}
