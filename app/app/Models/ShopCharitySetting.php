<?php

namespace App\Models;

use App\Support\RichTextSanitizer;
use App\Support\SchemaCache;
use App\Support\StorefrontMemo;
use Illuminate\Database\Eloquent\Model;

/**
 * Сторінка «Благодійність»: текст, банка Monobank і запрошення допомогти після замовлення.
 */
class ShopCharitySetting extends Model
{
    public const DEFAULTS = [
        'header_label' => 'Благодійність',
        'title' => 'Допоможіть тваринам, що постраждали від війни',
        'button_label' => 'Задонатити',
        'order_prompt' => 'Бажаєте допомогти тваринам, що постраждали під час війни?',
    ];

    /** Банка приймається лише з доменів Monobank і лише по https. */
    private const ALLOWED_JAR_HOSTS = ['send.monobank.ua', 'monobank.ua', 'www.monobank.ua'];

    private const MEMO_KEY = 'charity-settings';

    protected $fillable = [
        'is_enabled',
        'header_label',
        'title',
        'body',
        'jar_url',
        'button_label',
        'order_prompt',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetResolvedCache());
        static::deleted(fn () => self::forgetResolvedCache());
    }

    /**
     * Налаштування для вітрини з підставленими типовими підписами.
     *
     * @return array{is_enabled: bool, header_label: string, title: string, body_html: string, jar_url: ?string, button_label: string, order_prompt: string}
     */
    public static function resolved(): array
    {
        return StorefrontMemo::remember(self::MEMO_KEY, function (): array {
            $row = SchemaCache::hasTable('shop_charity_settings')
                ? self::query()->orderBy('id')->first()
                : null;

            $text = fn (string $key): string => filled(trim((string) $row?->{$key}))
                ? trim((string) $row->{$key})
                : self::DEFAULTS[$key];
            $jarUrl = trim((string) $row?->jar_url);

            return [
                'is_enabled' => (bool) $row?->is_enabled,
                'header_label' => $text('header_label'),
                'title' => $text('title'),
                'body_html' => RichTextSanitizer::toHtml($row?->body),
                'jar_url' => self::isAllowedJarUrl($jarUrl) ? $jarUrl : null,
                'button_label' => $text('button_label'),
                'order_prompt' => $text('order_prompt'),
            ];
        });
    }

    public static function isEnabled(): bool
    {
        return self::resolved()['is_enabled'];
    }

    public static function isAllowedJarUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower((string) ($parts['host'] ?? '')), self::ALLOWED_JAR_HOSTS, true);
    }

    public static function forgetResolvedCache(): void
    {
        StorefrontMemo::forget(self::MEMO_KEY);
    }
}
