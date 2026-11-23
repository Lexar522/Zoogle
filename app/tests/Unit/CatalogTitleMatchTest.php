<?php

namespace Tests\Unit;

use App\Support\CatalogTitleMatch;
use PHPUnit\Framework\TestCase;

class CatalogTitleMatchTest extends TestCase
{
    public function test_close_spellings_match_the_product_title(): void
    {
        foreach (['перура', 'пірера', 'пирура', 'піррура', 'Перура'] as $query) {
            $this->assertTrue(CatalogTitleMatch::matches($query, 'Папуга піррура'), $query);
        }
    }

    public function test_unrelated_words_do_not_match(): void
    {
        $this->assertFalse(CatalogTitleMatch::matches('перура', 'Корм для котів'));
        $this->assertFalse(CatalogTitleMatch::matches('клітка', 'Кріль декоративний'));
    }

    public function test_prefix_still_matches_while_typing(): void
    {
        $this->assertTrue(CatalogTitleMatch::matches('клі', 'Клітка для папуги'));
        $this->assertSame(0, CatalogTitleMatch::score('клі', 'Клітка для папуги'));
    }

    public function test_relevance_puts_exact_and_leading_matches_first(): void
    {
        $query = 'клітка для кролика';
        $titles = [
            'Підставка під клітку для кролика',
            'Клітка для кроликів і шиншил велика',
            'Клітка для кролика',
            'Ковпачок для клітки кролика',
            'Клітка для кролика з дахом',
        ];

        usort($titles, fn (string $a, string $b): int => CatalogTitleMatch::relevance($query, $a) <=> CatalogTitleMatch::relevance($query, $b));

        $this->assertSame('Клітка для кролика', $titles[0]);
        $this->assertSame('Клітка для кролика з дахом', $titles[1]);
        $this->assertSame('Клітка для кроликів і шиншил велика', $titles[2]);
    }

    public function test_whole_words_beat_partial_words_and_typos(): void
    {
        $whole = CatalogTitleMatch::relevance('корм', 'Корм для гризунів');
        $insideTitle = CatalogTitleMatch::relevance('корм', 'Комбікорм для гризунів');
        $typo = CatalogTitleMatch::relevance('корм', 'Кірм для птахів');

        $this->assertLessThan($insideTitle, $whole);
        $this->assertNotNull($typo);
        $this->assertLessThan($typo, $whole);
        $this->assertNull(CatalogTitleMatch::relevance('корм', 'Клітка для папуги'));
    }

    public function test_other_word_endings_still_match(): void
    {
        $this->assertTrue(CatalogTitleMatch::matches('клітка для кролика', 'Клітка для кроликів'));
        $this->assertTrue(CatalogTitleMatch::matches('папуга', 'Клітка для папуги'));
        $this->assertTrue(CatalogTitleMatch::matches('миска', 'Керамічні миски'));
        $this->assertFalse(CatalogTitleMatch::matches('кролик', 'Корм для котів'));
    }

    public function test_any_apostrophe_matches_the_title(): void
    {
        $title = 'Клітка для хом’яків, щурів та дегу';

        foreach (["хом'яків", 'хомʼяків', 'хом’яків', 'хомяків', 'клітка для хомʼ'] as $query) {
            $this->assertTrue(CatalogTitleMatch::matches($query, $title), $query);
        }
        $this->assertLessThan(
            CatalogTitleMatch::relevance('клітка для хомʼяків', 'Купольна клітка для хом’ячків'),
            CatalogTitleMatch::relevance('клітка для хомʼяків', $title),
        );
    }
}
