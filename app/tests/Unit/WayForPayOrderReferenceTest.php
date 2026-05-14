<?php

namespace Tests\Unit;

use App\Services\Payments\WayForPayClient;
use PHPUnit\Framework\TestCase;

class WayForPayOrderReferenceTest extends TestCase
{
    public function test_parses_full_payment_with_ulid_suffix(): void
    {
        $ref = '42-pay-01hzxd74yeskkqqwsbgvkamwsr';

        $parsed = WayForPayClient::parseOrderReference($ref);

        $this->assertSame(42, $parsed['orderId']);
        $this->assertNull($parsed['leg']);
    }

    public function test_parses_immediate_leg_with_ulid(): void
    {
        $ref = '99-imm-01hzxd74yeskkqqwsbgvkamwsr';

        $parsed = WayForPayClient::parseOrderReference($ref);

        $this->assertSame(99, $parsed['orderId']);
        $this->assertSame('immediate', $parsed['leg']);
    }

    public function test_parses_legacy_refs_without_ulid(): void
    {
        $this->assertSame(['orderId' => 7, 'leg' => 'immediate'], WayForPayClient::parseOrderReference('7-imm'));
        $this->assertSame(['orderId' => 7, 'leg' => 'deferred'], WayForPayClient::parseOrderReference('7-def'));
        $this->assertSame(['orderId' => 3, 'leg' => null], WayForPayClient::parseOrderReference('3'));
    }
}
