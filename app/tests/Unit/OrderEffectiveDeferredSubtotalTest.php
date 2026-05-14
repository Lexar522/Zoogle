<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderEffectiveDeferredSubtotalTest extends TestCase
{
    public function test_uses_total_when_deferred_subtotal_stored_as_zero_with_flag(): void
    {
        $o = new Order([
            'deferred_subtotal' => 0,
            'deferred_online_payment' => true,
            'total' => 100.50,
        ]);

        $this->assertSame(100.5, $o->effectiveDeferredSubtotal());
    }

    public function test_positive_deferred_subtotal_unchanged(): void
    {
        $o = new Order([
            'deferred_subtotal' => 40,
            'deferred_online_payment' => true,
            'total' => 100,
        ]);

        $this->assertSame(40.0, $o->effectiveDeferredSubtotal());
    }
}
