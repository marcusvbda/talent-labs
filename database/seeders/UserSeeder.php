<?php

namespace Database\Seeders;

use App\Enums\ConnectedIntegrationStatus;
use App\Enums\PlanKey;
use App\Enums\UserStatus;
use App\Models\ApplicationProfile;
use App\Models\ConnectedIntegration;
use App\Models\JobPreference;
use App\Models\User;
use App\Support\RegionResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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

            $user = User::updateOrCreate(['email' => $email], [
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

            if ($key === 'client') {
                $this->seedClientState($user);
            }
        }
    }

    /**
     * Recreate the test client's signup state (application profiles with CVs, saved job
     * preferences, connected Gmail) from database/seeders/data/client, so a fresh database
     * needs no manual onboarding. Rows that already exist are left untouched.
     */
    private function seedClientState(User $user): void
    {
        /** @var array{profiles: array<string, array<string, mixed>>, preference: array<string, mixed>, gmail: array<string, mixed>} $state */
        $state = require database_path('seeders/data/client/state.php');

        foreach ($state['profiles'] as $language => $profile) {
            $this->seedProfile($user, $language, $profile);
        }

        $preference = JobPreference::forUser($user);

        if ($preference->saved_at === null) {
            $preference->forceFill([...$state['preference'], 'saved_at' => now()])->save();
        }

        $this->seedGmail($user, $state['gmail']);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function seedProfile(User $user, string $language, array $profile): void
    {
        if (ApplicationProfile::query()->whereBelongsTo($user)->where('language', $language)->exists()) {
            return;
        }

        $cv = file_get_contents(database_path("seeders/data/client/cv/{$language}.pdf"));
        $path = "cvs/{$user->id}/{$language}/seed.pdf";

        if ($cv === false || ! Storage::disk('local')->put($path, $cv)) {
            $this->command->warn("Could not store the seed CV for [{$language}]; the profile was not seeded.");

            return;
        }

        ApplicationProfile::create([
            ...$profile,
            'user_id' => $user->id,
            'language' => $language,
            'cv_path' => $path,
            'cv_size_bytes' => strlen($cv),
            'cv_uploaded_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $gmail
     */
    private function seedGmail(User $user, array $gmail): void
    {
        $refreshToken = config('talent.seed.client.gmail_refresh_token');

        if (empty($refreshToken)) {
            $this->command->warn('Skipping the seeded Gmail connection: SEED_CLIENT_GMAIL_REFRESH_TOKEN is not set.');

            return;
        }

        // No access token: the first use refreshes it from the refresh token.
        ConnectedIntegration::query()->firstOrCreate(
            ['user_id' => $user->id, 'plugin_key' => 'gmail'],
            [
                ...$gmail,
                'status' => ConnectedIntegrationStatus::Connected,
                'refresh_token' => $refreshToken,
                'connected_at' => now(),
            ],
        );
    }
}
