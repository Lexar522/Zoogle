<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'bundle_id',
        'title_snapshot',
        'option_value_ids',
        'bundle_snapshot',
        'price',
        'qty',
        'line_total',
    ];

    protected $casts = [
        'option_value_ids' => 'array',
        'bundle_snapshot' => 'array',
        'price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        $fk = Schema::hasColumn($this->getTable(), 'product_id')
            ? 'product_id'
            : 'animal_listing_id';

        return $this->belongsTo(Product::class, $fk);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class, 'bundle_id');
    }
}
