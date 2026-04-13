<?php

namespace App\Filament\Admin\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->label('Номер')
                    ->required()
                    ->disabled(),
                Select::make('status')
                    ->label('Статус')
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
                        'paid' => 'Оплачено',
                        'failed' => 'Помилка оплати',
                    ]),
                TextInput::make('customer_name')
                    ->label("Ім'я клієнта")
                    ->required(),
                TextInput::make('customer_phone')
                    ->label('Телефон')
                    ->tel()
                    ->required(),
                TextInput::make('customer_email')
                    ->label('Email')
                    ->email(),
                TextInput::make('customer_address')
                    ->label('Адреса (додатково)'),
                Select::make('delivery_type')
                    ->label('Доставка')
                    ->required()
                    ->options(Order::deliveryTypeLabels())
                    ->default(Order::DELIVERY_PICKUP),
                TextInput::make('delivery_city')
                    ->label('Місто (НП)'),
                TextInput::make('delivery_branch')
                    ->label('Відділення / поштомат'),
                Textarea::make('delivery_address')
                    ->label('Адреса (курʼєр)')
                    ->rows(2),
                Textarea::make('customer_notes')
                    ->label('Примітки до доставки')
                    ->columnSpanFull(),
                Textarea::make('comment')
                    ->label('Коментар до замовлення')
                    ->columnSpanFull(),
                TextInput::make('total')
                    ->label('Сума')
                    ->required()
                    ->numeric()
                    ->prefix('UAH')
                    ->disabled(),
                DateTimePicker::make('placed_at')
                    ->label('Створено'),
                DateTimePicker::make('paid_at')
                    ->label('Оплачено'),
                TextInput::make('payment_provider')
                    ->label('Платіжний провайдер')
                    ->disabled(),
                TextInput::make('payment_external_id')
                    ->label('ID платежу')
                    ->disabled(),
                DateTimePicker::make('payment_last_callback_at')
                    ->label('Останній callback')
                    ->disabled(),
            ]);
    }
}
