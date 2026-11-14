<?php

namespace Tests\Unit;

use App\Support\OptionGroupExclusivity;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OptionGroupExclusivityTest extends TestCase
{
    #[DataProvider('nameProvider')]
    public function test_key_from_name(?string $name, ?string $expected): void
    {
        $this->assertSame($expected, OptionGroupExclusivity::keyFromName($name));
    }

    public static function nameProvider(): array
    {
        return [
            'size family a' => ['Розмір: 1-1,5 см', 'розмір'],
            'size family b' => ['Розмір: 3-4 см', 'розмір'],
            'fullwidth colon' => ['Розмір：15 шт', 'розмір'],
            'plain size' => ['Розмір', null],
            'gift wrap' => ['Подарункова упаковка', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }
}
