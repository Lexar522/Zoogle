<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
    ) {
        $this->order->loadMissing(['items.product.variants', 'items.bundle', 'items.variant']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Замовлення '.$this->order->number.' — ZOOGLE',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.orders.placed',
            text: 'emails.orders.placed-text',
        );
    }
}
