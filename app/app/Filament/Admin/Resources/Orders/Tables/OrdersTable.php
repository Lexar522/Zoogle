<?php

namespace App\Filament\Admin\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Номер')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->searchable(),
                TextColumn::make('payment_status')
                    ->label('Оплата')
                    ->badge()
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label("Ім'я")
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('customer_address')
                    ->label('Адреса')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_type')
                    ->label('Доставка')
                    ->formatStateUsing(fn (?string $state): string => Order::deliveryTypeLabels()[$state ?? ''] ?? (string) $state)
                    ->badge()
                    ->searchable(),
                TextColumn::make('delivery_city')
                    ->label('Місто')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_branch')
                    ->label('Відділення')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Сума')
                    ->money('UAH')
                    ->sortable(),
                TextColumn::make('placed_at')
                    ->label('Створено')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Оплачено')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Створено запис')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        Order::STATUS_NEW => 'Нове',
                        Order::STATUS_PAID => 'Оплачено',
                        Order::STATUS_PROCESSING => 'В обробці',
                        Order::STATUS_SHIPPED => 'Відправлено',
                        Order::STATUS_COMPLETED => 'Завершено',
                        Order::STATUS_CANCELLED => 'Скасовано',
                    ]),
                SelectFilter::make('payment_status')
                    ->label('Оплата')
                    ->options([
                        'pending' => 'Очікує оплату',
                        'paid' => 'Оплачено',
                        'failed' => 'Помилка оплати',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
