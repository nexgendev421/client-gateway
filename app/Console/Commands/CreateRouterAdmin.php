<?php

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateRouterAdmin extends Command
{
    protected $signature = 'router:admin:create';

    protected $description = 'Create an active super administrator account';

    public function handle(): int
    {
        $name = (string) $this->ask('Administrator name');
        $email = (string) $this->ask('Administrator email');
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)],
            ],
            ['password.confirmed' => 'The password confirmation does not match.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => AdminRole::SuperAdmin,
            'status' => AdminStatus::Active,
        ]);

        $this->info('Super administrator account created.');

        return self::SUCCESS;
    }
}
