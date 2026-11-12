<?php

namespace Tests\Unit;

use App\Support\Chart;
use PHPUnit\Framework\TestCase;

class ChartTest extends TestCase
{
    public function test_the_axis_gets_a_round_top(): void
    {
        $this->assertSame(['max' => '10000.00', 'ticks' => ['10K', '5K', '0']], Chart::scale(['8340.00', '120.50', null]));
        $this->assertSame(['max' => '400.00', 'ticks' => ['400', '200', '0']], Chart::scale(['310']));
        $this->assertSame(['max' => '1', 'ticks' => ['', '', '0']], Chart::scale(['0.00', '0']));
    }

    public function test_a_bar_is_sized_as_a_share_of_the_top(): void
    {
        $this->assertSame('42.5', Chart::percent('425', '1000'));
        $this->assertSame('0', Chart::percent('-5', '1000'));
        $this->assertSame('0', Chart::percent('5', '0'));
        $this->assertSame('100', Chart::percent('1200', '1000'));
    }

    public function test_big_numbers_are_shortened(): void
    {
        $this->assertSame('750', Chart::compact(750));
        $this->assertSame('12.5K', Chart::compact(12500));
        $this->assertSame('3M', Chart::compact(3000000));
    }
}
