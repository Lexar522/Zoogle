<?php

namespace App\Filament\Admin\Resources\Sponsorships;

use App\Filament\Admin\Resources\Sponsorships\Pages\EditSponsorship;
use App\Filament\Admin\Resources\Sponsorships\Pages\ListSponsorships;
use App\Filament\Admin\Resources\Sponsorships\Schemas\SponsorshipForm;
use App\Filament\Admin\Resources\Sponsorships\Tables\SponsorshipsTable;
use App\Models\Sponsorship;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as DbSchema;
use UnitEnum;

class SponsorshipResource extends Resource
{
    protected static ?string $model = Sponsorship::class;

    protected static ?string $slug = 'sponsorships';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'Опікуни';

    protected static ?string $modelLabel = 'Опікунство';

    protected static ?string $pluralModelLabel = 'Опікуни';

    protected static string|UnitEnum|null $navigationGroup = 'Віртуальна опіка';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'sponsor_name';

    public static function getNavigationBadge(): ?string
    {
        if (! DbSchema::hasTable('sponsorships')) {
            return null;
        }

        $waiting = Sponsorship::query()
            ->where(fn ($q) => $q
                ->where('status', Sponsorship::STATUS_PENDING)
                ->orWhere('photo_status', Sponsorship::PHOTO_PENDING))
            ->where('status', '!=', Sponsorship::STATUS_CANCELLED)
            ->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Заявки, що чекають підтвердження оплати або перевірки фото';
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Sponsorship
            ? $record->reference().' · '.$record->animal_name
            : parent::getRecordTitle($record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return SponsorshipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SponsorshipsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsorships::route('/'),
            'edit' => EditSponsorship::route('/{record}/edit'),
        ];
    }
}
