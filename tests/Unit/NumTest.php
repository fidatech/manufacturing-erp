<?php

namespace Tests\Unit;

use App\Support\Num;
use App\Support\Shamsi;
use PHPUnit\Framework\TestCase;

class NumTest extends TestCase
{
    public function test_money_is_rounded_half_up_without_floats(): void
    {
        $this->assertSame('0.30', Num::money(Num::add('0.1', '0.2')));
        $this->assertSame('1.01', Num::money('1.005'));
        $this->assertSame('-1.01', Num::money('-1.005'));
        $this->assertSame('2.35', Num::money(Num::div('7.05', '3')));
        $this->assertSame('0.00', Num::money(Num::div('5', '0')));
        $this->assertSame('1234.50', Num::money(' 1,234.5 '));
        $this->assertSame('0.00', Num::money('abc'));
    }

    public function test_numbers_are_shown_with_thousands_separators(): void
    {
        $this->assertSame('1,234,567.50', Num::format('1234567.5'));
        $this->assertSame('15', Num::format('15.0000', 4, true));
        $this->assertSame('0.025', Num::format('0.0250', 4, true));
        $this->assertSame('-975.00', Num::format('-975'));
        $this->assertSame('0.00', Num::format('-0.001'));
    }

    public function test_comparisons(): void
    {
        $this->assertTrue(Num::isZero('0.000'));
        $this->assertTrue(Num::isPositive('0.01'));
        $this->assertTrue(Num::isNegative('-0.01'));
        $this->assertSame('5.5', rtrim(Num::abs('-5.5'), '0'));
        $this->assertSame('6.00', Num::money(Num::sum(['1', '2', '3'])));
    }

    public function test_solar_hijri_dates_convert_both_ways(): void
    {
        $this->assertSame('1405/08/05', Shamsi::format('2026-10-27'));
        $this->assertSame('1405/01/01', Shamsi::format('2026-03-21'));
        $this->assertSame('2026-10-27', Shamsi::parse('1405/08/05'));
        $this->assertSame('2026-10-27', Shamsi::parse('1405-8-5'));
        $this->assertSame('2026-10-27', Shamsi::parse('۱۴۰۵/۰۸/۰۵'));
        $this->assertNull(Shamsi::parse('1405/07/31'));
        $this->assertNull(Shamsi::parse('1405/13/01'));
        $this->assertNull(Shamsi::parse('hello'));
    }
}
