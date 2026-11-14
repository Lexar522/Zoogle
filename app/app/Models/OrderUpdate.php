<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderUpdate extends Model
{
    protected $fillable = [
        'order_id',
        'status',
        'previous_status',
        'message',
        'notify_email',
        'email_sent_at',
        'created_by',
    ];

    protected $casts = [
        'notify_email' => 'boolean',
        'email_sent_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): ?string
    {
        if (! is_string($this->status) || $this->status === '') {
            return null;
        }

        return Order::statusLabels()[$this->status] ?? $this->status;
    }

    public function previousStatusLabel(): ?string
    {
        if (! is_string($this->previous_status) || $this->previous_status === '') {
            return null;
        }

        return Order::statusLabels()[$this->previous_status] ?? $this->previous_status;
    }

    public function statusChanged(): bool
    {
        return is_string($this->previous_status)
            && $this->previous_status !== ''
            && $this->previous_status !== $this->status;
    }
}
