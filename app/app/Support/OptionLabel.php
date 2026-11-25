<?php

namespace App\Support;

/**
 * Підпис обраної опції «Група: значення».
 * Назви груп в адмінці часто вже з двокрапкою («Стать:»), тож без обрізання виходило «Стать:: Дівчинка».
 */
final class OptionLabel
{
    public static function join(string $groupName, string $valueName): string
    {
        $group = rtrim(trim($groupName), " \t:：");
        $value = trim($valueName);

        return $group !== '' ? $group.': '.$value : $value;
    }
}
