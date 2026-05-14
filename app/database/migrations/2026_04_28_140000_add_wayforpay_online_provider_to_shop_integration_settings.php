<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_integration_settings', function (Blueprint $table): void {
            $table->string('online_payment_provider', 32)->default('liqpay')->after('contact_telegram');
            $table->string('wayforpay_merchant_account', 128)->nullable()->after('online_payment_provider');
            $table->text('wayforpay_secret_key')->nullable()->after('wayforpay_merchant_account');
            $table->string('wayforpay_merchant_domain', 255)->nullable()->after('wayforpay_secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('shop_integration_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'online_payment_provider',
                'wayforpay_merchant_account',
                'wayforpay_secret_key',
                'wayforpay_merchant_domain',
            ]);
        });
    }
};
