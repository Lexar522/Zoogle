<?php

namespace App\Enums;

enum PromotionDiscountMode: string
{
    case Percent = 'percent';
    case AmountOff = 'amount_off';
    case FixedPrice = 'fixed_price';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Відсоток знижки',
            self::AmountOff => 'Фіксована знижка (₴)',
            self::FixedPrice => 'Фіксована ціна (₴)',
        };
    }
}
