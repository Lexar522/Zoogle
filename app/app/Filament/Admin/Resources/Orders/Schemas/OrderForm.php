<?php

namespace App\Filament\Admin\Resources\Orders\Schemas;

use App\Models\Order;
use App\Support\OnlinePaymentSettings;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                View::make('filament.admin.orders.order-summary'),
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Group::make(self::mainSections())->columnSpan(['lg' => 2]),
                        Group::make(self::sideSections())->columnSpan(['lg' => 1]),
                    ]),
            ]);
    }

    /**
     * @return list<Section>
     */
    private static function mainSections(): array
    {
        return [
            Section::make('Клієнт')
                ->columns(2)
                ->schema([
                    TextInput::make('customer_name')
                        ->label("Ім'я")
                        ->required(),
                    TextInput::make('customer_phone')
                        ->label('Телефон')
                        ->tel()
                        ->required(),
                    TextInput::make('customer_email')
                        ->label('Email')
                        ->email()
                        ->columnSpanFull(),
                    TextInput::make('customer_address')
                        ->label('Адреса (додатково)')
                        ->columnSpanFull(),
                ]),
            Section::make('Доставка')
                ->columns(2)
                ->schema([
                    Select::make('delivery_type')
                        ->label('Спосіб доставки')
                        ->required()
                        ->options(Order::deliveryTypeLabels())
                        ->default(Order::DELIVERY_PICKUP),
                    TextInput::make('nova_poshta_ttn')
                        ->label('ТТН / накладна Нова Пошта')
                        ->maxLength(64)
                        ->helperText('Бачить покупець у кабінеті та на сторінці замовлення після збереження.')
                        ->columnSpanFull(),
                    TextInput::make('delivery_city')
                        ->label('Місто'),
                    TextInput::make('delivery_branch')
                        ->label('Відділення / поштомат'),
                    TextInput::make('delivery_street')
                        ->label('Вулиця (курʼєр НП)'),
                    TextInput::make('delivery_building')
                        ->label('Будинок'),
                    TextInput::make('delivery_flat')
                        ->label('Квартира'),
                    Textarea::make('delivery_address')
                        ->label('Адреса (курʼєр, повний текст)')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Section::make('Повідомлення покупцю')
                ->description('Зміна статусу та коментар менеджера йдуть на email і з’являються на сторінці замовлення.')
                ->schema([
                    Placeholder::make('customer_email_hint')
                        ->hiddenLabel()
                        ->content(function (?Model $record): string {
                            if (! $record instanceof Order) {
                                return 'Збережіть замовлення, щоб надсилати листи.';
                            }
                            $email = trim((string) $record->customer_email);

                            return $email !== ''
                                ? 'Лист піде на '.$email.'.'
                                : 'У замовленні немає email — статус зміниться, але лист не надішлеться.';
                        }),
                    Textarea::make('customer_update_message')
                        ->label('Коментар для покупця')
                        ->rows(4)
                        ->maxLength(2000)
                        ->dehydrated(false)
                        ->placeholder('Наприклад: зібрали замовлення, завтра відправка Новою Поштою.')
                        ->helperText('Не обов’язково. Можна лише змінити статус — лист усе одно піде, якщо увімкнено перемикач.'),
                    Toggle::make('notify_customer_email')
                        ->label('Надіслати лист на email')
                        ->default(true)
                        ->dehydrated(false)
                        ->helperText('Вимкніть, якщо треба лише змінити статус у кабінеті без листа.'),
                ]),
            Section::make('Примітки')
                ->schema([
                    Textarea::make('customer_notes')
                        ->label('Примітки покупця до доставки')
                        ->rows(2),
                    Textarea::make('comment')
                        ->label('Внутрішній коментар (тільки для команди)')
                        ->rows(2)
                        ->helperText('Покупець цей текст не бачить і на email він не йде.'),
                ]),
        ];
    }

    /**
     * @return list<Section>
     */
    private static function sideSections(): array
    {
        return [
            Section::make('Статус і оплата')
                ->schema([
                    Select::make('status')
                        ->label('Статус замовлення')
                        ->required()
                        ->native(false)
                        ->options(Order::statusLabels())
                        ->helperText('Покупець побачить новий статус після збереження.'),
                    Select::make('payment_status')
                        ->label('Статус оплати')
                        ->required()
                        ->native(false)
                        ->options([
                            'pending' => 'Очікує оплату',
                            'partial' => 'Частково сплачено (онлайн)',
                            'paid' => 'Оплачено',
                            'failed' => 'Помилка оплати',
                        ]),
                ]),
            Section::make('Відкладена онлайн-оплата')
                ->description('Коли частину суми покупець оплачує онлайн після узгодження з менеджером.')
                ->schema([
                    Toggle::make('deferred_online_payment')
                        ->label('Потрібна відкладена онлайн-доплата')
                        ->helperText('Зазвичай виставляється при оформленні. Увімкніть, якщо з покупцем домовились про онлайн-доплату після дозволу.')
                        ->live(),
                    Toggle::make('online_payment_allowed')
                        ->label('Дозволити онлайн-оплату (посилання / кабінет)')
                        ->helperText('Після збереження покупець зможе оплатити карткою, коли в «Інтеграціях» налаштовано LiqPay або WayForPay. Перше вмикання — лист на email (якщо вказано).')
                        ->visible(function (Get $get, ?Model $record): bool {
                            if ($record instanceof Order && $record->deferred_online_payment) {
                                return true;
                            }

                            return (bool) $get('deferred_online_payment');
                        })
                        ->disabled(fn (): bool => ! app(OnlinePaymentSettings::class)->isConfigured()),
                ]),
            Section::make('Платіжна система (технічно)')
                ->collapsed()
                ->schema([
                    TextInput::make('payment_provider')
                        ->label('Провайдер')
                        ->disabled(),
                    TextInput::make('payment_external_id')
                        ->label('ID транзакції')
                        ->disabled(),
                    DateTimePicker::make('payment_last_callback_at')
                        ->label('Останній callback')
                        ->disabled(),
                ]),
        ];
    }
}
