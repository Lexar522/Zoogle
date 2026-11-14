<?php

namespace App\Support;

/**
 * Скільки номерів сторінок показувати в пагінації каталогу.
 */
class CatalogPageWindow
{
    /**
     * @return array{start: int, end: int}
     */
    public static function range(int $current, int $last, int $visible): array
    {
        $visible = max(5, $visible);
        $last = max(1, $last);
        $current = min(max(1, $current), $last);
        $count = min($visible, $last);
        $start = $current - intdiv($count - 1, 2);
        if ($start < 1) {
            $start = 1;
        }
        $end = $start + $count - 1;
        if ($end > $last) {
            $end = $last;
            $start = max(1, $end - $count + 1);
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}
