<?php

namespace Tests\Feature;

use App\Models\ShopIntegrationSetting;
use App\Models\User;
use App\Support\GoogleMapsApiKey;
use App\Support\NovaPoshtaApiKey;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrationSecretsTest extends TestCase
{
    use RefreshDatabase;

    private const WAYFORPAY_SECRET = '0123456789abcdef0123456789abcdef01234567';

    public function test_keys_saved_through_the_model_can_be_read_back(): void
    {
        ShopIntegrationSetting::record()->forceFill([
            'wayforpay_secret_key' => self::WAYFORPAY_SECRET,
            'nova_poshta_api_key' => 'np-key-123',
            'google_maps_api_key' => 'maps-key-456',
        ])->save();

        $record = ShopIntegrationSetting::query()->firstOrFail();

        $this->assertSame(self::WAYFORPAY_SECRET, $record->readEncrypted('wayforpay_secret_key'));
        $this->assertSame('np-key-123', $record->readEncrypted('nova_poshta_api_key'));
        $this->assertTrue($record->isEncryptedReadable('google_maps_api_key'));
    }

    public function test_older_serialized_values_and_broken_values_do_not_crash(): void
    {
        $record = ShopIntegrationSetting::record();
        DB::table('shop_integration_settings')->where('id', $record->id)->update([
            'nova_poshta_api_key' => Crypt::encrypt('legacy-np-key'),
            'google_maps_api_key' => 'not-encrypted-at-all',
        ]);
        $record = ShopIntegrationSetting::query()->firstOrFail();

        $this->assertSame('legacy-np-key', $record->readEncrypted('nova_poshta_api_key'));
        $this->assertNull($record->readEncrypted('google_maps_api_key'));
    }

    public function test_admin_integrations_and_checkout_open_when_keys_are_stored(): void
    {
        ShopIntegrationSetting::record()->forceFill([
            'wayforpay_secret_key' => self::WAYFORPAY_SECRET,
            'nova_poshta_api_key' => 'np-key-123',
            'google_maps_api_key' => 'maps-key-456',
        ])->save();

        $this->assertSame('np-key-123', app(NovaPoshtaApiKey::class)->current());
        $this->assertSame('maps-key-456', app(GoogleMapsApiKey::class)->current());

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)->get('/admin/shop-integrations')->assertOk();
    }
}
