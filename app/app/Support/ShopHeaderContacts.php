<?php

namespace App\Support;

use App\Models\ShopIntegrationSetting;

/**
 * @phpstan-type ContactItem array{key: string, href: string, label: string, kind: 'phone'|'email'|'instagram'|'viber'|'whatsapp'|'telegram', external: bool}
 */
class ShopHeaderContacts
{
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
     * @return list<ContactItem>
     */
    public static function itemsFrom(?ShopIntegrationSetting $record): array
    {
        if ($record === null) {
            return [];
        }

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

        return 'tel:+'.$digits;
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
        if (! self::looksLikeHttpUrl($s) || ! self::isHttpUrlSafe($s)) {
            return null;
        }
        $host = strtolower((string) (parse_url($s, PHP_URL_HOST) ?? ''));
        if (! self::isViberWebHost($host)) {
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
        if ($s === '' || ! self::looksLikeHttpUrl($s) || ! self::isHttpUrlSafe($s)) {
            return null;
        }
        $path = (string) (parse_url($s, PHP_URL_PATH) ?? '');
        if ($path === '' || $path === '/') {
            return null;
        }
        $host = strtolower((string) (parse_url($s, PHP_URL_HOST) ?? ''));
        $ok = $host === 'wa.me' || str_ends_with($host, '.wa.me')
            || $host === 'api.whatsapp.com' || $host === 'www.whatsapp.com' || $host === 'web.whatsapp.com'
            || str_ends_with($host, '.whatsapp.com');
        if (! $ok) {
            return null;
        }

        return [
            'key' => 'whatsapp',
            'href' => $s,
            'label' => 'WhatsApp',
            'kind' => 'whatsapp',
            'external' => true,
        ];
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
