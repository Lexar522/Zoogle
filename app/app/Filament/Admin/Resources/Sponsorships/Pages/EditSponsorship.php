<?php

namespace App\Filament\Admin\Resources\Sponsorships\Pages;

use App\Filament\Admin\Resources\Sponsorships\SponsorshipResource;
use App\Filament\Admin\Resources\Sponsorships\Tables\SponsorshipActions;
use App\Models\Sponsorship;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

class EditSponsorship extends EditRecord
{
    protected static string $resource = SponsorshipResource::class;

    protected function getHeaderActions(): array
    {
        return SponsorshipActions::all();
    }

    /**
     * Статус «Активна», виставлений вручну без дат, діє з сьогодні на обраний термін.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Sponsorship $record */
        $record = $this->getRecord();
        if (($data['status'] ?? null) !== Sponsorship::STATUS_ACTIVE || filled($data['ends_at'] ?? null)) {
            return $data;
        }

        $start = filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at']) : Carbon::today();
        $period = $record->periodFrom($start);

        return [
            ...$data,
            'starts_at' => $period['starts_at']->toDateString(),
            'ends_at' => $period['ends_at']->toDateString(),
            'paid_at' => $record->paid_at ?? now(),
        ];
    }
}
