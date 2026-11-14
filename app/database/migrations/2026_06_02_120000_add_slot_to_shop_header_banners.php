<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_header_banners', function (Blueprint $table): void {
            $table->string('slot', 16)->default('top')->after('sort_order');
            $table->index(['slot', 'is_active', 'sort_order']);
        });

        $rows = DB::table('shop_header_banners')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id');

        foreach ($rows as $index => $id) {
            DB::table('shop_header_banners')
                ->where('id', $id)
                ->update(['slot' => $index % 2 === 0 ? 'top' : 'bottom']);
        }
    }

    public function down(): void
    {
        Schema::table('shop_header_banners', function (Blueprint $table): void {
            $table->dropIndex(['slot', 'is_active', 'sort_order']);
            $table->dropColumn('slot');
        });
    }
};
