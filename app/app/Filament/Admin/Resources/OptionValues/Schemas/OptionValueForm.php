<?php

namespace App\Filament\Admin\Resources\OptionValues\Schemas;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Str;

class OptionValueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('option_group_id')
                    ->label('Група опції')
                    ->required()
                    ->options(fn (): array => OptionGroup::query()
                        ->orderBy('product_type')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (OptionGroup $group): array => [
                            $group->id => (in_array($group->product_type, [OptionGroup::CATALOG_PRODUCT_TYPE], true) ? 'Товари' : 'Аксесуари').' - '.$group->name,
                        ])
                        ->all())
                    ->live()
                    ->searchable(),
                Select::make('parent_id')
                    ->label('Батьківська категорія (необов`язково)')
                    ->helperText('Для під-підкатегорії оберіть тут підкатегорію. Якщо порожньо — це верхній рівень.')
                    ->visible(function (Get $get): bool {
                        if (! DbSchema::hasColumn('option_values', 'parent_id')) {
                            return false;
                        }

                        $groupId = (int) ($get('option_group_id') ?? 0);
                        if ($groupId <= 0) {
                            return false;
                        }

                        return OptionGroup::query()
                            ->whereKey($groupId)
                            ->where('slug', 'category')
                            ->exists();
                    })
                    ->options(function (Get $get, $record): array {
                        $groupId = (int) ($get('option_group_id') ?? 0);
                        if ($groupId <= 0) {
                            return [];
                        }

                        $rows = OptionValue::query()
                            ->where('option_group_id', $groupId)
                            ->when($record, fn ($q) => $q->where('id', '!=', (int) $record->id))
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->get(['id', 'name', 'parent_id']);

                        $byParent = $rows->groupBy(fn (OptionValue $v): int => (int) ($v->parent_id ?? 0));
                        $out = [];
                        $walk = function (int $parentId, string $prefix = '') use (&$walk, $byParent, &$out): void {
                            foreach ($byParent->get($parentId, collect()) as $node) {
                                $out[(int) $node->id] = $prefix.$node->name;
                                $walk((int) $node->id, $prefix.'- ');
                            }
                        };
                        $walk(0);

                        return $out;
                    })
                    ->searchable(),
                TextInput::make('name')
                    ->label('Назва значення')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set): void {
                        if (blank($state)) {
                            return;
                        }

                        $set('slug', Str::slug($state));
                    }),
                TextInput::make('slug')
                    ->label('Слаг')
                    ->required()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->label('Активне')
                    ->default(true),
            ]);
    }
}
