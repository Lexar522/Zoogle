<?php

namespace Tests\Unit;

use App\Support\OptionLabel;
use PHPUnit\Framework\TestCase;

class OptionLabelTest extends TestCase
{
    public function test_group_name_with_trailing_colon_gets_single_colon(): void
    {
        $this->assertSame('Стать: Дівчинка', OptionLabel::join('Стать:', 'Дівчинка'));
        $this->assertSame('Вікова категорія: Малюк', OptionLabel::join('Вікова категорія: ', ' Малюк'));
        $this->assertSame('Колір: Чорний', OptionLabel::join('Колір', 'Чорний'));
    }

    public function test_empty_group_leaves_only_value(): void
    {
        $this->assertSame('Чорний', OptionLabel::join(' : ', 'Чорний'));
    }
}
