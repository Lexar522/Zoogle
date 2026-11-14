<?php

namespace App\Support;

use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\Product;

/**
 * Пошук за назвою з допуском на описки: «перура», «пірера», «пирура» знаходять «піррура».
 */
final class CatalogTitleMatch
{
    /**
     * @return list<int>
     */
    public static function matchingIds(string $table, string $search): array
    {
        $search = trim($search);
        if ($search === '' || ! SchemaCache::hasTable($table)) {
            return [];
        }

        $rows = self::titlesForTable($table);
        $ids = [];
        foreach ($rows as $id => $title) {
            if (self::matches($search, $title)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public static function matches(string $query, string $title): bool
    {
        return self::score($query, $title) !== null;
    }

    /**
     * Окремі слова з текстів, які продовжують останнє набране. «клі» → «клітка».
     *
     * @param  list<string>  $texts
     * @return list<string>
     */
    public static function suggestWords(string $query, array $texts, int $limit = 4): array
    {
        $queryWords = self::words($query);
        if ($queryWords === [] || $limit < 1) {
            return [];
        }

        $prefix = $queryWords[array_key_last($queryWords)];
        if (mb_strlen($prefix) < 2) {
            return [];
        }

        $counts = [];
        $labels = [];
        foreach ($texts as $text) {
            if (! is_string($text) || trim($text) === '') {
                continue;
            }
            $rawWords = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            if (! is_array($rawWords)) {
                continue;
            }
            foreach ($rawWords as $rawWord) {
                $foldedWords = self::words($rawWord);
                $folded = $foldedWords[0] ?? '';
                if ($folded === '' || $folded === $prefix || isset($counts[$folded])) {
                    if ($folded !== '' && $folded !== $prefix && isset($counts[$folded])) {
                        $counts[$folded]++;
                    }
                    continue;
                }
                $isPrefix = str_starts_with($folded, $prefix);
                if (! $isPrefix && self::wordScore($prefix, $folded) === null) {
                    continue;
                }
                $counts[$folded] = 1;
                $labels[$folded] = mb_strtolower($rawWord, 'UTF-8');
            }
        }

        uksort($counts, function (string $left, string $right) use ($counts, $labels): int {
            $byCount = $counts[$right] <=> $counts[$left];
            if ($byCount !== 0) {
                return $byCount;
            }
            $byLength = mb_strlen($labels[$left]) <=> mb_strlen($labels[$right]);

            return $byLength !== 0 ? $byLength : strcmp($labels[$left], $labels[$right]);
        });

        $words = [];
        foreach (array_keys($counts) as $folded) {
            $words[] = $labels[$folded];
            if (count($words) >= $limit) {
                break;
            }
        }

        return $words;
    }

    /**
     * Менше — ближче. null, якщо назва не підходить.
     */
    public static function score(string $query, string $title): ?int
    {
        $queryWords = self::words($query);
        $titleWords = self::words($title);
        if ($queryWords === [] || $titleWords === []) {
            return null;
        }

        $total = 0;
        foreach ($queryWords as $queryWord) {
            $best = null;
            foreach ($titleWords as $titleWord) {
                $wordScore = self::wordScore($queryWord, $titleWord);
                if ($wordScore !== null && ($best === null || $wordScore < $best)) {
                    $best = $wordScore;
                }
            }
            if ($best === null) {
                return null;
            }
            $total += $best;
        }

        return $total;
    }

    /**
     * @return array<int, string>
     */
    private static function titlesForTable(string $table): array
    {
        if ($table === (new Product)->getTable()) {
            $rows = Product::query()
                ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
                ->where('is_available', true)
                ->get(['id', 'title']);
        } elseif ($table === (new Bundle)->getTable()) {
            $rows = Bundle::query()
                ->where('is_visible', true)
                ->where('is_active', true)
                ->get(['id', 'title']);
        } else {
            return [];
        }

        $titles = [];
        foreach ($rows as $row) {
            $title = trim(mt((string) $row->title));
            if ($title !== '') {
                $titles[(int) $row->id] = $title;
            }
        }

        return $titles;
    }

    /**
     * @return list<string>
     */
    private static function words(string $value): array
    {
        $folded = self::fold($value);
        if ($folded === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}]+/u', $folded, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? array_values($parts) : [];
    }

    private static function wordScore(string $queryWord, string $titleWord): ?int
    {
        if ($queryWord === $titleWord || str_starts_with($titleWord, $queryWord)) {
            return 0;
        }

        $queryLength = mb_strlen($queryWord);
        $titleLength = mb_strlen($titleWord);
        if ($queryLength < 4) {
            return null;
        }

        $limit = $queryLength >= 8 ? 2 : 1;
        if (abs($queryLength - $titleLength) > $limit) {
            return null;
        }

        $distance = self::levenshtein($queryWord, $titleWord);

        return $distance <= $limit ? $distance : null;
    }

    private static function fold(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'і' => 'и',
            'ї' => 'и',
            'ы' => 'и',
            'й' => 'и',
            'є' => 'е',
            'э' => 'е',
            'ё' => 'е',
            'ґ' => 'г',
            'ъ' => '',
            'ь' => '',
            "'" => '',
            '’' => '',
            '`' => '',
        ]);
        $collapsed = preg_replace('/(\p{L})\1+/u', '$1', $value);

        return is_string($collapsed) ? $collapsed : $value;
    }

    private static function levenshtein(string $left, string $right): int
    {
        $a = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($a);
        $m = count($b);
        if ($n === 0) {
            return $m;
        }
        if ($m === 0) {
            return $n;
        }

        $prev = range(0, $m);
        for ($i = 1; $i <= $n; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $m; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $cur[$j] = min(
                    $prev[$j] + 1,
                    $cur[$j - 1] + 1,
                    $prev[$j - 1] + $cost,
                );
            }
            $prev = $cur;
        }

        return $prev[$m];
    }
}
