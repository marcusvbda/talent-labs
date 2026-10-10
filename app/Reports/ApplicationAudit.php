<?php

namespace App\Reports;

use App\Enums\ApplicationStatus;
use App\Enums\SourceAdapter;
use App\Models\Application;
use App\Models\User;
use App\Support\AggregatorUrl;

/**
 * @phpstan-type AuditRow array{
 *     application_id: int, status: string, user_id: int, origin: string, sent_at: string,
 *     adapter: string, source_name: string, job_posting_id: int|string, posting_title: string,
 *     normalized_title: string, subject: string, job_url: string, destination_domain: string,
 *     recipient_email: string, recipient_local_part: string,
 *     flag_a_multi_role: int, flag_b_aggregator_url: int, flag_c_geo: int, flag_c_reason: string,
 *     flag_d_profile: int, flag_d_reason: string, flag_e_generic_recipient: int, flags_count: int
 * }
 * @phpstan-type Ranked list<array{name: string, count: int}>
 * @phpstan-type Example array{application_id: int, posting_title: string, subject: string, reason: string}
 * @phpstan-type FlagSummary array{
 *     column: string, label: string, count: int, percent: float,
 *     top_sources: Ranked, top_domains: Ranked, examples: list<Example>
 * }
 * @phpstan-type AuditSummary array{
 *     generated_at: string,
 *     statuses: list<string>,
 *     user_id: int|null,
 *     total: int,
 *     posting_deleted: int,
 *     by_status: array<string, int>,
 *     flags: array<string, FlagSummary>,
 *     two_or_more_flags: array{count: int, percent: float},
 *     no_flags: array{count: int, percent: float}
 * }
 * @phpstan-type Audit array{rows: list<AuditRow>, summary: AuditSummary}
 */
final class ApplicationAudit
{
    /**
     * CSV header, in order.
     *
     * @var list<string>
     */
    public const array COLUMNS = [
        'application_id', 'status', 'user_id', 'origin', 'sent_at',
        'adapter', 'source_name', 'job_posting_id', 'posting_title', 'normalized_title',
        'subject', 'job_url', 'destination_domain', 'recipient_email',
        'recipient_local_part', 'flag_a_multi_role', 'flag_b_aggregator_url',
        'flag_c_geo', 'flag_c_reason', 'flag_d_profile', 'flag_d_reason',
        'flag_e_generic_recipient', 'flags_count',
    ];

    /**
     * Flag column => summary label.
     *
     * @var array<string, string>
     */
    public const array FLAGS = [
        'flag_a_multi_role' => 'a · Multiple roles',
        'flag_b_aggregator_url' => 'b · Aggregator/thread or missing job URL',
        'flag_c_geo' => 'c · Location/authorization restriction',
        'flag_d_profile' => 'd · Out-of-profile role/seniority',
        'flag_e_generic_recipient' => 'e · Generic recipient',
    ];

    private const int TOP = 10;

    private const int EXAMPLES = 10;

    /**
     * Read-only audit of applications in the given statuses (optionally one user's): one row per
     * application with quality flags a–e, plus a summary per flag.
     *
     * @param  list<ApplicationStatus>  $statuses
     * @return Audit
     */
    public function build(array $statuses, ?User $user = null): array
    {
        $rows = [];
        $byStatus = array_fill_keys(array_map(fn (ApplicationStatus $status): string => $status->value, $statuses), 0);
        $postingDeleted = 0;
        $sources = $domains = $examples = [];
        $counts = array_fill_keys(array_keys(self::FLAGS), 0);
        $twoOrMore = $none = 0;

        $applications = Application::query()
            ->whereIn('status', $statuses)
            ->when($user !== null, fn ($query) => $query->where('user_id', $user?->id))
            ->with(['jobPosting.source', 'jobPosting.profile', 'user.jobPreference'])
            ->lazyById(500);

        foreach ($applications as $application) {
            [$row, $reasons, $sortKey] = $this->row($application);
            $rows[] = $row;
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + 1;

            if ($row['job_posting_id'] === '') {
                $postingDeleted++;
            }

            if ($row['flags_count'] >= 2) {
                $twoOrMore++;
            } elseif ($row['flags_count'] === 0) {
                $none++;
            }

            $source = $row['adapter'] === '' ? 'posting deleted' : "{$row['adapter']} · {$row['source_name']}";
            $domain = $row['destination_domain'] === '' ? '(none)' : $row['destination_domain'];

            foreach (array_keys(self::FLAGS) as $flag) {
                if ($row[$flag] !== 1) {
                    continue;
                }

                $counts[$flag]++;
                $sources[$flag][$source] = ($sources[$flag][$source] ?? 0) + 1;
                $domains[$flag][$domain] = ($domains[$flag][$domain] ?? 0) + 1;
                $examples[$flag][] = ['sort' => $sortKey, 'example' => [
                    'application_id' => $row['application_id'],
                    'posting_title' => $row['posting_title'],
                    'subject' => $row['subject'],
                    'reason' => $reasons[$flag],
                ]];

                if (count($examples[$flag]) > self::EXAMPLES * 5) {
                    $examples[$flag] = $this->mostRecent($examples[$flag]);
                }
            }
        }

        $total = count($rows);
        $flags = [];

        foreach (self::FLAGS as $flag => $label) {
            $flags[$flag] = [
                'column' => $flag,
                'label' => $label,
                'count' => $counts[$flag],
                'percent' => $this->percent($counts[$flag], $total),
                'top_sources' => $this->top($sources[$flag] ?? []),
                'top_domains' => $this->top($domains[$flag] ?? []),
                'examples' => array_column($this->mostRecent($examples[$flag] ?? []), 'example'),
            ];
        }

        return [
            'rows' => $rows,
            'summary' => [
                'generated_at' => now()->toIso8601String(),
                'statuses' => array_keys($byStatus),
                'user_id' => $user?->id,
                'total' => $total,
                'posting_deleted' => $postingDeleted,
                'by_status' => $byStatus,
                'flags' => $flags,
                'two_or_more_flags' => ['count' => $twoOrMore, 'percent' => $this->percent($twoOrMore, $total)],
                'no_flags' => ['count' => $none, 'percent' => $this->percent($none, $total)],
            ],
        ];
    }

