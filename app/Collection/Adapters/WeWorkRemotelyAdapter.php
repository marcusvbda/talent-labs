<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;
use RuntimeException;
use SimpleXMLElement;

class WeWorkRemotelyAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    private const DEFAULT_CATEGORY = 'remote-programming-jobs';

    private const HEADERS = ['Accept' => 'application/rss+xml, application/xml, text/xml'];

    public function fetch(Source $source): iterable
    {
        $categories = $this->settingList($source, 'categories');
        $categories = $categories === [] ? [self::DEFAULT_CATEGORY] : array_slice($categories, 0, self::MAX_REQUESTS_PER_RUN);

        /** @var array<string, JobPostingData> $postings */
        $postings = [];

        foreach ($categories as $index => $slug) {
            if ($index > 0) {
                $this->pause();
            }

            $url = 'https://weworkremotely.com/categories/'.rawurlencode($slug).'.rss';
            $response = $this->getOrNullWhenRateLimited($url, [], self::HEADERS);

            if ($response === null) {
                $this->stopOnRateLimit($postings);

                break;
            }

            foreach ($this->parseItems($response->body(), $slug) as $item) {
                $posting = $this->map($item);

                if ($posting !== null) {
                    $postings[$posting->externalId] ??= $posting;
                }
            }
        }

        yield from array_values($postings);
    }

    /**
     * The feed's `<item>`s as plain arrays (external entities are never loaded).
     *
     * @return list<array<string, mixed>>
     */
    private function parseItems(string $body, string $slug): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false || ! isset($xml->channel)) {
            throw new RuntimeException("Invalid RSS from We Work Remotely ({$slug})");
        }

        $items = [];

        foreach ($xml->channel->item as $item) {
            $items[] = json_decode((string) json_encode($item), true);
        }

        return $this->items($items);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function map(array $item): ?JobPostingData
    {
        $guid = $this->stringOrNull($item['guid'] ?? null);
        $link = $this->stringOrNull($item['link'] ?? null);
        $externalId = $guid ?? $link;
        $url = $link ?? $guid;
        $heading = $this->stringOrNull($item['title'] ?? null);

        if ($externalId === null || $url === null || $heading === null) {
            return null;
        }

        // "Company: Role" — split on the first ": " only (roles may contain colons).
        $parts = explode(': ', $heading, 2);
        $companyName = $this->stringOrNull($parts[0]);
        $title = $this->stringOrNull($parts[1] ?? null);

        if ($companyName === null || $title === null) {
            return null;
        }

        $html = $this->stringOrNull($item['description'] ?? null);

        if ($html !== null && str_contains($html, '&lt;')) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return new JobPostingData(
            externalId: $externalId,
            title: $title,
            companyName: $companyName,
            location: $this->stringOrNull($item['region'] ?? null) ?? $this->stringOrNull($item['country'] ?? null),
            isRemote: true,
            department: $this->stringOrNull($item['category'] ?? null),
            employmentType: $this->stringOrNull($item['type'] ?? null),
            url: $url,
            applyUrl: $url,
            descriptionHtml: $html,
            descriptionText: $this->htmlToText($html),
            publishedAt: $this->parseDate($item['pubDate'] ?? null),
            raw: $item,
        );
    }
}
