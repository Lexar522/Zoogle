<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Mail\OrderOnlinePaymentUnlocked;
use App\Models\Order;
use App\Support\OnlinePaymentSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected ?string $heading = 'Замовлення';

    /** Чи потрібно надіслати лист після збереження (перше увімкнення «дозволено»). */
    protected bool $pendingUnlockEmail = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allowOnlinePayment')
                ->label('Дозволити онлайн-оплату')
                ->icon('heroicon-o-credit-card')
                ->visible(fn (): bool => $this->canAllowDeferredOnlinePayment())
                ->requiresConfirmation()
                ->modalHeading('Дозволити онлайн-оплату')
                ->modalDescription('Покупець зможе оплатити карткою (LiqPay або WayForPay — залежно від «Інтеграцій») за посиланням з листа або в особистому кабінеті. Те саме, що перемикач «Дозволити онлайн-оплату» у блоці нижче.')
                ->action(function (): void {
                    /** @var Order $order */
                    $order = $this->record;
                    $order->online_payment_unlocked_at = now();
                    $order->save();

                    $email = trim((string) $order->customer_email);
                    if ($email !== '') {
                        Mail::to($email)->send(new OrderOnlinePaymentUnlocked($order->fresh(['items'])));
                    }

                    Notification::make()
                        ->title($email !== '' ? 'Онлайн-оплату дозволено, лист надіслано' : 'Онлайн-оплату дозволено (немає email — лист не надіслано)')
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
        ];
    }

    private function canAllowDeferredOnlinePayment(): bool
    {
        $o = $this->record;
        if (! $o instanceof Order) {
            return false;
        }
        if (! app(OnlinePaymentSettings::class)->isConfigured()) {
            return false;
        }
        if ($o->payment_status === 'paid') {
            return false;
        }
        if (! $o->deferred_online_payment) {
            return false;
        }
        if ($o->online_payment_unlocked_at !== null) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);
        $data['online_payment_allowed'] = ! empty($data['online_payment_unlocked_at'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $wasUnlocked = $this->record->online_payment_unlocked_at !== null;
        $wantUnlock = (bool) ($data['online_payment_allowed'] ?? false);
        unset($data['online_payment_allowed']);

        if ($wantUnlock) {
            if (! $wasUnlocked) {
                $this->pendingUnlockEmail = true;
            }
            $data['online_payment_unlocked_at'] = $wasUnlocked
                ? $this->record->online_payment_unlocked_at
                : now();
        } else {
            $data['online_payment_unlocked_at'] = null;
            $this->pendingUnlockEmail = false;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->pendingUnlockEmail) {
            return;
        }

        $order = $this->record->fresh(['items']);
        if ($order->online_payment_unlocked_at === null) {
            $this->pendingUnlockEmail = false;

            return;
        }

        $email = trim((string) $order->customer_email);
        if ($email !== '') {
            Mail::to($email)->send(new OrderOnlinePaymentUnlocked($order));
            Notification::make()
                ->title('Лист з посиланням на оплату надіслано покупцю')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Онлайн-оплату увімкнено. Email у замовленні порожній — лист не надіслано.')
                ->warning()
                ->send();
        }

        $this->pendingUnlockEmail = false;
    }
}
