<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;

class JobicyAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    public function fetch(Source $source): iterable
    {
        $response = $this->http()->get('https://jobicy.com/api/v2/remote-jobs', $this->query($source));

        foreach ($this->items($response->json('jobs')) as $item) {
            $externalId = $this->stringOrNull($item['id'] ?? null);
            $title = $this->stringOrNull($item['jobTitle'] ?? null);
            $companyName = $this->stringOrNull($item['companyName'] ?? null);
            $url = $this->stringOrNull($item['url'] ?? null);

            if ($externalId === null || $title === null || $companyName === null || $url === null) {
                continue;
            }

            $html = $this->stringOrNull($item['jobDescription'] ?? null);
            $industries = $item['jobIndustry'] ?? null;
            $types = $item['jobType'] ?? null;

            yield new JobPostingData(
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
    }

    /**
     * Query params from the source settings; `count` is always sent (default 50).
     *
     * @return array<string, string|int>
     */
    private function query(Source $source): array
    {
        $settings = is_array($source->settings) ? $source->settings : [];

        $count = filter_var($settings['count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $query = ['count' => is_int($count) ? $count : 50];

        foreach (['geo', 'industry', 'tag'] as $key) {
            $value = $this->stringOrNull($settings[$key] ?? null);

            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
