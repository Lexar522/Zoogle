<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_values', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('option_group_id')
                ->constrained('option_values')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('option_values', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
