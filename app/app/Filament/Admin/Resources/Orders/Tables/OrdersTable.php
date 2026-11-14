<?php

namespace App\Filament\Admin\Resources\Orders\Tables;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\OrderCustomerNotifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Для телефона замовлення мають власний компактний вигляд в orders.css.
            ->stackedOnMobile(false)
            ->searchable([
                'customer_name',
                'items.title_snapshot',
                'items.product.title',
                'items.product.sku',
                'items.bundle.title',
                'items.bundle.sku',
                function (Builder $query, string $search): void {
                    $itemsTable = $query->getModel()->items()->getRelated()->getTable();
                    if (! Schema::hasColumn($itemsTable, 'bundle_snapshot')) {
                        return;
                    }

                    $escaped = str_replace(
                        ['\\', '%', '_'],
                        ['\\\\', '\\%', '\\_'],
                        $search,
                    );
                    $term = '%'.$escaped.'%';

                    $query->whereHas('items', function (Builder $items) use ($itemsTable, $term): void {
                        $items->where($itemsTable.'.bundle_snapshot', 'like', $term);
                    });
                },
            ])
            ->searchPlaceholder('Номер, клієнт, товар або артикул')
            ->columns([
                TextColumn::make('number')
                    ->label('Номер')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (Order $record): string => implode(' · ', array_filter([
                        trim((string) $record->customer_name) ?: 'Без імені',
                        trim((string) $record->customer_phone),
                    ]))),
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
                TextColumn::make('items_count')
                    ->label('Позиції')
                    ->counts('items')
                    ->alignCenter()
                    ->visibleFrom('2xl'),
                TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable()
                    ->visibleFrom('2xl'),
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
                    // Короткі назви, щоб усі рядки таблиці були однакової висоти.
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Order::DELIVERY_NOVA_POSHTA_WAREHOUSE => 'НП, відділення',
                        Order::DELIVERY_NOVA_POSHTA_COURIER => 'НП, курʼєр',
                        default => Order::deliveryTypeLabels()[$state ?? ''] ?? (string) $state,
                    })
                    ->description(fn (Order $record): ?string => filled($record->delivery_city) ? (string) $record->delivery_city : null)
                    ->searchable()
                    ->visibleFrom('lg'),
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
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('placed_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),
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
            ->emptyStateHeading('Замовлень немає')
            ->emptyStateDescription('Тут з\'являться замовлення, які відповідають вибраному статусу чи пошуку.')
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Action::make('changeStatus')
                    ->label('Змінити статус')
                    ->icon(Heroicon::ArrowPath)
                    ->iconButton()
                    ->tooltip('Змінити статус')
                    ->color('gray')
                    ->schema([
                        Select::make('status')
                            ->label('Новий статус')
                            ->required()
                            ->native(false)
                            ->options(Order::statusLabels())
                            ->default(fn (Order $record): string => (string) $record->status),
                        Textarea::make('message')
                            ->label('Коментар для покупця')
                            ->rows(3)
                            ->maxLength(2000),
                        Toggle::make('notify_email')
                            ->label('Надіслати лист на email')
                            ->default(true),
                    ])
                    ->action(function (Order $record, array $data): void {
                        try {
                            $update = app(OrderCustomerNotifier::class)->apply(
                                $record,
                                (string) ($data['status'] ?? $record->status),
                                $data['message'] ?? null,
                                (bool) ($data['notify_email'] ?? true),
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Статус не змінено')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($update === null) {
                            Notification::make()
                                ->title('Нічого не змінилось')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($update->email_sent_at
                                ? 'Статус оновлено, лист надіслано'
                                : 'Статус оновлено')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
