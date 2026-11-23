<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductQuestions\Pages\ListProductQuestions;
use App\Mail\ProductQuestionAnswered;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ProductQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_question_is_saved_and_staff_get_a_bell_notification(): void
    {
        $product = $this->product();

        $this->post(route('catalog.questions.store', $product->slug), [
            'author_name' => 'Олена',
            'email' => 'olena@example.com',
            'body' => 'Чи підійде клітка для двох шиншил?',
        ])->assertRedirect(route('catalog.show', $product->slug).'#questions');

        $question = ProductQuestion::query()->sole();
        $this->assertSame(ProductQuestion::STATUS_PENDING, $question->status);
        $this->assertSame('olena@example.com', $question->email);

        $admin = $this->admin();
        $this->assertSame(1, $admin->unreadNotifications()->count());
        $this->assertStringContainsString('Нове питання', (string) $admin->unreadNotifications()->first()->data['title']);

        // Без відповіді на сайті питання не видно
        $this->get(route('catalog.show', $product->slug))->assertOk()->assertDontSee('Чи підійде клітка для двох шиншил?');
    }

    public function test_answer_appears_on_product_page_and_is_emailed(): void
    {
        Mail::fake();
        $product = $this->product();
        $question = $this->question($product, email: 'olena@example.com');
        $this->actingAs($this->admin());

        Livewire::test(ListProductQuestions::class)
            ->callTableAction('answer', $question, ['answer' => 'Так, підійде — розмір 80 см.', 'send_email' => true])
            ->assertHasNoTableActionErrors();

        $question->refresh();
        $this->assertSame(ProductQuestion::STATUS_ANSWERED, $question->status);
        $this->assertNotNull($question->answer_emailed_at);
        Mail::assertSent(ProductQuestionAnswered::class, fn (ProductQuestionAnswered $mail): bool => $mail->hasTo('olena@example.com'));

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertSee('Чи підійде клітка?')
            ->assertSee('Так, підійде — розмір 80 см.')
            ->assertSee('Відповідь ZOOGLE')
            ->assertDontSee('olena@example.com');
    }

    public function test_answer_without_email_does_not_send_mail(): void
    {
        Mail::fake();
        $product = $this->product();
        $question = $this->question($product);
        $this->actingAs($this->admin());

        Livewire::test(ListProductQuestions::class)
            ->callTableAction('answer', $question, ['answer' => 'Так.']);

        Mail::assertNothingSent();
        $this->assertSame(ProductQuestion::STATUS_ANSWERED, $question->fresh()->status);
    }

    public function test_bots_profanity_and_empty_questions_are_rejected(): void
    {
        $product = $this->product();

        $this->post(route('catalog.questions.store', $product->slug), [
            'author_name' => 'Bot', 'body' => 'Buy pills now', 'website' => 'http://spam.example',
        ]);
        $this->post(route('catalog.questions.store', $product->slug), [
            'author_name' => 'Олена', 'body' => 'Що за хуйня?', 'email' => 'не-email',
        ])->assertSessionHasErrorsIn('question', ['body', 'email']);

        $this->assertSame(0, ProductQuestion::query()->count());
    }

    public function test_admin_home_shows_questions_waiting_for_answer(): void
    {
        $product = $this->product();
        $this->question($product, body: 'Яка висота клітки?');

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Питання покупців: потрібна відповідь')
            ->assertSee('1 питання чекає відповіді')
            ->assertSee('Яка висота клітки?')
            ->assertSee(route('catalog.show', $product->slug).'#questions', false);
    }

    public function test_only_staff_can_delete_question_from_product_page(): void
    {
        $product = $this->product();
        $question = $this->question($product);
        $question->saveAnswer('Так.', null);

        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('catalog.show', $product->slug))->assertDontSee('Видалити питання');
        $this->actingAs($customer)->delete(route('catalog.questions.destroy', [$product->slug, $question]))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('catalog.show', $product->slug))->assertSee('Видалити питання');
        $this->actingAs($admin)
            ->delete(route('catalog.questions.destroy', [$product->slug, $question]))
            ->assertRedirect(route('catalog.show', $product->slug).'#questions');

        $this->assertModelMissing($question);
    }

    public function test_review_and_question_forms_keep_their_own_input(): void
    {
        $product = $this->product();

        $response = $this->followingRedirects()
            ->post(route('catalog.questions.store', $product->slug), ['author_name' => 'Олена', 'body' => 'Ой'])
            ->assertOk()
            ->assertSee('id="questions" role="tabpanel" aria-labelledby="pdp-tab-questions" >', false);

        $this->assertSame(1, substr_count((string) $response->getContent(), '>Ой</textarea>'), 'Текст питання не має потрапити у форму відгуку');
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();
    }

    private function question(Product $product, string $body = 'Чи підійде клітка?', ?string $email = null): ProductQuestion
    {
        return ProductQuestion::query()->create([
            'product_id' => $product->id,
            'author_name' => 'Олена Петренко',
            'email' => $email,
            'body' => $body,
            'status' => ProductQuestion::STATUS_PENDING,
        ]);
    }

    private function product(): Product
    {
        $group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        $category = OptionValue::query()->firstOrCreate(
            ['option_group_id' => $group->id, 'slug' => 'questions-root'],
            ['name' => 'Клітки', 'sort_order' => 1, 'is_active' => true]
        );

        return Product::query()->create([
            'title' => 'Клітка для шиншили',
            'slug' => 'questions-product-'.uniqid(),
            'description' => '<p>Опис</p>',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $category->id,
            'category_value_id' => $category->id,
            'price' => 900,
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
            'variant_options' => [
                ['option_group_id' => $group->id, 'option_value_ids' => [$category->id]],
            ],
        ]);
    }
}
