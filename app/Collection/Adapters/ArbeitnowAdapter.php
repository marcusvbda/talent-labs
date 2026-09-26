<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;

class ArbeitnowAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    private const URL = 'https://www.arbeitnow.com/api/job-board-api';

    private const MAX_PAGES = 5;

    public function fetch(Source $source): iterable
    {
        $settings = is_array($source->settings) ? $source->settings : [];

        $pages = filter_var($settings['pages'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $pages = is_int($pages) ? min($pages, self::MAX_PAGES) : 1;

        $query = $this->stringOrNull($settings['remote'] ?? null) === 'true' ? ['remote' => 'true'] : [];

        /** @var array<string, JobPostingData> $postings */
        $postings = [];

        for ($page = 1; $page <= $pages; $page++) {
            if ($page > 1) {
                $this->pause();
            }

            $response = $page === 1 && $pages === 1 && $query === []
                ? $this->http()->get(self::URL)
                : $this->getOrNullWhenRateLimited(self::URL, ['page' => $page] + $query);

            if ($response === null) {
                $this->stopOnRateLimit($postings);

                break;
            }

            $items = $this->items($response->json('data'));

            foreach ($items as $item) {
                $posting = $this->map($item);

                if ($posting !== null) {
                    $postings[$posting->externalId] ??= $posting;
                }
            }

            if ($items === [] || $this->stringOrNull($response->json('links.next')) === null) {
                break;
            }
        }

        yield from array_values($postings);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function map(array $item): ?JobPostingData
    {
        $externalId = $this->stringOrNull($item['slug'] ?? null);
        $title = $this->stringOrNull($item['title'] ?? null);
        $companyName = $this->stringOrNull($item['company_name'] ?? null);
        $url = $this->stringOrNull($item['url'] ?? null);

        if ($externalId === null || $title === null || $companyName === null || $url === null) {
            return null;
        }

        $html = $this->stringOrNull($item['description'] ?? null);
        $jobTypes = $item['job_types'] ?? null;

        return new JobPostingData(
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
