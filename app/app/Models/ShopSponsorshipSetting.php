<?php

namespace App\Models;

use App\Support\RichTextSanitizer;
use App\Support\SchemaCache;
use App\Support\StorefrontMemo;
use Illuminate\Database\Eloquent\Model;

/**
 * Налаштування «Віртуальної опіки»: увімкнення, загальна банка та всі підписи розділу.
 */
class ShopSponsorshipSetting extends Model
{
    /** @var array<string, array{label: string, default: string}> ключ → підпис в адмінці й типовий текст */
    public const TEXTS = [
        'header_label' => ['label' => 'Кнопка в шапці', 'default' => 'Віртуальна опіка'],
        'title' => ['label' => 'Заголовок сторінки', 'default' => 'Віртуальна опіка'],
        'become_label' => ['label' => 'Кнопка на сторінці тварини', 'default' => 'Стати опікуном'],
        'choose_term_title' => ['label' => 'Заголовок вибору терміну', 'default' => 'Оберіть термін опікунства'],
        'term_month' => ['label' => 'Термін: 1 місяць', 'default' => '1 місяць'],
        'term_half_year' => ['label' => 'Термін: 6 місяців', 'default' => '6 місяців'],
        'term_year' => ['label' => 'Термін: 1 рік', 'default' => '1 рік'],
        'form_title' => ['label' => 'Заголовок анкети', 'default' => 'Анкета опікуна'],
        'form_photo_label' => ['label' => 'Поле фото в анкеті', 'default' => 'Ваше фото (за бажанням)'],
        'form_photo_hint' => ['label' => 'Підказка під полем фото', 'default' => 'Після перевірки фото з’явиться на сторінці тварини поруч з іменем.'],
        'form_submit' => ['label' => 'Кнопка анкети', 'default' => 'Перейти до оплати'],
        'payment_title' => ['label' => 'Заголовок сторінки оплати', 'default' => 'Залишився один крок — оплата'],
        'active_title' => ['label' => 'Заголовок, коли оплату підтверджено', 'default' => 'Дякуємо! Ви — опікун'],
        'payment_hint' => ['label' => 'Підказка про оплату', 'default' => 'У коментарі до переказу вкажіть номер заявки — так ми швидко підтвердимо опікунство.'],
        'pay_button' => ['label' => 'Кнопка оплати', 'default' => 'Оплатити на банку Monobank'],
        'sponsors_title' => ['label' => 'Заголовок списку опікунів', 'default' => 'Опікуни'],
    ];

    public const DEFAULT_THANKS_TEXT = 'Дякуємо, що допомагаєте! Після підтвердження оплати на ваш email надходитимуть фото та відео, як тваринка радіє.';

    private const MEMO_KEY = 'sponsorship-settings';

    protected $fillable = [
        'is_enabled',
        'jar_url',
        'texts',
        'intro',
        'thanks_text',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'texts' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetResolvedCache());
        static::deleted(fn () => self::forgetResolvedCache());
    }

    /**
     * @return array{is_enabled: bool, jar_url: ?string, texts: array<string, string>, intro_html: string, thanks_html: string}
     */
    public static function resolved(): array
    {
        return StorefrontMemo::remember(self::MEMO_KEY, function (): array {
            $row = SchemaCache::hasTable('shop_sponsorship_settings')
                ? self::query()->orderBy('id')->first()
                : null;
            $stored = is_array($row?->texts) ? $row->texts : [];

            $texts = [];
            foreach (self::TEXTS as $key => $meta) {
                $value = trim((string) ($stored[$key] ?? ''));
                $texts[$key] = $value !== '' ? $value : $meta['default'];
            }

            $jarUrl = trim((string) $row?->jar_url);
            $thanks = RichTextSanitizer::hasVisibleContent($row?->thanks_text)
                ? (string) $row->thanks_text
                : self::DEFAULT_THANKS_TEXT;

            return [
                'is_enabled' => (bool) $row?->is_enabled,
                'jar_url' => ShopCharitySetting::isAllowedJarUrl($jarUrl) ? $jarUrl : null,
                'texts' => $texts,
                'intro_html' => RichTextSanitizer::toHtml($row?->intro),
                'thanks_html' => RichTextSanitizer::toHtml($thanks),
            ];
        });
    }

    public static function isEnabled(): bool
    {
        return self::resolved()['is_enabled'];
    }

    public static function text(string $key): string
    {
        return self::resolved()['texts'][$key] ?? (self::TEXTS[$key]['default'] ?? $key);
    }

    public static function termLabel(string $term): string
    {
        return self::text('term_'.$term);
    }

    public static function forgetResolvedCache(): void
    {
        StorefrontMemo::forget(self::MEMO_KEY);
    }
}
