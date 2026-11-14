<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\SiteStatistics;
use App\Filament\Admin\Resources\Bundles\BundleResource;
use App\Filament\Admin\Resources\CareArticles\CareArticleResource;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Filament\Admin\Resources\Promotions\PromotionResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class AdminHomeWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    protected string $view = 'filament.admin.widgets.admin-home';

    protected int | string | array $columnSpan = 'full';

    public function greeting(): string
    {
        $hour = (int) now()->timezone('Europe/Kyiv')->format('G');
        $greeting = match (true) {
            $hour < 5 => 'Доброї ночі',
            $hour < 12 => 'Доброго ранку',
            $hour < 18 => 'Добрий день',
            default => 'Добрий вечір',
        };
        $name = trim((string) auth()->user()?->name);

        return $name !== '' ? $greeting.', '.$name : $greeting;
    }

    /**
     * @return list<array{label: string, hint: string, url: string, icon: Heroicon, tone: string}>
     */
    public function links(): array
    {
        $links = [];

        if (OrderResource::canAccess()) {
            $links[] = ['label' => 'Замовлення', 'hint' => 'Обробити й відправити', 'url' => OrderResource::getUrl('index'), 'icon' => Heroicon::OutlinedShoppingBag, 'tone' => '#007aff'];
        }
        if (ProductResource::canAccess()) {
            $links[] = ['label' => 'Товари', 'hint' => 'Додати чи змінити', 'url' => ProductResource::getUrl('index'), 'icon' => Heroicon::OutlinedCube, 'tone' => '#ff9500'];
        }
        if (BundleResource::canAccess()) {
            $links[] = ['label' => 'Комплекти', 'hint' => 'Набори товарів', 'url' => BundleResource::getUrl('index'), 'icon' => Heroicon::OutlinedGift, 'tone' => '#ff2d55'];
        }
        if (PromotionResource::canAccess()) {
            $links[] = ['label' => 'Акції', 'hint' => 'Знижки на товари', 'url' => PromotionResource::getUrl('index'), 'icon' => Heroicon::OutlinedSparkles, 'tone' => '#af52de'];
        }
        if (CareArticleResource::canAccess()) {
            $links[] = ['label' => 'Статті', 'hint' => 'Поради для покупців', 'url' => CareArticleResource::getUrl('index'), 'icon' => Heroicon::OutlinedBookOpen, 'tone' => '#5ac8fa'];
        }
        if (SiteStatistics::canAccess()) {
            $links[] = ['label' => 'Статистика', 'hint' => 'Відвідувачі й продажі', 'url' => SiteStatistics::getUrl(), 'icon' => Heroicon::OutlinedChartBar, 'tone' => '#34c759'];
        }

        return $links;
    }
}
