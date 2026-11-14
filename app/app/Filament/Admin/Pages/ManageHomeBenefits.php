<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopHomeBenefit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema as DbSchema;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageHomeBenefits extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'home-benefits';

    protected static ?string $title = 'Чому обирають нас';

    protected static ?string $navigationLabel = 'Чому обирають нас';

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 93;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return DbSchema::hasTable('shop_home_benefits');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill($this->loadFormData());
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFormData(): array
    {
        $row = ShopHomeBenefit::query()->orderBy('id')->first();
        if ($row === null) {
            return ShopHomeBenefit::formDefaults();
        }

        return [
            'is_enabled' => (bool) $row->is_enabled,
            'title' => (string) ($row->title ?? ''),
            'lead' => (string) ($row->lead ?? ''),
            'items' => ShopHomeBenefit::sanitizeItems(is_array($row->items) ? $row->items : []),
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');

            $row = ShopHomeBenefit::query()->orderBy('id')->first() ?? new ShopHomeBenefit;
            $row->fill([
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'title' => trim((string) ($data['title'] ?? '')),
                'lead' => trim((string) ($data['lead'] ?? '')),
                'items' => ShopHomeBenefit::sanitizeItems(is_array($data['items'] ?? null) ? $data['items'] : []),
            ]);
            $row->save();

            $this->callHook('afterSave');
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Блок «Чому обирають саме нас» оновлено.')
            ->send();

        $this->form->fill($this->loadFormData());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Блок на головній')
                    ->description('Можна повністю сховати блок, змінити заголовок і додати стільки карток, скільки потрібно.')
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label('Показувати блок на головній')
                            ->default(true),
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->maxLength(255)
                            ->placeholder('Чому обирають саме нас'),
                        Textarea::make('lead')
                            ->label('Короткий опис')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
                Section::make('Картки')
                    ->schema([
                        Repeater::make('items')
                            ->collapsible()
                            ->collapsed()
                            ->hiddenLabel()
                            ->reorderable()
                            ->addActionLabel('Додати картку')
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Заголовок картки')
                                    ->maxLength(255)
                                    ->required(),
                                Textarea::make('text')
                                    ->label('Текст')
                                    ->rows(3)
                                    ->maxLength(2000),
                                ColorPicker::make('color')
                                    ->label('Колір')
                                    ->default('#367df1')
                                    ->required(),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $title = trim((string) ($state['title'] ?? ''));

                                return $title !== '' ? $title : 'Нова картка';
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return list<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Зберегти')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? 'Чому обирають нас';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->key('form-actions'),
            ]);
    }
}
