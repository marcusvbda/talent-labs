<?php

namespace App\Http\Resources\Client;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Outreach\Support\ClientSafeText;
use Illuminate\Http\Request;

/**
 * The `ApplicationDetail` contract: the item fields plus the message as sent. The job link
 * is replaced by a `{{ job_url }}` token and any other link or email is redacted.
 * Expects `company`, `jobPosting.profile` and `applicationProfile` loaded.
 *
 * @property Application $resource
 */
class ApplicationDetailResource extends ApplicationItemResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $application = $this->resource;
        $jobUrl = $application->jobPosting?->url;

        return [
            ...parent::toArray($request),
            'subject' => ClientSafeText::tokenizeJobUrl($application->subject, $jobUrl),
            'body' => ClientSafeText::tokenizeJobUrl($application->body, $jobUrl),
            'cvFileName' => $application->applicationProfile?->cv_original_name,
            'timeline' => $this->timeline(),
        ];
    }

    /**
     * Derived only from what is recorded; no step is ever invented.
     *
     * @return list<array{stage: string, at: string|null}>
     */
    private function timeline(): array
    {
        $application = $this->resource;

        if ($application->sent_at !== null) {
            return [['stage' => 'sent', 'at' => $application->sent_at->toIso8601String()]];
        }

        if ($application->status === ApplicationStatus::Failed) {
            return [['stage' => 'failed', 'at' => $application->updated_at?->toIso8601String()]];
        }

        return [];
    }
}
