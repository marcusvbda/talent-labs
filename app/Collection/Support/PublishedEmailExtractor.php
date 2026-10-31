<?php

namespace App\Collection\Support;

use App\Enums\DomainStatus;
use App\Enums\SourceContactKind;
use App\Models\Company;
use App\Support\AggregatorUrl;
use App\Support\RegistrableDomain;

/**
 * Emails a posting publishes for applications, shared by every source: mailto links and
 * addresses in the text, minus no-reply, non-application, job-board, ATS and asset names.
 */
final class PublishedEmailExtractor
{
    public const EMAIL_PATTERN = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';

    public const MAX_EMAILS = 5;

    /**
     * Email domains that belong to Hacker News itself, never to the hiring company.
     *
     * @var list<string>
     */
    public const NON_CONTACT_EMAIL_DOMAINS = ['news.ycombinator.com', 'ycombinator.com'];

    /**
     * @var list<string>
     */
    private const NO_REPLY_LOCAL_PARTS = ['noreply', 'no-reply', 'donotreply', 'do-not-reply'];

    /**
     * File extensions that look like a TLD in asset names ("logo@2x.png").
     *
     * @var list<string>
     */
    private const ASSET_TLDS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'css', 'js'];

    /**
     * A mailto apply URL first, then mailto links in the HTML, then addresses in the text
     * ($text, or the HTML as text) after undoing bracketed obfuscation only ("[at]",
     * "( at )", "{dot}"); bare words like " at " are never rewritten. Lowercased, unique.
     *
     * @return list<string>
     */
    public static function extract(?string $html, ?string $text, ?string $applyUrl): array
    {
        $candidates = [];

        $applyUrl = trim((string) $applyUrl);

        if (stripos($applyUrl, 'mailto:') === 0) {
            $candidates[] = self::mailtoAddress(substr($applyUrl, 7));
        }

        preg_match_all('/href=["\']mailto:([^"\']+)["\']/i', (string) $html, $matches);

        foreach ($matches[1] as $href) {
            $candidates[] = self::mailtoAddress($href);
        }

        $text ??= $html === null ? '' : html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s*(?:\[\s*at\s*\]|\(\s*at\s*\)|\{\s*at\s*\})\s*/i', '@', $text);
        $text = (string) preg_replace('/\s*(?:\[\s*dot\s*\]|\(\s*dot\s*\)|\{\s*dot\s*\})\s*/i', '.', $text);

        preg_match_all(self::EMAIL_PATTERN, $text, $matches);

        array_push($candidates, ...$matches[0]);

        $emails = [];

        foreach ($candidates as $candidate) {
            $email = mb_strtolower(rtrim(trim($candidate), '.,;:)'));

            if (
                filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || self::isDiscarded($email)
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

    /**
     * Whether the email can belong to the company: always when the company has no verified
     * domain or the address is on a free-mail provider, otherwise when the registrable domains match.
     */
    public static function matchesCompany(string $email, Company $company): bool
    {
        $companyDomain = mb_strtolower(trim((string) $company->domain));

        if ($companyDomain === '' || $company->domain_status !== DomainStatus::Found) {
            return true;
        }

        $domain = self::domainOf($email);

        /** @var list<string> $freeMail */
        $freeMail = config('talent.outreach.free_mail_domains', []);

        if (in_array($domain, $freeMail, true)) {
            return true;
        }

        return RegistrableDomain::of($domain) === $companyDomain;
    }

    /**
     * The `email` entries of a posting's stored source contacts.
     *
     * @param  list<array{kind?: string, email?: string|null}>|null  $sourceContacts
     * @return list<string>
     */
    public static function emailsOf(?array $sourceContacts): array
    {
        $emails = [];

        foreach ($sourceContacts ?? [] as $contact) {
            $email = $contact['email'] ?? null;

            if (($contact['kind'] ?? null) === SourceContactKind::Email->value && is_string($email) && $email !== '' && ! in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * The address of a mailto target: entities decoded, `?subject=...` dropped.
     */
    private static function mailtoAddress(string $target): string
    {
        return rawurldecode(explode('?', html_entity_decode($target, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 2)[0]);
    }

    /**
     * No-reply and non-application mailboxes, Hacker News, job boards, ATS hosts and asset names.
     */
    private static function isDiscarded(string $email): bool
    {
        $local = substr($email, 0, (int) strrpos($email, '@'));
        $domain = self::domainOf($email);

        /** @var list<string> $nonApplication */
        $nonApplication = config('talent.outreach.non_application_local_parts', []);

        if (in_array($local, self::NO_REPLY_LOCAL_PARTS, true) || in_array($local, $nonApplication, true)) {
            return true;
        }

        if (in_array($domain, self::NON_CONTACT_EMAIL_DOMAINS, true) || AggregatorUrl::is("https://{$domain}")) {
            return true;
        }

        /** @var list<string> $ats */
        $ats = config('talent.outreach.ats_domains', []);

        foreach ($ats as $entry) {
            $entry = strtolower(trim($entry));

            if ($entry !== '' && ($domain === $entry || str_ends_with($domain, ".{$entry}"))) {
                return true;
            }
        }

        $tld = substr($domain, (int) strrpos($domain, '.') + 1);

        return in_array($tld, self::ASSET_TLDS, true);
    }

    private static function domainOf(string $email): string
    {
        return mb_strtolower(substr($email, (int) strrpos($email, '@') + 1));
    }
}
