<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Pages\ManageShopIntegrations;
use App\Models\ShopContactItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageShopIntegrationsContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_contacts_can_include_tiktok_and_keep_order(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(ManageShopIntegrations::class)
            ->assertSuccessful()
            ->fillForm([
                'contact_items' => [
                    ['kind' => 'tiktok', 'value' => '@zoogle.forpet'],
                    ['kind' => 'phone', 'value' => '+38093359868188'],
                    ['kind' => 'instagram', 'value' => '@zoogle.ua'],
                ],
            ])
            ->call('saveHeaderContacts')
            ->assertHasNoFormErrors();

        $rows = ShopContactItem::query()->ordered()->get();
        $this->assertSame(['tiktok', 'phone', 'instagram'], $rows->pluck('kind')->all());
        $this->assertSame([0, 1, 2], $rows->pluck('sort_order')->all());
        $this->assertSame('@zoogle.forpet', $rows[0]->value);
    }
}
