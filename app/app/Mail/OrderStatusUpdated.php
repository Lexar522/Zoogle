<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public OrderUpdate $update,
    ) {
        $this->order->loadMissing('items');
    }

    public function envelope(): Envelope
    {
        $status = $this->update->statusLabel();
        $subject = $this->update->statusChanged() && $status
            ? __('shop.mail_order_status_subject', [
                'number' => $this->order->number,
                'status' => $status,
            ])
            : __('shop.mail_order_message_subject', [
                'number' => $this->order->number,
            ]);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.orders.status-updated',
        );
    }
}
