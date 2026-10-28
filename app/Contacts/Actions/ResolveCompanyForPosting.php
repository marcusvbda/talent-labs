<?php

namespace App\Contacts\Actions;

use App\Models\Company;
use App\Models\JobPosting;

class ResolveCompanyForPosting
{
    /**
     * Legal-form suffixes stripped from the end of a normalized company name.
     *
     * @var list<string>
     */
    private const LEGAL_SUFFIXES = ['inc', 'llc', 'ltd', 'gmbh', 'sa', 'co', 'corp', 'corporation', 'company', 'plc'];

    /**
     * Find or create the company for a posting and link it without firing model events.
     */
    public function handle(JobPosting $posting): Company
    {
        $company = $this->forName($posting->company_name);

        JobPosting::query()->whereKey($posting->id)->toBase()->update(['company_id' => $company->id]);

        $posting->company_id = $company->id;

        return $company;
    }

    /**
     * Find or create the company with this name, matched by its normalized name.
     */
    public function forName(string $companyName): Company
    {
        return Company::firstOrCreate(
            ['normalized_name' => self::normalize($companyName)],
            ['name' => $companyName],
        );
    }

    public static function normalize(string $name): string
    {
        $fallback = trim(mb_strtolower($name));

        $normalized = (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $fallback);
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $normalized));

        $tokens = $normalized === '' ? [] : explode(' ', $normalized);

        while ($tokens !== [] && in_array(end($tokens), self::LEGAL_SUFFIXES, true)) {
            array_pop($tokens);
        }

        $normalized = trim(implode(' ', $tokens));

        return $normalized !== '' ? $normalized : $fallback;
    }
}
