<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;

class GreenhouseAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    public function fetch(Source $source): iterable
    {
        $identifier = $this->requireIdentifier($source);

        $response = $this->http()->get(
            'https://boards-api.greenhouse.io/v1/boards/'.rawurlencode($identifier).'/jobs',
            ['content' => 'true'],
        );

        foreach ($this->items($response->json('jobs')) as $item) {
            $externalId = $this->stringOrNull($item['id'] ?? null);
            $title = $this->stringOrNull($item['title'] ?? null);
            $url = $this->stringOrNull($item['absolute_url'] ?? null);

            if ($externalId === null || $title === null || $url === null) {
                continue;
            }

            $content = $this->stringOrNull($item['content'] ?? null);
            $html = $content === null ? null : html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            yield new JobPostingData(
                externalId: $externalId,
                title: $title,
                companyName: $this->stringOrNull($item['company_name'] ?? null) ?? $source->name,
                location: $this->stringOrNull(data_get($item, 'location.name')),
                isRemote: null,
                department: $this->stringOrNull(data_get($item, 'departments.0.name')),
                employmentType: null,
                url: $url,
                applyUrl: $url,
                descriptionHtml: $html,
                descriptionText: $this->htmlToText($html),
                publishedAt: $this->parseDate($item['first_published'] ?? null) ?? $this->parseDate($item['updated_at'] ?? null),
                raw: $item,
            );
        }
    }
}
