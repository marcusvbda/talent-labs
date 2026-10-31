<?php

namespace App\Ai\Support;

use App\Ai\Agents\GenerateCoverLetter;
use App\Models\AiAgentResponseCache;
use App\Models\ApplicationProfile;
use App\Models\JobPosting;
use App\Models\User;

/**
 * Cache identity of a generated cover letter: one per user, posting, language and
 * manual letter + email body. Editing either text yields a new letter.
 */
final class CoverLetterFingerprint
{
    public static function for(User $user, JobPosting $posting, ApplicationProfile $profile): string
    {
        return implode('|', [
            GenerateCoverLetter::CACHE_SCHEMA_VERSION,
            $user->id,
            $posting->id,
            $profile->language->value,
            hash('sha256', $profile->cover_letter."\n".$profile->email_body),
        ]);
    }

    /**
     * The cached letter for the configured model, or null when there is none.
     */
    public static function cached(User $user, JobPosting $posting, ApplicationProfile $profile): ?string
    {
        $response = AiAgentResponseCache::lookup(
            GenerateCoverLetter::CACHE_KEY,
            (string) config('talent.ai.cover_letter.model'),
            self::for($user, $posting, $profile),
        );

        $letter = $response['cover_letter'] ?? null;

        return is_string($letter) && $letter !== '' ? $letter : null;
    }
}
