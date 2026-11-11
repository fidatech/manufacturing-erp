<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserInitialsTest extends TestCase
{
    public function test_initials_use_the_first_two_words(): void
    {
        $this->assertSame('AK', (new User(['name' => 'ahmad karimi']))->initials());
        $this->assertSame('F', (new User(['name' => 'Fida']))->initials());
        $this->assertSame('FM', (new User(['name' => '  Fida   Muhammad Rahmani ']))->initials());
        $this->assertSame('?', (new User(['name' => '']))->initials());
    }
}
