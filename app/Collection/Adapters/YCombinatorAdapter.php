<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Collection\Data\SourceContactData;
use App\Enums\SourceContactKind;
use App\Models\Source;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Public Y Combinator job board (www.ycombinator.com/jobs/role/{slug}). Each page embeds its
 * Inertia payload in a `data-page` attribute: role pages list `jobPostings`, company pages
 * carry the company website and founders (names/titles only, never emails). Role pages are
 * fetched first; the rest of the per-run request budget enriches a random set of companies.
 */
class YCombinatorAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    private const BASE_URL = 'https://www.ycombinator.com';

    private const DEFAULT_ROLES = ['software-engineer', 'product-manager', 'support'];

    private const HEADERS = ['Accept' => 'text/html'];

    private const CREATED_AT_PATTERN = '/^(?:(?:about|over|almost)\s+)?(\d+)\s+(minute|hour|day|week|month|year)s?$/i';

    public function fetch(Source $source): iterable
    {
        $roles = $this->settingList($source, 'roles');
        $roles = array_slice($roles === [] ? self::DEFAULT_ROLES : $roles, 0, self::MAX_REQUESTS_PER_RUN);

        $requests = 0;
        $rateLimited = false;

        /** @var array<string, array<string, mixed>> $items */
        $items = [];

        foreach ($roles as $slug) {
            if ($requests > 0) {
                $this->pause();
            }

            $requests++;
            $response = $this->getOrNullWhenRateLimited(self::BASE_URL.'/jobs/role/'.rawurlencode($slug), [], self::HEADERS);

            if ($response === null) {
                $this->stopOnRateLimit($items);
                $rateLimited = true;

                break;
            }

            $page = $this->pageData($response->body());

            if ($page === null) {
                throw new RuntimeException('Y Combinator page has no data-page payload');
            }

            foreach ($this->items(data_get($page, 'props.jobPostings')) as $item) {
                $id = $this->stringOrNull($item['id'] ?? null);

                if ($id !== null) {
                    $items[$id] ??= $item;
                }
            }
        }

        // After a 429 the board is not asked again in this run.
        $budget = $rateLimited ? 0 : self::MAX_REQUESTS_PER_RUN - $requests;
        $companies = $this->companies($items, $budget);

        $postings = [];

        foreach ($items as $item) {
            $companyUrl = $this->stringOrNull($item['companyUrl'] ?? null);
            $posting = $this->map($item, $companyUrl !== null ? ($companies[$companyUrl] ?? null) : null, $source);

            if ($posting !== null) {
                $postings[] = $posting;
            }
        }

        yield from $postings;
    }

    /**
     * Website and founders of a random set of the postings' companies, within the remaining
     * request budget, keyed by `companyUrl`. Pages without a payload are skipped; a 429 stops
     * enrichment and keeps the postings already collected.
     *
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, array{website: string|null, founders: list<array{full_name: string|null, title: string|null, has_email: bool|null}>}>
     */
    private function companies(array $items, int $budget): array
    {
        $urls = [];

        foreach ($items as $item) {
            $url = $this->stringOrNull($item['companyUrl'] ?? null);

            if ($url !== null && str_starts_with($url, '/companies/')) {
                $urls[$url] = true;
            }
        }

        $urls = array_keys($urls);
        shuffle($urls);

        $companies = [];

        // Role pages always came first, so every company request is preceded by a pause.
        foreach (array_slice($urls, 0, max(0, $budget)) as $url) {
            $this->pause();

            $response = $this->getOrNullWhenRateLimited(self::BASE_URL.$url, [], self::HEADERS);

            if ($response === null) {
                break;
            }

            $company = data_get($this->pageData($response->body()), 'props.company');

            if (! is_array($company)) {
                continue;
            }

            $founders = [];

            foreach ($this->items($company['founders'] ?? null) as $founder) {
                $founders[] = [
                    'full_name' => $this->stringOrNull($founder['full_name'] ?? null),
                    'title' => $this->stringOrNull($founder['title'] ?? null),
                    'has_email' => $this->boolOrNull($founder['has_email'] ?? null),
                ];
            }

            $companies[$url] = [
                'website' => $this->stringOrNull($company['website'] ?? null),
                'founders' => $founders,
            ];
        }

        return $companies;
    }

    /**
     * The decoded `data-page` attribute, or null when missing/invalid.
     *
     * @return array<string, mixed>|null
     */
    private function pageData(string $body): ?array
    {
        if (preg_match('/data-page="([^"]+)"/', $body, $m) !== 1) {
            return null;
        }

        $data = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{website: string|null, founders: list<array{full_name: string|null, title: string|null, has_email: bool|null}>}|null  $company
     */
    private function map(array $item, ?array $company, Source $source): ?JobPostingData
    {
        $externalId = $this->stringOrNull($item['id'] ?? null);
        $title = $this->stringOrNull($item['title'] ?? null);
        $path = $this->stringOrNull($item['url'] ?? null);

        if ($externalId === null || $title === null || $path === null) {
            return null;
        }

        $url = self::BASE_URL.$path;
        $location = $this->stringOrNull($item['location'] ?? null);
        $prettyRole = $this->stringOrNull($item['prettyRole'] ?? null);

        if ($company !== null) {
            $item['company'] = $company;
        }

        return new JobPostingData(
            externalId: $externalId,
            title: $title,
            companyName: $this->stringOrNull($item['companyName'] ?? null) ?? $source->name,
            location: $location,
            isRemote: $location !== null && preg_match('/remote/i', $location) === 1,
            department: $prettyRole,
            employmentType: $this->stringOrNull($item['type'] ?? null),
            url: $url,
            // YC's applyUrl requires a login; link to the public posting instead.
            applyUrl: $url,
            descriptionHtml: null,
            descriptionText: $this->descriptionText($item, $prettyRole, $location),
            publishedAt: $this->createdAt($item['createdAt'] ?? null),
            raw: $item,
            companyWebsite: $company['website'] ?? null,
            sourceContacts: $this->contacts($item, $company),
        );
    }

    /**
     * Plain lines from the payload fields (empty ones skipped).
     *
     * @param  array<string, mixed>  $item
     */
    private function descriptionText(array $item, ?string $prettyRole, ?string $location): ?string
    {
        $role = implode(' · ', array_filter([$prettyRole, $this->stringOrNull($item['roleSpecificType'] ?? null)]));
        $experience = $this->stringOrNull($item['minExperience'] ?? null);
        $salary = $this->stringOrNull($item['salaryRange'] ?? null);

        $skills = [];

        foreach (is_array($item['skills'] ?? null) ? $item['skills'] : [] as $skill) {
            $skill = $this->stringOrNull(is_array($skill) ? ($skill['name'] ?? null) : $skill);

            if ($skill !== null) {
                $skills[] = $skill;
            }
        }

        $lines = array_filter([
            $this->stringOrNull($item['companyOneLiner'] ?? null),
            $role !== '' ? "Role: {$role}" : null,
            $location !== null ? "Location: {$location}" : null,
            $experience !== null ? "Experience: {$experience}" : null,
            $skills !== [] ? 'Skills: '.implode(', ', $skills) : null,
            $salary !== null ? "Salary: {$salary}" : null,
        ]);

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * Relative age ("17 days", "about 2 months") subtracted from now; anything else is null.
     */
    private function createdAt(mixed $value): ?CarbonImmutable
    {
        $value = $this->stringOrNull($value);

        if ($value === null || preg_match(self::CREATED_AT_PATTERN, $value, $m) !== 1) {
            return null;
        }

        return CarbonImmutable::now()->sub(mb_strtolower($m[2]), (int) $m[1]);
    }

    /**
     * One Founder per named founder plus the hiring manager when present. Never emails.
     *
     * @param  array<string, mixed>  $item
     * @param  array{website: string|null, founders: list<array{full_name: string|null, title: string|null, has_email: bool|null}>}|null  $company
     * @return list<SourceContactData>
     */
    private function contacts(array $item, ?array $company): array
    {
        $contacts = [];

        foreach ($company['founders'] ?? [] as $founder) {
            if ($founder['full_name'] !== null) {
                $contacts[] = new SourceContactData(SourceContactKind::Founder, name: $founder['full_name'], title: $founder['title']);
            }
        }

        $manager = $item['hiringManager'] ?? null;

        if (is_array($manager)) {
            $name = $this->stringOrNull($manager['name'] ?? null) ?? $this->stringOrNull($manager['full_name'] ?? null);

            if ($name !== null) {
                $contacts[] = new SourceContactData(SourceContactKind::HiringManager, name: $name, title: $this->stringOrNull($manager['title'] ?? null));
            }
        }

        return $contacts;
    }
}
