<?php

namespace App\Collection\Support;

use App\Enums\RoleFamily;

/**
 * Deterministic title/tag based role classification. Keywords are regex
 * fragments (a literal space matches any run of spaces or hyphens); each list
 * is wrapped in Unicode-aware word boundaries and matched case-insensitively.
 */
class RoleClassifier
{
    /**
     * Gender markers such as "(m/w/d)", "(H/F)", "m/f/d" or "(all genders)".
     */
    private const GENDER_MARKERS = [
        '\(\s*(?:all\s+genders?|gn|genderneutral|w\/m\/d|m\/w\/d)\s*\)',
        '\(?(?<![\p{L}\p{N}])(?:[mwfdxh]|div|divers)(?:\s*[\/|,]\s*(?:[mwfdxh]|div|divers))+(?![\p{L}\p{N}])\)?',
    ];

    /**
     * Non-target roles. Checked before any family and win over them.
     */
    private const EXCLUSIONS = [
        'sales', 'account executive', 'account manager', 'account director', 'key account',
        'business development', 'business developer', 'marketing', 'recruiter', 'recruiting',
        'talent acquisition', 'accountant', 'accounting', 'legal', 'designer', 'office assistant',
        'executive assistant',
    ];

    private const FULLSTACK = ['full stack', 'fullstack'];

    private const BACKEND = [
        'back end', 'backend', 'api (?:engineer|developer)', 'php', 'laravel', 'node(?:\.?js)?',
        'golang', 'java', 'python', 'ruby', 'rails', 'asp\.net', '\.net', 'dotnet', 'c#',
        'rust (?:developer|engineer)', 'elixir', 'django', 'spring',
    ];

    private const FRONTEND = [
        'front end', 'frontend', 'react(?:\.?js)?(?![\s\-]*native)', 'vue(?:\.?js)?', 'angular(?:js)?',
        'ui (?:engineer|developer)', 'web developer', '(?:javascript|typescript) (?:developer|engineer)',
    ];

    private const MOBILE = ['ios', 'android', 'flutter', 'react native', 'mobile (?:developer|engineer)'];

    private const DEVOPS = [
        'dev ops', 'devops', 'sre', 'site reliability', 'platform engineer(?:ing)?',
        'infrastructure engineer', 'cloud engineer', 'sys admin', 'sysadmin',
    ];

    private const QA = ['qa', 'quality assurance', 'test engineer', 'sdet', 'tester'];

    private const PRODUCT = [
        'product manager', 'product owner', 'product analyst', 'product operations', 'head of product',
    ];

    private const SUPPORT = [
        'support engineer', 'technical support', 'help desk', 'helpdesk', 'it support',
        'customer support', 'suporte', 'kundenservice\p{L}*', 'kundensupport\p{L}*',
        'support technique', 'support (?:specialist|agent)',
    ];

    private const CUSTOMER_SERVICE = [
        'customer success', 'customer service', 'customer care', 'customer experience',
        'atendimento', 'service client\p{L}*', 'kundenbetreu\p{L}*',
    ];

    private const SOFTWARE = [
        'software (?:engineer|developer|architect|development)', 'engineering (?:manager|lead|director)',
        'head of engineering', 'programmer', 'developer', '\p{L}*entwickler\p{L}*',
        'd[ée]veloppeu(?:r|se)', 'desenvolvedor[a]?', 'desarrollador[a]?',
    ];

    /**
     * Generic engineering-role nouns; tags are only trusted when the title has one.
     */
    private const ENGINEER_ROLE = [
        'engineer', 'developer', 'programmer', 'architect', 'sdet',
        '\p{L}*entwickler\p{L}*', 'desenvolvedor[a]?', 'desarrollador[a]?', 'd[ée]veloppeu(?:r|se)',
    ];

    /**
     * Families tags may produce, in priority order.
     */
    private const TAG_FAMILIES = [
        RoleFamily::Fullstack, RoleFamily::Backend, RoleFamily::Frontend, RoleFamily::Mobile,
        RoleFamily::Devops, RoleFamily::Qa, RoleFamily::Software,
    ];

    /**
     * Families tried against the title, in priority order.
     */
    private const TITLE_FAMILIES = [
        RoleFamily::Fullstack, RoleFamily::Backend, RoleFamily::Frontend, RoleFamily::Mobile,
        RoleFamily::Devops, RoleFamily::Qa, RoleFamily::Product, RoleFamily::Support,
        RoleFamily::CustomerService, RoleFamily::Software,
    ];

    /**
     * @param  list<string>  $tags
     */
    public function classify(string $title, array $tags = []): ?RoleFamily
    {
        $title = $this->cleanTitle($title);

        if (preg_match($this->pattern(self::EXCLUSIONS), $title) === 1) {
            return null;
        }

        foreach (self::TITLE_FAMILIES as $family) {
            if (preg_match($this->pattern($this->keywords($family)), $title) === 1) {
                return $family;
            }
        }

        if (preg_match($this->pattern(self::ENGINEER_ROLE), $title) !== 1) {
            return null;
        }

        foreach (self::TAG_FAMILIES as $family) {
            $pattern = $this->pattern($this->keywords($family));

            foreach ($tags as $tag) {
                if (preg_match($pattern, $tag) === 1) {
                    return $family;
                }
            }
        }

        return null;
    }

    private function cleanTitle(string $title): string
    {
        foreach (self::GENDER_MARKERS as $marker) {
            $title = (string) preg_replace('/'.$marker.'/iu', ' ', $title);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $title));
    }

    /**
     * @return list<string>
     */
    private function keywords(RoleFamily $family): array
    {
        return match ($family) {
            RoleFamily::Fullstack => self::FULLSTACK,
            RoleFamily::Backend => self::BACKEND,
            RoleFamily::Frontend => self::FRONTEND,
            RoleFamily::Mobile => self::MOBILE,
            RoleFamily::Devops => self::DEVOPS,
            RoleFamily::Qa => self::QA,
            RoleFamily::Product => self::PRODUCT,
            RoleFamily::Support => self::SUPPORT,
            RoleFamily::CustomerService => self::CUSTOMER_SERVICE,
            RoleFamily::Software => self::SOFTWARE,
        };
    }

    /**
     * @param  list<string>  $keywords
     */
    private function pattern(array $keywords): string
    {
        $alternatives = implode('|', array_map(
            fn (string $keyword): string => str_replace(' ', '[\s\-]+', $keyword),
            $keywords,
        ));

        return '/(?<![\p{L}\p{N}])(?:'.$alternatives.')(?![\p{L}\p{N}])/iu';
    }
}
