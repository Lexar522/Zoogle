<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_footer_faq_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('question_uk', 512)->nullable();
            $table->string('question_en', 512)->nullable();
            $table->string('question_ru', 512)->nullable();
            $table->text('answer_uk')->nullable();
            $table->text('answer_en')->nullable();
            $table->text('answer_ru')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_footer_faq_items');
    }
};
