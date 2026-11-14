<?php

namespace App\Support;

/**
 * Кольори карток «Чому обирають саме нас»: з одного акценту збирає відтінки рамки, номера й тексту.
 */
final class HomeBenefitStyles
{
    /** @var list<string> */
    public const DEFAULTS = ['#367df1', '#feb400', '#339a39', '#ef3829'];

    public static function normalizeHex(?string $color, string $fallback = '#367df1'): string
    {
        $color = trim((string) $color);
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $matches) === 1) {
            $hex = $matches[1];
            $color = '#'.$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
            return strtolower($color);
        }

        return self::normalizeHex($fallback, '#367df1');
    }

    public static function cssVars(?string $color): string
    {
        $hex = self::normalizeHex($color);
        [$r, $g, $b] = self::rgb($hex);

        return sprintf(
            '--benefit:%s;--benefit-rgb:%d,%d,%d;--benefit-light:%s;--benefit-dark:%s;--benefit-title:%s;--benefit-text:%s;',
            $hex,
            $r,
            $g,
            $b,
            self::mix($r, $g, $b, 255, 255, 255, 0.32),
            self::mix($r, $g, $b, 0, 0, 0, 0.24),
            self::mix($r, $g, $b, 0, 0, 0, 0.45),
            self::mix($r, $g, $b, 55, 58, 66, 0.55),
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function mix(int $r, int $g, int $b, int $targetR, int $targetG, int $targetB, float $amount): string
    {
        $amount = max(0.0, min(1.0, $amount));

        return sprintf(
            '#%02x%02x%02x',
            (int) round($r + (($targetR - $r) * $amount)),
            (int) round($g + (($targetG - $g) * $amount)),
            (int) round($b + (($targetB - $b) * $amount)),
        );
    }
}
