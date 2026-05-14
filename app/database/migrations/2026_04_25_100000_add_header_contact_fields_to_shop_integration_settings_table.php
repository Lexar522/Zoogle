<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_integration_settings', function (Blueprint $table) {
            $table->string('contact_phone', 64)->nullable()->after('pickup_lng');
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_instagram', 512)->nullable();
            $table->string('contact_viber', 512)->nullable();
            $table->string('contact_whatsapp', 512)->nullable();
            $table->string('contact_telegram', 255)->nullable();
        });

        $firstId = DB::table('shop_integration_settings')->orderBy('id')->value('id');
        if ($firstId !== null) {
            DB::table('shop_integration_settings')->where('id', $firstId)->update([
                'contact_phone' => '+38 099 403 43 59',
                'contact_email' => 'zoogle.ukraine@gmail.com',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('shop_integration_settings', function (Blueprint $table) {
            $table->dropColumn([
                'contact_phone',
                'contact_email',
                'contact_instagram',
                'contact_viber',
                'contact_whatsapp',
                'contact_telegram',
            ]);
        });
    }
};
