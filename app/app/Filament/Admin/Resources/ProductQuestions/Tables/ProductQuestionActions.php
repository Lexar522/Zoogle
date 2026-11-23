<?php

namespace App\Filament\Admin\Resources\ProductQuestions\Tables;

use App\Models\ProductQuestion;
use App\Services\ProductQuestionAnswerer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

/**
 * Відповідь на питання покупця і приховування питання.
 */
final class ProductQuestionActions
{
    public const ANSWER_MAX = 2000;

    public static function answer(): Action
    {
        return Action::make('answer')
            ->label(fn (ProductQuestion $record): string => $record->status === ProductQuestion::STATUS_ANSWERED ? 'Змінити відповідь' : 'Відповісти')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color(fn (ProductQuestion $record): string => $record->status === ProductQuestion::STATUS_PENDING ? 'primary' : 'gray')
            ->modalHeading(fn (ProductQuestion $record): string => 'Питання від '.$record->author_name)
            ->modalDescription(fn (ProductQuestion $record): string => mt((string) $record->product?->title).' — «'.$record->body.'»')
            ->modalSubmitActionLabel('Опублікувати відповідь')
            ->fillForm(fn (ProductQuestion $record): array => [
                'answer' => $record->answer,
                'send_email' => filled($record->email) && $record->answer_emailed_at === null,
            ])
            ->schema([
                Textarea::make('answer')
                    ->label('Відповідь')
                    ->rows(5)
                    ->required()
                    ->maxLength(self::ANSWER_MAX)
                    ->helperText('З\'явиться під питанням на сторінці товару.'),
                Toggle::make('send_email')
                    ->label(fn (ProductQuestion $record): string => 'Надіслати відповідь на '.$record->email)
                    ->visible(fn (ProductQuestion $record): bool => filled($record->email)),
            ])
            ->action(function (ProductQuestion $record, array $data): void {
                $emailed = app(ProductQuestionAnswerer::class)->answer(
                    $record,
                    (string) $data['answer'],
                    auth()->user(),
                    (bool) ($data['send_email'] ?? false),
                );

                Notification::make()
                    ->success()
                    ->title('Відповідь опубліковано')
                    ->body($emailed ? 'Лист із відповіддю надіслано покупцю.' : null)
                    ->send();
            });
    }

    public static function hide(): Action
    {
        return Action::make('hide')
            ->label('Приховати')
            ->icon('heroicon-o-eye-slash')
            ->color('gray')
            ->visible(fn (ProductQuestion $record): bool => $record->status !== ProductQuestion::STATUS_HIDDEN)
            ->requiresConfirmation()
            ->modalHeading('Приховати питання?')
            ->modalDescription('Питання зникне з сайту, але лишиться в адмінці. Підходить для спаму чи питань не по темі.')
            ->action(function (ProductQuestion $record): void {
                $record->hide();
                Notification::make()->success()->title('Питання приховано')->send();
            });
    }
}
