<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;

class JobicyAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    private const URL = 'https://jobicy.com/api/v2/remote-jobs';

    public function fetch(Source $source): iterable
    {
        $industries = array_slice($this->settingList($source, 'industries'), 0, self::MAX_REQUESTS_PER_RUN);

        if ($industries === []) {
            $response = $this->http()->get(self::URL, $this->query($source));

            foreach ($this->items($response->json('jobs')) as $item) {
                $posting = $this->map($item);

                if ($posting !== null) {
                    yield $posting;
                }
            }

            return;
        }

        /** @var array<string, JobPostingData> $postings */
        $postings = [];

        foreach ($industries as $index => $industry) {
            if ($index > 0) {
                $this->pause();
            }

            $response = $this->getOrNullWhenRateLimited(self::URL, ['industry' => $industry] + $this->query($source));

            if ($response === null) {
                $this->stopOnRateLimit($postings);

                break;
            }

            foreach ($this->items($response->json('jobs')) as $item) {
                $posting = $this->map($item);

                if ($posting !== null) {
                    $postings[$posting->externalId] ??= $posting;
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
        $externalId = $this->stringOrNull($item['id'] ?? null);
        $title = $this->stringOrNull($item['jobTitle'] ?? null);
        $companyName = $this->stringOrNull($item['companyName'] ?? null);
        $url = $this->stringOrNull($item['url'] ?? null);

        if ($externalId === null || $title === null || $companyName === null || $url === null) {
            return null;
        }

        $html = $this->stringOrNull($item['jobDescription'] ?? null);
        $industries = $item['jobIndustry'] ?? null;
        $types = $item['jobType'] ?? null;

        return new JobPostingData(
            externalId: $externalId,
            title: $title,
            companyName: $companyName,
            location: $this->stringOrNull($item['jobGeo'] ?? null),
            isRemote: true,
            department: is_array($industries) ? $this->stringOrNull($industries[0] ?? null) : null,
            employmentType: is_array($types) ? $this->stringOrNull($types[0] ?? null) : null,
            url: $url,
            applyUrl: $url,
            descriptionHtml: $html,
            descriptionText: $this->htmlToText($html),
            publishedAt: $this->parseDate($item['pubDate'] ?? null),
            raw: $item,
        );
    }

    /**
     * Query params from the source settings; `count` is always sent (default 50, max 100).
     *
     * @return array<string, string|int>
     */
    private function query(Source $source): array
    {
        $settings = is_array($source->settings) ? $source->settings : [];

        $count = filter_var($settings['count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $query = ['count' => is_int($count) ? min($count, 100) : 50];

        foreach (['geo', 'industry', 'tag'] as $key) {
            $value = $this->stringOrNull($settings[$key] ?? null);

            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
