<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class AdzunaAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    private const URL = 'https://api.adzuna.com/v1/api/jobs/%s/search/%d';

    private const MAX_PAGES = 3;

    private const PAGE_SIZE = 50;

    public function fetch(Source $source): iterable
    {
        $appId = $this->stringOrNull(config('services.adzuna.app_id'));
        $appKey = $this->stringOrNull(config('services.adzuna.app_key'));

        if ($appId === null || $appKey === null) {
            throw new RuntimeException('Adzuna keys are missing (ADZUNA_APP_ID / ADZUNA_APP_KEY).');
        }

        $settings = is_array($source->settings) ? $source->settings : [];

        $country = mb_strtolower($this->stringOrNull($settings['country'] ?? null) ?? 'br');

        $pages = filter_var($settings['pages'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $pages = is_int($pages) ? min($pages, self::MAX_PAGES) : 1;

        // No queries: a single browse request (page 1, no `what`).
        $queries = $this->settingList($source, 'queries');
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

                $params = [
                    'app_id' => $appId,
                    'app_key' => $appKey,
                    'results_per_page' => self::PAGE_SIZE,
                    'content-type' => 'application/json',
                ] + ($query === null ? [] : ['what' => $query]);

                try {
                    $response = $this->getOrNullWhenRateLimited(sprintf(self::URL, rawurlencode($country), $page), $params);
                } catch (ConnectionException) {
                    // The original message carries the request URL, which holds the keys.
                    throw new RuntimeException('Adzuna request failed (connection error).');
                }

                if ($response === null) {
                    $this->stopOnRateLimit($postings);

                    break 2;
                }

                $items = $this->items($response->json('results'));

                if ($items === []) {
                    break;
                }

                foreach ($items as $item) {
                    $posting = $this->map($item, $source);

                    if ($posting !== null) {
                        $postings[$posting->externalId] ??= $posting;
                    }
                }
            }
        }

        yield from array_values($postings);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function map(array $item, Source $source): ?JobPostingData
    {
        $externalId = $this->stringOrNull($item['id'] ?? null);
        $title = $this->htmlToText($this->stringOrNull($item['title'] ?? null));
        $url = $this->stringOrNull($item['redirect_url'] ?? null);

        if ($externalId === null || $title === null || $url === null) {
            return null;
        }

        return new JobPostingData(
            externalId: $externalId,
            title: $title,
            companyName: $this->nested($item, 'company', 'display_name') ?? $source->name,
            location: $this->nested($item, 'location', 'display_name'),
            isRemote: null,
            department: $this->nested($item, 'category', 'label'),
            employmentType: $this->stringOrNull($item['contract_time'] ?? null),
            url: $url,
            applyUrl: $url,
            descriptionHtml: null,
            descriptionText: $this->htmlToText($this->stringOrNull($item['description'] ?? null)),
            publishedAt: $this->parseDate($item['created'] ?? null),
            raw: $item,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function nested(array $item, string $object, string $key): ?string
    {
        $value = $item[$object] ?? null;

        return is_array($value) ? $this->stringOrNull($value[$key] ?? null) : null;
    }
}
