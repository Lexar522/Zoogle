<?php

namespace App\Rules;

use App\Support\ProfanityFilter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Текст від відвідувача без нецензурних слів (відгуки, питання).
 */
class NoProfanity implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ProfanityFilter::contains($value)) {
            $fail(__('shop.reviews_profanity'));
        }
    }
}
