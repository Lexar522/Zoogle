<?php

namespace App\Support;

/**
 * Темніший відтінок кольору бренду для тексту на білому чи світлому тлі (контраст WCAG ≥ 4.5).
 * Яскравий колір лишається на рамках і кнопках, а напис стає читабельним (PageSpeed: «Контраст»).
 */
final class ReadableColor
{
    /** З запасом над 4.5: чіпи мають ще й легку кольорову підкладку, на якій контраст трохи нижчий. */
    public const TEXT_ON_WHITE_RATIO = 5.2;

    private const DARKEN_STEP = 0.01;

    /** Колір, що вже дає потрібний контраст, повертається без змін; некоректний — як є. */
    public static function onWhite(string $hex, float $ratio = self::TEXT_ON_WHITE_RATIO): string
    {
        $rgb = self::parseHex($hex);
        if ($rgb === null) {
            return $hex;
        }

        for ($keep = 1.0; $keep > 0; $keep -= self::DARKEN_STEP) {
            $shade = array_map(fn (int $channel): int => (int) round($channel * $keep), $rgb);
            if (self::contrastWithWhite($shade) >= $ratio) {
                return $keep === 1.0 ? strtolower($hex) : sprintf('#%02x%02x%02x', ...$shade);
            }
        }

        return '#000000';
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public static function contrastWithWhite(array $rgb): float
    {
        return 1.05 / (self::luminance($rgb) + 0.05);
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function parseHex(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return null;
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private static function luminance(array $rgb): float
    {
        $linear = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
