<?php

namespace App\Filament\Admin\Resources\ProductReviews\Tables;

use App\Models\ProductReview;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Модерація відгуку: опублікувати на сайті або відхилити.
 */
final class ProductReviewActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Опублікувати')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ProductReview $record): bool => $record->status !== ProductReview::STATUS_APPROVED)
            ->action(function (ProductReview $record): void {
                $record->approve();
                Notification::make()->success()->title('Відгук опубліковано на сайті')->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Відхилити')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (ProductReview $record): bool => $record->status !== ProductReview::STATUS_REJECTED)
            ->requiresConfirmation()
            ->modalHeading('Відхилити відгук?')
            ->modalDescription('Відгук зникне з сайту, але лишиться в адмінці.')
            ->action(function (ProductReview $record): void {
                $record->reject();
                Notification::make()->success()->title('Відгук відхилено')->send();
            });
    }
}
