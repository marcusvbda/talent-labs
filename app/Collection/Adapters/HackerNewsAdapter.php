<?php

namespace App\Collection\Adapters;

use App\Collection\Adapters\Concerns\InteractsWithJobBoardApi;
use App\Collection\Contracts\JobSourceAdapter;
use App\Collection\Data\JobPostingData;
use App\Collection\Data\SourceContactData;
use App\Enums\SourceContactKind;
use App\Models\Source;
use App\Support\RegistrableDomain;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Top-level comments of the latest monthly "Ask HN: Who is hiring?" thread. Each
 * comment's first line is a free-form, pipe-separated header ("Company | Role |
 * Location | Full-time | ...") parsed deterministically; posts without one are skipped.
 * Emails (mailto links and bracket-obfuscated "jobs [at] acme [dot] io") become source contacts.
 */
class HackerNewsAdapter implements JobSourceAdapter
{
    use InteractsWithJobBoardApi;

    private const SEARCH_URL = 'https://hn.algolia.com/api/v1/search_by_date';

    private const ITEM_URL = 'https://hn.algolia.com/api/v1/items/';

    private const THREAD_PREFIX = 'ask hn: who is hiring?';

    private const ROLE_PATTERN = '/(engineer|developer|programmer|software|swe|devops|sre|frontend|front-end|backend|back-end|full[- ]?stack|fullstack|manager|designer|support|success|product|architect|scientist|analyst|lead|head of|director|founder|researcher|qa|ios|android)/i';

    /**
     * Nouns that name a role on their own; used to detect multi-role titles and body role lines.
     */
    private const ROLE_NOUN_PATTERN = '/\b(engineer|developer|manager|designer|scientist|analyst|architect|sdet|qa)s?\b/i';

    private const URL_PATTERN = '/https?:\/\/\S+/i';

    private const EMPLOYMENT_PATTERN = '/full[- ]?time|part[- ]?time|contract|intern/i';

    private const WORKPLACE_PATTERN = '/(remote|onsite|on-site|hybrid)/i';

    private const CURRENCY_PATTERN = '/[$€£]\s?\d|\d\s?k\b/i';

    private const EMAIL_PATTERN = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    private const MAX_EMAILS = 5;

