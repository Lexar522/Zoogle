<?php

namespace App\Filament\Admin\Resources\ProductQuestions\Schemas;

use App\Filament\Admin\Resources\ProductQuestions\Tables\ProductQuestionActions;
use App\Models\ProductQuestion;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Питання')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('product_link')
                            ->label('Товар')
                            ->state(fn (?ProductQuestion $record): string => mt((string) $record?->product?->title))
                            ->url(fn (?ProductQuestion $record): ?string => $record?->product ? route('catalog.show', $record->product->slug).'#questions' : null, shouldOpenInNewTab: true)
                            ->color('primary')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->columnSpanFull(),
                        TextInput::make('author_name')
                            ->label('Ім\'я')
                            ->helperText('На сайті видно лише ім\'я.')
                            ->required()
                            ->maxLength(80),
                        TextEntry::make('email')
                            ->label('Email покупця')
                            ->placeholder('Не вказано')
                            ->copyable(),
                        Textarea::make('body')
                            ->label('Питання')
                            ->rows(3)
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
                Section::make('Відповідь магазину')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Textarea::make('answer')
                            ->label('Відповідь')
                            ->rows(5)
                            ->maxLength(ProductQuestionActions::ANSWER_MAX)
                            ->helperText('Щоб відповідь з\'явилась на сайті й пішла покупцю листом — кнопка «Відповісти» вгорі.')
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Статус')
                            ->options(ProductQuestion::STATUS_LABELS)
                            ->required(),
                        TextEntry::make('answer_emailed_at')
                            ->label('Лист покупцю')
                            ->state(fn (?ProductQuestion $record): string => $record?->answer_emailed_at ? 'Надіслано '.$record->answer_emailed_at->format('d.m.Y H:i') : 'Не надсилали'),
                    ]),
            ]);
    }
}
