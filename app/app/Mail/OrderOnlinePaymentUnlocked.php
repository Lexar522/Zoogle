<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderOnlinePaymentUnlocked extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
    ) {
        $this->order->loadMissing('items');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Онлайн-оплата дозволена: замовлення '.$this->order->number.' — ZOOGLE',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.online-payment-unlocked',
        );
    }
}
