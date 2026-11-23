<?php

namespace App\Mail;

use App\Models\ProductQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductQuestionAnswered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProductQuestion $question)
    {
        $this->question->loadMissing('product:id,title,slug');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('shop.mail_question_subject', ['product' => mt((string) $this->question->product?->title)]));
    }

    public function content(): Content
    {
        return new Content(html: 'emails.questions.answered');
    }
}
