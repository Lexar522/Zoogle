<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_animals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('species', 120)->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('story')->nullable();
            $table->json('photos')->nullable();
            $table->json('photo_meta')->nullable();
            $table->decimal('price_month', 10, 2)->nullable();
            $table->decimal('price_half_year', 10, 2)->nullable();
            $table->decimal('price_year', 10, 2)->nullable();
            $table->string('jar_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_animal_id')->nullable()->constrained('sponsor_animals')->nullOnDelete();
            $table->string('animal_name', 120);
            $table->string('public_token', 64)->unique();
            $table->string('term', 20);
            $table->unsignedTinyInteger('months');
            $table->decimal('amount', 10, 2);
            $table->string('sponsor_name', 120);
            $table->string('email', 160);
            $table->string('phone', 50)->nullable();
            $table->text('message')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->string('photo_status', 20)->default('none');
            $table->boolean('show_publicly')->default(true);
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->index(['sponsor_animal_id', 'status', 'ends_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('shop_sponsorship_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('jar_url', 500)->nullable();
            $table->json('texts')->nullable();
            $table->longText('intro')->nullable();
            $table->longText('thanks_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('sponsor_animals');
        Schema::dropIfExists('shop_sponsorship_settings');
    }
};
