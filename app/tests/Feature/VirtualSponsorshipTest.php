<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ManageSponsorship;
use App\Filament\Admin\Resources\SponsorAnimals\SponsorAnimalResource;
use App\Filament\Admin\Resources\Sponsorships\Pages\EditSponsorship;
use App\Filament\Admin\Resources\Sponsorships\Pages\ListSponsorships;
use App\Models\ShopSponsorshipSetting;
use App\Models\SponsorAnimal;
use App\Models\Sponsorship;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VirtualSponsorshipTest extends TestCase
{
    use RefreshDatabase;

    private const JAR_URL = 'https://send.monobank.ua/jar/OpikaTest1';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_section_is_hidden_until_enabled(): void
    {
        $this->animal();

        $this->get(route('sponsorship.index'))->assertNotFound();
        $this->get(route('home'))->assertDontSee(route('sponsorship.index'), false);
    }

    public function test_index_lists_only_active_animals_and_header_links_to_it(): void
    {
        $this->enable();
        $visible = $this->animal(['name' => 'Кролик Пуша']);
        $this->animal(['name' => 'Схований їжак', 'is_active' => false]);

        $this->get(route('home'))->assertSee('href="'.route('sponsorship.index').'"', false);

        $this->get(route('sponsorship.index'))
            ->assertOk()
            ->assertSee('Кролик Пуша')
            ->assertSee(route('sponsorship.show', $visible), false)
            ->assertDontSee('Схований їжак');
    }

    public function test_animal_page_shows_story_and_offered_terms_with_prices(): void
    {
        $this->enable();
        $animal = $this->animal([
            'story' => '<p>Пушу знайшли <strong>взимку</strong></p><script>alert(1)</script>',
            'price_half_year' => null,
        ]);

        $this->get(route('sponsorship.show', $animal))
            ->assertOk()
            ->assertSee('<strong>взимку</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Стати опікуном')
            ->assertSee('1 місяць')
            ->assertSee('1 рік')
            ->assertDontSee('6 місяців')
            ->assertSee(route('sponsorship.apply', ['animal' => $animal, 'term' => 'month']), false);
    }

    public function test_application_creates_pending_sponsorship_and_shows_payment_step(): void
    {
        $this->enable();
        $animal = $this->animal(['price_month' => 300]);

        $response = $this->post(route('sponsorship.store', $animal), [
            'term' => 'month',
            'sponsor_name' => 'Олена Коваль',
            'email' => 'olena@example.com',
            'phone' => '+380 67 000 00 00',
            'message' => 'Хай росте здоровим',
            'show_publicly' => '1',
            'photo' => UploadedFile::fake()->image('me.jpg', 600, 600),
        ]);

        $sponsorship = Sponsorship::query()->sole();
        $response->assertRedirect(route('sponsorship.payment', $sponsorship->public_token));

        $this->assertSame(Sponsorship::STATUS_PENDING, $sponsorship->status);
        $this->assertSame(1, $sponsorship->months);
        $this->assertSame('300.00', (string) $sponsorship->amount);
        $this->assertSame(Sponsorship::PHOTO_PENDING, $sponsorship->photo_status);
        Storage::disk('public')->assertExists($sponsorship->photo_path);

        $this->get(route('sponsorship.payment', $sponsorship->public_token))
            ->assertOk()
            ->assertSee($sponsorship->reference())
            ->assertSee('300')
            ->assertSee(self::JAR_URL, false)
            ->assertSee('на ваш email');
    }

    public function test_application_validates_term_contacts_and_photo_type(): void
    {
        $this->enable();
        $animal = $this->animal(['price_half_year' => null]);

        $this->post(route('sponsorship.store', $animal), [
            'term' => 'half_year',
            'sponsor_name' => '',
            'email' => 'not-an-email',
            'photo' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors(['term', 'sponsor_name', 'email', 'photo']);

        $this->assertSame(0, Sponsorship::query()->count());
    }

    public function test_payment_page_needs_the_secret_token(): void
    {
        $this->enable();

        $this->get(route('sponsorship.payment', 'wrong-token'))->assertNotFound();
    }

    public function test_confirmed_sponsor_appears_on_animal_page_with_approved_photo_only(): void
    {
        $this->enable();
        $animal = $this->animal();
        $approved = $this->sponsorship($animal, ['sponsor_name' => 'Олена Коваль', 'photo_path' => 'sponsorships/olena.jpg', 'photo_status' => Sponsorship::PHOTO_APPROVED]);
        $pendingPhoto = $this->sponsorship($animal, ['sponsor_name' => 'Іван Петренко', 'photo_path' => 'sponsorships/ivan.jpg', 'photo_status' => Sponsorship::PHOTO_PENDING]);
        $this->sponsorship($animal, ['sponsor_name' => 'Неоплачений Гість']);

        Carbon::setTestNow('2026-11-16 10:00:00');
        $approved->confirmPayment();
        $pendingPhoto->confirmPayment();

        $this->assertSame('2026-12-15', $approved->fresh()->ends_at->toDateString());

        $this->get(route('sponsorship.show', $animal))
            ->assertOk()
            ->assertSee('Олена')
            ->assertDontSee('Коваль')
            ->assertSee('sponsorships/olena.jpg', false)
            ->assertSee('Іван')
            ->assertDontSee('sponsorships/ivan.jpg', false)
            ->assertDontSee('Неоплачений');

        Carbon::setTestNow('2026-12-16 10:00:00');
        $this->get(route('sponsorship.show', $animal))->assertDontSee('Олена');
        Carbon::setTestNow();
    }

    public function test_admin_manages_animals_and_confirms_sponsorships(): void
    {
        $this->actingAsAdmin();
        $this->enable();
        $animal = $this->animal();
        $sponsorship = $this->sponsorship($animal, ['photo_path' => 'sponsorships/olena.jpg', 'photo_status' => Sponsorship::PHOTO_PENDING]);

        $this->get('/admin/sponsor-animals')->assertOk()->assertSee('Кролик Пуша');
        $this->get('/admin/sponsor-animals/create')->assertOk();
        $this->get('/admin/sponsor-animals/'.$animal->id.'/edit')->assertOk();
        // Посилання, яке будує сама адмінка (кнопка «Редагувати» в таблиці), має відкриватися
        $this->get(SponsorAnimalResource::getUrl('edit', ['record' => $animal]))->assertOk();
        $this->get('/admin/sponsorships')->assertOk()->assertSee($sponsorship->reference());
        $this->get('/admin/sponsorships/'.$sponsorship->id.'/edit')->assertOk();
        $this->get('/admin/sponsorship-settings')->assertOk();

        Livewire::test(ListSponsorships::class)
            ->callTableAction('confirmPayment', $sponsorship)
            ->callTableAction('approvePhoto', $sponsorship);

        $sponsorship->refresh();
        $this->assertSame(Sponsorship::STATUS_ACTIVE, $sponsorship->status);
        $this->assertNotNull($sponsorship->ends_at);
        $this->assertSame(Sponsorship::PHOTO_APPROVED, $sponsorship->photo_status);
    }

    public function test_admin_sponsorship_page_shows_which_animal_was_chosen(): void
    {
        $this->actingAsAdmin();
        Storage::disk('public')->put('sponsor-animals/photos/pusha.jpg', UploadedFile::fake()->image('pusha.jpg', 400, 400)->getContent());
        $animal = $this->animal(['photos' => ['sponsor-animals/photos/pusha.jpg']]);
        $sponsorship = $this->sponsorship($animal, ['sponsor_name' => 'Олена Коваль']);

        $this->get('/admin/sponsorships/'.$sponsorship->id.'/edit')
            ->assertOk()
            ->assertSee($sponsorship->reference().' · Кролик Пуша')
            ->assertSee('Пуша любить моркву')
            ->assertSee('pusha', false)
            ->assertSee(route('sponsorship.show', $animal), false)
            ->assertSee(SponsorAnimalResource::getUrl('edit', ['record' => $animal]), false);

        $this->get('/admin/sponsorships')->assertOk()->assertSee('pusha', false);
    }

    public function test_admin_lists_warn_when_section_is_switched_off(): void
    {
        $this->actingAsAdmin();
        $warning = 'Віртуальна опіка вимкнена';

        $this->get('/admin/sponsor-animals')->assertOk()->assertSee($warning)->assertSee('/admin/sponsorship-settings', false);
        $this->get('/admin/sponsorships')->assertOk()->assertSee($warning);

        $this->enable();
        ShopSponsorshipSetting::forgetResolvedCache();

        $this->get('/admin/sponsor-animals')->assertOk()->assertDontSee($warning);
    }

    public function test_admin_edits_section_texts_and_rejects_foreign_jar(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ManageSponsorship::class)
            ->set('data.is_enabled', true)
            ->set('data.jar_url', 'https://evil.example/jar/x')
            ->call('save')
            ->assertHasErrors(['data.jar_url']);

        Livewire::test(ManageSponsorship::class)
            ->set('data.is_enabled', true)
            ->set('data.jar_url', self::JAR_URL)
            ->set('data.texts.header_label', 'Опіка над хвостиками')
            ->set('data.texts.become_label', 'Взяти під крило')
            ->call('save')
            ->assertHasNoErrors();

        ShopSponsorshipSetting::forgetResolvedCache();
        $animal = $this->animal();

        $this->get(route('home'))->assertSee('Опіка над хвостиками');
        $this->get(route('sponsorship.show', $animal))->assertSee('Взяти під крило');
    }

    public function test_sponsor_photo_is_reencoded_and_downscaled(): void
    {
        $this->enable();
        $animal = $this->animal();

        $this->post(route('sponsorship.store', $animal), $this->validForm([
            'photo' => UploadedFile::fake()->image('big.png', 2400, 1800),
        ]))->assertRedirect();

        $path = Sponsorship::query()->sole()->photo_path;
        $this->assertStringEndsWith('.jpg', $path);
        [$width, $height] = getimagesize(Storage::disk('public')->path($path));
        $this->assertLessThanOrEqual(1200, max($width, $height));
    }

    public function test_bots_filling_the_hidden_field_are_ignored(): void
    {
        $this->enable();
        $animal = $this->animal();

        $this->post(route('sponsorship.store', $animal), $this->validForm(['website' => 'https://spam.example']))
            ->assertRedirect(route('sponsorship.show', $animal));

        $this->assertSame(0, Sponsorship::query()->count());
    }

    public function test_rejected_or_cancelled_photos_are_deleted_from_disk(): void
    {
        $this->actingAsAdmin();
        $this->enable();
        $animal = $this->animal();
        Storage::disk('public')->put('sponsorships/a.jpg', 'x');
        Storage::disk('public')->put('sponsorships/b.jpg', 'x');
        $rejected = $this->sponsorship($animal, ['photo_path' => 'sponsorships/a.jpg', 'photo_status' => Sponsorship::PHOTO_PENDING]);
        $cancelled = $this->sponsorship($animal, ['photo_path' => 'sponsorships/b.jpg', 'photo_status' => Sponsorship::PHOTO_PENDING]);

        Livewire::test(ListSponsorships::class)
            ->callTableAction('rejectPhoto', $rejected)
            ->callTableAction('cancel', $cancelled);

        Storage::disk('public')->assertMissing('sponsorships/a.jpg');
        Storage::disk('public')->assertMissing('sponsorships/b.jpg');
        $this->assertNull($rejected->fresh()->photo_path);
        $this->assertSame(Sponsorship::STATUS_CANCELLED, $cancelled->fresh()->status);
    }

    public function test_confirming_payment_twice_keeps_the_first_dates(): void
    {
        $animal = $this->animal();
        $sponsorship = $this->sponsorship($animal);

        Carbon::setTestNow('2026-11-16 10:00:00');
        $sponsorship->confirmPayment();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $sponsorship->fresh()->confirmPayment();
        Carbon::setTestNow();

        $this->assertSame('2026-11-16', $sponsorship->fresh()->starts_at->toDateString());
    }

    public function test_month_end_start_keeps_a_full_month(): void
    {
        $sponsorship = $this->sponsorship($this->animal());

        $this->assertSame('2026-02-28', $sponsorship->periodFrom(Carbon::parse('2026-01-31'))['ends_at']->toDateString());
        $this->assertSame('2026-01-31', $sponsorship->periodFrom(Carbon::parse('2026-01-01'))['ends_at']->toDateString());
        $this->assertSame('2026-12-15', $sponsorship->periodFrom(Carbon::parse('2026-11-16'))['ends_at']->toDateString());
    }

    public function test_expired_sponsorship_is_not_shown_as_active_on_payment_page(): void
    {
        $this->enable();
        $sponsorship = $this->sponsorship($this->animal());
        Carbon::setTestNow('2026-01-01 10:00:00');
        $sponsorship->confirmPayment();
        Carbon::setTestNow();

        $this->get(route('sponsorship.payment', $sponsorship->public_token))
            ->assertOk()
            ->assertDontSee('Опікунство активне до');
    }

    public function test_admin_setting_status_active_by_hand_fills_the_dates(): void
    {
        $this->actingAsAdmin();
        $sponsorship = $this->sponsorship($this->animal(), ['term' => 'half_year', 'months' => 6]);

        Livewire::test(EditSponsorship::class, ['record' => $sponsorship->getRouteKey()])
            ->set('data.status', Sponsorship::STATUS_ACTIVE)
            ->call('save')
            ->assertHasNoErrors();

        $sponsorship->refresh();
        $this->assertNotNull($sponsorship->starts_at);
        $this->assertSame(
            $sponsorship->starts_at->copy()->addMonthsNoOverflow(6)->subDay()->toDateString(),
            $sponsorship->ends_at->toDateString()
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return [
            'term' => 'month',
            'sponsor_name' => 'Олена Коваль',
            'email' => 'olena@example.com',
            'show_publicly' => '1',
            ...$overrides,
        ];
    }

    public function test_admin_home_shows_what_needs_attention(): void
    {
        $this->actingAsAdmin();
        $this->get('/admin')->assertOk()->assertDontSee('Віртуальна опіка: потребує уваги');

        $animal = $this->animal();
        $this->sponsorship($animal, ['sponsor_name' => 'Олена Коваль']);
        $this->sponsorship($animal, ['status' => Sponsorship::STATUS_ACTIVE, 'ends_at' => now()->addMonth(), 'photo_path' => 'sponsorships/x.jpg', 'photo_status' => Sponsorship::PHOTO_PENDING]);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Віртуальна опіка: потребує уваги')
            ->assertSee('1 заявка чекають оплату')
            ->assertSee('1 фото на перевірці')
            ->assertSee('Олена Коваль');
    }

    private function actingAsAdmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    private function enable(): void
    {
        ShopSponsorshipSetting::query()->create([
            'is_enabled' => true,
            'jar_url' => self::JAR_URL,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function animal(array $overrides = []): SponsorAnimal
    {
        return SponsorAnimal::query()->create([
            'name' => 'Кролик Пуша',
            'species' => 'Кролик',
            'excerpt' => 'Пуша любить моркву',
            'story' => '<p>Історія Пуші</p>',
            'price_month' => 300,
            'price_half_year' => 1600,
            'price_year' => 3000,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sponsorship(SponsorAnimal $animal, array $overrides = []): Sponsorship
    {
        return Sponsorship::query()->create([
            'sponsor_animal_id' => $animal->id,
            'animal_name' => $animal->name,
            'term' => 'month',
            'months' => 1,
            'amount' => 300,
            'sponsor_name' => 'Гість',
            'email' => 'guest@example.com',
            'status' => Sponsorship::STATUS_PENDING,
            ...$overrides,
        ]);
    }
}
