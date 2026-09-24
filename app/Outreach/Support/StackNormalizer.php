<?php

namespace App\Outreach\Support;

class StackNormalizer
{
    private const ALIASES = [
        'nodejs' => 'node.js',
        'node' => 'node.js',
        'reactjs' => 'react',
        'vuejs' => 'vue',
        'js' => 'javascript',
        'ts' => 'typescript',
        'golang' => 'go',
        'postgres' => 'postgresql',
        'k8s' => 'kubernetes',
        'c sharp' => 'c#',
    ];

    /**
     * Normalize technology names to canonical lowercase, deduplicated names.
     *
     * @param  array<array-key, mixed>  $items
     * @return list<string>
     */
    public static function normalize(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $name = (string) preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $item)));

            if ($name === '') {
                continue;
            }

            $normalized[] = self::ALIASES[$name] ?? $name;
        }

        return array_values(array_unique($normalized));
    }
}
