<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\OptionGroups\OptionGroupResource;
use App\Filament\Admin\Resources\OptionGroups\Schemas\OptionGroupForm;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Support\CatalogCategoryTree;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageCatalogCategories extends Page
{
    use CanUseDatabaseTransactions;

    /**
     * Усе дерево категорій рендериться однією формою з вкладеними repeater'ами: ~0,35 МБ на категорію.
     * Типових для хостингу 128 МБ на кілька сотень категорій не вистачає (помилка 500 у v1.1.81).
     */
    private const MEMORY_LIMIT_BYTES = 512 * 1024 * 1024;

    protected static ?string $slug = 'catalog-categories';

    public static function canAccess(): bool
    {
        $record = OptionGroup::query()->where('slug', 'category')->first();

        return $record !== null && OptionGroupResource::canEdit($record);
    }

    protected static ?string $title = 'Категорії';

    protected static ?string $navigationLabel = 'Категорії';

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    #[Locked]
    public ?OptionGroup $record = null;

    /** Коренева рубрика, чию гілку показує форма (?branch=ID). Перемикання — через перезавантаження сторінки. */
    #[Locked]
    public ?int $branch = null;

    /**
     * Livewire викликає boot() і при відкритті сторінки, і при кожному збереженні форми.
     */
    public function boot(): void
    {
        self::ensureMemoryLimit();
    }

    /** Лише піднімає ліміт: «без обмеження» (-1) чи вже більший ліміт лишаються як є. */
    private static function ensureMemoryLimit(): void
    {
        $current = self::iniBytes((string) ini_get('memory_limit'));
        if ($current < 0 || $current >= self::MEMORY_LIMIT_BYTES) {
            return;
        }

        if (ini_set('memory_limit', (string) self::MEMORY_LIMIT_BYTES) === false) {
            report(new \RuntimeException('Не вдалося підняти memory_limit для сторінки категорій (зараз '.ini_get('memory_limit').').'));
        }
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public function mount(): void
    {
        $this->record = OptionGroup::query()
            ->where('slug', 'category')
            ->firstOrFail();

        abort_unless(OptionGroupResource::canEdit($this->record), 403);

        $this->branch = $this->resolveBranch(request()->integer('branch'));

        $this->fillForm();
    }

    /**
     * @return array<int, string> id => назва, у порядку сортування
     */
    private function rootCategoryOptions(): array
    {
        return OptionValue::query()
            ->where('option_group_id', $this->record?->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (OptionValue $root): array => [(int) $root->id => (string) mt($root->name)])
            ->all();
    }

    /** Невідома чи вже видалена гілка → перша коренева рубрика. */
    private function resolveBranch(int $requested): ?int
    {
        $rootIds = array_keys($this->rootCategoryOptions());

        return in_array($requested, $rootIds, true) ? $requested : ($rootIds[0] ?? null);
    }

    protected function fillForm(): void
    {
        $data = $this->record->attributesToArray();

        $this->callHook('beforeFill');

        $data = $this->mutateFormDataBeforeFill($data);

        $this->form->fill($data);

        $this->callHook('afterFill');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'branch_switch' => $this->branch];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_active'] = true;

        return $data;
    }

    public function save(): void
    {
        abort_unless($this->record instanceof OptionGroup, 404);
        abort_unless(OptionGroupResource::canEdit($this->record), 403);

        try {
            $this->beginDatabaseTransaction();

            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeSave($data);

            $this->callHook('beforeSave');

            $this->handleRecordUpdate($this->record, $data);

            $this->callHook('afterSave');
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            return;
        } catch (ValidationException $exception) {
            $this->rollBackDatabaseTransaction();

            $message = collect($exception->errors())->flatten()->filter()->first();

            Notification::make()
                ->danger()
                ->title('Не вдалося зберегти категорії')
                ->body(is_string($message) && $message !== ''
                    ? $message
                    : 'Перевірте назви, слаги та те, що категорії з товарами не видаляються.')
                ->persistent()
                ->send();

            return;
        } catch (QueryException $exception) {
            $this->rollBackDatabaseTransaction();

            report($exception);

            Notification::make()
                ->danger()
                ->title('Не вдалося зберегти категорії')
                ->body('Помилка бази даних під час збереження. Спробуйте ще раз або зменшіть кількість змін за раз.')
                ->persistent()
                ->send();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();

        $this->getSavedNotification()?->send();

        if ($redirectUrl = $this->getRedirectUrl()) {
            $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        return $record;
    }

    protected function getSavedNotification(): ?Notification
    {
        $title = $this->getSavedNotificationTitle();

        if (blank($title)) {
            return null;
        }

        return Notification::make()
            ->success()
            ->title($title);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Збережено';
    }

    /** Якщо обрану гілку щойно видалили — відкриваємо першу наявну. */
    protected function getRedirectUrl(): ?string
    {
        $branch = $this->resolveBranch((int) $this->branch);

        return $branch === $this->branch ? null : static::getUrl(['branch' => $branch]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->model($this->record)
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Кореневі категорії')
                    ->description('Назви, порядок і правила доставки, оплати та Google Merchant для цілої гілки.')
                    ->schema([OptionGroupForm::catalogRootCategoriesRepeater()])
                    ->collapsible()
                    ->columnSpanFull(),
                Section::make('Підкатегорії')
                    ->description('Показуємо одну гілку за раз (до '.CatalogCategoryTree::MAX_DEPTH.' рівнів), щоб сторінка відкривалася швидко. Збережіть зміни перед перемиканням гілки.')
                    ->schema($this->branch === null ? [] : [
                        Select::make('branch_switch')
                            ->label('Гілка')
                            ->options(fn (): array => $this->rootCategoryOptions())
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live()
                            ->dehydrated(false)
                            ->afterStateUpdated(fn ($state) => $this->redirect(static::getUrl(['branch' => (int) $state]))),
                        OptionGroupForm::catalogBranchRepeater($this->branch),
                    ])
                    ->visible($this->branch !== null)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return list<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label('Зберегти')
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? 'Категорії';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->sticky($this->areFormActionsSticky())
                    ->key('form-actions'),
            ]);
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }
}