    /**
     * Email domains that belong to Hacker News itself, never to the hiring company.
     */
    private const NON_CONTACT_EMAIL_DOMAINS = ['news.ycombinator.com', 'ycombinator.com'];

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
            foreach ($this->map($item) as $posting) {
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
     * One posting per role: a comment listing several clearly delimited roles (header
     * segments, title separators or a bulleted body list) becomes "{id}#{n}" children
     * plus the original id marked not eligible; a multi-role title that can't be split
     * reliably stays one posting marked not eligible.
     *
     * @param  array<string, mixed>  $item
     * @return list<JobPostingData>
     */
    private function map(array $item): array
    {
        $externalId = $this->stringOrNull($item['id'] ?? null);
        $html = $this->stringOrNull($item['text'] ?? null);

        // Deleted comments have no text.
        if ($externalId === null || $html === null) {
            return [];
        }

        $segments = $this->headerSegments($html);

        if (count($segments) < 2) {
            return [];
        }

        $companyName = $this->companyName($segments[0]);
        $titleIndex = $this->titleIndex($segments);

        if ($companyName === null || $titleIndex === null) {
            return [];
        }

        $header = implode(' | ', $segments);
        $url = "https://news.ycombinator.com/item?id={$externalId}";

        unset($item['children']);

        $parent = new JobPostingData(
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
            sourceContacts: array_map(
                fn (string $email) => new SourceContactData(SourceContactKind::Email, email: $email),
                $this->emails($html),
            ),
        );

        $roles = $this->splitRoles($segments, $titleIndex, $html);

        if (count($roles) >= 2) {
            $postings = [];

            foreach ($roles as $index => $role) {
                $postings[] = $this->forRole($parent, $role, $index + 1);
            }

            // The original id stays (marked not eligible) so a row stored before the split leaves the pool.
            $postings[] = $this->withIneligibleReason($parent, 'Split into one posting per role.');

            return $postings;
        }

        if ($this->hasAmbiguousRoles($segments[$titleIndex])) {
            return [$this->withIneligibleReason($parent, 'Multiple roles in one post; could not split reliably.')];
        }

        return [$parent];
    }

    /**
     * The roles of a multi-role post, in order, when each one is clearly delimited:
     * 2+ role-like header segments, else 2+ role-like parts of the title segment split
     * on " · ", " • " or ";", else 2+ bulleted body lines that are role phrases.
     *
     * @param  list<string>  $segments
     * @return list<string>
     */
    private function splitRoles(array $segments, int $titleIndex, string $html): array
    {
        $headerRoles = [];

        foreach ($segments as $index => $segment) {
            if ($index !== 0 && $this->isRoleLike($segment)) {
                $headerRoles[] = $segment;
            }
        }

        if (count($headerRoles) >= 2) {
            return $headerRoles;
        }

        $parts = $this->nonEmptyParts((array) preg_split('/ · | • |;/u', $segments[$titleIndex]));

        // A separator inside parentheses ("Software Engineer (Backend; Frontend)") is not a role boundary.
        $balanced = array_filter($parts, fn (string $part): bool => substr_count($part, '(') === substr_count($part, ')'));

        if (count($parts) >= 2 && count($balanced) === count($parts) && count(array_filter($parts, $this->isRoleLike(...))) === count($parts)) {
            return $parts;
        }

        return $this->bodyRoleLines($html);
    }

    /**
     * Body lines (paragraphs, <br> and newlines, header excluded) that start with a
     * bullet ("-", "*", "•", "1." or "1)") followed by a title-shaped role (see
     * isTitleShapedRole); the bullet is dropped. Requirement bullets ("- 5+ years as a
     * software engineer", "- Mentor junior engineers") never count as roles.
     *
     * @return list<string>
     */
    private function bodyRoleLines(string $html): array
    {
        $body = preg_split('/<p>/i', $html, 2)[1] ?? '';
        $roles = [];

        foreach ((array) preg_split('/<p>|<br\s*\/?>|\R/i', $body) as $line) {
            $text = $this->htmlToText(is_string($line) ? $line : null);

            if ($text === null || preg_match('/^(?:[-*•]|\d{1,2}[.)])\s+(.+)$/u', $text, $matches) !== 1) {
                continue;
            }

            $role = trim($matches[1]);

            if ($this->isTitleShapedRole($role)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * A job title, not a sentence: at most 80 chars and 8 words that end in a role noun
     * ("Senior Backend Engineer"), optionally followed by one parenthetical or a
     * " - location" suffix; no sentence punctuation, and every word is capitalized
     * (or has a capital/digit, like "iOS") except short connectors ("of", "and", "&").
     */
    private function isTitleShapedRole(string $role): bool
    {
        if (preg_match('/^([^().:;]+?)(?:\s*\([^()]*\)|\s+[-–—]\s+[^().:;]+)?$/u', $role, $matches) !== 1) {
            return false;
        }

        $title = trim($matches[1]);
        $words = preg_split('/\s+/u', $title) ?: [];

        if (
            mb_strlen($title) > 80
            || count($words) > 8
            || preg_match('/\b(engineer|developer|manager|designer|scientist|analyst|architect|sdet|qa)s?$/i', $title) !== 1
            || ! $this->isRoleLike($role)
        ) {
            return false;
        }

        foreach ($words as $word) {
            if (preg_match('/[\p{Lu}\d]/u', $word) !== 1 && preg_match('/^(of|and|&|\/|for|the|in|on|at|to|[-–—])$/iu', $word) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * A title like "Designer / Engineer" (2+ parts that each name a role noun) or
     * "Backend and Frontend Engineers" (role-like parts sharing a trailing plural role
     * noun): several roles, but nothing delimits them well enough to split.
     */
    private function hasAmbiguousRoles(string $title): bool
    {
        $parts = $this->nonEmptyParts((array) preg_split('/ and | & | \/ |, /iu', $title));

        if (count($parts) < 2) {
            return false;
        }

        $withNoun = array_filter($parts, fn (string $part): bool => preg_match(self::ROLE_NOUN_PATTERN, $part) === 1);

        if (count($withNoun) === count($parts)) {
            return true;
        }

        return preg_match('/\b(engineer|developer|manager|designer|scientist|analyst|architect|sdet|qa)s\b/i', $parts[count($parts) - 1]) === 1
            && count(array_filter($parts, fn (string $part): bool => preg_match(self::ROLE_PATTERN, $part) === 1)) === count($parts);
    }

    /**
     * @param  array<mixed>  $parts
     * @return list<string>
     */
    private function nonEmptyParts(array $parts): array
    {
        $result = [];

        foreach ($parts as $part) {
            $part = is_string($part) ? trim($part) : '';

            if ($part !== '') {
                $result[] = $part;
            }
        }

        return $result;
    }

    private function isRoleLike(string $text): bool
    {
        return preg_match(self::ROLE_PATTERN, $text) === 1 && ! $this->isNonTitle($text);
    }

    /**
     * Child posting "{id}#{n}" for one role: its own parenthetical location (and remote
     * flag) when it has one, else the parent's; every other field copied from the parent.
     */
    private function forRole(JobPostingData $parent, string $role, int $n): JobPostingData
    {
        $location = null;

        if (preg_match('/\(([^()]*)\)/u', $role, $matches) === 1 && preg_match('/(remote|onsite|on-site|hybrid|,)/i', $matches[1]) === 1) {
            $location = trim($matches[1]);
        }

        return new JobPostingData(
            externalId: "{$parent->externalId}#{$n}",
            title: $role,
            companyName: $parent->companyName,
            location: $location ?? $parent->location,
            isRemote: $location === null ? $parent->isRemote : preg_match('/remote/i', "{$role} {$location}") === 1,
            department: $parent->department,
            employmentType: $parent->employmentType,
            url: $parent->url,
            applyUrl: $parent->applyUrl,
            descriptionHtml: $parent->descriptionHtml,
            descriptionText: $parent->descriptionText,
            publishedAt: $parent->publishedAt,
            raw: $parent->raw,
            companyWebsite: $parent->companyWebsite,
            sourceContacts: $parent->sourceContacts,
        );
    }

    private function withIneligibleReason(JobPostingData $posting, string $reason): JobPostingData
    {
        return new JobPostingData(
            externalId: $posting->externalId,
            title: $posting->title,
            companyName: $posting->companyName,
            location: $posting->location,
            isRemote: $posting->isRemote,
            department: $posting->department,
            employmentType: $posting->employmentType,
            url: $posting->url,
            applyUrl: $posting->applyUrl,
            descriptionHtml: $posting->descriptionHtml,
            descriptionText: $posting->descriptionText,
            publishedAt: $posting->publishedAt,
            raw: $posting->raw,
            companyWebsite: $posting->companyWebsite,
            sourceContacts: $posting->sourceContacts,
            ineligibleReason: $reason,
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

    /**
     * Contact emails in the comment: mailto links first, then addresses in the plain
     * text after undoing bracketed obfuscation only ("[at]", "( at )", "{dot}"); bare
     * words like " at " are never rewritten. Lowercased, unique, Hacker News's own dropped.
     *
     * @return list<string>
     */
    private function emails(string $html): array
    {
        $candidates = [];

        preg_match_all('/href="mailto:([^"]+)"/i', $html, $matches);

        foreach ($matches[1] as $href) {
            $candidates[] = explode('?', html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 2)[0];
        }

        $text = $this->htmlToText($html) ?? '';
        $text = (string) preg_replace('/\s*(?:\[\s*at\s*\]|\(\s*at\s*\)|\{\s*at\s*\})\s*/i', '@', $text);
        $text = (string) preg_replace('/\s*(?:\[\s*dot\s*\]|\(\s*dot\s*\)|\{\s*dot\s*\})\s*/i', '.', $text);

        preg_match_all(self::EMAIL_PATTERN, $text, $matches);

        array_push($candidates, ...$matches[0]);

        $emails = [];

        foreach ($candidates as $candidate) {
            $email = mb_strtolower(rtrim(trim($candidate), '.,;:)'));

            if (
                filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || in_array(Str::afterLast($email, '@'), self::NON_CONTACT_EMAIL_DOMAINS, true)
                || in_array($email, $emails, true)
            ) {
                continue;
            }

            $emails[] = $email;

            if (count($emails) === self::MAX_EMAILS) {
                break;
            }
        }

        return $emails;
    }
}
