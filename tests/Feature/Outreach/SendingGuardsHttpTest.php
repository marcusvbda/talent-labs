<?php

use App\Enums\ApplicationStatus;
use App\Enums\PlanKey;
use App\Enums\UserStatus;
use App\Models\Application;
use App\Models\User;
use App\Outreach\Actions\CanSendApplications;
use App\Outreach\Actions\QueueApplication;
use App\Outreach\Data\SendEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

/*
 * Mode enforcement and the queue-time quota rule, without a database: the
 * client is an unsaved User, the eligibility check is stubbed and the queueing
 * action must never be reached.
 */

function clientOn(PlanKey $plan): User
{
    $user = new User([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'status' => UserStatus::Active,
        'plan_key' => $plan,
        'locale' => 'en',
    ]);
    $user->id = 4242;

    return $user;
}

beforeEach(function (): void {
    Queue::fake();

    $this->mock(QueueApplication::class)->shouldNotReceive('handle');
});

it('rejects POST /internal/applications for non-select plans with 403', function (PlanKey $plan): void {
    $this->mock(CanSendApplications::class)->shouldNotReceive('check');

    $this->actingAs(clientOn($plan))
        ->postJson('/internal/applications', ['jobIds' => [1]])
        ->assertForbidden();

    Queue::assertNothingPushed();
})->with([
    'auto (free)' => PlanKey::Free,
    'review (pro)' => PlanKey::Pro,
]);

it('rejects the review endpoints for non-review plans with 403', function (string $uri, PlanKey $plan): void {
    $this->actingAs(clientOn($plan))
        ->postJson($uri, ['jobIds' => [1], 'jobId' => 1, 'subject' => 'Hi', 'body' => 'Hello'])
        ->assertForbidden();

    Queue::assertNothingPushed();
})->with([
    'drafts' => '/internal/applications/drafts',
    'reviewed' => '/internal/applications/reviewed',
])->with([
    'auto (free)' => PlanKey::Free,
    'select (starter)' => PlanKey::Starter,
]);

it('lets a review plan past authorization on the review endpoints', function (string $uri): void {
    // An empty payload fails validation (422), proving authorization passed.
    $this->actingAs(clientOn(PlanKey::Pro))
        ->postJson($uri, [])
        ->assertUnprocessable();
})->with([
    'drafts' => '/internal/applications/drafts',
    'reviewed' => '/internal/applications/reviewed',
]);

it('rejects jobIds over the remaining daily quota with 422', function (int $remaining, array $jobIds): void {
    $this->mock(CanSendApplications::class)
        ->shouldReceive('check')
        ->andReturn(new SendEligibility([], 10, $remaining));

    $this->actingAs(clientOn(PlanKey::Starter))
        ->postJson('/internal/applications', ['jobIds' => $jobIds])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jobIds');

    Queue::assertNothingPushed();
})->with([
    'three jobs, two left' => [2, [1, 2, 3]],
    'one job, none left' => [0, [1]],
]);

it('counts queued, sending, sent and ambiguous towards the quota, by queued_at today', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-30 15:00:00'));

    expect(ApplicationStatus::countedTowardsQuota())->toBe([
        ApplicationStatus::Queued,
        ApplicationStatus::Sending,
        ApplicationStatus::Sent,
        ApplicationStatus::Ambiguous,
    ]);

    $query = Application::query()->countedToday();
    $bindings = array_map(
        fn (mixed $value): mixed => $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value,
        $query->getBindings(),
    );

    expect($query->toSql())->toContain('"status" in')->toContain('"queued_at" between')
        ->and($bindings)->toBe([
            'queued', 'sending', 'sent', 'ambiguous',
            '2026-09-30 00:00:00', '2026-09-30 23:59:59',
        ]);

    CarbonImmutable::setTestNow();
});
