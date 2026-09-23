<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the admin and client users from config (idempotent).
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('Refusing to seed users in production.');

            return;
        }

        foreach (['admin' => true, 'client' => false] as $key => $isAdmin) {
            $name = config("talent.seed.{$key}.name");
            $email = config("talent.seed.{$key}.email");
            $password = config("talent.seed.{$key}.password");

            if (empty($email) || empty($password)) {
                $upperKey = strtoupper($key);
                $this->command->warn("Skipping {$key} seed user: SEED_{$upperKey}_EMAIL or SEED_{$upperKey}_PASSWORD is not set.");

                continue;
            }

            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make($password),
                'is_admin' => $isAdmin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]);
        }
    }
}
