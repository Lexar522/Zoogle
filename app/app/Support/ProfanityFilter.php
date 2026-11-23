<?php

namespace App\Support;

/**
 * Пошук нецензурної лексики (укр., рос., англ.) у тексті від відвідувачів.
 * Латинські літери-двійники («xуй», «ebat») зводяться до кирилиці, тож прості обходи теж ловляться.
 * Корені підібрані так, щоб не чіпати звичайні слова: «педикюр», «мандарин», «сукня», «ребус».
 */
final class ProfanityFilter
{
    /** Латиниця й цифри, схожі на кириличні літери. */
    private const LOOKALIKES = [
        'a' => 'а', 'b' => 'б', 'c' => 'с', 'e' => 'е', 'h' => 'н', 'i' => 'і', 'k' => 'к', 'm' => 'м',
        'o' => 'о', 'p' => 'р', 'r' => 'г', 't' => 'т', 'u' => 'у', 'x' => 'х', 'y' => 'у',
        '0' => 'о', '3' => 'з', '@' => 'а', 'ё' => 'е', 'ї' => 'і', 'є' => 'е', 'ы' => 'и',
    ];

    /** Кириличні корені: десь у слові. */
    private const ANYWHERE = [
        'ху[йяеіию]',
        'пи[зс]д',
        'бля[дт]',
        'залуп',
        'гандон',
        'шлюх',
        'курв',
        'дроч',
        'мудак',
        'мудил',
        'п[іи]д[ао]р',
        'сучар',
        'сучк',
        'уеб',
        'уйоб',
    ];

    /** Кириличні корені: лише на початку слова (можливо, з префіксом). */
    private const WORD_START = [
        // Без однолітерних префіксів і без «иб»: інакше ловилися б «себе», «зебра», «наиболее»
        '(?:за|на|по|від|вид|ви|роз|рас|раз|до|пере|про|об|от|под|під)?(?:еб[аеиіуоялн]|йоб|іб[ау])',
        'бля(?:\P{L}|$)',
        'сук(?:а|и|у|ою|ам|ами|ах)?(?:\P{L}|$)',
        'педик(?:и|а|ам|ів|ов)?(?:\P{L}|$)',
        'манд(?:а|и|у|ою)(?:\P{L}|$)',
    ];

    /**
     * Англійські слова (перевіряються до заміни латиниці). «bitch» і просте «shit…» не беремо:
     * у зоомагазині це «сука» як термін і «shitzu» (ши-тцу).
     */
    private const ENGLISH = '/fuck|\\bshit(?:s|ty)?\\b|\\bcunt|\\bwhore|asshole|motherf/u';

    public static function contains(string $text): bool
    {
        $lower = mb_strtolower($text, 'UTF-8');
        if (preg_match(self::ENGLISH, $lower) === 1) {
            return true;
        }

        $normalized = self::normalize($lower);
        if (preg_match('/'.implode('|', self::ANYWHERE).'/u', $normalized) === 1) {
            return true;
        }

        return preg_match('/(?:^|\P{L})(?:'.implode('|', self::WORD_START).')/u', $normalized) === 1;
    }

    private static function normalize(string $lower): string
    {
        $value = strtr($lower, self::LOOKALIKES);
        // «х.у.й», «х-у-й»: прибираємо розділювачі між одиничними літерами
        $value = preg_replace('/(?<=\p{L})[.\-_*]+(?=\p{L})/u', '', $value) ?? $value;
        // «хууууй» → «хуй»
        $collapsed = preg_replace('/(\p{L})\1{2,}/u', '$1', $value);

        return is_string($collapsed) ? $collapsed : $value;
    }
}
