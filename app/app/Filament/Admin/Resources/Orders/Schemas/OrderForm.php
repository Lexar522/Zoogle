<?php

namespace App\Filament\Admin\Resources\Orders\Schemas;

use App\Models\Order;
use App\Support\OnlinePaymentSettings;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Статус і оплата')
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label('Номер')
                            ->disabled(),
                        TextInput::make('total')
                            ->label('Сума')
                            ->numeric()
                            ->prefix('UAH')
                            ->disabled(),
                        Select::make('status')
                            ->label('Статус замовлення')
                            ->required()
                            ->options([
                                Order::STATUS_NEW => 'Нове',
                                Order::STATUS_PAID => 'Оплачено (очікує відправки)',
                                Order::STATUS_PROCESSING => 'В обробці',
                                Order::STATUS_SHIPPED => 'Відправлено',
                                Order::STATUS_COMPLETED => 'Завершено',
                                Order::STATUS_CANCELLED => 'Скасовано',
                            ]),
                        Select::make('payment_status')
                            ->label('Статус оплати')
                            ->required()
                            ->options([
                                'pending' => 'Очікує оплату',
                                'partial' => 'Частково сплачено (онлайн)',
                                'paid' => 'Оплачено',
                                'failed' => 'Помилка оплати',
                            ]),
                        DateTimePicker::make('placed_at')
                            ->label('Оформлено')
                            ->disabled()
                            ->dehydrated(false),
                        DateTimePicker::make('paid_at')
                            ->label('Оплачено')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columnSpanFull(),
                Section::make('Відкладена онлайн-оплата')
                    ->description('Для замовлень з товарами, де онлайн-оплата частини суми — після узгодження з менеджером (провайдер: LiqPay або WayForPay з «Інтеграцій»). Якщо чек-аут не виставив прапорець, увімкніть вручну; перевірте суми «відкладеної» частини в блоці технічних полів або в товарі.')
                    ->columns(2)
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
                    ])
                    ->columnSpanFull(),
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
                    ])
                    ->columnSpanFull(),
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
                    ])
                    ->columnSpanFull(),
                Section::make('Платіжна система (технічно)')
                    ->collapsed()
                    ->columns(2)
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
                    ])
                    ->columnSpanFull(),
                Section::make('Примітки')
                    ->schema([
                        Textarea::make('customer_notes')
                            ->label('Примітки покупця до доставки')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('comment')
                            ->label('Внутрішній коментар')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
