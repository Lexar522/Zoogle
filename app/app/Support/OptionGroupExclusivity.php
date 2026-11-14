<?php

namespace App\Support;

/**
 * Групи на кшталт «Розмір: 1–1,5 см» / «Розмір: 3–4 см» — одна вісь вибору:
 * на вітрині можна обрати лише одну позицію з усієї сімʼї.
 */
final class OptionGroupExclusivity
{
    public static function keyFromName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        if (! preg_match('/^(.+?)\s*[:：]\s*.+/u', $name, $matches)) {
            return null;
        }

        $key = mb_strtolower(trim((string) ($matches[1] ?? '')));

        return $key !== '' ? $key : null;
    }
}
