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
}
