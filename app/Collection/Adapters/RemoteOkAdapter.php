<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;

class RemoteOkAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    public function fetch(Source $source): iterable
    {
        $response = $this->http()->get('https://remoteok.com/api');

        // The first element is a legal/metadata object; the required-field guard skips it.
        foreach ($this->items($response->json()) as $item) {
            $externalId = $this->stringOrNull($item['id'] ?? null);
            $title = $this->decodedOrNull($item['position'] ?? null);
            $companyName = $this->decodedOrNull($item['company'] ?? null);
            $url = $this->stringOrNull($item['url'] ?? null);

            if ($externalId === null || $title === null || $companyName === null || $url === null) {
                continue;
            }

            $html = $this->stringOrNull($item['description'] ?? null);

            yield new JobPostingData(
                externalId: $externalId,
                title: $title,
                companyName: $companyName,
                location: $this->stringOrNull($item['location'] ?? null),
                isRemote: true,
                department: null,
                employmentType: null,
                url: $url,
                applyUrl: $this->stringOrNull($item['apply_url'] ?? null) ?? $url,
                descriptionHtml: $html,
                descriptionText: $this->htmlToText($html),
                publishedAt: $this->parseDate($item['date'] ?? null),
                raw: $item,
            );
        }
    }

    /**
     * RemoteOK HTML-encodes some plain-text fields (e.g. "Partners &amp; Logistics").
     */
    private function decodedOrNull(mixed $value): ?string
    {
        $value = $this->stringOrNull($value);

        return $value === null ? null : html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
