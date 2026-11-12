<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * The main administrator. Made only when that email does not exist yet,
 * so running the seeder again never resets a password that was changed.
 *
 * CHANGE THIS PASSWORD after the first sign-in: it is written here in plain text.
 */
class UserSeeder extends Seeder
{
    public const EMAIL = 'admin@gmail.com';

    public const PASSWORD = 'admin@123';

    public function run(): void
    {
        Permissions::sync();

        $user = User::firstOrCreate(['email' => self::EMAIL], [
            'name' => 'Administrator',
            'password' => self::PASSWORD,
            'is_active' => true,
        ]);

        if (! $user->hasRole(User::ADMIN_ROLE)) {
            $user->assignRole(User::ADMIN_ROLE);
        }
    }
}
