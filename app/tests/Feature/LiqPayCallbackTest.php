<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiqPayCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_liqpay_callback_rejects_invalid_signature(): void
    {
        $this->post(route('payments.liqpay.callback'), [
            'data' => base64_encode(json_encode(['order_id' => '1', 'status' => 'success'])),
            'signature' => 'invalid',
        ])->assertStatus(400);
    }
}
