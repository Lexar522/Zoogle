<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Живих тварин Google Merchant рекламувати забороняє, а доставляти їх можна лише самовивозом. */
    private const LIVE_ANIMALS_ROOT_NAME = 'тварини';

    public function up(): void
    {
        Schema::table('option_values', function (Blueprint $table) {
            $table->boolean('exclude_from_merchant_feed')->default(false);
        });

        $groupId = DB::table('option_groups')->where('slug', 'category')->value('id');
        if ($groupId === null) {
            return;
        }

        $roots = DB::table('option_values')
            ->where('option_group_id', $groupId)
            ->whereNull('parent_id')
            ->get(['id', 'name']);

        $animalRootIds = $roots
            ->filter(fn (object $row): bool => $this->ukrainianName($row->name) === self::LIVE_ANIMALS_ROOT_NAME)
            ->pluck('id')
            ->all();

        if ($animalRootIds !== []) {
            DB::table('option_values')->whereIn('id', $animalRootIds)->update([
                'exclude_from_merchant_feed' => true,
                'pickup_only_subtree' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('option_values', function (Blueprint $table) {
            $table->dropColumn('exclude_from_merchant_feed');
        });
    }

    private function ukrainianName(mixed $name): string
    {
        $decoded = is_string($name) ? json_decode($name, true) : null;
        $text = is_array($decoded) ? (string) ($decoded['uk'] ?? reset($decoded) ?: '') : (string) $name;

        return mb_strtolower(trim($text));
    }
};
