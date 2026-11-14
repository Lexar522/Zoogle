<?php

namespace Tests\Unit;

use App\Support\ListingDefaultOptionPrice;
use Tests\TestCase;

class ListingDefaultOptionPriceTest extends TestCase
{
    public function test_uses_admin_default_addon_instead_of_minimum(): void
    {
        $rows = [
            [
                'option_group_id' => 10,
                'option_value_ids' => [1, 2, 3],
                'default_option_value_id' => 1,
                'value_prices' => [
                    '1' => 530.0,
                    '2' => 405.0,
                    '3' => 1030.0,
                ],
            ],
        ];

        $this->assertSame(530.0, ListingDefaultOptionPrice::addonTotalForRows($rows));
    }

    public function test_falls_back_to_minimum_addon_when_default_missing(): void
    {
        $rows = [
            [
                'option_group_id' => 10,
                'option_value_ids' => [1, 2, 3],
                'value_prices' => [
                    '1' => 530.0,
                    '2' => 405.0,
                    '3' => 1030.0,
                ],
            ],
        ];

        $this->assertSame(405.0, ListingDefaultOptionPrice::addonTotalForRows($rows));
    }

    public function test_prefers_zero_addon_when_present_and_no_default(): void
    {
        $rows = [
            [
                'option_group_id' => 10,
                'option_value_ids' => [1, 2],
                'value_prices' => [
                    '2' => 100.0,
                ],
            ],
        ];

        $this->assertSame(0.0, ListingDefaultOptionPrice::addonTotalForRows($rows));
    }
}
