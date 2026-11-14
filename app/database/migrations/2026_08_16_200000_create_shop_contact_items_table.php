<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_contact_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('kind', 32);
            $table->string('value', 512);
            $table->timestamps();
        });

        if (! Schema::hasTable('shop_integration_settings')) {
            return;
        }

        $legacyMap = [
            'contact_phone' => 'phone',
            'contact_email' => 'email',
            'contact_instagram' => 'instagram',
            'contact_viber' => 'viber',
            'contact_whatsapp' => 'whatsapp',
            'contact_telegram' => 'telegram',
        ];

        foreach (DB::table('shop_integration_settings')->get() as $row) {
            $sort = 0;
            foreach ($legacyMap as $column => $kind) {
                $value = trim((string) ($row->{$column} ?? ''));
                if ($value === '') {
                    continue;
                }
                DB::table('shop_contact_items')->insert([
                    'sort_order' => $sort,
                    'kind' => $kind,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sort++;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_contact_items');
    }
};
