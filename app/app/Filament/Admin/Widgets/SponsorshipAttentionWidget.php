<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Sponsorships\SponsorshipResource;
use App\Models\Sponsorship;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Головна адмінки: заявки на опіку, що чекають підтвердження оплати, і фото опікунів на перевірці.
 */
class SponsorshipAttentionWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = -9;

    private const RECENT_LIMIT = 5;

    protected string $view = 'filament.admin.widgets.sponsorship-attention';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Schema::hasTable('sponsorships')
            && SponsorshipResource::canAccess()
            && self::counts()['total'] > 0;
    }

    /**
     * @return array{payments: int, photos: int, total: int}
     */
    public static function counts(): array
    {
        $payments = Sponsorship::query()->where('status', Sponsorship::STATUS_PENDING)->count();
        $photos = Sponsorship::query()
            ->where('photo_status', Sponsorship::PHOTO_PENDING)
            ->where('status', '!=', Sponsorship::STATUS_CANCELLED)
            ->count();

        return ['payments' => $payments, 'photos' => $photos, 'total' => $payments + $photos];
    }

    /**
     * @return Collection<int, Sponsorship>
     */
    public function recent(): Collection
    {
        return Sponsorship::query()
            ->where('status', '!=', Sponsorship::STATUS_CANCELLED)
            ->where(fn ($q) => $q
                ->where('status', Sponsorship::STATUS_PENDING)
                ->orWhere('photo_status', Sponsorship::PHOTO_PENDING))
            ->latest()
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    public function paymentsUrl(): string
    {
        return SponsorshipResource::getUrl('index', ['filters' => ['status' => ['value' => Sponsorship::STATUS_PENDING]]]);
    }

    public function photosUrl(): string
    {
        return SponsorshipResource::getUrl('index', ['filters' => ['photo_status' => ['value' => Sponsorship::PHOTO_PENDING]]]);
    }

    public function editUrl(Sponsorship $sponsorship): string
    {
        return SponsorshipResource::getUrl('edit', ['record' => $sponsorship]);
    }
}
