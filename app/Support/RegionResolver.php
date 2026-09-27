<?php

namespace App\Support;

use App\Enums\Region;

final class RegionResolver
{
    /**
     * Map an ISO-3166-1 alpha-2 country code to its region; unknown or blank codes are Region::Row.
     */
    public static function fromCountry(?string $country): Region
    {
        $country = strtoupper(trim((string) $country));

        if ($country === '') {
            return Region::Row;
        }

        foreach (Region::cases() as $region) {
            /** @var list<string> $countries */
            $countries = config("talent.regions.{$region->value}.countries", []);

            if (in_array($country, $countries, true)) {
                return $region;
            }
        }

        return Region::Row;
    }
}
