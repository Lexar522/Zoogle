<?php

namespace Tests\Unit;

use App\Support\HomeBenefitStyles;
use PHPUnit\Framework\TestCase;

class HomeBenefitStylesTest extends TestCase
{
    public function test_short_hex_expands_and_builds_rgb_vars(): void
    {
        $vars = HomeBenefitStyles::cssVars('#abc');

        $this->assertStringContainsString('--benefit:#aabbcc;', $vars);
        $this->assertStringContainsString('--benefit-rgb:170,187,204;', $vars);
        $this->assertStringContainsString('--benefit-title:', $vars);
    }

    public function test_invalid_color_falls_back_to_blue(): void
    {
        $this->assertSame('#367df1', HomeBenefitStyles::normalizeHex('red'));
        $this->assertStringContainsString('--benefit:#367df1;', HomeBenefitStyles::cssVars('nope'));
    }
}
