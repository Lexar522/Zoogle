<?php

namespace App\Support;

final class ShopPrice
{
    /**
     * Сума для вітрини: «310» замість «310,00», але «249,50» лишається з копійками.
     */
    public static function amount(float $value): string
    {
        $rounded = round($value, 2);
        $decimals = abs($rounded - round($rounded)) < 0.005 ? 0 : 2;

        return number_format($rounded, $decimals, ',', ' ');
    }
}
