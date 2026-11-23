<?php

namespace App\Services;

use App\Mail\ProductQuestionAnswered;
use App\Models\ProductQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Відповідь магазину на питання покупця: публікує на сторінці товару й, за бажанням, надсилає лист.
 */
class ProductQuestionAnswerer
{
    /**
     * @return bool чи надіслано лист
     */
    public function answer(ProductQuestion $question, string $answer, ?User $by, bool $sendEmail): bool
    {
        $question->saveAnswer($answer, $by);

        if (! $sendEmail || blank($question->email)) {
            return false;
        }

        try {
            Mail::to($question->email)->send(new ProductQuestionAnswered($question->fresh('product') ?? $question));
            $question->forceFill(['answer_emailed_at' => now()])->save();

            return true;
        } catch (Throwable $e) {
            Log::warning('product_question_email_failed', [
                'question_id' => $question->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
