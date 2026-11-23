<?php

namespace App\Filament\Admin\Resources\ProductQuestions\Tables;

use App\Models\ProductQuestion;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductQuestionsTable
{
    private const BODY_PREVIEW = 140;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('product:id,title,slug'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')
                    ->label('Питання')
                    ->formatStateUsing(fn (string $state): string => Str::limit($state, self::BODY_PREVIEW))
                    ->description(fn (ProductQuestion $record): ?string => filled($record->answer) ? 'Відповідь: '.Str::limit((string) $record->answer, 90) : null)
                    ->wrap()
                    ->searchable(['body', 'answer']),
                TextColumn::make('product.title')
                    ->label('Товар')
                    ->formatStateUsing(fn (ProductQuestion $record): string => mt((string) $record->product?->title))
                    ->url(fn (ProductQuestion $record): ?string => $record->product ? route('catalog.show', $record->product->slug).'#questions' : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->wrap(),
                TextColumn::make('author_name')
                    ->label('Автор')
                    ->description(fn (ProductQuestion $record): string => trim(($record->email ?? '').' · '.$record->created_at?->format('d.m.Y H:i'), ' ·'))
                    ->searchable(['author_name', 'email']),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProductQuestion::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ProductQuestion::STATUS_PENDING => 'danger',
                        ProductQuestion::STATUS_ANSWERED => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ProductQuestion::STATUS_LABELS),
            ])
            ->recordActions([
                ProductQuestionActions::answer(),
                ActionGroup::make([
                    ProductQuestionActions::hide(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
