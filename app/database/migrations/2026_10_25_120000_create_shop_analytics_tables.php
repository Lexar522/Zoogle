<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_analytics_events')) {
            Schema::create('shop_analytics_events', function (Blueprint $table) {
                $table->id();
                $table->date('occurred_on')->index();
                $table->timestamp('occurred_at')->nullable()->index();
                $table->string('visitor_key', 64)->index();
                $table->string('type', 32)->index();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('label', 191)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedInteger('results_count')->nullable();
                $table->index(['type', 'occurred_on']);
            });
        }

        if (! Schema::hasTable('shop_analytics_presence')) {
            Schema::create('shop_analytics_presence', function (Blueprint $table) {
                $table->string('visitor_key', 64)->primary();
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable()->index();
            });
        }

        if (! Schema::hasTable('shop_analytics_online_samples')) {
            Schema::create('shop_analytics_online_samples', function (Blueprint $table) {
                $table->id();
                $table->timestamp('sampled_at')->nullable()->unique();
                $table->unsignedInteger('online_count');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_analytics_online_samples');
        Schema::dropIfExists('shop_analytics_presence');
        Schema::dropIfExists('shop_analytics_events');
    }
};
