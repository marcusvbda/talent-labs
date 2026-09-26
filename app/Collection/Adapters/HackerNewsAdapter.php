<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Models\Source;
use App\Support\RegistrableDomain;
use RuntimeException;

/**
 * Top-level comments of the latest monthly "Ask HN: Who is hiring?" thread. Each
 * comment's first line is a free-form, pipe-separated header ("Company | Role |
 * Location | Full-time | ...") parsed deterministically; posts without one are skipped.
 */
class HackerNewsAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    private const SEARCH_URL = 'https://hn.algolia.com/api/v1/search_by_date';

    private const ITEM_URL = 'https://hn.algolia.com/api/v1/items/';

    private const THREAD_PREFIX = 'ask hn: who is hiring?';

    private const ROLE_PATTERN = '/(engineer|developer|programmer|software|swe|devops|sre|frontend|front-end|backend|back-end|full[- ]?stack|fullstack|manager|designer|support|success|product|architect|scientist|analyst|lead|head of|director|founder|researcher|qa|ios|android)/i';

    private const URL_PATTERN = '/https?:\/\/\S+/i';

    private const EMPLOYMENT_PATTERN = '/full[- ]?time|part[- ]?time|contract|intern/i';

    private const WORKPLACE_PATTERN = '/(remote|onsite|on-site|hybrid)/i';

    private const CURRENCY_PATTERN = '/[$€£]\s?\d|\d\s?k\b/i';

    /**
     * Hosts (and their subdomains) that are never the company's own website: HN itself,
     * ATS/job boards, forms, social networks and link shorteners.
     */
    private const NON_COMPANY_HOSTS = [
        'news.ycombinator.com', 'ycombinator.com', 'greenhouse.io', 'lever.co', 'ashbyhq.com', 'workable.com',
        'recruitee.com', 'teamtailor.com', 'smartrecruiters.com', 'bamboohr.com', 'breezy.hr', 'personio.de',
        'personio.com', 'join.com', 'myworkdayjobs.com', 'jobvite.com', 'trinethire.com', 'wellfound.com',
        'angel.co', 'linkedin.com', 'github.com', 'gitlab.com', 'notion.site', 'notion.so', 'google.com',
        'forms.gle', 'typeform.com', 'airtable.com', 'calendly.com', 'twitter.com', 'x.com', 'medium.com',
        'youtube.com', 'bit.ly', 'grnh.se', 'lnkd.in', 't.co', 'goo.gl', 'tinyurl.com', 'buff.ly', 'ow.ly', 'rb.gy',
        'is.gd', 'youtu.be', 'discord.gg', 'discord.com', 'facebook.com', 'instagram.com', 'crunchbase.com',
        'wikipedia.org', 'reddit.com', 'substack.com', 'workatastartup.com', 'rippling.com',
    ];

    public function fetch(Source $source): iterable
    {
        $threadId = $this->currentThreadId();

        $this->pause();

        // The whole comment tree comes back in one (heavy) response.
        $response = $this->http()->timeout(30)->get(self::ITEM_URL.$threadId);

        /** @var array<string, JobPostingData> $postings */
        $postings = [];

        foreach ($this->items($response->json('children')) as $item) {
            $posting = $this->map($item);

            if ($posting !== null) {
                $postings[$posting->externalId] ??= $posting;
            }
        }

        yield from array_values($postings);
    }

    /**
     * The newest story by `whoishiring` titled "Ask HN: Who is hiring?" (not "Who wants to be hired?").
     */
    private function currentThreadId(): string
    {
        $response = $this->http()->get(self::SEARCH_URL, [
            'tags' => 'story,author_whoishiring',
            'hitsPerPage' => 10,
        ]);

        foreach ($this->items($response->json('hits')) as $hit) {
            $title = mb_strtolower($this->stringOrNull($hit['title'] ?? null) ?? '');
            $id = $this->stringOrNull($hit['objectID'] ?? null);

            if ($id !== null && str_starts_with($title, self::THREAD_PREFIX)) {
                return $id;
            }
        }

        throw new RuntimeException('No "Ask HN: Who is hiring?" thread found');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function map(array $item): ?JobPostingData
    {
        $externalId = $this->stringOrNull($item['id'] ?? null);
        $html = $this->stringOrNull($item['text'] ?? null);

        // Deleted comments have no text.
        if ($externalId === null || $html === null) {
            return null;
        }

        $segments = $this->headerSegments($html);

        if (count($segments) < 2) {
            return null;
        }

        $companyName = $this->companyName($segments[0]);
        $titleIndex = $this->titleIndex($segments);

        if ($companyName === null || $titleIndex === null) {
            return null;
        }

        $header = implode(' | ', $segments);
        $url = "https://news.ycombinator.com/item?id={$externalId}";

        unset($item['children']);

        return new JobPostingData(
            externalId: $externalId,
            title: $segments[$titleIndex],
            companyName: $companyName,
            location: $this->location($segments, $titleIndex),
            isRemote: preg_match('/remote/i', $header) === 1,
            department: null,
            employmentType: $this->employmentType($segments),
            url: $url,
            applyUrl: $this->applyUrl($html) ?? $url,
            descriptionHtml: $html,
            descriptionText: $this->htmlToText($html),
            publishedAt: $this->parseDate($item['created_at'] ?? null),
            raw: $item,
            companyWebsite: $this->companyWebsite($segments, $html, $companyName),
        );
    }

    /**
     * The header line (text before the first paragraph) split on "|" into trimmed, non-empty segments.
     *
     * @return list<string>
     */
    private function headerSegments(string $html): array
    {
        $header = preg_split('/<p>/i', $html, 2)[0] ?? $html;
        $header = html_entity_decode(strip_tags($header), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $header = trim((string) preg_replace('/\s+/u', ' ', $header));

        $segments = [];

        foreach (explode('|', $header) as $segment) {
            $segment = trim($segment);

            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /**
     * Segment 0 without URLs ("Snout https://snout.com/", "Smarkets ( https://www.smarkets.com )").
     */
    private function companyName(string $segment): ?string
    {
        $name = (string) preg_replace('/\(\s*https?:\/\/[^\s)]+\s*\)/i', '', $segment);
        $name = (string) preg_replace(self::URL_PATTERN, '', $name);
        $name = (string) preg_replace('/\s+/u', ' ', $name);
        $name = (string) preg_replace('/^[\s\-–—:,]+|[\s\-–—:,]+$/u', '', $name);

        return $name === '' ? null : $name;
    }

    /**
     * Index (>= 1) of the title segment: the first role-like segment that is not a
     * URL/employment type/workplace/salary; null when there is none.
     *
     * @param  list<string>  $segments
     */
    private function titleIndex(array $segments): ?int
    {
        // No role-like segment means we cannot title the post: skip it rather than
        // picking a place or a slogan as the title.
        foreach ($segments as $index => $segment) {
            if ($index !== 0 && ! $this->isNonTitle($segment) && preg_match(self::ROLE_PATTERN, $segment) === 1) {
                return $index;
            }
        }

        return null;
    }

    private function isNonTitle(string $segment): bool
    {
        $hasRoleWord = preg_match(self::ROLE_PATTERN, $segment) === 1;

        return preg_match(self::URL_PATTERN, $segment) === 1
            || preg_match('/^(full[- ]?time|part[- ]?time|contract|internship|intern)\b/i', $segment) === 1
            || (preg_match(self::WORKPLACE_PATTERN, $segment) === 1 && ! $hasRoleWord)
            || preg_match(self::CURRENCY_PATTERN, $segment) === 1
            || (preg_match('/^[A-Z]{2,10}$/', $segment) === 1 && ! $hasRoleWord);
    }

    /**
     * The first non-title segment that looks like a place or workplace ("Remote (Europe)", "Utrecht, The Netherlands").
     *
     * @param  list<string>  $segments
     */
    private function location(array $segments, int $titleIndex): ?string
    {
        foreach ($segments as $index => $segment) {
            if ($index === 0 || $index === $titleIndex) {
                continue;
            }

            if (preg_match(self::URL_PATTERN, $segment) === 1 || preg_match(self::CURRENCY_PATTERN, $segment) === 1) {
                continue;
            }

            if (preg_match('/(remote|onsite|on-site|hybrid|,)/i', $segment) === 1) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * The company's own website: the first header URL ("Snout https://snout.com/",
     * "Smarkets ( https://www.smarkets.com )" or its own segment), else the first
     * comment link, skipping ATS/job boards, forms and social networks.
     *
     * @param  list<string>  $segments
     */
    private function companyWebsite(array $segments, string $html, string $companyName): ?string
    {
        foreach ($segments as $segment) {
            preg_match_all(self::URL_PATTERN, $segment, $matches);

            foreach ($matches[0] as $url) {
                $url = rtrim($url, '.,;)');

                if ($this->isCompanyUrl($url)) {
                    return mb_substr($url, 0, 2000);
                }
            }
        }

        preg_match_all('/href="([^"]+)"/i', $html, $matches);

        foreach ($matches[1] as $href) {
            $href = rtrim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'), '.,;)');

            // A stray link in the body may point anywhere (an article, a product, a partner):
            // only trust it when its domain looks like the company's own.
            if ($this->isCompanyUrl($href) && $this->hostMatchesCompany($href, $companyName)) {
                return mb_substr($href, 0, 2000);
            }
        }

        return null;
    }

    private function hostMatchesCompany(string $url, string $companyName): bool
    {
        $key = (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($companyName));
        $domain = RegistrableDomain::of($url);

        if ($domain === null || mb_strlen($key) < 3) {
            return false;
        }

        $label = (string) preg_replace('/[^a-z0-9]/', '', explode('.', $domain)[0]);

        return mb_strlen($label) >= 3 && (str_contains($key, $label) || str_contains($label, $key));
    }

    private function isCompanyUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || RegistrableDomain::of($url) === null) {
            return false;
        }

        foreach (self::NON_COMPANY_HOSTS as $denied) {
            if ($host === $denied || str_ends_with($host, '.'.$denied)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $segments
     */
    private function employmentType(array $segments): ?string
    {
        foreach ($segments as $segment) {
            if (preg_match(self::EMPLOYMENT_PATTERN, $segment) === 1) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * The first http(s) link in the comment that does not point back to Hacker News.
     */
    private function applyUrl(string $html): ?string
    {
        preg_match_all('/href="([^"]+)"/i', $html, $matches);

        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));

            if (in_array($scheme, ['http', 'https'], true) && $host !== '' && $host !== 'news.ycombinator.com') {
                return $href;
            }
        }

        return null;
    }
}
