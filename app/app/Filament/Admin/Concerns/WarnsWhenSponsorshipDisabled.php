<?php

namespace App\Filament\Admin\Concerns;

use App\Filament\Admin\Pages\ManageSponsorship;
use App\Models\ShopSponsorshipSetting;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Списки «Віртуальної опіки» попереджають, якщо розділ вимкнено: тоді тварин і кнопку на сайті не видно.
 */
trait WarnsWhenSponsorshipDisabled
{
    public function getSubheading(): string|Htmlable|null
    {
        if (ShopSponsorshipSetting::isEnabled()) {
            return null;
        }

        return new HtmlString(
            '<span style="color:#ff9500;font-weight:600">⚠ Віртуальна опіка вимкнена — на сайті її не видно.</span> '
            .'<a href="'.e(ManageSponsorship::getUrl()).'" style="color:#007aff;font-weight:600;text-decoration:underline">Увімкнути в налаштуваннях опіки</a>'
        );
    }
}
