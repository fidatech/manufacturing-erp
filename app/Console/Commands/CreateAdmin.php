<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an administrator account (asks for name, email and password)';

    public function handle(): int
    {
        $data = [
            'name' => trim((string) $this->ask('Full name')),
            'email' => strtolower(trim((string) $this->ask('Email address'))),
            'password' => (string) $this->secret('Password (at least 8 characters)'),
            'password_confirmation' => (string) $this->secret('Repeat the password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $this->info("Administrator {$data['email']} created. You can sign in now.");

        return self::SUCCESS;
    }
}
