<?php

namespace Tests\Unit;

use App\Support\ReadableColor;
use App\Support\ShopHeaderChipStyles;
use PHPUnit\Framework\TestCase;

class ReadableColorTest extends TestCase
{
    public function test_bright_brand_colors_get_darker_shade_with_enough_contrast(): void
    {
        foreach (ShopHeaderChipStyles::DEFAULT_PALETTE as $slot) {
            $ink = ReadableColor::onWhite($slot['accent']);

            $this->assertNotSame($slot['accent'], $ink);
            $this->assertGreaterThanOrEqual(ReadableColor::TEXT_ON_WHITE_RATIO, ReadableColor::contrastWithWhite($this->rgb($ink)), $slot['accent']);
        }
    }

    public function test_dark_color_and_invalid_value_stay_unchanged(): void
    {
        $this->assertSame('#222222', ReadableColor::onWhite('#222222'));
        $this->assertSame('rgba(0,0,0,.5)', ReadableColor::onWhite('rgba(0,0,0,.5)'));
    }

    public function test_custom_palette_without_text_color_uses_readable_shade(): void
    {
        $vars = ShopHeaderChipStyles::toCssVars([
            'custom_palette_enabled' => true,
            'palette' => [3 => ['accent' => '#fcb501']],
        ]);

        $this->assertSame('#fcb501', $vars['--hub-chip-color-3']);
        $this->assertSame(ReadableColor::onWhite('#fcb501'), $vars['--hub-chip-text-3']);
    }

    public function test_saved_text_equal_to_accent_becomes_readable_but_own_text_color_is_kept(): void
    {
        $vars = ShopHeaderChipStyles::toCssVars([
            'custom_palette_enabled' => true,
            'palette' => [
                1 => ['accent' => '#3b82f2', 'text' => '#3B82F2'],
                2 => ['accent' => '#ed3626', 'text' => '#111111'],
            ],
        ]);

        $this->assertSame(ReadableColor::onWhite('#3b82f2'), $vars['--hub-chip-text-1']);
        $this->assertSame('#111111', $vars['--hub-chip-text-2']);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
