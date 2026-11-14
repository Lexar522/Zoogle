<?php

namespace App\Filament\Admin\Widgets\Analytics;

final class AnalyticsChartStyle
{
    public const BLUE = '#007aff';

    public const GREEN = '#34c759';

    public const ORANGE = '#ff9500';

    public const PINK = '#ff2d55';

    public const PURPLE = '#af52de';

    public const MUTED = '#8e8e93';

    public const GRID = 'rgba(142, 142, 147, 0.16)';

    private const FONT = '-apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Inter, sans-serif';

    /**
     * @param  list<int|float>  $values
     * @return array<string, mixed>
     */
    public static function line(string $color, array $values, bool $area = true): array
    {
        $data = array_map(fn (int|float $value): float => (float) $value, array_values($values));

        return [
            'data' => $data === [] ? [0.0] : $data,
            'borderColor' => $color,
            'backgroundColor' => $area ? self::fade($color, 0.12) : 'transparent',
            'borderWidth' => 2.5,
            'borderCapStyle' => 'round',
            'borderJoinStyle' => 'round',
            'fill' => $area,
            'tension' => 0.4,
            'pointRadius' => 0,
            'pointHoverRadius' => 5,
            'pointHoverBackgroundColor' => $color,
            'pointHoverBorderColor' => '#ffffff',
            'pointHoverBorderWidth' => 2,
            'pointHitRadius' => 18,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function options(array $extra = []): array
    {
        $tickFont = ['family' => self::FONT, 'size' => 11];

        return array_replace_recursive([
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                    'align' => 'start',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'boxWidth' => 7,
                        'boxHeight' => 7,
                        'padding' => 16,
                        'color' => self::MUTED,
                        'font' => ['family' => self::FONT, 'size' => 12],
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'rgba(28, 28, 30, 0.92)',
                    'titleColor' => '#f5f5f7',
                    'bodyColor' => '#f5f5f7',
                    'padding' => 12,
                    'cornerRadius' => 12,
                    'boxPadding' => 6,
                    'usePointStyle' => true,
                    'titleFont' => ['family' => self::FONT, 'weight' => '600'],
                    'bodyFont' => ['family' => self::FONT],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                    'border' => ['display' => false],
                    'ticks' => [
                        'color' => self::MUTED,
                        'font' => $tickFont,
                        'maxRotation' => 0,
                        'autoSkipPadding' => 24,
                        'padding' => 8,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => self::GRID,
                        'drawTicks' => false,
                    ],
                    'border' => ['display' => false],
                    'ticks' => [
                        'color' => self::MUTED,
                        'font' => $tickFont,
                        'maxTicksLimit' => 5,
                        'precision' => 0,
                        'padding' => 10,
                    ],
                ],
            ],
            'layout' => [
                'padding' => ['top' => 4, 'right' => 4],
            ],
        ], $extra);
    }

    /**
     * Маленький графік у картці показника. Координати для viewBox 0 0 100 32.
     *
     * @param  array<int, int|float>  $values
     * @return array{line: string, area: string}|null
     */
    public static function sparkline(array $values): ?array
    {
        $values = array_map('floatval', array_values($values));
        if (count($values) < 2 || max($values) <= 0) {
            return null;
        }

        $max = max($values);
        $step = 100 / (count($values) - 1);
        $points = [];
        foreach ($values as $index => $value) {
            $points[] = round($index * $step, 2).' '.round(30 - ($value / $max) * 28, 2);
        }

        $line = 'M'.implode(' L', $points);

        return [
            'line' => $line,
            'area' => $line.' L100 32 L0 32 Z',
        ];
    }

    private static function fade(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        return 'rgba('.$red.', '.$green.', '.$blue.', '.$alpha.')';
    }
}
