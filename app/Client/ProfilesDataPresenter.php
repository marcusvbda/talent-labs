<?php

namespace App\Client;

use App\Enums\ApplicationLanguage;
use App\Http\Resources\Client\ApplicationProfileResource;
use App\Models\ApplicationProfile;
use App\Models\User;
use App\Outreach\Support\ApplicationTemplateRenderer;

final class ProfilesDataPresenter
{
    /**
     * Build the `ProfilesData` contract (resources/js/types/contracts.ts).
     *
     * @return array{profiles: list<ApplicationProfileResource>, variables: list<string>, unlockCounts: array{en: int, pt: int}}
     */
    public static function forUser(User $user): array
    {
        $byLanguage = $user->applicationProfiles()
            ->get()
            ->keyBy(fn (ApplicationProfile $profile): string => $profile->language->value);

        $profiles = [];

        foreach (ApplicationLanguage::cases() as $language) {
            $profile = $byLanguage->get($language->value);

            if ($profile instanceof ApplicationProfile) {
                $profiles[] = new ApplicationProfileResource($profile);
            }
        }

        return [
            'profiles' => $profiles,
            'variables' => ApplicationTemplateRenderer::ALLOWED_VARIABLES,
            'unlockCounts' => LanguageUnlockCounts::forUser($user),
        ];
    }
}
