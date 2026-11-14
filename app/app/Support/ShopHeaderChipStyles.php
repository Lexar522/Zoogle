<?php

namespace App\Support;

/**
 * Стилі чіпів шапки каталогу (JSON chip_styles → CSS vars).
 *
 * Порожньо / вимкнена палітра без overrides = поточний вигляд вітрини.
 */
final class ShopHeaderChipStyles
{
    public const DEFAULT_PALETTE = [
        1 => ['accent' => '#3b82f2', 'tint' => 'rgba(59, 130, 242, 0.12)'],
        2 => ['accent' => '#ed3626', 'tint' => 'rgba(237, 54, 38, 0.12)'],
        3 => ['accent' => '#fcb501', 'tint' => 'rgba(252, 181, 1, 0.18)'],
        4 => ['accent' => '#397dea', 'tint' => 'rgba(57, 125, 234, 0.12)'],
        5 => ['accent' => '#33983b', 'tint' => 'rgba(51, 152, 59, 0.12)'],
        6 => ['accent' => '#ee3c2b', 'tint' => 'rgba(238, 60, 43, 0.12)'],
    ];

    /**
     * Стан форми: порожні рівні = без CSS-override (старий вигляд вітрини).
     * Палітра показує поточні 6 кольорів; зберігається лише якщо увімкнено.
     *
     * @return array<string, mixed>
     */
    public static function formDefaults(): array
    {
        return [
            'custom_palette_enabled' => false,
            'center' => self::emptyLevel(),
            'side_parent' => self::emptyLevel(),
            'side_sub' => array_merge(self::emptyLevel(), self::emptyColors(), [
                'use_palette' => false,
            ]),
            'side_nested' => array_merge(self::emptyLevel(), self::emptyColors(), [
                'use_palette' => false,
            ]),
            'palette' => self::paletteFormDefaults(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyLevel(): array
    {
        return [
            'font_size_px' => null,
            'font_weight' => null,
            'font_style' => 'normal',
            'padding_x_px' => null,
            'padding_y_px' => null,
            'radius_px' => null,
            'gap_px' => null,
        ];
    }

    /**
     * @return array<string, null>
     */
    private static function emptyColors(): array
    {
        return [
            'bg' => null,
            'text' => null,
            'border' => null,
            'hover_bg' => null,
            'hover_text' => null,
            'active_bg' => null,
            'active_text' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function paletteFormDefaults(): array
    {
        $out = [];
        foreach (self::DEFAULT_PALETTE as $i => $slot) {
            $out[$i] = [
                'accent' => $slot['accent'],
                'tint' => $slot['tint'],
                'text' => $slot['accent'],
                'hover_bg' => $slot['accent'],
                'hover_text' => '#ffffff',
                'active_bg' => $slot['accent'],
                'active_text' => '#ffffff',
            ];
        }

        return $out;
    }

    /**
     * Злити збережене з дефолтами форми.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function mergeForForm(?array $stored): array
    {
        $defaults = self::formDefaults();
        if ($stored === null || $stored === []) {
            return $defaults;
        }

        return self::deepMerge($defaults, $stored);
    }

    /**
     * Санітизація перед збереженням. Повертає null якщо нічого кастомного.
     *
     * @param  array<string, mixed>|null  $input
     * @return array<string, mixed>|null
     */
    public static function sanitizeForStorage(?array $input): ?array
    {
        if ($input === null || $input === []) {
            return null;
        }

        $out = [];
        $paletteEnabled = (bool) ($input['custom_palette_enabled'] ?? false);
        if ($paletteEnabled) {
            $out['custom_palette_enabled'] = true;
        }

        foreach (['center', 'side_parent', 'side_sub', 'side_nested'] as $level) {
            $levelData = is_array($input[$level] ?? null) ? $input[$level] : [];
            $withColors = in_array($level, ['side_sub', 'side_nested'], true);
            $clean = self::sanitizeLevel($levelData, $withColors);
            if ($clean !== []) {
                $out[$level] = $clean;
            }
        }

        if ($paletteEnabled) {
            $palette = is_array($input['palette'] ?? null) ? $input['palette'] : [];
            $cleanPalette = self::sanitizePalette($palette);
            if ($cleanPalette !== []) {
                $out['palette'] = $cleanPalette;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * CSS custom properties для інлайну на `.catalog-filters--in-header`.
     * Порожній рядок = без overrides (старий вигляд).
     *
     * @param  array<string, mixed>|null  $stored
     */
    public static function toInlineCss(?array $stored): string
    {
        $vars = self::toCssVars($stored);
        if ($vars === []) {
            return '';
        }

        $parts = [];
        foreach ($vars as $name => $value) {
            $parts[] = $name.': '.$value;
        }

        return implode('; ', $parts).';';
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, string>
     */
    public static function toCssVars(?array $stored): array
    {
        if ($stored === null || $stored === []) {
            return [];
        }

        $vars = [];

        foreach ([
            'center' => 'center',
            'side_parent' => 'side-parent',
            'side_sub' => 'side-sub',
            'side_nested' => 'side-nested',
        ] as $key => $cssKey) {
            $level = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            self::appendLevelCssVars($vars, $cssKey, $level);
            if (in_array($key, ['side_sub', 'side_nested'], true)) {
                self::appendLevelColorCssVars($vars, $cssKey, $level);
            }
        }

        if (! empty($stored['side_sub']['use_palette'])) {
            $vars['--chip-side-sub-use-palette'] = '1';
        }

        if (! empty($stored['side_nested']['use_palette'])) {
            $vars['--chip-side-nested-use-palette'] = '1';
        }

        if (! empty($stored['custom_palette_enabled'])) {
            $palette = is_array($stored['palette'] ?? null) ? $stored['palette'] : [];
            self::appendPaletteCssVars($vars, $palette);
        }

        return $vars;
    }

    /**
     * @param  array<string, string>  $vars
     * @param  array<string, mixed>  $level
     */
    private static function appendLevelCssVars(array &$vars, string $cssKey, array $level): void
    {
        $fontSize = self::nullableInt($level['font_size_px'] ?? null, 10, 32);
        if ($fontSize !== null) {
            $vars["--chip-{$cssKey}-font-size"] = $fontSize.'px';
        }

        $weight = self::nullableInt($level['font_weight'] ?? null, 400, 900);
        if ($weight !== null) {
            $vars["--chip-{$cssKey}-font-weight"] = (string) $weight;
        }

        $style = self::nullableFontStyle($level['font_style'] ?? null);
        if ($style !== null) {
            $vars["--chip-{$cssKey}-font-style"] = $style;
        }

        $padX = self::nullableInt($level['padding_x_px'] ?? null, 0, 32);
        if ($padX !== null) {
            $vars["--chip-{$cssKey}-padding-x"] = $padX.'px';
        }

        $padY = self::nullableInt($level['padding_y_px'] ?? null, 0, 32);
        if ($padY !== null) {
            $vars["--chip-{$cssKey}-padding-y"] = $padY.'px';
        }

        $radius = self::nullableInt($level['radius_px'] ?? null, 0, 40);
        if ($radius !== null) {
            $vars["--chip-{$cssKey}-radius"] = $radius.'px';
        }

        $gap = self::nullableInt($level['gap_px'] ?? null, 0, 24);
        if ($gap !== null) {
            $vars["--chip-{$cssKey}-gap"] = $gap.'px';
        }
    }

    /**
     * @param  array<string, string>  $vars
     * @param  array<string, mixed>  $level
     */
    private static function appendLevelColorCssVars(array &$vars, string $cssKey, array $level): void
    {
        foreach (['bg', 'text', 'border', 'hover_bg', 'hover_text', 'active_bg', 'active_text'] as $key) {
            $color = self::nullableColor($level[$key] ?? null);
            if ($color === null) {
                continue;
            }

            $vars['--chip-'.$cssKey.'-'.str_replace('_', '-', $key)] = $color;
        }
    }

    /**
     * @param  array<string, string>  $vars
     * @param  array<int|string, mixed>  $palette
     */
    private static function appendPaletteCssVars(array &$vars, array $palette): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $slot = is_array($palette[$i] ?? $palette[(string) $i] ?? null)
                ? ($palette[$i] ?? $palette[(string) $i])
                : [];
            $defaults = self::DEFAULT_PALETTE[$i];

            $accent = self::nullableColor($slot['accent'] ?? null) ?? $defaults['accent'];
            $tint = self::nullableColor($slot['tint'] ?? null) ?? $defaults['tint'];
            $text = self::nullableColor($slot['text'] ?? null) ?? $accent;
            $hoverBg = self::nullableColor($slot['hover_bg'] ?? null) ?? $accent;
            $hoverText = self::nullableColor($slot['hover_text'] ?? null) ?? '#ffffff';
            $activeBg = self::nullableColor($slot['active_bg'] ?? null) ?? $accent;
            $activeText = self::nullableColor($slot['active_text'] ?? null) ?? '#ffffff';

            $vars["--hub-chip-color-{$i}"] = $accent;
            $vars["--hub-chip-bg-{$i}"] = $tint;
            $vars["--hub-chip-text-{$i}"] = $text;
            $vars["--hub-chip-hover-bg-{$i}"] = $hoverBg;
            $vars["--hub-chip-hover-text-{$i}"] = $hoverText;
            $vars["--hub-chip-active-bg-{$i}"] = $activeBg;
            $vars["--hub-chip-active-text-{$i}"] = $activeText;
        }
    }

    /**
     * @param  array<string, mixed>  $level
     * @return array<string, mixed>
     */
    private static function sanitizeLevel(array $level, bool $withColorsAndPalette): array
    {
        $out = [];

        $fontSize = self::nullableInt($level['font_size_px'] ?? null, 10, 32);
        if ($fontSize !== null) {
            $out['font_size_px'] = $fontSize;
        }

        $weight = self::nullableInt($level['font_weight'] ?? null, 400, 900);
        if ($weight !== null) {
            $out['font_weight'] = $weight;
        }

        $style = self::nullableFontStyle($level['font_style'] ?? null);
        if ($style !== null && $style !== 'normal') {
            $out['font_style'] = $style;
        }

        $padX = self::nullableInt($level['padding_x_px'] ?? null, 0, 32);
        if ($padX !== null) {
            $out['padding_x_px'] = $padX;
        }

        $padY = self::nullableInt($level['padding_y_px'] ?? null, 0, 32);
        if ($padY !== null) {
            $out['padding_y_px'] = $padY;
        }

        $radius = self::nullableInt($level['radius_px'] ?? null, 0, 40);
        if ($radius !== null) {
            $out['radius_px'] = $radius;
        }

        $gap = self::nullableInt($level['gap_px'] ?? null, 0, 24);
        if ($gap !== null) {
            $out['gap_px'] = $gap;
        }

        if ($withColorsAndPalette) {
            foreach (['bg', 'text', 'border', 'hover_bg', 'hover_text', 'active_bg', 'active_text'] as $key) {
                $color = self::nullableColor($level[$key] ?? null);
                if ($color !== null) {
                    $out[$key] = $color;
                }
            }

            if (! empty($level['use_palette'])) {
                $out['use_palette'] = true;
            }
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $palette
     * @return array<int, array<string, string>>
     */
    private static function sanitizePalette(array $palette): array
    {
        $out = [];
        for ($i = 1; $i <= 6; $i++) {
            $slot = is_array($palette[$i] ?? $palette[(string) $i] ?? null)
                ? ($palette[$i] ?? $palette[(string) $i])
                : [];
            $clean = [];
            foreach (['accent', 'tint', 'text', 'hover_bg', 'hover_text', 'active_bg', 'active_text'] as $key) {
                $color = self::nullableColor($slot[$key] ?? null);
                if ($color !== null) {
                    $clean[$key] = $color;
                }
            }
            if ($clean !== []) {
                $out[$i] = $clean;
            }
        }

        return $out;
    }

    private static function nullableInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $n = (int) $value;

        return min($max, max($min, $n));
    }

    private static function nullableFontStyle(mixed $value): ?string
    {
        $v = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($v, ['normal', 'italic'], true) ? $v : null;
    }

    private static function nullableColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $v = trim($value);
        if ($v === '') {
            return null;
        }

        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v) === 1) {
            return strtolower($v);
        }

        if (preg_match('/^([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v) === 1) {
            return '#'.strtolower($v);
        }

        if (preg_match('/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*(,\s*[\d.]+\s*)?\)$/i', $v) === 1) {
            return preg_replace('/\s+/', ' ', $v) ?? $v;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private static function deepMerge(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                // Palette keys are numeric 1..6
                if ($key === 'palette') {
                    $merged = $base[$key];
                    foreach ($value as $pi => $slot) {
                        $piKey = is_numeric($pi) ? (int) $pi : $pi;
                        if (is_array($slot) && isset($merged[$piKey]) && is_array($merged[$piKey])) {
                            $merged[$piKey] = array_merge($merged[$piKey], $slot);
                        } elseif (is_array($slot)) {
                            $merged[$piKey] = $slot;
                        }
                    }
                    $base[$key] = $merged;
                } else {
                    $base[$key] = self::deepMerge($base[$key], $value);
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
