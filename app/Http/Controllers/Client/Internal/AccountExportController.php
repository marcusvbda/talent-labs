<?php

namespace App\Http\Controllers\Client\Internal;

use App\Enums\ApplicationLanguage;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationProfile;
use App\Models\User;
use App\Outreach\Support\ClientSafeText;
use App\Support\RegionResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

/**
 * The client's own data as a JSON download. Recipient addresses, contacts, provider
 * message ids and internal errors are never part of it.
 */
class AccountExportController extends Controller
{
    public function __invoke(Request $request): StreamedJsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $preference = $user->jobPreference;

        $profilesByLanguage = $user->applicationProfiles()
            ->get()
            ->keyBy(fn (ApplicationProfile $profile): string => $profile->language->value);

        $applicationProfiles = [];

        foreach (ApplicationLanguage::cases() as $language) {
            $profile = $profilesByLanguage->get($language->value);

            if (! $profile instanceof ApplicationProfile) {
                continue;
            }

            $applicationProfiles[] = [
                'language' => $profile->language->value,
                'active' => $profile->is_active,
                'cvOriginalName' => $profile->cv_original_name,
                'cvUploadedAt' => $profile->cv_uploaded_at?->toIso8601String(),
                'emailSubject' => $profile->email_subject,
                'emailBody' => $profile->email_body,
                'coverLetter' => $profile->cover_letter ?? '',
                'links' => $profile->links ?? [],
            ];
        }

        $applications = $user->applications()
            ->with(['company:id,name', 'jobPosting:id,title,url'])
            ->orderBy('id')
            ->lazy()
            ->map(function (Application $application): array {
                $jobUrl = $application->jobPosting?->url;

                return [
                    'company' => $application->company->name,
                    'title' => $application->jobPosting?->title,
                    'language' => $application->language,
                    'origin' => $application->origin->value,
                    'status' => $application->status->value,
                    'subject' => ClientSafeText::tokenizeJobUrl($application->subject, $jobUrl),
                    'body' => ClientSafeText::tokenizeJobUrl($application->body, $jobUrl),
                    'queuedAt' => $application->queued_at?->toIso8601String(),
                    'scheduledFor' => $application->scheduled_for?->toIso8601String(),
                    'sentAt' => $application->sent_at?->toIso8601String(),
                ];
            })
            ->getIterator();

        $filename = sprintf('talent-labs-export-%d-%s.json', $user->id, now()->format('Y-m-d'));

        return response()->streamJson([
            'exportedAt' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'country' => $user->country,
                'region' => ($user->region ?? RegionResolver::fromCountry($user->country))->value,
                'planKey' => $user->plan_key->value,
                'createdAt' => $user->created_at?->toIso8601String(),
            ],
            'preferences' => $preference === null ? null : [
                'titles' => $preference->titles,
                'seniorities' => $preference->seniorities,
                'stack' => $preference->stack,
                'locations' => $preference->locations,
                'remoteMode' => $preference->remote_mode->value,
                'excludeWords' => $preference->exclude_words,
                'savedAt' => $preference->saved_at?->toIso8601String(),
            ],
            'applicationProfiles' => $applicationProfiles,
            'applications' => $applications,
        ], headers: [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
