<?php

namespace Tests\Unit;

use App\Support\ProfanityFilter;
use PHPUnit\Framework\TestCase;

class ProfanityFilterTest extends TestCase
{
    public function test_finds_obscene_words_and_simple_disguises(): void
    {
        foreach ([
            'Та це хуйня', 'ХУЙНЯ повна', 'пиздець', 'бляха муха, бля', 'блять', 'якийсь їбанутий продавець',
            'заебали', 'йобаний товар', 'сука', 'мудак', 'підар', 'xуйня', 'х.у.й', 'хууууй', 'fucking shit', 'ebat',
        ] as $text) {
            $this->assertTrue(ProfanityFilter::contains($text), $text);
        }
    }

    public function test_ordinary_words_are_not_flagged(): void
    {
        foreach ([
            'Купила для себе і сестри', 'Зебра намальована на клітці', 'Наиболее удобная поилка', 'Педикюр для кролика',
            'Мандарини хом\'як не їсть', 'Нова сукня', 'Небо, ребус, хліб', 'Худий хом\'ячок, хутро гарне', 'Хуліган кіт',
            'Shitzu dog bed', 'Бляха на ошийник', 'Вибачте за затримку', 'Ибо', 'Суєта', 'Іграшка для собаки', 'мандат',
            'Пропонуємо бебі-корм', 'Хуан купив', 'shih tzu',
        ] as $text) {
            $this->assertFalse(ProfanityFilter::contains($text), $text);
        }
    }
}
