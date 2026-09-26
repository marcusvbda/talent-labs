<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;

class HimalayasAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    private const URL = 'https://himalayas.app/jobs/api/search';

    private const MAX_PAGES = 5;

    private const PAGE_SIZE = 20;

    public function fetch(Source $source): iterable
    {
        $settings = is_array($source->settings) ? $source->settings : [];

        $pages = filter_var($settings['pages'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $pages = is_int($pages) ? min($pages, self::MAX_PAGES) : 1;

        $passthrough = [];

        foreach (['country', 'worldwide', 'seniority'] as $key) {
            $value = $this->stringOrNull($settings[$key] ?? null);

            if ($value !== null) {
                $passthrough[$key] = $value;
            }
        }

        $queries = $this->settingList($source, 'queries');

        // No queries: a single browse request (page 1, no `q`).
        $plan = $queries === []
            ? [[null, 1]]
            : array_map(fn (string $query): array => [$query, $pages], $queries);

        /** @var array<string, JobPostingData> $postings */
        $postings = [];
        $requests = 0;

        foreach ($plan as [$query, $queryPages]) {
            for ($page = 1; $page <= $queryPages; $page++) {
                if ($requests >= self::MAX_REQUESTS_PER_RUN) {
                    break 2;
                }

                if ($requests > 0) {
                    $this->pause();
                }

                $requests++;

                $params = ($query === null ? [] : ['q' => $query]) + ['page' => $page] + $passthrough;
                $response = $this->getOrNullWhenRateLimited(self::URL, $params);

                if ($response === null) {
                    $this->stopOnRateLimit($postings);

                    break 2;
                }

                $items = $this->items($response->json('jobs'));

                foreach ($items as $item) {
                    $posting = $this->map($item);

                    if ($posting !== null) {
                        $postings[$posting->externalId] ??= $posting;
                    }
                }

                if (count($items) < self::PAGE_SIZE) {
                    break;
                }
            }
        }

        yield from array_values($postings);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function map(array $item): ?JobPostingData
    {
        $externalId = $this->stringOrNull($item['guid'] ?? null);
        $title = $this->stringOrNull($item['title'] ?? null);
        $companyName = $this->stringOrNull($item['companyName'] ?? null);

        if ($externalId === null || $title === null || $companyName === null) {
            return null;
        }

        $html = $this->stringOrNull($item['description'] ?? null);
        $locations = $item['locationRestrictions'] ?? null;
        $locations = is_array($locations) ? array_values(array_filter(array_map($this->stringOrNull(...), $locations))) : [];
        $categories = $item['parentCategories'] ?? null;

        return new JobPostingData(
            externalId: $externalId,
            title: $title,
            companyName: $companyName,
            location: $locations === [] ? null : implode(', ', $locations),
            isRemote: true,
            department: is_array($categories) ? $this->stringOrNull($categories[0] ?? null) : null,
            employmentType: $this->stringOrNull($item['employmentType'] ?? null),
            url: $externalId,
            applyUrl: $this->stringOrNull($item['applicationLink'] ?? null) ?? $externalId,
            descriptionHtml: $html,
            descriptionText: $this->htmlToText($html),
            publishedAt: $this->timestampOrNull($item['pubDate'] ?? null),
            raw: $item,
        );
    }
}
