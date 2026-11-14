<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Mail\OrderOnlinePaymentUnlocked;
use App\Models\Order;
use App\Services\OrderCustomerNotifier;
use App\Support\OnlinePaymentSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Throwable;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected ?string $heading = 'Замовлення';

    /** Чи потрібно надіслати лист після збереження (перше увімкнення «дозволено»). */
    protected bool $pendingUnlockEmail = false;

    protected ?string $statusBeforeSave = null;

    protected string $pendingCustomerMessage = '';

    protected bool $pendingNotifyCustomer = true;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'zoogle-orders-page'];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (ValidationException $e) {
            if (! array_key_exists('cart', $e->errors())) {
                throw $e;
            }

            $this->record->refresh();
            Notification::make()
                ->title('Замовлення не збережено')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resolvePaymentIssue')
                ->label('Проблему з оплатою вирішено')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->visible(fn (): bool => filled($this->record->payment_issue))
                ->requiresConfirmation()
                ->modalHeading('Зняти позначку?')
                ->modalDescription('Статус оплати не зміниться, зникне лише попередження. Якщо потрібно, змініть статус вручну.')
                ->action(function (): void {
                    $this->record->payment_issue = null;
                    $this->record->save();
                    Notification::make()->title('Позначку знято')->success()->send();
                }),
            Action::make('allowOnlinePayment')
                ->label('Дозволити онлайн-оплату')
                ->icon('heroicon-o-credit-card')
                ->color('gray')
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
                    if ($email !== '' && ! $this->sendUnlockEmail($order->fresh(['items']), $email)) {
                        Notification::make()
                            ->title('Онлайн-оплату дозволено, але лист не надіслано: перевірте налаштування пошти')
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title($email !== '' ? 'Онлайн-оплату дозволено, лист надіслано' : 'Онлайн-оплату дозволено (немає email — лист не надіслано)')
                            ->success()
                            ->send();
                    }

                    $this->fillForm();
                }),
            Action::make('writeToCustomer')
                ->label('Написати покупцю')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->schema([
                    Textarea::make('message')
                        ->label('Коментар для покупця')
                        ->required()
                        ->rows(4)
                        ->maxLength(2000),
                    Toggle::make('notify_email')
                        ->label('Надіслати лист на email')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    /** @var Order $order */
                    $order = $this->record;
                    $update = app(OrderCustomerNotifier::class)->apply(
                        $order,
                        null,
                        (string) ($data['message'] ?? ''),
                        (bool) ($data['notify_email'] ?? true),
                    );

                    if ($update === null) {
                        return;
                    }

                    $email = trim((string) $order->fresh()?->customer_email);
                    $sent = $update->email_sent_at !== null;
                    Notification::make()
                        ->title($sent
                            ? 'Повідомлення збережено і надіслано на email'
                            : ($email === ''
                                ? 'Повідомлення збережено. Email порожній — лист не надіслано'
                                : 'Повідомлення збережено без листа'))
                        ->success()
                        ->send();

                    $this->record->refresh();
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
        $data['customer_update_message'] = '';
        $data['notify_customer_email'] = true;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->statusBeforeSave = (string) $this->record->status;
        $this->pendingCustomerMessage = trim((string) ($data['customer_update_message'] ?? ''));
        $this->pendingNotifyCustomer = (bool) ($data['notify_customer_email'] ?? true);
        unset($data['customer_update_message'], $data['notify_customer_email']);

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

    #[On('order-items-changed')]
    public function refreshAfterItemsChanged(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }

    protected function afterSave(): void
    {
        $order = $this->record->fresh(['items']);
        if ($order instanceof Order) {
            $update = app(OrderCustomerNotifier::class)->notifyAfterSave(
                $order,
                $this->statusBeforeSave,
                $this->pendingCustomerMessage,
                $this->pendingNotifyCustomer,
            );

            if ($update !== null) {
                $sent = $update->email_sent_at !== null;
                Notification::make()
                    ->title($sent
                        ? 'Покупця повідомлено листом'
                        : (trim((string) $order->customer_email) === ''
                            ? 'Статус/коментар збережено. Email порожній — лист не надіслано'
                            : 'Статус/коментар збережено без листа'))
                    ->success()
                    ->send();
            }
        }

        $this->statusBeforeSave = null;
        $this->pendingCustomerMessage = '';
        $this->pendingNotifyCustomer = true;

        if (! $this->pendingUnlockEmail) {
            return;
        }

        $order = $this->record->fresh(['items']);
        if ($order->online_payment_unlocked_at === null) {
            $this->pendingUnlockEmail = false;

            return;
        }

        $email = trim((string) $order->customer_email);
        if ($email !== '' && ! $this->sendUnlockEmail($order, $email)) {
            Notification::make()
                ->title('Онлайн-оплату увімкнено, але лист не надіслано: перевірте налаштування пошти')
                ->warning()
                ->send();
        } elseif ($email !== '') {
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

    private function sendUnlockEmail(Order $order, string $email): bool
    {
        try {
            Mail::to($email)->send(new OrderOnlinePaymentUnlocked($order));

            return true;
        } catch (Throwable $e) {
            Log::warning('order_unlock_email_failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
