<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Вивід описів товару: legacy plain text або HTML з Filament RichEditor (Tiptap).
 */
final class RichTextSanitizer
{
    private static ?HtmlSanitizer $instance = null;

    private static function instance(): HtmlSanitizer
    {
        return self::$instance ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->withMaxInputLength(-1)
                ->allowRelativeLinks(true)
        );
    }

    /**
     * Чи це текст без HTML-тегів (старі поля, «5 &lt; 10», «&lt;3» тощо).
     */
    public static function isPlainText(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (! str_contains($value, '<')) {
            return true;
        }

        return ! preg_match('/<\/?[a-z][a-z0-9]*\b/i', $value);
    }

    /**
     * Безпечний HTML для Blade ({!! ... !!}): plain → nl2br + e(), інакше Symfony HtmlSanitizer.
     */
    public static function toHtml(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if (trim($value) === '') {
            return '';
        }

        if (self::isPlainText($value)) {
            return nl2br(e($value), false);
        }

        return self::instance()->sanitize($value);
    }

    /**
     * Чи є видимий текст після зняття HTML (порожні &lt;p&gt;&lt;br&gt; не рахуються).
     */
    public static function hasVisibleContent(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $plain = trim(preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ));

        return $plain !== '';
    }

    /**
     * Рядок для БД після зневоднення: лише trim (без strip_tags — інакше губиться валідний HTML з редактора).
     * Масив Tiptap обробляйте через RichContentRenderer у шарі Filament.
     *
     * @param  mixed  $value  HTML-рядок з RichEditor
     */
    public static function normalizeNullableStoredHtml(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Filament RichEditor (ueberdosis/tiptap-php): будь-який рядок, який успішно json_decode
     * як JSON-скаляр або не-doc масив, потрапляє в гілку JSON і ламає Schema::apply (offset on int).
     * Допустимо лише HTML/plain або рядок із JSON-документом Tiptap (type=doc).
     */
    public static function coerceForFilamentRichEditor(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $value;
        }

        if (is_array($decoded) && (($decoded['type'] ?? '') === 'doc')) {
            return $trimmed;
        }

        return '<p>'.htmlspecialchars($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
    }
}
