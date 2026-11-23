<?php

namespace App\Support;

use App\Models\ShopContactItem;
use App\Models\ShopIntegrationSetting;

/**
 * @phpstan-type ContactItem array{key: string, href: string, label: string, kind: 'phone'|'email'|'instagram'|'tiktok'|'viber'|'whatsapp'|'telegram', external: bool}
 */
class ShopHeaderContacts
{
    /**
     * Кольорова іконка контакту (як у шапці на комп'ютері).
     */
    public static function coloredIconUrl(string $kind): string
    {
        $heroicons = 'https://cdn.jsdelivr.net/npm/heroicons@2.1.5/24/outline';
        $commons = 'https://upload.wikimedia.org/wikipedia/commons';

        return match ($kind) {
            'phone' => "{$heroicons}/phone.svg",
            'email' => "{$commons}/7/7e/Gmail_icon_%282020%29.svg",
            'instagram' => "{$commons}/9/95/Instagram_logo_2022.svg",
            'tiktok' => "{$commons}/a/a6/Tiktok_icon.svg",
            'viber' => asset('images/contacts/viber.svg'),
            'whatsapp' => "{$commons}/6/6b/WhatsApp.svg",
            default => 'https://telegram.org/img/t_logo.svg',
        };
    }

    /**
     * Контакти для модалки PDP (відкладена оплата): телефон + месенджери (без email/Instagram).
     *
     * @return list<ContactItem>
     */
    public static function deferModalContactItemsFrom(?ShopIntegrationSetting $record): array
    {
        $items = self::itemsFrom($record);
        $allowed = ['phone', 'viber', 'whatsapp', 'telegram'];

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => in_array($item['kind'], $allowed, true)
        ));
    }

    /**
     * Лише месенджери для PDP-модалки (Viber, WhatsApp, Telegram).
     *
     * @return list<ContactItem>
     */
    public static function messengerItemsFrom(?ShopIntegrationSetting $record): array
    {
        $items = self::itemsFrom($record);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => in_array($item['kind'], ['viber', 'whatsapp', 'telegram'], true)
        ));
    }

    /**
     * Футер: звʼязок → месенджери → соцмережі. Порядок у групі лишається як у списку (sort_order).
     *
     * @param  list<ContactItem>  $items
     * @return array<string, list<ContactItem>>
     */
    public static function groupsForFooter(array $items): array
    {
        $groups = [
            'direct' => [],
            'online' => [],
        ];
        $groupOf = [
            'phone' => 'direct',
            'email' => 'direct',
            'viber' => 'online',
            'whatsapp' => 'online',
            'telegram' => 'online',
            'instagram' => 'online',
            'tiktok' => 'online',
        ];
        $onlineRank = [
            'viber' => 0,
            'whatsapp' => 1,
            'telegram' => 2,
            'instagram' => 3,
            'tiktok' => 4,
        ];

        foreach ($items as $item) {
            $group = $groupOf[$item['kind'] ?? ''] ?? null;
            if ($group === null) {
                continue;
            }
            $groups[$group][] = $item;
        }

        if ($groups['online'] !== []) {
            usort($groups['online'], static function (array $a, array $b) use ($onlineRank): int {
                return ($onlineRank[$a['kind'] ?? ''] ?? 99) <=> ($onlineRank[$b['kind'] ?? ''] ?? 99);
            });
        }

        return array_filter($groups, static fn (array $group): bool => $group !== []);
    }

    /**
     * Контакти шапки вітрини з кешу (однакові для всіх відвідувачів).
     *
     * @return list<array<string, mixed>>
     */
    public static function storefrontItems(): array
    {
        return StorefrontViewCache::remember('header-contacts', fn (): array => self::itemsFrom(ShopIntegrationSetting::storefront()));
    }

    /**
     * @return list<ContactItem>
     */
    public static function itemsFrom(?ShopIntegrationSetting $record): array
    {
        if ($record === null) {
            return [];
        }

        if (! $record->exists) {
            return self::itemsFromLegacyColumns($record);
        }

        if (self::contactItemsTableExists()) {
            $rows = ShopContactItem::query()->ordered()->get();
            if ($rows->isNotEmpty()) {
                return self::itemsFromContactRows($rows);
            }
        }

        return self::itemsFromLegacyColumns($record);
    }

    private static function contactItemsTableExists(): bool
    {
        try {
            return SchemaCache::hasTable('shop_contact_items');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  iterable<ShopContactItem>  $rows
     * @return list<ContactItem>
     */
    private static function itemsFromContactRows(iterable $rows): array
    {
        $out = [];
        $kindCounts = [];
        foreach ($rows as $row) {
            $kind = (string) $row->kind;
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0);
            $key = $kind.'-'.$kindCounts[$kind];
            $kindCounts[$kind]++;

            $item = self::itemFromKindAndValue($kind, (string) $row->value, $key);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return list<ContactItem>
     */
    private static function itemsFromLegacyColumns(ShopIntegrationSetting $record): array
    {
        $out = [];
        if ($item = self::phoneItem($record->contact_phone)) {
            $out[] = $item;
        }
        if ($item = self::emailItem($record->contact_email)) {
            $out[] = $item;
        }
        if ($item = self::instagramItem($record->contact_instagram)) {
            $out[] = $item;
        }
        if ($item = self::viberItem($record->contact_viber)) {
            $out[] = $item;
        }
        if ($item = self::whatsappItem($record->contact_whatsapp)) {
            $out[] = $item;
        }
        if ($item = self::telegramItem($record->contact_telegram)) {
            $out[] = $item;
        }

        return $out;
    }

    private static function itemFromKindAndValue(string $kind, string $value, string $key): ?array
    {
        $item = match ($kind) {
            'phone' => self::phoneItem($value),
            'email' => self::emailItem($value),
            'instagram' => self::instagramItem($value),
            'tiktok' => self::tiktokItem($value),
            'viber' => self::viberItem($value),
            'whatsapp' => self::whatsappItem($value),
            'telegram' => self::telegramItem($value),
            default => null,
        };
        if ($item === null) {
            return null;
        }
        $item['key'] = $key;

        return $item;
    }

    /**
     * @return ?ContactItem
     */
    private static function phoneItem(?string $raw): ?array
    {
        $label = trim((string) $raw);
        if ($label === '') {
            return null;
        }
        $href = self::phoneToTelHref($label);
        if ($href === null) {
            return null;
        }

        return [
            'key' => 'phone',
            'href' => $href,
            'label' => $label,
            'kind' => 'phone',
            'external' => false,
        ];
    }

    private static function phoneToTelHref(string $phone): ?string
    {
        $digits = self::normalizePhoneDigits($phone);
        if ($digits === null) {
            return null;
        }

        return 'tel:+'.$digits;
    }

    private static function normalizePhoneDigits(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $digits = '38'.substr($digits, 1);
        }
        $digits = ltrim($digits, '+');
        if ($digits === '') {
            return null;
        }

        return $digits;
    }

    /**
     * @return ?ContactItem
     */
    private static function emailItem(?string $raw): ?array
    {
        $addr = trim((string) $raw);
        if ($addr === '' || ! filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'key' => 'email',
            'href' => 'mailto:'.$addr,
            'label' => $addr,
            'kind' => 'email',
            'external' => false,
        ];
    }

    /**
     * @return ?ContactItem
     */
    private static function instagramItem(?string $raw): ?array
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (self::looksLikeHttpUrl($s)) {
            if (! self::isHttpUrlSafe($s)) {
                return null;
            }

            return [
                'key' => 'instagram',
                'href' => $s,
                'label' => 'Instagram',
                'kind' => 'instagram',
                'external' => true,
            ];
        }
        $user = ltrim($s, '@');
        $user = (string) (preg_replace('/^instagram\.com\//i', '', $user) ?? $user);
        if ($user === '' || ! preg_match('/^[A-Za-z0-9._]{1,100}$/u', $user)) {
            return null;
        }
        $href = 'https://www.instagram.com/'.$user.'/';

        return [
            'key' => 'instagram',
            'href' => $href,
            'label' => '@'.$user,
            'kind' => 'instagram',
            'external' => true,
        ];
    }

    private static function isViberWebHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }
        if ($host === 'viber.com' || $host === 'viber.me' || $host === 'me.viber.com' || $host === 'chats.viber.com' || $host === 'invite.viber.com') {
            return true;
        }
        if (str_ends_with($host, '.viber.com') || str_ends_with($host, '.viber.me')) {
            return true;
        }

        return false;
    }

    /**
     * @return ?ContactItem
     */
    private static function viberItem(?string $raw): ?array
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (self::looksLikeViberScheme($s)) {
            if (! self::isViberDeepLinkSafe($s)) {
                return null;
            }

            return [
                'key' => 'viber',
                'href' => $s,
                'label' => 'Viber',
                'kind' => 'viber',
                'external' => true,
            ];
        }
        if (self::looksLikeHttpUrl($s) && self::isHttpUrlSafe($s)) {
            $host = strtolower((string) (parse_url($s, PHP_URL_HOST) ?? ''));
            if (self::isViberWebHost($host)) {
                return [
                    'key' => 'viber',
                    'href' => $s,
                    'label' => 'Viber',
                    'kind' => 'viber',
                    'external' => true,
                ];
            }
        }
        if ($digits = self::normalizePhoneDigits($s)) {
            return [
                'key' => 'viber',
                'href' => 'viber://chat?number='.$digits,
                'label' => $s,
                'kind' => 'viber',
                'external' => true,
            ];
        }

        return null;
    }

    private static function isViberDeepLinkSafe(string $url): bool
    {
        if (str_contains($url, "\0") || str_contains($url, ' ') || str_contains($url, "\n") || str_contains($url, "\r")) {
            return false;
        }
        if (! str_starts_with(strtolower($url), 'viber:')) {
            return false;
        }
        if (str_contains($url, 'javascript:') || str_contains($url, 'data:')) {
            return false;
        }

        return true;
    }

    /**
     * @return ?ContactItem
     */
    private static function whatsappItem(?string $raw): ?array
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (self::looksLikeHttpUrl($s) && self::isHttpUrlSafe($s)) {
            $path = (string) (parse_url($s, PHP_URL_PATH) ?? '');
            if ($path !== '' && $path !== '/') {
                $host = strtolower((string) (parse_url($s, PHP_URL_HOST) ?? ''));
                $ok = $host === 'wa.me' || str_ends_with($host, '.wa.me')
                    || $host === 'api.whatsapp.com' || $host === 'www.whatsapp.com' || $host === 'web.whatsapp.com'
                    || str_ends_with($host, '.whatsapp.com');
                if ($ok) {
                    return [
                        'key' => 'whatsapp',
                        'href' => $s,
                        'label' => 'WhatsApp',
                        'kind' => 'whatsapp',
                        'external' => true,
                    ];
                }
            }
        }
        if ($digits = self::normalizePhoneDigits($s)) {
            return [
                'key' => 'whatsapp',
                'href' => 'https://wa.me/'.$digits,
                'label' => $s,
                'kind' => 'whatsapp',
                'external' => true,
            ];
        }

        return null;
    }

    /**
     * @return ?ContactItem
     */
    private static function telegramItem(?string $raw): ?array
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (self::looksLikeHttpUrl($s)) {
            if (! self::isHttpUrlSafe($s)) {
                return null;
            }
            $host = strtolower((string) (parse_url($s, PHP_URL_HOST) ?? ''));
            if (! self::isTelegramWebHost($host)) {
                return null;
            }

            return [
                'key' => 'telegram',
                'href' => $s,
                'label' => 'Telegram',
                'kind' => 'telegram',
                'external' => true,
            ];
        }
        $user = ltrim($s, '@');
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{3,64}$/u', $user)) {
            return null;
        }
        $href = 'https://t.me/'.$user;

        return [
            'key' => 'telegram',
            'href' => $href,
            'label' => '@'.$user,
            'kind' => 'telegram',
            'external' => true,
        ];
    }

    private static function isTelegramWebHost(string $host): bool
    {
        return $host === 't.me' || $host === 'telegram.me' || str_ends_with($host, '.t.me') || str_ends_with($host, '.telegram.me');
    }

    /**
     * @return ?ContactItem
     */
    private static function tiktokItem(?string $raw): ?array
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (self::looksLikeHttpUrl($s)) {
            if (! self::isHttpUrlSafe($s)) {
                return null;
            }
            $host = strtolower((string) (parse_url($s, PHP_URL_HOST) ?? ''));
            if (! self::isTiktokWebHost($host)) {
                return null;
            }
            $path = (string) (parse_url($s, PHP_URL_PATH) ?? '');
            $label = 'TikTok';
            if (preg_match('#/@([A-Za-z0-9._]{1,24})#', $path, $m) === 1) {
                $label = '@'.$m[1];
            }

            return [
                'key' => 'tiktok',
                'href' => $s,
                'label' => $label,
                'kind' => 'tiktok',
                'external' => true,
            ];
        }
        $user = ltrim($s, '@');
        $user = (string) (preg_replace('/^(?:www\.)?tiktok\.com\/@?/i', '', $user) ?? $user);
        $user = ltrim($user, '@');
        if ($user === '' || ! preg_match('/^[A-Za-z0-9._]{2,24}$/u', $user)) {
            return null;
        }

        return [
            'key' => 'tiktok',
            'href' => 'https://www.tiktok.com/@'.$user,
            'label' => '@'.$user,
            'kind' => 'tiktok',
            'external' => true,
        ];
    }

    private static function isTiktokWebHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        return $host === 'tiktok.com' || str_ends_with($host, '.tiktok.com');
    }

    private static function looksLikeHttpUrl(string $s): bool
    {
        $l = strtolower($s);

        return str_starts_with($l, 'http://') || str_starts_with($l, 'https://');
    }

    private static function looksLikeViberScheme(string $s): bool
    {
        return str_starts_with(strtolower($s), 'viber:');
    }

    private static function isHttpUrlSafe(string $url): bool
    {
        if (str_contains($url, "\0") || str_contains($url, ' ') || str_contains($url, "\n") || str_contains($url, "\r")) {
            return false;
        }
        $p = parse_url($url);
        if (! is_array($p) || empty($p['host']) || str_contains($p['host'], '@')) {
            return false;
        }
        $scheme = strtolower($p['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return false;
        }
        if (str_contains($p['host'], ' ')) {
            return false;
        }

        return true;
    }
}