    /**
     * @return array{0: AuditRow, 1: array<string, string>, 2: int}
     */
    private function row(Application $application): array
    {
        $posting = $application->jobPosting;
        $preference = $application->user->jobPreference;
        $profile = $posting?->profile;
        $roleFamily = $posting?->role_family?->value;

        $jobUrl = $this->jobUrl($application->body, $posting?->url, $posting?->apply_url);
        $domain = (string) AggregatorUrl::domain($jobUrl);
        $localPart = strtolower(explode('@', $application->recipient_email, 2)[0]);

        $multiRole = ApplicationAuditFlags::multiRole(
            $posting?->title,
            $application->subject,
            $posting?->source?->adapter === SourceAdapter::HackerNews,
            $posting === null ? [] : $posting->raw,
            $posting?->description_text,
        );
        $aggregator = AggregatorUrl::is($jobUrl) ? ($jobUrl === '' ? 'no link' : ($domain === '' ? $jobUrl : $domain)) : null;
        $geo = $posting === null ? [] : ApplicationAuditFlags::geo(
            $posting->title,
            $posting->location,
            $posting->description_text,
            $preference?->remote_mode,
            $preference === null ? [] : $preference->locations,
        );
        $outOfProfile = $posting === null ? [] : ApplicationAuditFlags::profile(
            $posting->title,
            $profile?->seniority,
            $roleFamily,
            $preference === null ? [] : $preference->titles,
            $preference === null ? [] : $preference->seniorities,
        );
        $generic = ApplicationAuditFlags::genericRecipient($localPart);

        $flags = [
            'flag_a_multi_role' => (int) ($multiRole !== null),
            'flag_b_aggregator_url' => (int) ($aggregator !== null),
            'flag_c_geo' => (int) ($geo !== []),
            'flag_d_profile' => (int) ($outOfProfile !== []),
            'flag_e_generic_recipient' => (int) ($generic !== null),
        ];

        $row = [
            'application_id' => $application->id,
            'status' => $application->status->value,
            'user_id' => $application->user_id,
            'origin' => $application->origin->value,
            'sent_at' => $application->sent_at?->toIso8601String() ?? '',
            'adapter' => (string) $posting?->source?->adapter->value,
            'source_name' => (string) $posting?->source?->name,
            'job_posting_id' => $posting === null ? '' : $posting->id,
            'posting_title' => (string) $posting?->title,
            'normalized_title' => (string) $profile?->normalized_title,
            'subject' => $application->subject,
            'job_url' => $jobUrl,
            'destination_domain' => $domain,
            'recipient_email' => $application->recipient_email,
            'recipient_local_part' => $localPart,
            'flag_a_multi_role' => $flags['flag_a_multi_role'],
            'flag_b_aggregator_url' => $flags['flag_b_aggregator_url'],
            'flag_c_geo' => $flags['flag_c_geo'],
            'flag_c_reason' => implode('; ', $geo),
            'flag_d_profile' => $flags['flag_d_profile'],
            'flag_d_reason' => implode('; ', $outOfProfile),
            'flag_e_generic_recipient' => $flags['flag_e_generic_recipient'],
            'flags_count' => array_sum($flags),
        ];

        $reasons = [
            'flag_a_multi_role' => (string) $multiRole,
            'flag_b_aggregator_url' => (string) $aggregator,
            'flag_c_geo' => $row['flag_c_reason'],
            'flag_d_profile' => $row['flag_d_reason'],
            'flag_e_generic_recipient' => (string) $generic,
        ];

        $at = $application->sent_at ?? $application->queued_at;

        return [$row, $reasons, $at === null ? 0 : $at->getTimestamp()];
    }

    /**
     * The first http(s) URL in the body equal to the posting's url or apply_url; otherwise the
     * posting's url; otherwise ''.
     */
    private function jobUrl(string $body, ?string $url, ?string $applyUrl): string
    {
        $candidates = array_values(array_filter([trim((string) $url), trim((string) $applyUrl)], fn (string $value): bool => $value !== ''));

        if (preg_match_all('#https?://[^\s<>"\'()\[\]]+#i', $body, $matches) > 0) {
            foreach ($matches[0] as $found) {
                $found = rtrim($found, '.,;:!?');

                if (in_array($found, $candidates, true)) {
                    return $found;
                }
            }
        }

        return trim((string) $url);
    }

    /**
     * @param  list<array{sort: int, example: Example}>  $examples
     * @return list<array{sort: int, example: Example}>
     */
    private function mostRecent(array $examples): array
    {
        usort($examples, fn (array $a, array $b): int => [$b['sort'], $b['example']['application_id']] <=> [$a['sort'], $a['example']['application_id']]);

        return array_slice($examples, 0, self::EXAMPLES);
    }

    /**
     * @param  array<string, int>  $counts
     * @return Ranked
     */
    private function top(array $counts): array
    {
        arsort($counts);

        $ranked = [];

        foreach (array_slice($counts, 0, self::TOP, true) as $name => $count) {
            $ranked[] = ['name' => (string) $name, 'count' => $count];
        }

        return $ranked;
    }

    private function percent(int $count, int $of): float
    {
        return $of > 0 ? round($count / $of * 100, 1) : 0.0;
    }
}
