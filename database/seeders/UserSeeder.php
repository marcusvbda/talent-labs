<?php

namespace Database\Seeders;

use App\Enums\PlanKey;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\RegionResolver;
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

        $profiles = [
            'admin' => ['is_admin' => true, 'country' => 'BR', 'locale' => 'en', 'timezone' => 'America/Sao_Paulo', 'plan_key' => PlanKey::Free],
            'client' => ['is_admin' => false, 'country' => 'BR', 'locale' => 'pt', 'timezone' => 'America/Sao_Paulo', 'plan_key' => PlanKey::Starter],
        ];

        foreach ($profiles as $key => $profile) {
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
                'is_admin' => $profile['is_admin'],
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'country' => $profile['country'],
                'region' => RegionResolver::fromCountry($profile['country']),
                'locale' => $profile['locale'],
                'timezone' => $profile['timezone'],
                'plan_key' => $profile['plan_key'],
            ]);
        }
    }
}
