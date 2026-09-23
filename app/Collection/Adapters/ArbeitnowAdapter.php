<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;
use Carbon\CarbonImmutable;

class ArbeitnowAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    public function fetch(Source $source): iterable
    {
        $response = $this->http()->get('https://www.arbeitnow.com/api/job-board-api');

        foreach ($this->items($response->json('data')) as $item) {
            $externalId = $this->stringOrNull($item['slug'] ?? null);
            $title = $this->stringOrNull($item['title'] ?? null);
            $companyName = $this->stringOrNull($item['company_name'] ?? null);
            $url = $this->stringOrNull($item['url'] ?? null);

            if ($externalId === null || $title === null || $companyName === null || $url === null) {
                continue;
            }

            $html = $this->stringOrNull($item['description'] ?? null);
            $jobTypes = $item['job_types'] ?? null;

            yield new JobPostingData(
                externalId: $externalId,
                title: $title,
                companyName: $companyName,
                location: $this->stringOrNull($item['location'] ?? null),
                isRemote: $this->boolOrNull($item['remote'] ?? null),
                department: null,
                employmentType: is_array($jobTypes) ? $this->stringOrNull($jobTypes[0] ?? null) : null,
                url: $url,
                applyUrl: $url,
                descriptionHtml: $html,
                descriptionText: $this->htmlToText($html),
                publishedAt: $this->timestampOrNull($item['created_at'] ?? null),
                raw: $item,
            );
        }
    }

    /**
     * Arbeitnow sends `created_at` as Unix seconds.
     */
    private function timestampOrNull(mixed $value): ?CarbonImmutable
    {
        if (! is_int($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $value);
    }
}
