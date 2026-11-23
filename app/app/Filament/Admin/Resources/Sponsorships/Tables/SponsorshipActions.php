<?php

namespace App\Filament\Admin\Resources\Sponsorships\Tables;

use App\Models\Sponsorship;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Дії менеджера над заявкою: підтвердити оплату, схвалити чи відхилити фото, скасувати.
 */
final class SponsorshipActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::confirmPayment(),
            self::approvePhoto(),
            self::rejectPhoto(),
            self::cancel(),
        ];
    }

    public static function confirmPayment(): Action
    {
        return Action::make('confirmPayment')
            ->label('Підтвердити оплату')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Sponsorship $record): bool => $record->status === Sponsorship::STATUS_PENDING)
            ->requiresConfirmation()
            ->modalHeading(fn (Sponsorship $record): string => 'Підтвердити оплату '.$record->reference().'?')
            ->modalDescription(fn (Sponsorship $record): string => 'Перевірте, що на банку надійшло '.number_format((float) $record->amount, 0, ',', ' ').' ₴. Опікунство діятиме з сьогодні: '.$record->termLabel().'.')
            ->action(function (Sponsorship $record): void {
                $record->confirmPayment();
                Notification::make()->success()->title('Опікунство активне до '.$record->ends_at?->format('d.m.Y'))->send();
            });
    }

    public static function approvePhoto(): Action
    {
        return Action::make('approvePhoto')
            ->label('Схвалити фото')
            ->icon('heroicon-o-photo')
            ->color('info')
            ->visible(fn (Sponsorship $record): bool => filled($record->photo_path) && $record->photo_status !== Sponsorship::PHOTO_APPROVED)
            ->action(fn (Sponsorship $record) => $record->forceFill(['photo_status' => Sponsorship::PHOTO_APPROVED])->save());
    }

    public static function rejectPhoto(): Action
    {
        return Action::make('rejectPhoto')
            ->label('Відхилити фото')
            ->icon('heroicon-o-no-symbol')
            ->color('warning')
            ->visible(fn (Sponsorship $record): bool => filled($record->photo_path))
            ->requiresConfirmation()
            ->modalDescription('Фото буде видалено з сервера.')
            ->action(function (Sponsorship $record): void {
                $record->forceFill(['photo_status' => Sponsorship::PHOTO_REJECTED])->save();
                $record->discardPhoto();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Скасувати')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Sponsorship $record): bool => $record->status !== Sponsorship::STATUS_CANCELLED)
            ->requiresConfirmation()
            ->modalDescription('Опікун зникне зі сторінки тварини, фото буде видалено. Заявка залишиться в списку зі статусом «Скасована».')
            ->action(function (Sponsorship $record): void {
                $record->forceFill(['status' => Sponsorship::STATUS_CANCELLED])->save();
                $record->discardPhoto();
            });
    }
}
