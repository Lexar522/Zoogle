<?php

namespace App\Filament\Admin\Pages;

use App\Models\ShopMediaSetting;
use BackedEnum;
use Filament\Actions\Action;
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
class ManageMediaOptimization extends Page
{
    use CanUseDatabaseTransactions;

    protected static ?string $slug = 'media-optimization';

    protected static ?string $title = 'Оптимізація зображень';

    protected static ?string $navigationLabel = 'Оптимізація зображень';

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 94;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return DbSchema::hasTable('shop_media_settings');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill($this->loadFormData());
    }

    /**
     * @return array{optimize_images: bool}
     */
    private function loadFormData(): array
    {
        ShopMediaSetting::forgetOptimizeImagesCache();

        return [
            'optimize_images' => ShopMediaSetting::optimizeImagesEnabled(),
        ];
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');

            $row = ShopMediaSetting::query()->orderBy('id')->first() ?? new ShopMediaSetting;
            $row->optimize_images = (bool) ($data['optimize_images'] ?? false);
            $row->save();
            ShopMediaSetting::forgetOptimizeImagesCache();

            $this->callHook('afterSave');
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        $enabled = (bool) ($data['optimize_images'] ?? false);

        Notification::make()
            ->success()
            ->title('Збережено')
            ->body($enabled
                ? 'Оптимізація зображень увімкнена.'
                : 'Оптимізація зображень вимкнена. Вітрина показує оригінали.')
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
                Section::make('Вітрина')
                    ->description('Один рубильник для зменшених копій і покрокової підгрузки. Головне фото на сторінці товару завжди лишається оригіналом.')
                    ->schema([
                        Toggle::make('optimize_images')
                            ->label('Оптимізація зображень і покрокова підгрузка')
                            ->helperText('Увімкнено: картки, мініатюри, кошик, обране й банери беруть легкі WebP. Перші картки вантажаться одразу, решта — під час прокрутки. GIF і відео в каталозі показують кадр-постер. Вимкнено: скрізь оригінальні файли, усі фото карток вантажаться одразу, GIF і відео знову програються на картках.')
                            ->default(true),
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
        return static::$title ?? 'Оптимізація зображень';
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
