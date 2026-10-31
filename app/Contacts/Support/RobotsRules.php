<?php

namespace App\Contacts\Support;

/**
 * The robots.txt group for `talent-labs` when present, otherwise the `User-agent: *` group, matched
 * with longest-match semantics (a tie goes to Allow). Patterns are prefixes; `*` (any run of
 * characters) and a trailing `$` (end of path) are honoured by an iterative matcher, so a hostile
 * file cannot trigger regex compilation errors. A file with too many or too long rules blocks
 * everything (fail closed).
 */
final class RobotsRules
{
    private const string OWN_AGENT = 'talent-labs';

    private const int MAX_RULES = 500;

    private const int MAX_RULE_LENGTH = 256;

    /**
     * @param  list<array{allow: bool, pattern: string}>  $rules
     */
    private function __construct(
        private readonly array $rules,
        private readonly bool $blocksEverything,
    ) {}

    public static function allowAll(): self
    {
        return new self([], false);
    }

    public static function disallowAll(): self
    {
        return new self([], true);
    }

    public static function parse(string $content): self
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        /** @var array{own: list<array{allow: bool, pattern: string}>, any: list<array{allow: bool, pattern: string}>} $rules */
        $rules = ['own' => [], 'any' => []];
        $ownGroupSeen = false;
        $groups = [];
        $readingAgents = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $hash = strpos($line, '#');
            $line = trim($hash === false ? $line : substr($line, 0, $hash));

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map(trim(...), explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (! $readingAgents) {
                    $groups = [];
                    $readingAgents = true;
                }

                $agent = strtolower($value);

                if ($agent === self::OWN_AGENT) {
                    $groups['own'] = true;
                    $ownGroupSeen = true;
                } elseif ($agent === '*') {
                    $groups['any'] = true;
                }

                continue;
            }

            $readingAgents = false;

            if ($groups === [] || ! in_array($field, ['allow', 'disallow'], true) || $value === '') {
                continue;
            }

            if (strlen($value) > self::MAX_RULE_LENGTH) {
                return self::disallowAll();
            }

            foreach (array_keys($groups) as $group) {
                $rules[$group][] = ['allow' => $field === 'allow', 'pattern' => $value];

                if (count($rules[$group]) > self::MAX_RULES) {
                    return self::disallowAll();
                }
            }
        }

        return new self($ownGroupSeen ? $rules['own'] : $rules['any'], false);
    }

    public function isAllowed(string $path): bool
    {
        if ($this->blocksEverything) {
            return false;
        }

        $path = $path === '' ? '/' : $path;
        $bestLength = -1;
        $allowed = true;

        foreach ($this->rules as $rule) {
            $length = strlen($rule['pattern']);

            if ($length < $bestLength || ! $this->matches($rule['pattern'], $path)) {
                continue;
            }

            if ($length > $bestLength || $rule['allow']) {
                $allowed = $rule['allow'];
            }

            $bestLength = $length;
        }

        return $allowed;
    }

    /**
     * Glob match with `*` only; an unanchored pattern matches any path it is a prefix of.
     */
    private function matches(string $pattern, string $path): bool
    {
        $pattern = str_ends_with($pattern, '$') ? substr($pattern, 0, -1) : $pattern.'*';
        $patternLength = strlen($pattern);
        $pathLength = strlen($path);
        $p = 0;
        $s = 0;
        $star = -1;
        $mark = 0;

        while ($s < $pathLength) {
            if ($p < $patternLength && $pattern[$p] === '*') {
                $star = $p++;
                $mark = $s;
            } elseif ($p < $patternLength && $pattern[$p] === $path[$s]) {
                $p++;
                $s++;
            } elseif ($star !== -1) {
                $p = $star + 1;
                $s = ++$mark;
            } else {
                return false;
            }
        }

        while ($p < $patternLength && $pattern[$p] === '*') {
            $p++;
        }

        return $p === $patternLength;
    }
}
