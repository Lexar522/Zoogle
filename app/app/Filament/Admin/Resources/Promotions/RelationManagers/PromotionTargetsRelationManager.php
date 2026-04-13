<?php

namespace App\Filament\Admin\Resources\Promotions\RelationManagers;

use App\Enums\PromotionDiscountMode;
use App\Filament\Admin\Resources\Promotions\Schemas\PromotionLineFormSchema;
use App\Filament\Support\PromotionTargetLabels;
use App\Models\PromotionTarget;
use App\Support\PromotionTargetFormState;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionTargetsRelationManager extends RelationManager
{
    protected static string $relationship = 'targets';

    protected static ?string $title = 'Товари в цій акції';

    protected static ?string $label = 'Товар у акції';

    public function form(Schema $schema): Schema
    {
        return $schema->components(PromotionLineFormSchema::components());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_variant')
                    ->label('Товар')
                    ->state(fn (PromotionTarget $record): string => PromotionTargetLabels::describeTarget($record)),
                TextColumn::make('sale_summary')
                    ->label('Умова')
                    ->state(fn (PromotionTarget $record): string => PromotionTargetLabels::formatDiscountSummary($record)),
                TextColumn::make('ends_at')
                    ->label('Кінець позиції')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('discount_mode')
                    ->label('Тип')
                    ->formatStateUsing(fn ($state): string => $state instanceof PromotionDiscountMode ? $state->label() : (string) $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discount_value')
                    ->label('Значення')
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Додати товар')
                    ->mutateFormDataUsing(fn (array $data): array => PromotionTargetFormState::persist($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Змінити')
                    ->mutateRecordDataUsing(fn (array $data): array => PromotionTargetFormState::hydrate($data))
                    ->mutateFormDataUsing(fn (array $data): array => PromotionTargetFormState::persist($data)),
                DeleteAction::make()->label('Видалити'),
            ]);
    }
}
