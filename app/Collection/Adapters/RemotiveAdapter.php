<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;

class RemotiveAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    public function fetch(Source $source): iterable
    {
        $response = $this->http()->get('https://remotive.com/api/remote-jobs', $this->query($source));

        foreach ($this->items($response->json('jobs')) as $item) {
            $externalId = $this->stringOrNull($item['id'] ?? null);
            $title = $this->stringOrNull($item['title'] ?? null);
            $companyName = $this->stringOrNull($item['company_name'] ?? null);
            $url = $this->stringOrNull($item['url'] ?? null);

            if ($externalId === null || $title === null || $companyName === null || $url === null) {
                continue;
            }

            $html = $this->stringOrNull($item['description'] ?? null);

            yield new JobPostingData(
                externalId: $externalId,
                title: $title,
                companyName: $companyName,
                location: $this->stringOrNull($item['candidate_required_location'] ?? null),
                isRemote: true,
                department: $this->stringOrNull($item['category'] ?? null),
                employmentType: $this->stringOrNull($item['job_type'] ?? null),
                url: $url,
                applyUrl: null,
                descriptionHtml: $html,
                descriptionText: $this->htmlToText($html),
                publishedAt: $this->parseDate($item['publication_date'] ?? null),
                raw: $item,
            );
        }
    }

    /**
     * Query params from the source settings; only keys that are actually set.
     *
     * @return array<string, string|int>
     */
    private function query(Source $source): array
    {
        $settings = is_array($source->settings) ? $source->settings : [];
        $query = [];

        foreach (['category', 'search'] as $key) {
            $value = $this->stringOrNull($settings[$key] ?? null);

            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        $limit = filter_var($settings['limit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (is_int($limit)) {
            $query['limit'] = $limit;
        }

        return $query;
    }
}
