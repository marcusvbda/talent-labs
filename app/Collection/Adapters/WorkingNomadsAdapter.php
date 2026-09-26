<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;

class WorkingNomadsAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    private const URL = 'https://www.workingnomads.com/api/exposed_jobs/';

    public function fetch(Source $source): iterable
    {
        $categories = array_map(mb_strtolower(...), $this->settingList($source, 'categories'));

        $response = $this->getOrNullWhenRateLimited(self::URL);

        if ($response === null) {
            $this->stopOnRateLimit([]);

            return;
        }

        /** @var array<string, JobPostingData> $postings */
        $postings = [];

        // The feed is a single top-level array with no pagination.
        foreach ($this->items($response->json()) as $item) {
            if ($categories !== [] && ! in_array(mb_strtolower($this->stringOrNull($item['category_name'] ?? null) ?? ''), $categories, true)) {
                continue;
            }

            $posting = $this->map($item);

            if ($posting !== null) {
                $postings[$posting->externalId] ??= $posting;
            }
        }

        yield from array_values($postings);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function map(array $item): ?JobPostingData
    {
        $url = $this->stringOrNull($item['url'] ?? null);
        $title = $this->stringOrNull($item['title'] ?? null);
        $companyName = $this->stringOrNull($item['company_name'] ?? null);

        if ($url === null || $title === null || $companyName === null) {
            return null;
        }

        // The feed has no id field; the numeric id lives in the "/job/go/<id>/" URL.
        $externalId = preg_match('/\/job\/go\/(\d+)/', $url, $matches) === 1 ? $matches[1] : $url;
        $html = $this->stringOrNull($item['description'] ?? null);

        return new JobPostingData(
            externalId: $externalId,
            title: $title,
            companyName: $companyName,
            location: $this->stringOrNull($item['location'] ?? null),
            isRemote: true,
            department: $this->stringOrNull($item['category_name'] ?? null),
            employmentType: null,
            url: $url,
            applyUrl: $url,
            descriptionHtml: $html,
            descriptionText: $this->htmlToText($html),
            publishedAt: $this->parseDate($item['pub_date'] ?? null),
            raw: $item,
        );
    }
}
