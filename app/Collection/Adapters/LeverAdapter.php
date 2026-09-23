<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;
use Carbon\CarbonImmutable;

class LeverAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    public function fetch(Source $source): iterable
    {
        $identifier = $this->requireIdentifier($source);

        $response = $this->http()->get(
            'https://api.lever.co/v0/postings/'.rawurlencode($identifier),
            ['mode' => 'json'],
        );

        foreach ($this->items($response->json()) as $item) {
            $externalId = $this->stringOrNull($item['id'] ?? null);
            $title = $this->stringOrNull($item['text'] ?? null);
            $url = $this->stringOrNull($item['hostedUrl'] ?? null);

            if ($externalId === null || $title === null || $url === null) {
                continue;
            }

            $createdAt = $item['createdAt'] ?? null;

            yield new JobPostingData(
                externalId: $externalId,
                title: $title,
                companyName: $source->name,
                location: $this->stringOrNull(data_get($item, 'categories.location')),
                isRemote: array_key_exists('workplaceType', $item) ? $item['workplaceType'] === 'remote' : null,
                department: $this->stringOrNull(data_get($item, 'categories.team')),
                employmentType: $this->stringOrNull(data_get($item, 'categories.commitment')),
                url: $url,
                applyUrl: $this->stringOrNull($item['applyUrl'] ?? null),
                descriptionHtml: $this->descriptionHtml($item),
                descriptionText: $this->joinNonEmpty([
                    $this->stringOrNull($item['descriptionPlain'] ?? null),
                    $this->stringOrNull($item['additionalPlain'] ?? null),
                ], "\n\n"),
                publishedAt: is_numeric($createdAt) ? CarbonImmutable::createFromTimestampMs((int) $createdAt) : null,
                raw: $item,
            );
        }
    }

    /**
     * description + each list as "<h3>{text}</h3>{content}" + additional.
     *
     * @param  array<string, mixed>  $item
     */
    private function descriptionHtml(array $item): ?string
    {
        $parts = [$this->stringOrNull($item['description'] ?? null)];

        foreach ($this->items($item['lists'] ?? null) as $list) {
            $text = $this->stringOrNull($list['text'] ?? null);
            $content = $this->stringOrNull($list['content'] ?? null);

            $parts[] = ($text === null ? '' : '<h3>'.e($text).'</h3>').($content ?? '');
        }

        $parts[] = $this->stringOrNull($item['additional'] ?? null);

        return $this->joinNonEmpty($parts, '');
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function joinNonEmpty(array $parts, string $glue): ?string
    {
        $parts = array_filter($parts, fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode($glue, $parts);
    }
}
