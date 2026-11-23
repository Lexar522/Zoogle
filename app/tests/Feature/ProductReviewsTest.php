<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ProductReviews\Pages\ListProductReviews;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_review_waits_for_moderation(): void
    {
        $product = $this->product();

        $this->post(route('catalog.reviews.store', $product->slug), [
            'author_name' => 'Олена Петренко',
            'rating' => 5,
            'body' => 'Клітка міцна, хом\'як щасливий.',
        ])->assertRedirect(route('catalog.show', $product->slug).'#reviews');

        $review = ProductReview::query()->sole();
        $this->assertSame(ProductReview::STATUS_PENDING, $review->status);
        $this->assertFalse($review->is_verified_buyer);

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertDontSee('хом\'як щасливий', false)
            ->assertDontSee('aggregateRating', false);
    }

    public function test_approved_reviews_show_first_name_and_give_google_stars(): void
    {
        $product = $this->product();
        $this->review($product, 'Олена Петренко', 5, ProductReview::STATUS_APPROVED);
        $this->review($product, 'Іван', 4, ProductReview::STATUS_APPROVED);
        $this->review($product, 'Спамер', 1, ProductReview::STATUS_REJECTED);

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertSee('Олена')
            ->assertDontSee('Петренко')
            ->assertDontSee('Спамер')
            ->assertSee('2 відгуки')
            ->assertSee('"aggregateRating":{"@type":"AggregateRating","ratingValue":"4.5","reviewCount":2', false)
            ->assertSee('"@type":"Review"', false);
    }

    public function test_bots_and_bad_input_are_stopped(): void
    {
        $product = $this->product();

        $this->post(route('catalog.reviews.store', $product->slug), [
            'author_name' => 'Bot',
            'rating' => 5,
            'body' => 'Buy cheap pills online now',
            'website' => 'http://spam.example',
        ])->assertRedirect();

        $this->post(route('catalog.reviews.store', $product->slug), [
            'author_name' => 'Олена',
            'rating' => 9,
            'body' => 'коротко',
        ])
            ->assertRedirect(route('catalog.show', $product->slug).'#reviews')
            ->assertSessionHasErrorsIn('review', ['rating', 'body']);

        $this->assertSame(0, ProductReview::query()->count());
    }

    public function test_profanity_is_rejected(): void
    {
        $product = $this->product();

        $this->post(route('catalog.reviews.store', $product->slug), [
            'author_name' => 'Олена',
            'rating' => 1,
            'body' => 'Повна хуйня, а не клітка',
        ])
            ->assertRedirect(route('catalog.show', $product->slug).'#reviews')
            ->assertSessionHasErrorsIn('review', ['body' => 'Будь ласка, напишіть без нецензурних слів.']);

        $this->assertSame(0, ProductReview::query()->count());
    }

    public function test_review_markup_is_escaped(): void
    {
        $product = $this->product();
        $this->review($product, 'Олена', 5, ProductReview::STATUS_APPROVED, '<script>alert(1)</script> гарна клітка');

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_buyer_review_is_marked_as_verified(): void
    {
        $product = $this->product();
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'customer_name' => 'Покупець',
            'customer_phone' => '0500000000',
            'total' => 900,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);
        $order->items()->create(['product_id' => $product->id, 'title_snapshot' => 'Клітка', 'price' => 900, 'qty' => 1, 'line_total' => 900]);

        $this->actingAs($user)->post(route('catalog.reviews.store', $product->slug), [
            'author_name' => 'Олена',
            'rating' => 5,
            'body' => 'Купувала цю клітку, усе добре.',
        ]);

        $this->assertTrue(ProductReview::query()->sole()->is_verified_buyer);
    }

    public function test_admin_publishes_review(): void
    {
        $product = $this->product();
        $review = $this->review($product, 'Олена', 5, ProductReview::STATUS_PENDING);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();
        $this->actingAs($admin);

        $this->get('/admin/product-reviews')->assertOk()->assertSee('Олена');

        Livewire::test(ListProductReviews::class)
            ->callTableAction('approve', $review);

        $review->refresh();
        $this->assertSame(ProductReview::STATUS_APPROVED, $review->status);
        $this->assertNotNull($review->approved_at);
    }

    public function test_admin_deletes_review_from_product_page(): void
    {
        $product = $this->product();
        $review = $this->review($product, 'Олена', 2, ProductReview::STATUS_APPROVED);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('catalog.show', $product->slug))
            ->assertSee('Видалити відгук');

        $this->actingAs($admin)
            ->delete(route('catalog.reviews.destroy', [$product->slug, $review]))
            ->assertRedirect(route('catalog.show', $product->slug).'#reviews');

        $this->assertModelMissing($review);
    }

    public function test_customers_cannot_delete_reviews(): void
    {
        $product = $this->product();
        $review = $this->review($product, 'Олена', 5, ProductReview::STATUS_APPROVED);

        $this->get(route('catalog.show', $product->slug))->assertDontSee('Видалити відгук');
        $this->delete(route('catalog.reviews.destroy', [$product->slug, $review]))->assertRedirect(route('login'));

        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('catalog.show', $product->slug))->assertDontSee('Видалити відгук');
        $this->actingAs($customer)->delete(route('catalog.reviews.destroy', [$product->slug, $review]))->assertForbidden();

        $this->assertModelExists($review);
    }

    public function test_review_cannot_be_deleted_through_another_product(): void
    {
        $product = $this->product();
        $other = $this->product();
        $review = $this->review($product, 'Олена', 5, ProductReview::STATUS_APPROVED);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)->delete(route('catalog.reviews.destroy', [$other->slug, $review]))->assertNotFound();

        $this->assertModelExists($review);
    }

    public function test_admin_home_lists_new_reviews_with_product_link(): void
    {
        $product = $this->product();
        $this->review($product, 'Олена', 5, ProductReview::STATUS_PENDING, 'Дуже зручна клітка для хом\'яка');
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Нові відгуки')
            ->assertSee('1 відгук чекає перевірки')
            ->assertSee('Дуже зручна клітка', false)
            ->assertSee(route('catalog.show', $product->slug).'#reviews', false);
    }

    public function test_admin_home_hides_reviews_block_when_there_are_none(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('Нові відгуки');
    }

    private function review(Product $product, string $name, int $rating, string $status, string $body = 'Чудовий товар для улюбленця'): ProductReview
    {
        return ProductReview::query()->create([
            'product_id' => $product->id,
            'author_name' => $name,
            'rating' => $rating,
            'body' => $body,
            'status' => $status,
            'approved_at' => $status === ProductReview::STATUS_APPROVED ? now() : null,
        ]);
    }

    private function product(): Product
    {
        $group = OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
        $category = OptionValue::query()->firstOrCreate(
            ['option_group_id' => $group->id, 'slug' => 'reviews-root'],
            ['name' => 'Клітки', 'sort_order' => 1, 'is_active' => true]
        );

        return Product::query()->create([
            'title' => 'Клітка для хом\'яка',
            'slug' => 'reviews-product-'.uniqid(),
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
