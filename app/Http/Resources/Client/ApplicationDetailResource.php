<?php

namespace App\Http\Resources\Client;

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
        $jobUrl = $application->jobPosting?->applicationUrl();

        return [
            ...parent::toArray($request),
            'subject' => ClientSafeText::tokenizeJobUrl($application->subject, $jobUrl),
            'body' => ClientSafeText::tokenizeJobUrl($application->body, $jobUrl),
            'cvFileName' => $application->applicationProfile?->cv_original_name,
        ];
    }
}
