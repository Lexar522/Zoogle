<?php

namespace App\Filament\Admin\Resources\Promotions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Назва')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Необов’язково. Унікальний ключ для інтеграцій.'),
                Textarea::make('description')
                    ->label('Внутрішній опис')
                    ->placeholder('Наприклад: умови для персоналу, винятки, узгоджено з …')
                    ->helperText(
                        'Поле тільки в адмін-панелі: покупці на сайті його не бачать. '.
                        'Корисно розписати нагадування для команди: навіщо кампанія, хто й коли додавав позиції, винятки з акції, ліміти, коментарі для наступних правок, посилання на документи чи узгодження. '.
                        'Назва лишається короткою для списку акцій; сюди — повний внутрішній контекст. '.
                        'Текст для покупців зараз задається в картках товарів або окремих сторінках (якщо з’являться); це поле для внутрішнього використання.'
                    )
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Активна')
                    ->default(true),
                DateTimePicker::make('starts_at')
                    ->label('Початок кампанії (довідково)')
                    ->seconds(false)
                    ->helperText('На вітрину не впливає: знижка показується, коли ввімкнено «Активна» і не минув «Кінець». Відкладений старт — вимикайте «Активна» до потрібної дати, потім увімкніть. Час у додатку: '.config('app.timezone').'.'),
                DateTimePicker::make('ends_at')
                    ->label('Кінець кампанії')
                    ->seconds(false)
                    ->afterOrEqual('starts_at')
                    ->helperText('Після цієї дати знижка зникає на вітрині. Окремий кінець можна задати у кожній позиції акції.'),
                TextInput::make('priority')
                    ->label('Пріоритет')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(32767)
                    ->helperText('При однаковій підсумковій ціні можна використовувати для сортування (вище — важливіше).'),
            ]);
    }
}
