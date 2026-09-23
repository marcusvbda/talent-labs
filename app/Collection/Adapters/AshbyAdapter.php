<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;

class AshbyAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    public function fetch(Source $source): iterable
    {
        $identifier = $this->requireIdentifier($source);

        $response = $this->http()->get(
            'https://api.ashbyhq.com/posting-api/job-board/'.rawurlencode($identifier),
            ['includeCompensation' => 'true'],
        );

        foreach ($this->items($response->json('jobs')) as $item) {
            if (($item['isListed'] ?? null) === false) {
                continue;
            }

            $externalId = $this->stringOrNull($item['id'] ?? null);
            $title = $this->stringOrNull($item['title'] ?? null);
            $url = $this->stringOrNull($item['jobUrl'] ?? null);

            if ($externalId === null || $title === null || $url === null) {
                continue;
            }

            yield new JobPostingData(
                externalId: $externalId,
                title: $title,
                companyName: $source->name,
                location: $this->stringOrNull($item['location'] ?? null),
                isRemote: $this->boolOrNull($item['isRemote'] ?? null),
                department: $this->stringOrNull($item['department'] ?? null),
                employmentType: $this->stringOrNull($item['employmentType'] ?? null),
                url: $url,
                applyUrl: $this->stringOrNull($item['applyUrl'] ?? null),
                descriptionHtml: $this->stringOrNull($item['descriptionHtml'] ?? null),
                descriptionText: $this->stringOrNull($item['descriptionPlain'] ?? null),
                publishedAt: $this->parseDate($item['publishedAt'] ?? null),
                raw: $item,
            );
        }
    }
}
