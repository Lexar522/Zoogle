<?php

namespace Tests\Unit;

use App\Filament\Admin\Concerns\ProtectsListingPhotosOnSave;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingPhotosSaveTest extends TestCase
{
    use ProtectsListingPhotosOnSave;

    public function test_empty_incoming_preserves_existing_files_on_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/1/photos/a.jpg', 'image');

        $product = new Product;
        $product->photos = ['products/1/photos/a.jpg'];

        $this->assertSame(
            ['products/1/photos/a.jpg'],
            $this->sanitizeListingPhotosForSave([], $product)
        );
    }

    public function test_broken_incoming_paths_fall_back_to_record(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/1/photos/a.jpg', 'image');

        $product = new Product;
        $product->photos = ['products/1/photos/a.jpg'];

        $this->assertSame(
            ['products/1/photos/a.jpg'],
            $this->sanitizeListingPhotosForSave(['livewire-tmp/fake.jpg'], $product)
        );
    }

    public function test_full_url_in_form_normalizes_to_relative_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/1/photos/a.jpg', 'image');

        $data = $this->sanitizeListingPhotosForForm([
            'photos' => ['https://zoogle.in.ua/storage/products/1/photos/a.jpg'],
        ]);

        $this->assertSame(['products/1/photos/a.jpg'], $data['photos']);
    }

    public function test_empty_incoming_restores_from_disk_when_db_empty(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/1/photos/a.jpg', 'image');

        $product = new Product;
        $product->id = 1;
        $product->photos = [];

        $this->assertSame(
            ['products/1/photos/a.jpg'],
            $this->sanitizeListingPhotosForSave([], $product)
        );
    }

    public function test_relocates_draft_photos_to_product_directory_on_save(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/draft/photos/a.jpg', 'image');

        $product = new Product;
        $product->id = 2;

        $this->assertSame(
            ['products/2/photos/a.jpg'],
            $this->sanitizeListingPhotosForSave(['products/draft/photos/a.jpg'], $product)
        );

        Storage::disk('public')->assertMissing('products/draft/photos/a.jpg');
        Storage::disk('public')->assertExists('products/2/photos/a.jpg');
    }
}
