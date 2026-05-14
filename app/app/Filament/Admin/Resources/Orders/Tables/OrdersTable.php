<?php

namespace App\Filament\Admin\Resources\Orders\Tables;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
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
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (Order $record): string => trim((string) $record->customer_name) !== ''
                        ? trim((string) $record->customer_name)
                        : 'Без імені'),
                TextColumn::make('status')
                    ->label('Стан')
                    ->formatStateUsing(fn (?string $state): string => Order::statusLabels()[$state ?? ''] ?? (string) $state)
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        Order::STATUS_NEW => 'info',
                        Order::STATUS_PAID => 'success',
                        Order::STATUS_PROCESSING => 'warning',
                        Order::STATUS_SHIPPED => 'primary',
                        Order::STATUS_COMPLETED => 'gray',
                        Order::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (Order $record): string => 'Оплата: '.$record->paymentStatusLabel())
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer_address')
                    ->label('Адреса')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_type')
                    ->label('Доставка')
                    ->formatStateUsing(fn (?string $state): string => Order::deliveryTypeLabels()[$state ?? ''] ?? (string) $state)
                    ->badge()
                    ->color('gray')
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        'partial' => 'Частково сплачено',
                        'paid' => 'Оплачено',
                        'failed' => 'Помилка оплати',
                    ]),
            ])
            ->defaultSort('placed_at', 'desc')
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Action::make('edit')
                    ->label('Відкрити')
                    ->icon(Heroicon::PencilSquare)
                    ->url(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
