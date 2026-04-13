<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\OptionGroups\OptionGroupResource;
use App\Filament\Admin\Resources\OptionGroups\Schemas\OptionGroupForm;
use App\Models\OptionGroup;
use App\Support\CatalogCategoryTree;
use BackedEnum;
use Filament\Actions\Action;
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
use Livewire\Attributes\Locked;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageCatalogCategories extends Page
{
    use CanUseDatabaseTransactions;

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

    public function mount(): void
    {
        $this->record = OptionGroup::query()
            ->where('slug', 'category')
            ->firstOrFail();

        abort_unless(OptionGroupResource::canEdit($this->record), 403);

        $this->fillForm();
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
        return $data;
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

    protected function getRedirectUrl(): ?string
    {
        return null;
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
                Section::make('Дерево категорій')
                    ->description('Кореневі рубрики та підкатегорії (до '.CatalogCategoryTree::MAX_DEPTH.' рівнів).')
                    ->schema([OptionGroupForm::catalogCategoriesRepeater()])
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
