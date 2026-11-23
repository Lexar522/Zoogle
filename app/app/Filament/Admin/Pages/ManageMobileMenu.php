<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopMobileMenuSetting;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema as DbSchema;
use Throwable;
use UnitEnum;

/**
 * Бургер-меню на телефоні: які пункти, у якому порядку, якого кольору; контакти й кнопка входу.
 *
 * @property-read Schema $form
 */
class ManageMobileMenu extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'mobile-menu';

    protected static ?string $title = 'Мобільне меню';

    protected static ?string $navigationLabel = 'Мобільне меню';

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?int $navigationSort = 91;

    private const MAX_ITEMS = 20;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return DbSchema::hasTable('shop_mobile_menu_settings');
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
        $row = ShopMobileMenuSetting::query()->orderBy('id')->first();
        $items = is_array($row?->items) && $row->items !== [] ? $row->items : ShopMobileMenuSetting::DEFAULT_ITEMS;

        return [
            'items' => array_values($items),
            'show_contacts' => $row?->show_contacts ?? true,
            'show_login' => $row?->show_login ?? true,
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $data = $this->form->getState();

            $items = [];
            foreach (array_values($data['items'] ?? []) as $item) {
                $target = (string) ($item['target'] ?? '');
                $items[] = [
                    'target' => $target,
                    'label' => trim((string) ($item['label'] ?? '')) ?: null,
                    'color' => (string) ($item['color'] ?? 'default'),
                    'group' => (string) ($item['group'] ?? 'main'),
                    'url' => $target === ShopMobileMenuSetting::TARGET_CUSTOM ? trim((string) ($item['url'] ?? '')) : null,
                ];
            }

            $row = ShopMobileMenuSetting::query()->orderBy('id')->first() ?? new ShopMobileMenuSetting;
            $row->fill([
                'items' => $items,
                'show_contacts' => (bool) ($data['show_contacts'] ?? true),
                'show_login' => (bool) ($data['show_login'] ?? true),
            ])->save();
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body('Мобільне меню оновлено.')
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
                Section::make('Пункти меню')
                    ->description('Перетягуйте пункти, щоб змінити порядок. Порожня назва — стандартна. «Віртуальна опіка» і «Благодійність» з\'являються лише коли ці розділи увімкнені.')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->cloneable()
                            ->maxItems(self::MAX_ITEMS)
                            ->defaultItems(0)
                            ->addActionLabel('Додати пункт')
                            ->itemLabel(fn (array $state): string => trim((string) ($state['label'] ?? ''))
                                ?: (ShopMobileMenuSetting::TARGETS[$state['target'] ?? ''] ?? 'Новий пункт'))
                            ->columns(['default' => 1, 'md' => 4])
                            ->schema([
                                Select::make('target')
                                    ->label('Куди веде')
                                    ->options(ShopMobileMenuSetting::TARGETS)
                                    ->required()
                                    ->native(false)
                                    ->live(),
                                TextInput::make('label')
                                    ->label('Назва')
                                    ->maxLength(60)
                                    ->placeholder('Стандартна')
                                    ->required(fn (Get $get): bool => $get('target') === ShopMobileMenuSetting::TARGET_CUSTOM),
                                Select::make('color')
                                    ->label('Колір')
                                    ->options(ShopMobileMenuSetting::COLORS)
                                    ->default('default')
                                    ->required()
                                    ->native(false),
                                Select::make('group')
                                    ->label('Група')
                                    ->options(ShopMobileMenuSetting::GROUPS)
                                    ->default('main')
                                    ->required()
                                    ->native(false),
                                TextInput::make('url')
                                    ->label('Посилання')
                                    ->placeholder('/catalog?category=12 або https://instagram.com/...')
                                    ->maxLength(500)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get): bool => $get('target') === ShopMobileMenuSetting::TARGET_CUSTOM)
                                    ->required(fn (Get $get): bool => $get('target') === ShopMobileMenuSetting::TARGET_CUSTOM)
                                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                        if (filled($value) && ! ShopMobileMenuSetting::isAllowedCustomUrl((string) $value)) {
                                            $fail('Вкажіть шлях сайту, що починається з «/», або адресу https://');
                                        }
                                    }),
                            ]),
                    ]),
                Section::make('Під пунктами')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Toggle::make('show_contacts')
                            ->label('Показувати всі контакти')
                            ->helperText('Телефони, email і соцмережі з розділу «Інтеграції».'),
                        Toggle::make('show_login')
                            ->label('Кнопка «Увійти» для гостей'),
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
        return static::$title ?? 'Мобільне меню';
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
