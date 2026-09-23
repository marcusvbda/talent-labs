<?php

namespace App\Collection\Adapters\Concerns;

use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

trait InteractsWithJobBoardApi
{
    /**
     * Shared HTTP client for all job board adapters (throws on non-2xx).
     */
    protected function http(): PendingRequest
    {
        return Http::timeout(20)
            ->retry(2, 500)
            ->acceptJson()
            ->withUserAgent('talent-labs/0.1 (local)')
            ->throw();
    }

    /**
     * The board identifier (company slug) these adapters cannot work without.
     */
    protected function requireIdentifier(Source $source): string
    {
        $identifier = $this->stringOrNull($source->identifier);

        if ($identifier === null) {
            throw new InvalidArgumentException("Source #{$source->id} ({$source->name}) has no identifier.");
        }

        return $identifier;
    }

    /**
     * Keep only array items that are string-keyed payload objects.
     *
     * @return list<array<string, mixed>>
     */
    protected function items(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $items[] = $item;
            }
        }

        return $items;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    protected function boolOrNull(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Plain text from HTML: tags stripped, entities decoded, whitespace collapsed.
     */
    protected function htmlToText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }
}
