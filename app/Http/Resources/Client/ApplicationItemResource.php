<?php

namespace App\Http\Resources\Client;

use App\Models\Application;
use App\Outreach\Support\ClientErrorMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `ApplicationItem` contract. Expects `company`, `jobPosting.profile` and `user` loaded.
 * Never exposes the recipient, contact data, provider ids or the raw `last_error`.
 *
 * @property Application $resource
 */
class ApplicationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $application = $this->resource;
        $company = $application->company;

        return [
            'id' => $application->id,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'initials' => self::initials($company->name),
            ],
            'title' => $application->jobPosting?->title,
            'language' => $this->language(),
            'origin' => $application->origin->value,
            'status' => $application->status->value,
            'stage' => $application->stage?->value,
            'subStep' => $application->sub_step?->value,
            'lastError' => ClientErrorMessage::for($application->last_error),
            'queuedAt' => $application->queued_at?->toIso8601String(),
            'scheduledFor' => $application->scheduled_for?->toIso8601String(),
            'sentAt' => $application->sent_at?->toIso8601String(),
        ];
    }

    /**
     * Temporary fallback: spec 5 fills `applications.language` on send, after which the
     * stored value is always present and this chain can go.
     */
    private function language(): string
    {
        $application = $this->resource;

        if ($application->language !== null && $application->language !== '') {
            return $application->language;
        }

        $profileLanguage = $application->jobPosting?->profile?->language;

        if (in_array($profileLanguage, ['en', 'pt'], true)) {
            return $profileLanguage;
        }

        $locale = $application->user->locale;

        return in_array($locale, ['en', 'pt'], true) ? $locale : 'en';
    }

    private static function initials(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim($name)) ?: [],
            fn (string $word): bool => preg_match('/[\p{L}\p{N}]/u', $word) === 1,
        ));

        if ($words === []) {
            return '';
        }

        $first = self::firstCharacter($words[0]);
        $last = count($words) > 1 ? self::firstCharacter($words[count($words) - 1]) : '';

        return mb_strtoupper($first.$last);
    }

    private static function firstCharacter(string $word): string
    {
        return preg_match('/[\p{L}\p{N}]/u', $word, $match) === 1 ? $match[0] : '';
    }
}
