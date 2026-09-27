<?php

namespace App\Outreach\Data;

use App\Ai\Agents\ExtractJobPostingProfile;
use App\Enums\RemoteMode;
use App\Models\JobPreference;
use App\Outreach\Support\StackNormalizer;

/**
 * Cleaned matching criteria for the job pool, built from a saved preference row or from
 * a validated (camelCase) draft that has not been saved yet.
 */
final readonly class PreferenceCriteria
{
    /**
     * @param  list<string>  $titles
     * @param  list<string>  $seniorities
     * @param  list<string>  $stack  already normalized by StackNormalizer
     * @param  list<string>  $locations
     * @param  list<string>  $excludeWords
     */
    public function __construct(
        public array $titles,
        public array $seniorities,
        public array $stack,
        public array $locations,
        public RemoteMode $remoteMode,
        public array $excludeWords,
    ) {}

    public static function empty(): self
    {
        return new self([], [], [], [], RemoteMode::RemoteOrLocations, []);
    }

    public static function fromPreference(JobPreference $preference): self
    {
        return new self(
            titles: self::cleanList($preference->titles),
            seniorities: self::cleanSeniorities($preference->seniorities),
            stack: StackNormalizer::normalize($preference->stack),
            locations: self::cleanList($preference->locations),
            remoteMode: $preference->remote_mode,
            excludeWords: self::cleanList($preference->exclude_words),
        );
    }

    /**
     * @param  array<string, mixed>  $draft  validated keys: titles, seniorities, stack, locations, remoteMode, excludeWords
     */
    public static function fromDraft(array $draft): self
    {
        $remoteMode = $draft['remoteMode'] ?? null;

        if (! $remoteMode instanceof RemoteMode) {
            $remoteMode = RemoteMode::tryFrom(is_string($remoteMode) ? $remoteMode : '') ?? RemoteMode::RemoteOrLocations;
        }

        return new self(
            titles: self::cleanList($draft['titles'] ?? null),
            seniorities: self::cleanSeniorities($draft['seniorities'] ?? null),
            stack: StackNormalizer::normalize(is_array($draft['stack'] ?? null) ? $draft['stack'] : []),
            locations: self::cleanList($draft['locations'] ?? null),
            remoteMode: $remoteMode,
            excludeWords: self::cleanList($draft['excludeWords'] ?? null),
        );
    }

    /**
     * Seniorities a client may pick: every profile seniority except "unknown".
     *
     * @return list<string>
     */
    public static function allowedSeniorities(): array
    {
        return array_values(array_diff(ExtractJobPostingProfile::SENIORITIES, ['unknown']));
    }

    /**
     * Trim, drop empties and dedupe case-insensitively (first spelling wins).
     *
     * @return list<string>
     */
    private static function cleanList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $cleaned = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            $key = mb_strtolower($value);

            if ($value !== '' && ! isset($cleaned[$key])) {
                $cleaned[$key] = $value;
            }
        }

        return array_values($cleaned);
    }

    /**
     * @return list<string>
     */
    private static function cleanSeniorities(mixed $values): array
    {
        $allowed = self::allowedSeniorities();

        return array_values(array_filter(
            array_map(mb_strtolower(...), self::cleanList($values)),
            fn (string $seniority): bool => in_array($seniority, $allowed, true),
        ));
    }
}
