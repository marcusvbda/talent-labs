<?php

namespace App\Client;

use App\Outreach\Support\StackNormalizer;

/**
 * Orders a profile stack for the job card so the user's preferred technologies are always visible.
 */
final class StackHighlight
{
    /**
     * Matching tags first (original order), then the rest (original order), capped at $limit.
     * Comparison uses StackNormalizer on both sides; displayed values keep the stored spelling.
     *
     * @param  array<array-key, mixed>  $stack  profile stack
     * @param  list<string>  $preferred  already normalized (StackNormalizer)
     * @return array{stack: list<string>, matches: list<string>}
     */
    public static function forCard(array $stack, array $preferred, int $limit = 6): array
    {
        $items = [];

        foreach ($stack as $item) {
            if (is_scalar($item)) {
                $items[] = (string) $item;
            }
        }

        if ($preferred === []) {
            return ['stack' => array_slice($items, 0, $limit), 'matches' => []];
        }

        $matching = [];
        $rest = [];

        foreach ($items as $item) {
            $normalized = StackNormalizer::normalize([$item])[0] ?? null;

            if ($normalized !== null && in_array($normalized, $preferred, true)) {
                $matching[] = $item;
            } else {
                $rest[] = $item;
            }
        }

        $matches = array_slice($matching, 0, $limit);

        return [
            'stack' => array_slice([...$matches, ...$rest], 0, $limit),
            'matches' => $matches,
        ];
    }
}
